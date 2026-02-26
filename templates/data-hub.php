<?php
/**
 * Template: Data Hub page
 * Used by /data/solar-generation-spain/ and /data/solar-capacity-spain/
 */
defined('ABSPATH') || exit;

global $post;

// Determine hub type from page slug
$slug          = $post ? $post->post_name : '';
$is_generation = strpos($slug, 'generation') !== false;
$type          = $is_generation ? 'generation' : 'capacity';

$title       = $is_generation
    ? 'Spain Solar Generation'
    : 'Spain Installed Solar Capacity';
$subtitle    = 'Historical Data Series';
$description = $is_generation
    ? 'Monthly solar photovoltaic generation (GWh) for Spain from January 2021 to present. Source: REE (Red Eléctrica de España) via REData API.'
    : 'Monthly installed solar photovoltaic capacity (GW) for Spain from January 2021 to present. Source: REE (Red Eléctrica de España) via REData API.';

$master_url = $is_generation
    ? SIS_CSV_Exporter::get_generation_master_url()
    : SIS_CSV_Exporter::get_capacity_master_url();

$rows = $is_generation
    ? SIS_Database::get_all_generation()
    : SIS_Database::get_all_capacity();

// Derive summary stats from the data set
$latest      = $rows ? $rows[0] : null;   // get_all_* returns newest-first
$oldest      = $rows ? $rows[count($rows) - 1] : null;
$total_months = count($rows);

if ($is_generation) {
    $latest_value  = $latest ? number_format((float)$latest->generation_gwh, 1) . ' GWh' : '—';
    $latest_mom    = $latest && $latest->mom_pct !== null ? (((float)$latest->mom_pct >= 0 ? '+' : '') . $latest->mom_pct . '%') : null;
    $latest_yoy    = $latest && $latest->yoy_pct !== null ? (((float)$latest->yoy_pct >= 0 ? '+' : '') . $latest->yoy_pct . '%') : null;
    $unit_label    = 'GWh';
} else {
    $latest_value  = $latest ? number_format((float)$latest->capacity_gw, 3) . ' GW' : '—';
    $latest_mom    = $latest && $latest->monthly_addition_gw !== null ? (((float)$latest->monthly_addition_gw >= 0 ? '+' : '') . $latest->monthly_addition_gw . ' GW') : null;
    $latest_yoy    = null;
    $unit_label    = 'GW';
}

$latest_period = $latest
    ? date('F Y', mktime(0, 0, 0, (int)$latest->period_month, 1, (int)$latest->period_year))
    : '—';
$oldest_period = $oldest
    ? date('M Y', mktime(0, 0, 0, (int)$oldest->period_month, 1, (int)$oldest->period_year))
    : '—';

get_header();
?>

<main class="sis-hub-wrap">
<article class="sis-data-hub">

    <!-- BREADCRUMB -->
    <nav class="sis-breadcrumb" aria-label="Breadcrumb">
        <a href="<?php echo esc_url(home_url('/')); ?>">Home</a>
        <span> / </span>
        <a href="<?php echo esc_url(home_url('/data/')); ?>">Data</a>
        <span> / </span>
        <?php echo esc_html($title); ?>
    </nav>

    <!-- HERO -->
    <section class="sis-hub-hero">
        <div class="sis-hub-hero__inner">
            <div class="sis-hub-hero__text">
                <p class="sis-hub-hero__eyebrow">Spain · Solar PV · Monthly Index</p>
                <h1 class="sis-hub-hero__title"><?php echo esc_html($title); ?></h1>
                <p class="sis-hub-hero__subtitle"><?php echo esc_html($subtitle); ?></p>
                <p class="sis-hub-hero__desc"><?php echo esc_html($description); ?></p>
                <a href="<?php echo esc_url($master_url); ?>" class="sis-btn-download sis-btn-download--master">
                    &#8595; Download Master CSV
                </a>
            </div>

            <?php if ($latest): ?>
            <div class="sis-hub-hero__stats">
                <div class="sis-hub-stat">
                    <div class="sis-hub-stat__label">Latest Data</div>
                    <div class="sis-hub-stat__value"><?php echo esc_html($latest_value); ?></div>
                    <div class="sis-hub-stat__sub"><?php echo esc_html($latest_period); ?></div>
                </div>
                <?php if ($latest_mom !== null): ?>
                <div class="sis-hub-stat">
                    <div class="sis-hub-stat__label"><?php echo $is_generation ? 'MoM Change' : 'Monthly Addition'; ?></div>
                    <div class="sis-hub-stat__value sis-hub-stat__value--<?php echo (str_starts_with($latest_mom, '+') ? 'up' : 'down'); ?>">
                        <?php echo esc_html($latest_mom); ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($latest_yoy !== null): ?>
                <div class="sis-hub-stat">
                    <div class="sis-hub-stat__label">YoY Change</div>
                    <div class="sis-hub-stat__value sis-hub-stat__value--<?php echo (str_starts_with($latest_yoy, '+') ? 'up' : 'down'); ?>">
                        <?php echo esc_html($latest_yoy); ?>
                    </div>
                </div>
                <?php endif; ?>
                <div class="sis-hub-stat">
                    <div class="sis-hub-stat__label">Coverage</div>
                    <div class="sis-hub-stat__value sis-hub-stat__value--neutral"><?php echo esc_html($total_months); ?></div>
                    <div class="sis-hub-stat__sub"><?php echo esc_html($oldest_period); ?> – <?php echo esc_html($latest_period); ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- FULL HISTORICAL TABLE -->
    <section class="sis-table-section">
        <div class="sis-section-header">
            <h2>Full Historical Series</h2>
            <?php if ($total_months): ?>
            <span class="sis-section-badge"><?php echo esc_html($total_months); ?> months</span>
            <?php endif; ?>
        </div>

        <?php if ($rows): ?>
        <div class="sis-table-scroll">
        <?php if ($is_generation): ?>
        <table class="sis-key-table sis-hub-table">
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Generation (GWh)</th>
                    <th>MoM %</th>
                    <th>YoY %</th>
                    <th>Rolling 12m (GWh)</th>
                    <th>Capacity Factor %</th>
                    <th>Momentum</th>
                    <th>Source</th>
                    <th>CSV</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r):
                $mom = $r->mom_pct !== null ? (float)$r->mom_pct : null;
                $yoy = $r->yoy_pct !== null ? (float)$r->yoy_pct : null;
            ?>
                <tr>
                    <td class="sis-hub-period"><?php echo esc_html(date('M Y', mktime(0,0,0,(int)$r->period_month,1,(int)$r->period_year))); ?></td>
                    <td><strong><?php echo esc_html(number_format((float)$r->generation_gwh, 1)); ?></strong></td>
                    <td class="<?php echo $mom !== null ? ($mom >= 0 ? 'sis-cell--up' : 'sis-cell--down') : ''; ?>">
                        <?php echo $mom !== null ? esc_html(($mom >= 0 ? '+' : '') . $mom . '%') : '—'; ?>
                    </td>
                    <td class="<?php echo $yoy !== null ? ($yoy >= 0 ? 'sis-cell--up' : 'sis-cell--down') : ''; ?>">
                        <?php echo $yoy !== null ? esc_html(($yoy >= 0 ? '+' : '') . $yoy . '%') : '—'; ?>
                    </td>
                    <td><?php echo $r->rolling_12m_gwh !== null ? esc_html(number_format((float)$r->rolling_12m_gwh, 0)) : '—'; ?></td>
                    <td><?php echo $r->capacity_factor_pct !== null ? esc_html($r->capacity_factor_pct . '%') : '—'; ?></td>
                    <td><?php echo $r->momentum_score !== null ? esc_html((string)$r->momentum_score) : '—'; ?></td>
                    <td>
                        <span class="sis-source-pill"><?php echo esc_html($r->data_source); ?></span>
                        <?php echo $r->is_revised ? '<span class="sis-revised-tag">R</span>' : ''; ?>
                    </td>
                    <td>
                        <a class="sis-csv-link" href="<?php echo esc_url(SIS_CSV_Exporter::get_generation_slice_url((int)$r->period_year, (int)$r->period_month)); ?>">&#8595; CSV</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <table class="sis-key-table sis-hub-table">
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Capacity (GW)</th>
                    <th>Monthly Addition</th>
                    <th>Rolling 12m Added</th>
                    <th>Build Pace (GW/yr)</th>
                    <th>Source</th>
                    <th>CSV</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r):
                $add = $r->monthly_addition_gw !== null ? (float)$r->monthly_addition_gw : null;
            ?>
                <tr>
                    <td class="sis-hub-period"><?php echo esc_html(date('M Y', mktime(0,0,0,(int)$r->period_month,1,(int)$r->period_year))); ?></td>
                    <td><strong><?php echo esc_html(number_format((float)$r->capacity_gw, 3)); ?></strong></td>
                    <td class="<?php echo $add !== null ? ($add >= 0 ? 'sis-cell--up' : 'sis-cell--down') : ''; ?>">
                        <?php echo $add !== null ? esc_html(($add >= 0 ? '+' : '') . $add . ' GW') : '—'; ?>
                    </td>
                    <td><?php echo $r->rolling_12m_added_gw !== null ? esc_html(number_format((float)$r->rolling_12m_added_gw, 2) . ' GW') : '—'; ?></td>
                    <td><?php echo $r->build_pace_gw_yr !== null ? esc_html(number_format((float)$r->build_pace_gw_yr, 1) . ' GW/yr') : '—'; ?></td>
                    <td>
                        <span class="sis-source-pill"><?php echo esc_html($r->data_source); ?></span>
                        <?php echo $r->is_revised ? '<span class="sis-revised-tag">R</span>' : ''; ?>
                    </td>
                    <td>
                        <a class="sis-csv-link" href="<?php echo esc_url(SIS_CSV_Exporter::get_capacity_slice_url((int)$r->period_year, (int)$r->period_month)); ?>">&#8595; CSV</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        </div>
        <?php else: ?>
            <div class="sis-empty-state">
                <p>No data available yet. Run the monthly fetch or backfill from the admin panel.</p>
            </div>
        <?php endif; ?>
    </section>

    <!-- BULLETIN LINKS -->
    <?php
    $bulletin_type = $is_generation ? 'solar_gen_index' : 'solar_cap_index';
    $bulletins = get_posts([
        'post_type'      => $bulletin_type,
        'post_status'    => 'publish',
        'posts_per_page' => 36,
        'orderby'        => 'meta_value_num',
        'meta_key'       => 'sis_period_year',
        'order'          => 'DESC',
    ]);
    if ($bulletins):
    ?>
    <section class="sis-hub-bulletins">
        <div class="sis-section-header">
            <h2>Monthly Bulletin Pages</h2>
            <span class="sis-section-badge"><?php echo count($bulletins); ?> published</span>
        </div>
        <div class="sis-hub-bulletin-grid">
            <?php foreach ($bulletins as $b):
                $b_year  = (int) get_post_meta($b->ID, 'sis_period_year',  true);
                $b_month = (int) get_post_meta($b->ID, 'sis_period_month', true);
                $b_label = ($b_year && $b_month) ? date('M Y', mktime(0,0,0,$b_month,1,$b_year)) : $b->post_title;
                $b_short = ($b_year && $b_month) ? date('M', mktime(0,0,0,$b_month,1,$b_year)) : '';
            ?>
            <a href="<?php echo esc_url(get_permalink($b->ID)); ?>" class="sis-bulletin-card">
                <span class="sis-bulletin-card__month"><?php echo esc_html($b_short); ?></span>
                <span class="sis-bulletin-card__year"><?php echo esc_html($b_year ?: ''); ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- METHODOLOGY -->
    <section class="sis-methodology">
        <h2>Methodology</h2>
        <?php if ($is_generation): ?>
        <ul>
            <li><strong>Source:</strong> REE — REData API (<code>estructura-generacion</code>), Solar fotovoltaica (id: 1458), national (peninsular + Balearic + Canary Islands).</li>
            <li><strong>Unit:</strong> GWh. Raw API value is MWh ÷ 1,000.</li>
            <li><strong>Coverage:</strong> January 2021 – present (monthly).</li>
            <li><strong>Derived metrics:</strong> MoM %, YoY %, rolling 12-month total, capacity factor, momentum score — all computed from stored series.</li>
            <li><span class="sis-revised-tag">R</span> in Source column = restated value.</li>
        </ul>
        <?php else: ?>
        <ul>
            <li><strong>Source:</strong> REE — REData API (<code>potencia-instalada</code>), Solar fotovoltaica (id: 1486), national.</li>
            <li><strong>Unit:</strong> GW. Raw API value is MW ÷ 1,000.</li>
            <li><strong>Snapshot:</strong> End-of-month installed capacity as reported by REE.</li>
            <li><strong>Coverage:</strong> January 2021 – present (monthly).</li>
            <li><strong>Derived metrics:</strong> Monthly addition, rolling 12-month additions, build pace — computed from stored series.</li>
        </ul>
        <?php endif; ?>
    </section>

</article>
</main>

<?php get_footer(); ?>
