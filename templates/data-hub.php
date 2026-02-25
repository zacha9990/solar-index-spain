<?php
/**
 * Template: Data Hub page
 * Used by /data/solar-generation-spain/ and /data/solar-capacity-spain/
 */
defined('ABSPATH') || exit;

global $post;

// Determine hub type from page slug
$slug = $post ? $post->post_name : '';
$is_generation = strpos($slug, 'generation') !== false;
$type          = $is_generation ? 'generation' : 'capacity';

$title         = $is_generation
    ? 'Spain Solar Generation — Historical Data Series'
    : 'Spain Installed Solar Capacity — Historical Data Series';
$description   = $is_generation
    ? 'Monthly solar photovoltaic generation data for Spain (GWh) from January 2021 to present. Source: REE (Red Eléctrica de España).'
    : 'Monthly installed solar photovoltaic capacity data for Spain (GW) from January 2021 to present. Source: REE (Red Eléctrica de España).';

$master_url = $is_generation
    ? SIS_CSV_Exporter::get_generation_master_url()
    : SIS_CSV_Exporter::get_capacity_master_url();

$rows = $is_generation
    ? SIS_Database::get_all_generation()
    : SIS_Database::get_all_capacity();

get_header();
?>

<main class="sis-hub-wrap">
    <article class="sis-data-hub">

        <header class="sis-hub-header">
            <h1><?php echo esc_html($title); ?></h1>
            <p class="sis-hub-description"><?php echo esc_html($description); ?></p>
            <a href="<?php echo esc_url($master_url); ?>" class="sis-btn-download sis-btn-download--master">
                &#8595; Download Master CSV (Jan 2021 → present)
            </a>
        </header>

        <!-- HISTORICAL TABLE -->
        <section class="sis-table-section">
            <h2>Full Historical Series</h2>
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
                        <th>Monthly CSV</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (array_reverse($rows) as $r): ?>
                    <tr>
                        <td><?php echo esc_html(sprintf('%04d-%02d', $r->period_year, $r->period_month)); ?></td>
                        <td><?php echo esc_html(number_format((float)$r->generation_gwh, 1)); ?></td>
                        <td><?php echo $r->mom_pct !== null ? esc_html(((float)$r->mom_pct >= 0 ? '+' : '') . $r->mom_pct . '%') : '—'; ?></td>
                        <td><?php echo $r->yoy_pct !== null ? esc_html(((float)$r->yoy_pct >= 0 ? '+' : '') . $r->yoy_pct . '%') : '—'; ?></td>
                        <td><?php echo $r->rolling_12m_gwh !== null ? esc_html(number_format((float)$r->rolling_12m_gwh, 0)) : '—'; ?></td>
                        <td><?php echo $r->capacity_factor_pct !== null ? esc_html($r->capacity_factor_pct . '%') : '—'; ?></td>
                        <td><?php echo $r->momentum_score !== null ? esc_html((string)$r->momentum_score) : '—'; ?></td>
                        <td><code><?php echo esc_html($r->data_source); ?></code><?php echo $r->is_revised ? ' <em>R</em>' : ''; ?></td>
                        <td>
                            <a href="<?php echo esc_url(SIS_CSV_Exporter::get_generation_slice_url((int)$r->period_year, (int)$r->period_month)); ?>">CSV</a>
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
                        <th>Monthly Addition (GW)</th>
                        <th>Rolling 12m Added (GW)</th>
                        <th>Build Pace (GW/yr)</th>
                        <th>Source</th>
                        <th>Monthly CSV</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (array_reverse($rows) as $r): ?>
                    <tr>
                        <td><?php echo esc_html(sprintf('%04d-%02d', $r->period_year, $r->period_month)); ?></td>
                        <td><?php echo esc_html(number_format((float)$r->capacity_gw, 3)); ?></td>
                        <td><?php echo $r->monthly_addition_gw !== null ? esc_html(((float)$r->monthly_addition_gw >= 0 ? '+' : '') . $r->monthly_addition_gw . ' GW') : '—'; ?></td>
                        <td><?php echo $r->rolling_12m_added_gw !== null ? esc_html(number_format((float)$r->rolling_12m_added_gw, 2) . ' GW') : '—'; ?></td>
                        <td><?php echo $r->build_pace_gw_yr !== null ? esc_html(number_format((float)$r->build_pace_gw_yr, 1) . ' GW/yr') : '—'; ?></td>
                        <td><code><?php echo esc_html($r->data_source); ?></code><?php echo $r->is_revised ? ' <em>R</em>' : ''; ?></td>
                        <td>
                            <a href="<?php echo esc_url(SIS_CSV_Exporter::get_capacity_slice_url((int)$r->period_year, (int)$r->period_month)); ?>">CSV</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            </div>
            <?php else: ?>
                <p>No data available yet. Data will appear here once monthly bulletins are published.</p>
            <?php endif; ?>
        </section>

        <!-- METHODOLOGY -->
        <section class="sis-methodology">
            <h2>Methodology</h2>
            <?php if ($is_generation): ?>
            <ul>
                <li><strong>Source:</strong> REE — REData API (<code>estructura-generacion</code>), Solar fotovoltaica, national (peninsular + Balearic + Canary Islands).</li>
                <li><strong>Unit:</strong> GWh. Raw API value is MWh, divided by 1,000.</li>
                <li><strong>Coverage:</strong> January 2021 – present (monthly).</li>
                <li><strong>Derived metrics:</strong> MoM %, YoY %, rolling 12-month total, implied capacity factor, momentum score — all computed from stored series. No hard-coded values.</li>
                <li><em>R</em> in Source column = restated value (see revision log on individual bulletin pages).</li>
            </ul>
            <?php else: ?>
            <ul>
                <li><strong>Source:</strong> REE — REData API (<code>potencia-instalada</code>), Solar fotovoltaica, national.</li>
                <li><strong>Unit:</strong> GW. Raw API value is MW, divided by 1,000.</li>
                <li><strong>Snapshot:</strong> End-of-month installed capacity as reported by REE.</li>
                <li><strong>Coverage:</strong> January 2021 – present (monthly).</li>
                <li><strong>Derived metrics:</strong> Monthly addition, rolling 12-month additions, build pace — all computed from stored series.</li>
            </ul>
            <?php endif; ?>
        </section>

        <!-- BULLETIN LINKS -->
        <section class="sis-hub-bulletins">
            <h2>Monthly Bulletin Pages</h2>
            <?php
            $bulletin_type = $is_generation ? 'solar_gen_index' : 'solar_cap_index';
            $bulletins = get_posts([
                'post_type'      => $bulletin_type,
                'post_status'    => 'publish',
                'posts_per_page' => 24,
                'orderby'        => 'meta_value_num',
                'meta_key'       => 'sis_period_year',
                'order'          => 'DESC',
            ]);
            if ($bulletins):
            ?>
            <ul class="sis-hub-bulletin-list">
                <?php foreach ($bulletins as $b):
                    $b_year  = (int) get_post_meta($b->ID, 'sis_period_year', true);
                    $b_month = (int) get_post_meta($b->ID, 'sis_period_month', true);
                    $b_label = $b_year && $b_month ? date('F Y', mktime(0,0,0,$b_month,1,$b_year)) : $b->post_title;
                ?>
                <li><a href="<?php echo esc_url(get_permalink($b->ID)); ?>"><?php echo esc_html($b_label); ?></a></li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
                <p>No published bulletins yet.</p>
            <?php endif; ?>
        </section>

    </article>
</main>

<?php get_footer(); ?>
