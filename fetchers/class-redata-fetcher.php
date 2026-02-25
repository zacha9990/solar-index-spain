<?php
defined('ABSPATH') || exit;

/**
 * REData Fetcher — Solar generation (widget: estructura-generacion)
 * No authentication required. Public API.
 */
class SIS_REData_Fetcher {

    const BASE_URL    = 'https://apidatos.ree.es/es/datos/generacion/estructura-generacion';
    const SOLAR_PV_ID = '1739'; // Solar fotovoltaica in estructura-generacion widget

    /**
     * Fetch monthly solar PV generation for Spain.
     *
     * @throws RuntimeException on HTTP or parsing errors.
     * @return array{gwh: float, source: string}
     */
    public function fetch_monthly_generation(int $year, int $month): array {
        $last_day = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        $url = add_query_arg([
            'start_date' => sprintf('%04d-%02d-01T00:00', $year, $month),
            'end_date'   => sprintf('%04d-%02d-%02dT23:59', $year, $month, $last_day),
            'time_trunc' => 'month',
        ], self::BASE_URL);

        $body = $this->request($url, "REData generation ({$year}-{$month})");

        foreach ($body['included'] ?? [] as $series) {
            if ((string)($series['id'] ?? '') === self::SOLAR_PV_ID) {
                $mwh = $series['attributes']['values'][0]['value'] ?? null;
                if ($mwh === null) {
                    throw new RuntimeException('Solar PV generation value is null in REData response');
                }
                return [
                    'gwh'    => round((float)$mwh / 1000, 2), // MWh → GWh
                    'source' => 'redata',
                ];
            }
        }

        throw new RuntimeException(
            'Solar fotovoltaica (id=' . self::SOLAR_PV_ID . ') not found in REData generacion response. '
            . 'Verify the ID against the live endpoint.'
        );
    }

    /**
     * Shared HTTP GET helper with retry.
     */
    private function request(string $url, string $label, int $retries = 3): array {
        $last_error = '';
        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            $response = wp_remote_get($url, [
                'headers' => [
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 30,
            ]);

            if (is_wp_error($response)) {
                $last_error = $response->get_error_message();
                if ($attempt < $retries) {
                    sleep(5 * $attempt);
                    continue;
                }
                throw new RuntimeException("{$label} request failed after {$retries} attempts: {$last_error}");
            }

            $code = wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                $last_error = "HTTP {$code}";
                if ($attempt < $retries && $code >= 500) {
                    sleep(5 * $attempt);
                    continue;
                }
                throw new RuntimeException("{$label} returned {$last_error}");
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($body)) {
                throw new RuntimeException("{$label}: could not decode JSON response");
            }
            return $body;
        }

        throw new RuntimeException("{$label}: all retries exhausted. Last error: {$last_error}");
    }
}
