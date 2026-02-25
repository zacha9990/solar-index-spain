<?php
defined('ABSPATH') || exit;

class SIS_Database {

    // ── INSTALL ────────────────────────────────────────────────────────────

    public static function install(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql_gen = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}solar_generation_data (
            id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            period_year         SMALLINT        NOT NULL,
            period_month        TINYINT         NOT NULL,
            generation_gwh      DECIMAL(10,2)   NOT NULL,
            mom_pct             DECIMAL(6,3)    DEFAULT NULL,
            yoy_pct             DECIMAL(6,3)    DEFAULT NULL,
            rolling_12m_gwh     DECIMAL(12,2)   DEFAULT NULL,
            capacity_factor_pct DECIMAL(5,2)    DEFAULT NULL,
            momentum_score      TINYINT         DEFAULT NULL,
            eu_position         TINYINT         DEFAULT NULL,
            eu_share_pct        DECIMAL(5,2)    DEFAULT NULL,
            data_source         VARCHAR(50)     NOT NULL DEFAULT 'manual',
            is_revised          TINYINT(1)      NOT NULL DEFAULT 0,
            created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY period (period_year, period_month)
        ) $charset;";

        $sql_cap = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}solar_capacity_data (
            id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            period_year          SMALLINT        NOT NULL,
            period_month         TINYINT         NOT NULL,
            capacity_gw          DECIMAL(8,3)    NOT NULL,
            monthly_addition_gw  DECIMAL(7,3)    DEFAULT NULL,
            rolling_12m_added_gw DECIMAL(8,3)    DEFAULT NULL,
            build_pace_gw_yr     DECIMAL(7,3)    DEFAULT NULL,
            data_source          VARCHAR(50)     NOT NULL DEFAULT 'manual',
            is_revised           TINYINT(1)      NOT NULL DEFAULT 0,
            created_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY period (period_year, period_month)
        ) $charset;";

        $sql_log = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}solar_revision_log (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            table_name   VARCHAR(50)     NOT NULL,
            period_year  SMALLINT        NOT NULL,
            period_month TINYINT         NOT NULL,
            field_name   VARCHAR(50)     NOT NULL,
            old_value    VARCHAR(100)    DEFAULT NULL,
            new_value    VARCHAR(100)    DEFAULT NULL,
            reason       TEXT            DEFAULT NULL,
            created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_gen);
        dbDelta($sql_cap);
        dbDelta($sql_log);
    }

    // ── GENERATION READS ───────────────────────────────────────────────────

    public static function get_generation(int $year, int $month): ?object {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}solar_generation_data
             WHERE period_year = %d AND period_month = %d",
            $year, $month
        )) ?: null;
    }

    public static function get_generation_last_n_months(int $n = 24): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}solar_generation_data
             ORDER BY period_year DESC, period_month DESC
             LIMIT %d",
            $n
        )) ?: [];
    }

    public static function get_all_generation(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}solar_generation_data
             ORDER BY period_year ASC, period_month ASC"
        ) ?: [];
    }

    // ── CAPACITY READS ─────────────────────────────────────────────────────

    public static function get_capacity(int $year, int $month): ?object {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}solar_capacity_data
             WHERE period_year = %d AND period_month = %d",
            $year, $month
        )) ?: null;
    }

    public static function get_capacity_last_n_months(int $n = 24): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}solar_capacity_data
             ORDER BY period_year DESC, period_month DESC
             LIMIT %d",
            $n
        )) ?: [];
    }

    public static function get_all_capacity(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}solar_capacity_data
             ORDER BY period_year ASC, period_month ASC"
        ) ?: [];
    }

    // ── UPSERT GENERATION ─────────────────────────────────────────────────

    public static function upsert_generation(int $year, int $month, float $gwh, array $metrics, string $source = 'manual'): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'solar_generation_data';

        $existing = self::get_generation($year, $month);
        $data = [
            'period_year'         => $year,
            'period_month'        => $month,
            'generation_gwh'      => $gwh,
            'mom_pct'             => $metrics['mom_pct'] ?? null,
            'yoy_pct'             => $metrics['yoy_pct'] ?? null,
            'rolling_12m_gwh'     => $metrics['rolling_12m_gwh'] ?? null,
            'capacity_factor_pct' => $metrics['capacity_factor_pct'] ?? null,
            'momentum_score'      => $metrics['momentum_score'] ?? null,
            'data_source'         => $source,
            'is_revised'          => $existing ? 1 : 0,
        ];
        $formats = ['%d','%d','%f','%f','%f','%f','%f','%d','%s','%d'];

        if ($existing) {
            $result = $wpdb->update($table, $data, ['period_year' => $year, 'period_month' => $month], $formats, ['%d','%d']);
        } else {
            $result = $wpdb->insert($table, $data, $formats);
        }
        return $result !== false;
    }

    // ── UPSERT CAPACITY ───────────────────────────────────────────────────

    public static function upsert_capacity(int $year, int $month, float $gw, array $metrics, string $source = 'manual'): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'solar_capacity_data';

        $existing = self::get_capacity($year, $month);
        $data = [
            'period_year'          => $year,
            'period_month'         => $month,
            'capacity_gw'          => $gw,
            'monthly_addition_gw'  => $metrics['monthly_addition_gw'] ?? null,
            'rolling_12m_added_gw' => $metrics['rolling_12m_added_gw'] ?? null,
            'build_pace_gw_yr'     => $metrics['build_pace_gw_yr'] ?? null,
            'data_source'          => $source,
            'is_revised'           => $existing ? 1 : 0,
        ];
        $formats = ['%d','%d','%f','%f','%f','%f','%s','%d'];

        if ($existing) {
            $result = $wpdb->update($table, $data, ['period_year' => $year, 'period_month' => $month], $formats, ['%d','%d']);
        } else {
            $result = $wpdb->insert($table, $data, $formats);
        }
        return $result !== false;
    }

    // ── REVISION LOG ──────────────────────────────────────────────────────

    public static function log_revision(string $table_name, int $year, int $month, string $field, $old, $new, string $reason = ''): void {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'solar_revision_log',
            [
                'table_name'  => $table_name,
                'period_year' => $year,
                'period_month'=> $month,
                'field_name'  => $field,
                'old_value'   => (string) $old,
                'new_value'   => (string) $new,
                'reason'      => $reason,
            ],
            ['%s','%d','%d','%s','%s','%s','%s']
        );
    }
}
