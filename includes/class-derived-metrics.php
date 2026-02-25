<?php
defined('ABSPATH') || exit;

class SIS_Derived_Metrics {

    /**
     * Calculate all derived metrics for a given month.
     * Returns ['gen' => [...], 'cap' => [...]]
     */
    public static function calculate(int $year, int $month, float $gen_gwh, float $cap_gw): array {
        global $wpdb;
        $table_gen = $wpdb->prefix . 'solar_generation_data';
        $table_cap = $wpdb->prefix . 'solar_capacity_data';

        [$prev_year, $prev_month] = self::prev_month($year, $month);

        // ── GENERATION ──────────────────────────────────────────────────────

        $prev_gen = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT generation_gwh FROM $table_gen WHERE period_year=%d AND period_month=%d",
            $prev_year, $prev_month
        ));

        $last_year_gen = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT generation_gwh FROM $table_gen WHERE period_year=%d AND period_month=%d",
            $year - 1, $month
        ));

        // MoM %
        $mom_pct = $prev_gen > 0
            ? round(($gen_gwh - $prev_gen) / $prev_gen * 100, 1)
            : null;

        // YoY %
        $yoy_pct = $last_year_gen > 0
            ? round(($gen_gwh - $last_year_gen) / $last_year_gen * 100, 1)
            : null;

        // Rolling 12-month total (including current month, read existing data then add current)
        $period_key = $year * 100 + $month;
        $rolling_raw = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(generation_gwh)
             FROM (
                 SELECT generation_gwh FROM $table_gen
                 WHERE (period_year * 100 + period_month) < %d
                 ORDER BY period_year DESC, period_month DESC
                 LIMIT 11
             ) AS prev11",
            $period_key
        ));
        $rolling_12m = $rolling_raw !== null ? round((float)$rolling_raw + $gen_gwh, 0) : null;

        // Implied Capacity Factor: CF = GWh / (GW × hours_in_month × 1 GWh/GWh)
        // denominator: GW * hours → GWh (GW × h = GWh)
        $hours = cal_days_in_month(CAL_GREGORIAN, $month, $year) * 24;
        $cf_pct = $cap_gw > 0
            ? round(($gen_gwh / ($cap_gw * $hours)) * 100, 1)
            : null;

        // Momentum Score (0-100)
        $momentum_score = null;
        if ($yoy_pct !== null && $mom_pct !== null) {
            $momentum_score = (int) min(100, max(0, round(50 + ($yoy_pct * 0.5) + ($mom_pct * 0.3))));
        }

        // ── CAPACITY ────────────────────────────────────────────────────────

        $prev_cap = $wpdb->get_var($wpdb->prepare(
            "SELECT capacity_gw FROM $table_cap WHERE period_year=%d AND period_month=%d",
            $prev_year, $prev_month
        ));

        $monthly_addition = $prev_cap !== null
            ? round($cap_gw - (float)$prev_cap, 3)
            : null;

        // Rolling 12m additions (sum of monthly_addition_gw for last 11 months + current)
        $rolling_add_raw = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(monthly_addition_gw)
             FROM (
                 SELECT monthly_addition_gw FROM $table_cap
                 WHERE monthly_addition_gw IS NOT NULL
                   AND (period_year * 100 + period_month) < %d
                 ORDER BY period_year DESC, period_month DESC
                 LIMIT 11
             ) AS prev11add",
            $period_key
        ));
        $rolling_12m_added = null;
        if ($rolling_add_raw !== null && $monthly_addition !== null) {
            $rolling_12m_added = round((float)$rolling_add_raw + $monthly_addition, 2);
        } elseif ($monthly_addition !== null) {
            $rolling_12m_added = $monthly_addition;
        }

        // Build pace = annualised additions (= rolling 12m total, already annualised)
        $build_pace = $rolling_12m_added !== null ? round($rolling_12m_added, 1) : null;

        return [
            'gen' => [
                'mom_pct'             => $mom_pct,
                'yoy_pct'             => $yoy_pct,
                'rolling_12m_gwh'     => $rolling_12m,
                'capacity_factor_pct' => $cf_pct,
                'momentum_score'      => $momentum_score,
            ],
            'cap' => [
                'monthly_addition_gw'  => $monthly_addition,
                'rolling_12m_added_gw' => $rolling_12m_added,
                'build_pace_gw_yr'     => $build_pace,
            ],
        ];
    }

    /**
     * Return [year, month] for the month preceding the given one.
     * Public so validator and other classes can call it.
     */
    public static function prev_month(int $year, int $month): array {
        if ($month === 1) {
            return [$year - 1, 12];
        }
        return [$year, $month - 1];
    }
}
