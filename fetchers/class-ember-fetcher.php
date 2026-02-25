<?php
defined('ABSPATH') || exit;

/**
 * Ember API Fetcher — EU solar context (optional).
 * Provides Spain's EU rank and share of EU-27 solar generation.
 * Docs: https://api.ember-climate.org/docs
 */
class SIS_Ember_Fetcher {

    const BASE_URL = 'https://api.ember-climate.org/v1';

    /**
     * Fetch Spain's rank and share within EU-27 solar generation for a given month.
     *
     * @throws RuntimeException on HTTP or parsing failures.
     * @return array{rank: int, share_pct: float, total_eu_twh: float}
     */
    public function fetch_eu_solar_rank(int $year, int $month): array {
        $last_day = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        $url = add_query_arg([
            'date_from'   => sprintf('%04d-%02d-01', $year, $month),
            'date_to'     => sprintf('%04d-%02d-%02d', $year, $month, $last_day),
            'source'      => 'Solar',
            'granularity' => 'monthly',
        ], self::BASE_URL . '/electricity-generation');

        $response = wp_remote_get($url, ['timeout' => 30]);

        if (is_wp_error($response)) {
            throw new RuntimeException('Ember API request failed: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            throw new RuntimeException("Ember API returned HTTP {$code}");
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['data'])) {
            throw new RuntimeException('Ember API returned empty or invalid data');
        }

        // Sort by value_twh descending, compute Spain's rank
        $countries = $body['data'];
        usort($countries, fn($a, $b) => ($b['value_twh'] ?? 0) <=> ($a['value_twh'] ?? 0));

        $eu_total   = array_sum(array_column($countries, 'value_twh'));
        $spain_rank = null;
        $spain_twh  = 0.0;

        foreach ($countries as $i => $c) {
            if (($c['country'] ?? '') === 'ESP') {
                $spain_rank = $i + 1;
                $spain_twh  = (float)($c['value_twh'] ?? 0);
                break;
            }
        }

        if ($spain_rank === null) {
            throw new RuntimeException('Spain (ESP) not found in Ember EU data');
        }

        return [
            'rank'         => $spain_rank,
            'share_pct'    => $eu_total > 0 ? round(($spain_twh / $eu_total) * 100, 1) : 0.0,
            'total_eu_twh' => round($eu_total, 1),
        ];
    }
}
