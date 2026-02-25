<?php
defined('ABSPATH') || exit;

class SIS_Admin_UI {

    public function __construct() {
        add_action('admin_menu',                        [$this, 'add_menu']);
        add_action('admin_enqueue_scripts',             [$this, 'enqueue_assets']);
        add_action('wp_ajax_sis_manual_entry',          [$this, 'handle_manual_entry']);
        add_action('wp_ajax_sis_run_fetch',             [$this, 'handle_run_fetch']);
        add_action('wp_ajax_sis_run_fetch_single',      [$this, 'handle_run_fetch_single']);
        add_action('wp_ajax_sis_get_log',               [$this, 'handle_get_log']);
    }

    public function add_menu(): void {
        add_menu_page(
            'Solar Index Spain',
            'Solar Index',
            'manage_options',
            'solar-index-spain',
            [$this, 'render_page'],
            'dashicons-chart-line',
            30
        );
    }

    public function enqueue_assets(string $hook): void {
        if ($hook !== 'toplevel_page_solar-index-spain') {
            return;
        }
        wp_enqueue_script(
            'sis-admin',
            SIS_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            SIS_VERSION,
            true
        );
        wp_localize_script('sis-admin', 'sisAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('sis_admin_nonce'),
        ]);
    }

    public function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        $recent_gen = SIS_Database::get_generation_last_n_months(5);
        $recent_cap = SIS_Database::get_capacity_last_n_months(5);
        $fetch_log  = get_option('sis_fetch_log', '(no log entries yet)');
        ?>
        <div class="wrap sis-admin-wrap">
            <h1>Solar Index Spain — Admin</h1>

            <!-- ── MANUAL ENTRY ─────────────────────────────────────────── -->
            <div class="sis-admin-card">
                <h2>Manual Monthly Entry</h2>
                <p>Enter the two headline values; all derived metrics are calculated automatically.</p>
                <form id="sis-manual-form">
                    <?php wp_nonce_field('sis_manual_entry', 'sis_manual_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="sis-year">Year</label></th>
                            <td><input id="sis-year" type="number" name="year" min="2021" max="2040" value="<?php echo esc_attr(date('Y')); ?>" class="small-text"></td>
                        </tr>
                        <tr>
                            <th><label for="sis-month">Month</label></th>
                            <td><input id="sis-month" type="number" name="month" min="1" max="12" value="<?php echo esc_attr(date('n')); ?>" class="small-text"></td>
                        </tr>
                        <tr>
                            <th><label for="sis-gen">Generation (GWh)</label></th>
                            <td><input id="sis-gen" type="number" name="generation_gwh" step="0.01" min="0" placeholder="e.g. 4320.50" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="sis-cap">Capacity (GW)</label></th>
                            <td><input id="sis-cap" type="number" name="capacity_gw" step="0.001" min="0" placeholder="e.g. 32.145" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="sis-source">Data Source</label></th>
                            <td>
                                <select id="sis-source" name="data_source">
                                    <option value="manual">manual</option>
                                    <option value="redata">redata</option>
                                    <option value="esios">esios</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary">Calculate &amp; Save</button>
                    </p>
                </form>
                <div id="sis-manual-result" style="margin-top:12px;"></div>
            </div>

            <!-- ── AUTO FETCH ───────────────────────────────────────────── -->
            <div class="sis-admin-card">
                <h2>Automatic Fetch</h2>
                <p>Trigger a live fetch from REData API for last month's data. Runs validation before saving.</p>
                <button id="sis-run-fetch" class="button button-secondary">&#9654; Run Fetch Now</button>
                <div id="sis-fetch-status" style="margin-top:8px;"></div>
            </div>

            <!-- ── BACKFILL YEAR ─────────────────────────────────────────── -->
            <div class="sis-admin-card">
                <h2>Backfill Entire Year</h2>
                <p>Fetch all months of a given year from REData API, one by one. Useful for filling historical data without CLI access.</p>
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <label for="sis-backfill-year"><strong>Year:</strong></label>
                    <input id="sis-backfill-year" type="number" min="2021" max="<?php echo esc_attr(date('Y')); ?>"
                           value="<?php echo esc_attr(date('Y') - 1); ?>" class="small-text">
                    <label for="sis-backfill-start-month"><strong>From month:</strong></label>
                    <input id="sis-backfill-start-month" type="number" min="1" max="12" value="1" class="small-text" style="width:50px;">
                    <label for="sis-backfill-end-month"><strong>To month:</strong></label>
                    <input id="sis-backfill-end-month" type="number" min="1" max="12" value="12" class="small-text" style="width:50px;">
                    <button id="sis-backfill-year-btn" class="button button-primary">&#9654; Backfill Year</button>
                    <button id="sis-backfill-stop-btn" class="button" style="display:none;">&#9632; Stop</button>
                </div>
                <div id="sis-backfill-progress" style="margin-top:12px;font-family:monospace;font-size:12px;max-height:200px;overflow-y:auto;background:#f6f7f7;border:1px solid #ddd;padding:8px 12px;display:none;"></div>
            </div>

            <!-- ── FETCH LOG ────────────────────────────────────────────── -->
            <div class="sis-admin-card">
                <h2>Fetch Log</h2>
                <textarea id="sis-fetch-log" readonly style="width:100%;height:200px;font-family:monospace;font-size:12px;"><?php echo esc_textarea($fetch_log); ?></textarea>
                <button id="sis-refresh-log" class="button" style="margin-top:6px;">&#8635; Refresh Log</button>
            </div>

            <!-- ── RECENT DATA ──────────────────────────────────────────── -->
            <div class="sis-admin-card">
                <h2>Recent Generation Data</h2>
                <?php if ($recent_gen): ?>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th>Period</th><th>GWh</th><th>MoM%</th><th>YoY%</th>
                            <th>CF%</th><th>Roll-12m GWh</th><th>Source</th><th>Revised</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent_gen as $r): ?>
                        <tr>
                            <td><?php echo esc_html(sprintf('%04d-%02d', $r->period_year, $r->period_month)); ?></td>
                            <td><?php echo esc_html(number_format((float)$r->generation_gwh, 1)); ?></td>
                            <td><?php echo $r->mom_pct !== null ? esc_html($r->mom_pct . '%') : '—'; ?></td>
                            <td><?php echo $r->yoy_pct !== null ? esc_html($r->yoy_pct . '%') : '—'; ?></td>
                            <td><?php echo $r->capacity_factor_pct !== null ? esc_html($r->capacity_factor_pct . '%') : '—'; ?></td>
                            <td><?php echo $r->rolling_12m_gwh !== null ? esc_html(number_format((float)$r->rolling_12m_gwh, 0)) : '—'; ?></td>
                            <td><code><?php echo esc_html($r->data_source); ?></code></td>
                            <td><?php echo $r->is_revised ? 'Yes' : 'No'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p>No generation data yet.</p>
                <?php endif; ?>
            </div>

            <div class="sis-admin-card">
                <h2>Recent Capacity Data</h2>
                <?php if ($recent_cap): ?>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th>Period</th><th>GW</th><th>Monthly Add (GW)</th>
                            <th>Roll-12m Add</th><th>Build Pace GW/yr</th><th>Source</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent_cap as $r): ?>
                        <tr>
                            <td><?php echo esc_html(sprintf('%04d-%02d', $r->period_year, $r->period_month)); ?></td>
                            <td><?php echo esc_html(number_format((float)$r->capacity_gw, 3)); ?></td>
                            <td><?php echo $r->monthly_addition_gw !== null ? esc_html($r->monthly_addition_gw) : '—'; ?></td>
                            <td><?php echo $r->rolling_12m_added_gw !== null ? esc_html($r->rolling_12m_added_gw) : '—'; ?></td>
                            <td><?php echo $r->build_pace_gw_yr !== null ? esc_html($r->build_pace_gw_yr) : '—'; ?></td>
                            <td><code><?php echo esc_html($r->data_source); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p>No capacity data yet.</p>
                <?php endif; ?>
            </div>

            <div class="sis-admin-card">
                <h2>CSV Downloads</h2>
                <ul>
                    <li><a href="<?php echo esc_url(SIS_CSV_Exporter::get_generation_master_url()); ?>" target="_blank">solar-generation-spain-master.csv</a></li>
                    <li><a href="<?php echo esc_url(SIS_CSV_Exporter::get_capacity_master_url()); ?>" target="_blank">solar-capacity-spain-master.csv</a></li>
                </ul>
                <button id="sis-regen-csv" class="button">&#8635; Regenerate Master CSVs</button>
                <div id="sis-csv-result" style="margin-top:8px;"></div>
            </div>
        </div>

        <style>
            .sis-admin-wrap .sis-admin-card {
                background:#fff;
                border:1px solid #c3c4c7;
                border-radius:4px;
                padding:20px 24px;
                margin-bottom:20px;
                max-width:900px;
            }
            .sis-admin-wrap .sis-admin-card h2 {
                margin-top:0;
                font-size:1.1em;
                border-bottom:1px solid #eee;
                padding-bottom:8px;
                margin-bottom:16px;
            }
        </style>
        <?php
    }

    // ── AJAX: Manual Entry ─────────────────────────────────────────────────

    public function handle_manual_entry(): void {
        check_ajax_referer('sis_manual_entry', 'sis_manual_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $year   = absint($_POST['year']   ?? 0);
        $month  = absint($_POST['month']  ?? 0);
        $gen    = floatval($_POST['generation_gwh'] ?? 0);
        $cap    = floatval($_POST['capacity_gw']    ?? 0);
        $source = sanitize_key($_POST['data_source'] ?? 'manual');

        if ($year < 2021 || $month < 1 || $month > 12 || $gen <= 0 || $cap <= 0) {
            wp_send_json_error('Invalid input values. Check year, month, generation, and capacity.');
        }

        try {
            $validator = new SIS_Validator();
            $validator->validate_generation(['gwh' => $gen], $year, $month);
            $validator->validate_capacity(['gw' => $cap], $year, $month);
        } catch (SIS_Validation_Exception $e) {
            wp_send_json_error('Validation: ' . $e->getMessage());
        }

        $metrics = SIS_Derived_Metrics::calculate($year, $month, $gen, $cap);

        SIS_Database::upsert_generation($year, $month, $gen, $metrics['gen'], $source);
        SIS_Database::upsert_capacity($year, $month, $cap, $metrics['cap'], $source);

        SIS_CSV_Exporter::regenerate_master();
        SIS_CSV_Exporter::generate_monthly_slice($year, $month);

        $this->create_bulletin_drafts($year, $month);

        wp_send_json_success([
            'message' => sprintf('Saved %04d-%02d. Drafts created.', $year, $month),
            'metrics' => $metrics,
        ]);
    }

    // ── AJAX: Run Fetch ────────────────────────────────────────────────────

    public function handle_run_fetch(): void {
        check_ajax_referer('sis_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        try {
            $cron = new SIS_Cron();
            $cron->run_monthly_fetch();
            $log = get_option('sis_fetch_log', '');
            wp_send_json_success(['log' => $log]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    // ── AJAX: Get Log ──────────────────────────────────────────────────────

    public function handle_get_log(): void {
        check_ajax_referer('sis_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        wp_send_json_success(['log' => get_option('sis_fetch_log', '')]);
    }

    // ── AJAX: Fetch Single Month (used by Backfill Year) ───────────────────

    public function handle_run_fetch_single(): void {
        check_ajax_referer('sis_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $year  = absint($_POST['year']  ?? 0);
        $month = absint($_POST['month'] ?? 0);

        if ($year < 2021 || $month < 1 || $month > 12) {
            wp_send_json_error('Invalid year or month.');
        }

        $logger = new SIS_Logger('SIS_Backfill');

        try {
            $gen_fetcher = new SIS_REData_Fetcher();
            $gen_data    = $gen_fetcher->fetch_monthly_generation($year, $month);

            $cap_fetcher = new SIS_REData_Cap_Fetcher();
            $cap_data    = $cap_fetcher->fetch_monthly_capacity($year, $month);

            $validator = new SIS_Validator();
            $validator->validate_generation($gen_data, $year, $month);
            $validator->validate_capacity($cap_data, $year, $month);

            $metrics = SIS_Derived_Metrics::calculate($year, $month, $gen_data['gwh'], $cap_data['gw']);

            SIS_Database::upsert_generation($year, $month, $gen_data['gwh'], $metrics['gen'], $gen_data['source']);
            SIS_Database::upsert_capacity($year, $month, $cap_data['gw'], $metrics['cap'], $cap_data['source']);

            SIS_CSV_Exporter::regenerate_master();
            SIS_CSV_Exporter::generate_monthly_slice($year, $month);

            $this->create_bulletin_drafts($year, $month);

            $logger->success("Backfill OK: {$year}-{$month} — Gen: {$gen_data['gwh']} GWh, Cap: {$cap_data['gw']} GW");

            wp_send_json_success([
                'period'  => sprintf('%04d-%02d', $year, $month),
                'gwh'     => $gen_data['gwh'],
                'gw'      => $cap_data['gw'],
            ]);

        } catch (SIS_Validation_Exception $e) {
            $logger->error("Backfill validation {$year}-{$month}: " . $e->getMessage());
            wp_send_json_error(['period' => sprintf('%04d-%02d', $year, $month), 'message' => $e->getMessage()]);
        } catch (Exception $e) {
            $logger->error("Backfill error {$year}-{$month}: " . $e->getMessage());
            wp_send_json_error(['period' => sprintf('%04d-%02d', $year, $month), 'message' => $e->getMessage()]);
        }
    }

    // ── Bulletin Draft Creator ─────────────────────────────────────────────

    private function create_bulletin_drafts(int $year, int $month): void {
        $period_label = date('F Y', mktime(0, 0, 0, $month, 1, $year));
        $last_day     = date('Y-m-d', mktime(0, 0, 0, $month + 1, 0, $year));
        $today        = date('Y-m-d');

        foreach ([
            ['solar_gen_index', "Solar Generation Monthly Index — {$period_label}"],
            ['solar_cap_index', "Installed Solar Capacity Monthly Index — {$period_label}"],
        ] as [$post_type, $title]) {
            // Check if post already exists
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
                SIS_ACF_Fields::set($post_id, 'sis_bulletin_type',  str_contains($post_type, 'gen') ? 'generation' : 'capacity');
            }
        }
    }
}
