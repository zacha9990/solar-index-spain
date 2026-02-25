<?php
defined('ABSPATH') || exit;

class SIS_CSV_Exporter {

    const UPLOAD_SUBDIR = 'solar-index-spain/csv';

    // ── MASTER CSVs ────────────────────────────────────────────────────────

    public static function regenerate_master(): void {
        self::regenerate_generation_master();
        self::regenerate_capacity_master();
    }

    private static function regenerate_generation_master(): void {
        $rows = SIS_Database::get_all_generation();

        $csv  = "year,month,period,generation_gwh,mom_pct,yoy_pct,rolling_12m_gwh,capacity_factor_pct,momentum_score,data_source\n";
        foreach ($rows as $r) {
            $period = sprintf('%04d-%02d', $r->period_year, $r->period_month);
            $csv .= implode(',', [
                $r->period_year,
                $r->period_month,
                $period,
                $r->generation_gwh,
                $r->mom_pct             ?? '',
                $r->yoy_pct             ?? '',
                $r->rolling_12m_gwh     ?? '',
                $r->capacity_factor_pct ?? '',
                $r->momentum_score      ?? '',
                $r->data_source,
            ]) . "\n";
        }
        self::write_file('solar-generation-spain-master.csv', $csv);
    }

    private static function regenerate_capacity_master(): void {
        $rows = SIS_Database::get_all_capacity();

        $csv  = "year,month,period,capacity_gw,monthly_addition_gw,rolling_12m_added_gw,build_pace_gw_yr,data_source\n";
        foreach ($rows as $r) {
            $period = sprintf('%04d-%02d', $r->period_year, $r->period_month);
            $csv .= implode(',', [
                $r->period_year,
                $r->period_month,
                $period,
                $r->capacity_gw,
                $r->monthly_addition_gw  ?? '',
                $r->rolling_12m_added_gw ?? '',
                $r->build_pace_gw_yr     ?? '',
                $r->data_source,
            ]) . "\n";
        }
        self::write_file('solar-capacity-spain-master.csv', $csv);
    }

    // ── MONTHLY SLICES ─────────────────────────────────────────────────────

    public static function generate_monthly_slice(int $year, int $month): void {
        self::generate_generation_slice($year, $month);
        self::generate_capacity_slice($year, $month);
    }

    private static function generate_generation_slice(int $year, int $month): void {
        $row = SIS_Database::get_generation($year, $month);
        if (!$row) {
            return;
        }
        $period   = sprintf('%04d-%02d', $year, $month);
        $filename = "solar-generation-spain-{$period}.csv";
        $csv      = "year,month,period,generation_gwh,mom_pct,yoy_pct,rolling_12m_gwh,capacity_factor_pct,momentum_score,data_source\n";
        $csv .= implode(',', [
            $row->period_year,
            $row->period_month,
            $period,
            $row->generation_gwh,
            $row->mom_pct             ?? '',
            $row->yoy_pct             ?? '',
            $row->rolling_12m_gwh     ?? '',
            $row->capacity_factor_pct ?? '',
            $row->momentum_score      ?? '',
            $row->data_source,
        ]) . "\n";
        self::write_file($filename, $csv);
    }

    private static function generate_capacity_slice(int $year, int $month): void {
        $row = SIS_Database::get_capacity($year, $month);
        if (!$row) {
            return;
        }
        $period   = sprintf('%04d-%02d', $year, $month);
        $filename = "solar-capacity-spain-{$period}.csv";
        $csv      = "year,month,period,capacity_gw,monthly_addition_gw,rolling_12m_added_gw,build_pace_gw_yr,data_source\n";
        $csv .= implode(',', [
            $row->period_year,
            $row->period_month,
            $period,
            $row->capacity_gw,
            $row->monthly_addition_gw  ?? '',
            $row->rolling_12m_added_gw ?? '',
            $row->build_pace_gw_yr     ?? '',
            $row->data_source,
        ]) . "\n";
        self::write_file($filename, $csv);
    }

    // ── FILE HELPERS ──────────────────────────────────────────────────────

    private static function write_file(string $filename, string $content): void {
        $upload = wp_upload_dir();
        $dir    = $upload['basedir'] . '/' . self::UPLOAD_SUBDIR;

        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
            // Block directory listing
            file_put_contents($dir . '/.htaccess', "Options -Indexes\n");
        }

        file_put_contents($dir . '/' . $filename, $content);
    }

    public static function get_download_url(string $filename): string {
        $upload = wp_upload_dir();
        return $upload['baseurl'] . '/' . self::UPLOAD_SUBDIR . '/' . $filename;
    }

    public static function get_generation_master_url(): string {
        return self::get_download_url('solar-generation-spain-master.csv');
    }

    public static function get_capacity_master_url(): string {
        return self::get_download_url('solar-capacity-spain-master.csv');
    }

    public static function get_generation_slice_url(int $year, int $month): string {
        $period = sprintf('%04d-%02d', $year, $month);
        return self::get_download_url("solar-generation-spain-{$period}.csv");
    }

    public static function get_capacity_slice_url(int $year, int $month): string {
        $period = sprintf('%04d-%02d', $year, $month);
        return self::get_download_url("solar-capacity-spain-{$period}.csv");
    }
}
