<?php
defined('ABSPATH') || exit;

class SIS_Cron {

    const HOOK = 'sis_monthly_fetch';

    public function __construct() {
        add_action(self::HOOK,      [$this, 'run_monthly_fetch']);
        add_filter('cron_schedules', [$this, 'add_monthly_schedule']);

        if (!wp_next_scheduled(self::HOOK)) {
            // Schedule next run: 10th of next month at 08:00 UTC
            $next = mktime(8, 0, 0, (int)date('n') + 1, 10, (int)date('Y'));
            wp_schedule_event($next, 'sis_monthly', self::HOOK);
        }

        // AJAX handler for CSV regen (from admin UI)
        add_action('wp_ajax_sis_regen_csv', [$this, 'handle_regen_csv']);
    }

    public function add_monthly_schedule(array $schedules): array {
        $schedules['sis_monthly'] = [
            'interval' => 30 * DAY_IN_SECONDS,
            'display'  => 'Once Monthly (SIS)',
        ];
        return $schedules;
    }

    public function run_monthly_fetch(): void {
        $logger = new SIS_Logger('SIS_Cron');

        $target_year  = (int) date('Y', strtotime('last month'));
        $target_month = (int) date('n', strtotime('last month'));

        $logger->info("Starting fetch for {$target_year}-{$target_month}");

        try {
            // Fetch solar generation from REData
            $gen_fetcher = new SIS_REData_Fetcher();
            $gen_data    = $gen_fetcher->fetch_monthly_generation($target_year, $target_month);

            // Fetch installed capacity from REData
            $cap_fetcher = new SIS_REData_Cap_Fetcher();
            $cap_data    = $cap_fetcher->fetch_monthly_capacity($target_year, $target_month);

            // Validate
            $validator = new SIS_Validator();
            $validator->validate_generation($gen_data, $target_year, $target_month);
            $validator->validate_capacity($cap_data, $target_year, $target_month);

            // Calculate derived metrics
            $metrics = SIS_Derived_Metrics::calculate(
                $target_year, $target_month,
                $gen_data['gwh'], $cap_data['gw']
            );

            // Persist
            SIS_Database::upsert_generation($target_year, $target_month, $gen_data['gwh'], $metrics['gen'], $gen_data['source']);
            SIS_Database::upsert_capacity($target_year, $target_month, $cap_data['gw'], $metrics['cap'], $cap_data['source']);

            // Regenerate CSVs
            SIS_CSV_Exporter::regenerate_master();
            SIS_CSV_Exporter::generate_monthly_slice($target_year, $target_month);

            // Create draft bulletin posts
            $this->create_bulletin_drafts($target_year, $target_month);

            $logger->success("Fetch completed for {$target_year}-{$target_month}");

            wp_mail(
                get_option('admin_email'),
                "[SolarIndexSpain] Draft Ready: {$target_year}-{$target_month}",
                "Data fetched successfully from REData.\n\n"
                . "Generation: {$gen_data['gwh']} GWh\n"
                . "Capacity: {$cap_data['gw']} GW\n\n"
                . "Please review and publish the bulletin drafts."
            );

        } catch (SIS_Validation_Exception $e) {
            $logger->error("Validation failed: " . $e->getMessage());
            wp_mail(
                get_option('admin_email'),
                "[SolarIndexSpain] ⚠️ Fetch Failed: {$target_year}-{$target_month}",
                "Validation error: " . $e->getMessage() . "\n\nManual check required."
            );
        } catch (Exception $e) {
            $logger->error("Unexpected error: " . $e->getMessage());
            wp_mail(
                get_option('admin_email'),
                "[SolarIndexSpain] ⚠️ Fetch Error: {$target_year}-{$target_month}",
                "Unexpected error: " . $e->getMessage()
            );
        }
    }

    public function handle_regen_csv(): void {
        check_ajax_referer('sis_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        SIS_CSV_Exporter::regenerate_master();
        wp_send_json_success(['message' => 'Master CSVs regenerated.']);
    }

    public function create_bulletin_drafts(int $year, int $month): void {
        $period_label = date('F Y', mktime(0, 0, 0, $month, 1, $year));
        $last_day     = date('Y-m-d', mktime(0, 0, 0, $month + 1, 0, $year));
        $today        = date('Y-m-d');

        $types = [
            ['solar_gen_index', "Solar Generation Monthly Index — {$period_label}", 'generation'],
            ['solar_cap_index', "Installed Solar Capacity Monthly Index — {$period_label}", 'capacity'],
        ];

        foreach ($types as [$post_type, $title, $bulletin_type]) {
            $existing = get_posts([
                'post_type'   => $post_type,
                'post_status' => ['draft', 'publish'],
                'meta_query'  => [
                    ['key' => 'sis_period_year',  'value' => $year,  'compare' => '='],
                    ['key' => 'sis_period_month', 'value' => $month, 'compare' => '='],
                ],
                'numberposts' => 1,
            ]);

            if ($existing) {
                $post_id = $existing[0]->ID;
            } else {
                $post_id = wp_insert_post([
                    'post_title'  => $title,
                    'post_type'   => $post_type,
                    'post_status' => 'draft',
                ]);
            }

            if ($post_id && !is_wp_error($post_id)) {
                SIS_ACF_Fields::set($post_id, 'sis_period_year',    $year);
                SIS_ACF_Fields::set($post_id, 'sis_period_month',   $month);
                SIS_ACF_Fields::set($post_id, 'sis_data_through',   $last_day);
                SIS_ACF_Fields::set($post_id, 'sis_published_date', $today);
                SIS_ACF_Fields::set($post_id, 'sis_bulletin_type',  $bulletin_type);
            }
        }
    }
}
