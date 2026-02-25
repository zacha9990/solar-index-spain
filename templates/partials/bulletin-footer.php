<?php
/**
 * Reusable bulletin footer partial.
 * Requires: $year, $month, $bulletin_type ('generation'|'capacity') in calling scope.
 */
defined('ABSPATH') || exit;

$period      = sprintf('%04d-%02d', $year, $month);
$update_log  = SIS_ACF_Fields::get(get_the_ID(), 'sis_update_log');
$data_through = SIS_ACF_Fields::get(get_the_ID(), 'sis_data_through');
$pub_date     = SIS_ACF_Fields::get(get_the_ID(), 'sis_published_date');

$type = $bulletin_type ?? 'generation';
$master_url = $type === 'generation'
    ? SIS_CSV_Exporter::get_generation_master_url()
    : SIS_CSV_Exporter::get_capacity_master_url();
$slice_url = $type === 'generation'
    ? SIS_CSV_Exporter::get_generation_slice_url($year, $month)
    : SIS_CSV_Exporter::get_capacity_slice_url($year, $month);
?>

<!-- DOWNLOADS -->
<section class="sis-downloads">
    <h2>Downloads</h2>
    <ul class="sis-download-list">
        <li>
            <a href="<?php echo esc_url($slice_url); ?>" class="sis-btn-download">
                &#8595; <?php echo esc_html(ucfirst($type)); ?> data — <?php echo esc_html($period); ?> (CSV)
            </a>
        </li>
        <li>
            <a href="<?php echo esc_url($master_url); ?>" class="sis-btn-download sis-btn-download--master">
                &#8595; Master series Jan 2021 → present (CSV)
            </a>
        </li>
    </ul>
    <p class="sis-data-hub-link">
        Full historical dataset &amp; methodology:
        <?php
        $hub_slug = $type === 'generation' ? 'solar-generation-spain' : 'solar-capacity-spain';
        $hub_page = get_page_by_path("data/{$hub_slug}");
        if ($hub_page) {
            echo '<a href="' . esc_url(get_permalink($hub_page)) . '">/data/' . esc_html($hub_slug) . '/</a>';
        } else {
            echo '<code>/data/' . esc_html($hub_slug) . '/</code>';
        }
        ?>
    </p>
</section>

<!-- METHODOLOGY -->
<section class="sis-methodology">
    <h2>Methodology &amp; Sources</h2>
    <?php if ($type === 'generation'): ?>
    <ul>
        <li><strong>Primary source:</strong> REE (Red Eléctrica de España) — REData API, widget <code>estructura-generacion</code>, Solar fotovoltaica (national, peninsular + islands).</li>
        <li><strong>Unit:</strong> GWh (gigawatt-hours). REData returns MWh; converted ÷ 1,000.</li>
        <li><strong>MoM %:</strong> (current month − previous month) ÷ previous month × 100.</li>
        <li><strong>YoY %:</strong> (current month − same month prior year) ÷ same month prior year × 100.</li>
        <li><strong>Rolling 12-month total:</strong> sum of the most recent 12 months including current.</li>
        <li><strong>Implied Capacity Factor:</strong> Generation GWh ÷ (Installed Capacity GW × hours in month) × 100. Typical range for Spain: 12–22%.</li>
        <li><strong>Momentum Score:</strong> Simple 0–100 composite of YoY and MoM changes (50 = in line with trend).</li>
    </ul>
    <?php else: ?>
    <ul>
        <li><strong>Primary source:</strong> REE — REData API, widget <code>potencia-instalada</code>, Solar fotovoltaica (national).</li>
        <li><strong>Unit:</strong> GW (gigawatts). REData returns MW; converted ÷ 1,000.</li>
        <li><strong>Snapshot:</strong> End-of-month installed capacity as reported by REE.</li>
        <li><strong>Monthly addition:</strong> Current month capacity − previous month capacity.</li>
        <li><strong>Rolling 12-month additions:</strong> Sum of monthly additions over the last 12 months.</li>
        <li><strong>Build pace (GW/yr):</strong> Annualised — equal to the rolling 12-month additions total.</li>
    </ul>
    <?php endif; ?>
    <p><em>Data retrieved automatically via REData public API. No authentication required. Values validated against historical ranges before publication.</em></p>
</section>

<!-- CITATION -->
<section class="sis-citation">
    <h2>Suggested Citation</h2>
    <?php
    $month_name = date('F', mktime(0, 0, 0, $month, 1, $year));
    $cite_type  = $type === 'generation' ? 'Solar Generation Monthly Index' : 'Installed Solar Capacity Monthly Index';
    $cite_url   = get_permalink();
    ?>
    <blockquote class="sis-cite-block">
        SolarIndexSpain. "Spain <?php echo esc_html($cite_type); ?> — <?php echo esc_html("{$month_name} {$year}"); ?>."
        SolarIndexSpain.com, <?php echo esc_html(date('j F Y')); ?>.
        <?php echo esc_url($cite_url); ?>
    </blockquote>
</section>

<!-- UPDATE LOG -->
<?php if ($update_log): ?>
<section class="sis-update-log">
    <h2>Update Log</h2>
    <pre class="sis-log-text"><?php echo esc_html($update_log); ?></pre>
</section>
<?php endif; ?>

<!-- META -->
<footer class="sis-bulletin-meta">
    <?php if ($data_through): ?>
        <p>Data through: <strong><?php echo esc_html($data_through); ?></strong></p>
    <?php endif; ?>
    <?php if ($pub_date): ?>
        <p>Published: <?php echo esc_html($pub_date); ?></p>
    <?php endif; ?>
    <p>Source: <a href="https://www.ree.es" target="_blank" rel="noopener">REE — Red Eléctrica de España</a></p>
</footer>
