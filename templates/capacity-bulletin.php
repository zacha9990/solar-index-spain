<?php
/**
 * Template: Installed Solar Capacity Monthly Index bulletin
 */
defined('ABSPATH') || exit;

$post_id = get_the_ID();
$year    = (int) SIS_ACF_Fields::get($post_id, 'sis_period_year');
$month   = (int) SIS_ACF_Fields::get($post_id, 'sis_period_month');

if (!$year || !$month) {
    get_header();
    echo '<p>Bulletin data not configured.</p>';
    get_footer();
    return;
}

$data       = SIS_Database::get_capacity($year, $month);
$chart_rows = SIS_Database::get_capacity_last_n_months(24);
$prev_data  = SIS_Database::get_capacity(...SIS_Derived_Metrics::prev_month($year, $month));
$ly_data    = SIS_Database::get_capacity($year - 1, $month);

$bulletin_type = 'capacity';
$period_label  = date('F Y', mktime(0, 0, 0, $month, 1, $year));

// Build chart datasets (oldest first)
$chart_rows_asc = array_reverse($chart_rows);

$ytd_this_additions = [];
$ytd_last_additions = [];
$ytd_this_cum = 0.0;
$ytd_last_cum = 0.0;

// Separate by year for YTD additions chart
$this_year_add = array_fill(0, 12, null);
$last_year_add = array_fill(0, 12, null);

foreach ($chart_rows_asc as $r) {
    $m_idx = (int)$r->period_month - 1;
    if ((int)$r->period_year === $year) {
        $this_year_add[$m_idx] = $r->monthly_addition_gw !== null ? (float)$r->monthly_addition_gw : null;
    } elseif ((int)$r->period_year === $year - 1) {
        $last_year_add[$m_idx] = $r->monthly_addition_gw !== null ? (float)$r->monthly_addition_gw : null;
    }
}

for ($i = 0; $i < $month; $i++) {
    if ($this_year_add[$i] !== null) {
        $ytd_this_cum += $this_year_add[$i];
        $ytd_this_additions[] = round($ytd_this_cum, 3);
    } else {
        $ytd_this_additions[] = null;
    }
    if ($last_year_add[$i] !== null) {
        $ytd_last_cum += $last_year_add[$i];
        $ytd_last_additions[] = round($ytd_last_cum, 3);
    } else {
        $ytd_last_additions[] = null;
    }
}

$chart_payload = [
    'type'         => 'capacity',
    'labels'       => array_map(fn($r) => date('M Y', mktime(0,0,0,(int)$r->period_month,1,(int)$r->period_year)), $chart_rows_asc),
    'values'       => array_map(fn($r) => (float)$r->capacity_gw, $chart_rows_asc),
    'additions'    => array_map(fn($r) => $r->monthly_addition_gw !== null ? (float)$r->monthly_addition_gw : null, $chart_rows_asc),
    'currentYear'  => $year,
    'currentMonth' => $month,
    'ytd' => [
        'thisYear' => $ytd_this_additions,
        'lastYear' => $ytd_last_additions,
    ],
];

wp_localize_script('sis-charts', 'sisChartData', $chart_payload);

get_header();
?>

<main class="sis-bulletin-wrap">
<article class="sis-bulletin sis-bulletin--capacity" id="post-<?php the_ID(); ?>">

    <!-- BREADCRUMB -->
    <nav class="sis-breadcrumb" aria-label="Breadcrumb">
        <a href="<?php echo esc_url(home_url('/')); ?>">Home</a>
        <span> / </span>
        <a href="<?php echo esc_url(get_post_type_archive_link('solar_cap_index') ?: home_url('/solar-capacity/')); ?>">Solar Capacity</a>
        <span> / </span>
        <?php echo esc_html($period_label); ?>
    </nav>

    <!-- HERO METRIC -->
    <section class="sis-hero">
        <div class="sis-hero__eyebrow">Spain Installed Solar Capacity — Monthly Index</div>
        <h1 class="sis-hero__period"><?php echo esc_html($period_label); ?></h1>

        <?php if ($data): ?>
        <div class="sis-hero__metric">
            <?php echo esc_html(number_format((float)$data->capacity_gw, 3, '.', ',')); ?>
            <span class="sis-unit">GW</span>
        </div>
        <div class="sis-hero__changes">
            <?php if ($data->monthly_addition_gw !== null): ?>
                <span class="sis-badge sis-badge--<?php echo (float)$data->monthly_addition_gw >= 0 ? 'up' : 'down'; ?>">
                    Monthly addition:
                    <?php echo (float)$data->monthly_addition_gw >= 0 ? '+' : ''; ?>
                    <?php echo esc_html((string)$data->monthly_addition_gw); ?> GW
                </span>
            <?php endif; ?>
            <span class="sis-source-tag">Source: REE</span>
        </div>
        <?php else: ?>
            <p class="sis-no-data">Data for <?php echo esc_html($period_label); ?> not yet available.</p>
        <?php endif; ?>
    </section>

    <?php if ($data): ?>

    <!-- KEY SIGNALS -->
    <section class="sis-signals" aria-label="Key signals">
        <div class="sis-signal-box sis-signal-box--accent">
            <h3>Build Pace</h3>
            <div class="sis-signal-box__value">
                <?php echo $data->build_pace_gw_yr !== null
                    ? esc_html(number_format((float)$data->build_pace_gw_yr, 1)) . ' GW/yr'
                    : '—'; ?>
            </div>
            <p>Annualised installation rate (= rolling 12-month additions).</p>
        </div>

        <div class="sis-signal-box">
            <h3>Past 12 Months Added</h3>
            <div class="sis-signal-box__value">
                <?php echo $data->rolling_12m_added_gw !== null
                    ? esc_html(number_format((float)$data->rolling_12m_added_gw, 2)) . ' GW'
                    : '—'; ?>
            </div>
            <p>Total new solar capacity added in the rolling 12-month window.</p>
        </div>

        <div class="sis-signal-box">
            <h3>Monthly Addition</h3>
            <div class="sis-signal-box__value">
                <?php echo $data->monthly_addition_gw !== null
                    ? esc_html(((float)$data->monthly_addition_gw >= 0 ? '+' : '') . $data->monthly_addition_gw) . ' GW'
                    : '—'; ?>
            </div>
            <p>Net change in installed capacity vs. prior month.</p>
        </div>

        <div class="sis-signal-box">
            <h3>Data Update</h3>
            <div class="sis-signal-box__value">
                <?php echo $data->is_revised ? 'Revised' : 'No changes'; ?>
            </div>
            <p>Whether this figure has been restated since first publication.</p>
        </div>
    </section>

    <!-- CHARTS -->
    <section class="sis-charts" aria-label="Charts">
        <div class="sis-chart-block">
            <h3>Installed Capacity — 24-Month Trend</h3>
            <canvas id="chart-trend-24m" height="100"></canvas>
        </div>
        <div class="sis-chart-block">
            <h3>Monthly Additions (GW)</h3>
            <canvas id="chart-yoy" height="100"></canvas>
        </div>
        <div class="sis-chart-block">
            <h3>YTD Cumulative Additions</h3>
            <canvas id="chart-ytd" height="100"></canvas>
        </div>
    </section>

    <!-- KEY TABLE -->
    <section class="sis-table-section">
        <h2>Headline Metrics Summary</h2>
        <div class="sis-table-scroll">
        <table class="sis-key-table">
            <thead>
                <tr>
                    <th>Metric</th>
                    <th>This Month</th>
                    <th>Last Month</th>
                    <th>Same Month LY</th>
                    <th>Monthly Δ</th>
                    <th>Source</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Installed Capacity (GW)</td>
                    <td><?php echo esc_html(number_format((float)$data->capacity_gw, 3)); ?></td>
                    <td><?php echo $prev_data ? esc_html(number_format((float)$prev_data->capacity_gw, 3)) : '—'; ?></td>
                    <td><?php echo $ly_data ? esc_html(number_format((float)$ly_data->capacity_gw, 3)) : '—'; ?></td>
                    <td><?php echo $data->monthly_addition_gw !== null ? esc_html(((float)$data->monthly_addition_gw >= 0 ? '+' : '') . $data->monthly_addition_gw . ' GW') : '—'; ?></td>
                    <td>REE/REData</td>
                </tr>
                <tr>
                    <td>Rolling 12-month additions (GW)</td>
                    <td colspan="4"><?php echo $data->rolling_12m_added_gw !== null ? esc_html(number_format((float)$data->rolling_12m_added_gw, 2) . ' GW') : '—'; ?></td>
                    <td>Derived</td>
                </tr>
                <tr>
                    <td>Build pace (GW/yr)</td>
                    <td colspan="4"><?php echo $data->build_pace_gw_yr !== null ? esc_html(number_format((float)$data->build_pace_gw_yr, 1) . ' GW/yr') : '—'; ?></td>
                    <td>Derived</td>
                </tr>
            </tbody>
        </table>
        </div>
    </section>

    <?php endif; // $data ?>

    <!-- FOOTER PARTIAL -->
    <?php include SIS_PLUGIN_DIR . 'templates/partials/bulletin-footer.php'; ?>

</article>
</main>

<?php get_footer(); ?>
