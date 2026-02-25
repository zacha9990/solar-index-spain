<?php
/**
 * Template: Solar Generation Monthly Index bulletin
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

$data       = SIS_Database::get_generation($year, $month);
$chart_rows = SIS_Database::get_generation_last_n_months(24);
$prev_data  = SIS_Database::get_generation(...SIS_Derived_Metrics::prev_month($year, $month));
$ly_data    = SIS_Database::get_generation($year - 1, $month);

$bulletin_type = 'generation';
$period_label  = date('F Y', mktime(0, 0, 0, $month, 1, $year));

// Build chart datasets
$chart_rows_asc = array_reverse($chart_rows); // oldest first

// YoY: split into this year vs last year by month (Jan-Dec)
$this_year_monthly = array_fill(0, 12, null);
$last_year_monthly = array_fill(0, 12, null);
$ytd_this = [];
$ytd_last = [];
$ytd_this_cum = 0.0;
$ytd_last_cum = 0.0;

foreach ($chart_rows_asc as $r) {
    $m_idx = (int)$r->period_month - 1; // 0-indexed
    if ((int)$r->period_year === $year) {
        $this_year_monthly[$m_idx] = (float)$r->generation_gwh;
    } elseif ((int)$r->period_year === $year - 1) {
        $last_year_monthly[$m_idx] = (float)$r->generation_gwh;
    }
}

// YTD cumulative (up to current month)
for ($i = 0; $i < $month; $i++) {
    if ($this_year_monthly[$i] !== null) {
        $ytd_this_cum += $this_year_monthly[$i];
        $ytd_this[] = round($ytd_this_cum, 1);
    } else {
        $ytd_this[] = null;
    }
    if ($last_year_monthly[$i] !== null) {
        $ytd_last_cum += $last_year_monthly[$i];
        $ytd_last[] = round($ytd_last_cum, 1);
    } else {
        $ytd_last[] = null;
    }
}

// Inline chart data for wp_localize_script equivalent (called before get_header)
$chart_payload = [
    'labels'       => array_map(fn($r) => date('M Y', mktime(0,0,0,(int)$r->period_month,1,(int)$r->period_year)), $chart_rows_asc),
    'values'       => array_map(fn($r) => (float)$r->generation_gwh, $chart_rows_asc),
    'currentYear'  => $year,
    'currentMonth' => $month,
    'yoy' => [
        'thisYear' => $this_year_monthly,
        'lastYear' => $last_year_monthly,
    ],
    'ytd' => [
        'thisYear' => $ytd_this,
        'lastYear' => $ytd_last,
    ],
];

wp_localize_script('sis-charts', 'sisChartData', $chart_payload);

get_header();
?>

<main class="sis-bulletin-wrap">
<article class="sis-bulletin sis-bulletin--generation" id="post-<?php the_ID(); ?>">

    <!-- BREADCRUMB -->
    <nav class="sis-breadcrumb" aria-label="Breadcrumb">
        <a href="<?php echo esc_url(home_url('/')); ?>">Home</a>
        <span> / </span>
        <a href="<?php echo esc_url(get_post_type_archive_link('solar_gen_index') ?: home_url('/solar-generation/')); ?>">Solar Generation</a>
        <span> / </span>
        <?php echo esc_html($period_label); ?>
    </nav>

    <!-- HERO METRIC -->
    <section class="sis-hero">
        <div class="sis-hero__eyebrow">Spain Solar Generation — Monthly Index</div>
        <h1 class="sis-hero__period"><?php echo esc_html($period_label); ?></h1>

        <?php if ($data): ?>
        <div class="sis-hero__metric">
            <?php echo esc_html(number_format((float)$data->generation_gwh, 0, '.', ',')); ?>
            <span class="sis-unit">GWh</span>
        </div>
        <div class="sis-hero__changes">
            <?php if ($data->mom_pct !== null): ?>
                <span class="sis-badge sis-badge--<?php echo (float)$data->mom_pct >= 0 ? 'up' : 'down'; ?>">
                    MoM <?php echo (float)$data->mom_pct >= 0 ? '+' : ''; ?><?php echo esc_html((string)$data->mom_pct); ?>%
                </span>
            <?php endif; ?>
            <?php if ($data->yoy_pct !== null): ?>
                <span class="sis-badge sis-badge--<?php echo (float)$data->yoy_pct >= 0 ? 'up' : 'down'; ?>">
                    YoY <?php echo (float)$data->yoy_pct >= 0 ? '+' : ''; ?><?php echo esc_html((string)$data->yoy_pct); ?>%
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
        <div class="sis-signal-box">
            <h3>Momentum Score</h3>
            <div class="sis-signal-box__value">
                <?php echo $data->momentum_score !== null ? esc_html((string)$data->momentum_score) . ' / 100' : '—'; ?>
            </div>
            <p>Composite of YoY and MoM performance. 50 = in line with trend.</p>
        </div>

        <div class="sis-signal-box sis-signal-box--accent">
            <h3>Past 12 Months Total</h3>
            <div class="sis-signal-box__value">
                <?php echo $data->rolling_12m_gwh !== null
                    ? esc_html(number_format((float)$data->rolling_12m_gwh, 0, '.', ',')) . ' GWh'
                    : '—'; ?>
            </div>
            <p>Rolling 12-month solar generation (GWh).</p>
        </div>

        <div class="sis-signal-box">
            <h3>Implied Capacity Factor</h3>
            <div class="sis-signal-box__value">
                <?php echo $data->capacity_factor_pct !== null ? esc_html((string)$data->capacity_factor_pct) . '%' : '—'; ?>
            </div>
            <p>Generation ÷ (installed capacity × hours in month). Typical: 12–22%.</p>
        </div>

        <div class="sis-signal-box">
            <h3>Data Update</h3>
            <div class="sis-signal-box__value">
                <?php echo $data->is_revised ? 'Revised' : 'No changes'; ?>
            </div>
            <p>Indicates whether this figure has been restated since first publication.</p>
        </div>
    </section>

    <!-- CHARTS -->
    <section class="sis-charts" aria-label="Charts">
        <div class="sis-chart-block">
            <h3>24-Month Trend</h3>
            <canvas id="chart-trend-24m" height="100"></canvas>
        </div>
        <div class="sis-chart-block">
            <h3>Year-on-Year Comparison</h3>
            <canvas id="chart-yoy" height="100"></canvas>
        </div>
        <div class="sis-chart-block">
            <h3>YTD Cumulative</h3>
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
                    <th>MoM %</th>
                    <th>YoY %</th>
                    <th>Source</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Solar Generation (GWh)</td>
                    <td><?php echo esc_html(number_format((float)$data->generation_gwh, 1)); ?></td>
                    <td><?php echo $prev_data ? esc_html(number_format((float)$prev_data->generation_gwh, 1)) : '—'; ?></td>
                    <td><?php echo $ly_data ? esc_html(number_format((float)$ly_data->generation_gwh, 1)) : '—'; ?></td>
                    <td><?php echo $data->mom_pct !== null ? esc_html(((float)$data->mom_pct >= 0 ? '+' : '') . $data->mom_pct . '%') : '—'; ?></td>
                    <td><?php echo $data->yoy_pct !== null ? esc_html(((float)$data->yoy_pct >= 0 ? '+' : '') . $data->yoy_pct . '%') : '—'; ?></td>
                    <td>REE/REData</td>
                </tr>
                <tr>
                    <td>Rolling 12-month total (GWh)</td>
                    <td colspan="5"><?php echo $data->rolling_12m_gwh !== null ? esc_html(number_format((float)$data->rolling_12m_gwh, 0)) : '—'; ?></td>
                    <td>Derived</td>
                </tr>
                <tr>
                    <td>Implied Capacity Factor (%)</td>
                    <td colspan="5"><?php echo $data->capacity_factor_pct !== null ? esc_html($data->capacity_factor_pct . '%') : '—'; ?></td>
                    <td>Derived</td>
                </tr>
                <tr>
                    <td>Momentum Score</td>
                    <td colspan="5"><?php echo $data->momentum_score !== null ? esc_html((string)$data->momentum_score . ' / 100') : '—'; ?></td>
                    <td>Derived</td>
                </tr>
            </tbody>
        </table>
        </div>
    </section>

    <?php endif; // $data ?>

    <!-- FOOTER PARTIAL (downloads, methodology, citation, update log) -->
    <?php include SIS_PLUGIN_DIR . 'templates/partials/bulletin-footer.php'; ?>

</article>
</main>

<?php get_footer(); ?>
