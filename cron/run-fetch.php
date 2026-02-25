<?php
/**
 * CLI-only cron entry point.
 * Run via crontab: 0 8 10 * * php /path/to/wp-content/plugins/solar-index-spain/cron/run-fetch.php
 *
 * SECURITY: Dies immediately if not invoked from CLI.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only.');
}

// Bootstrap WordPress
$wp_load = dirname(__DIR__, 4) . '/wp-load.php';
if (!file_exists($wp_load)) {
    fwrite(STDERR, "ERROR: wp-load.php not found at {$wp_load}\n");
    exit(1);
}
require_once $wp_load;

// Optional: run for a specific year/month passed as CLI args
// Usage: php run-fetch.php [year] [month]
$override_year  = isset($argv[1]) ? (int)$argv[1] : null;
$override_month = isset($argv[2]) ? (int)$argv[2] : null;

if ($override_year && $override_month) {
    // Run fetch for a specified period (useful for backfilling)
    echo "Running manual fetch for {$override_year}-{$override_month}\n";

    try {
        $gen_fetcher = new SIS_REData_Fetcher();
        $gen_data    = $gen_fetcher->fetch_monthly_generation($override_year, $override_month);

        $cap_fetcher = new SIS_REData_Cap_Fetcher();
        $cap_data    = $cap_fetcher->fetch_monthly_capacity($override_year, $override_month);

        $validator = new SIS_Validator();
        $validator->validate_generation($gen_data, $override_year, $override_month);
        $validator->validate_capacity($cap_data, $override_year, $override_month);

        $metrics = SIS_Derived_Metrics::calculate($override_year, $override_month, $gen_data['gwh'], $cap_data['gw']);

        SIS_Database::upsert_generation($override_year, $override_month, $gen_data['gwh'], $metrics['gen'], $gen_data['source']);
        SIS_Database::upsert_capacity($override_year, $override_month, $cap_data['gw'], $metrics['cap'], $cap_data['source']);

        SIS_CSV_Exporter::regenerate_master();
        SIS_CSV_Exporter::generate_monthly_slice($override_year, $override_month);

        echo "✓ Fetch complete. Generation: {$gen_data['gwh']} GWh, Capacity: {$cap_data['gw']} GW\n";

    } catch (SIS_Validation_Exception $e) {
        fwrite(STDERR, "VALIDATION ERROR: " . $e->getMessage() . "\n");
        exit(2);
    } catch (Exception $e) {
        fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
        exit(3);
    }
} else {
    // Standard: run last-month fetch via cron class
    echo "Running monthly fetch (last month)\n";
    $cron = new SIS_Cron();
    $cron->run_monthly_fetch();
    echo "Done.\n";
}
