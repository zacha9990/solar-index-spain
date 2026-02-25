<?php
/**
 * Plugin Name: Solar Index Spain
 * Description: Data pipeline & bulletin pages for SolarIndexSpain.com
 * Version: 1.0.0
 * Author: SolarIndexSpain
 * Text Domain: solar-index-spain
 */

defined('ABSPATH') || exit;

define('SIS_VERSION',    '1.0.0');
define('SIS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SIS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoloader — covers includes/ and fetchers/
spl_autoload_register(function (string $class): void {
    $slug = strtolower(str_replace(['SIS_', '_'], ['', '-'], $class));
    $candidates = [
        SIS_PLUGIN_DIR . 'includes/class-' . $slug . '.php',
        SIS_PLUGIN_DIR . 'fetchers/class-' . $slug . '.php',
    ];
    foreach ($candidates as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Install / upgrade DB tables on activation
register_activation_hook(__FILE__, ['SIS_Database', 'install']);

// Boot
add_action('plugins_loaded', function (): void {
    new SIS_Post_Types();
    new SIS_ACF_Fields();
    new SIS_Admin_UI();
    new SIS_Cron();

    // Template routing for custom post types
    add_filter('single_template', function (string $template): string {
        if (is_singular('solar_gen_index')) {
            return SIS_PLUGIN_DIR . 'templates/generation-bulletin.php';
        }
        if (is_singular('solar_cap_index')) {
            return SIS_PLUGIN_DIR . 'templates/capacity-bulletin.php';
        }
        return $template;
    });

    // Data hub pages via custom page templates (page slug matching)
    add_filter('page_template', function (string $template): string {
        global $post;
        if ($post && $post->post_name === 'solar-generation-spain') {
            $t = SIS_PLUGIN_DIR . 'templates/data-hub.php';
            if (file_exists($t)) return $t;
        }
        if ($post && $post->post_name === 'solar-capacity-spain') {
            $t = SIS_PLUGIN_DIR . 'templates/data-hub.php';
            if (file_exists($t)) return $t;
        }
        return $template;
    });
});

// Enqueue front-end assets only on bulletin / hub pages
add_action('wp_enqueue_scripts', function (): void {
    if (!is_singular(['solar_gen_index', 'solar_cap_index'])) {
        return;
    }
    wp_enqueue_script(
        'chartjs',
        'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js',
        [],
        '4',
        true
    );
    wp_enqueue_script(
        'sis-charts',
        SIS_PLUGIN_URL . 'assets/js/charts.js',
        ['chartjs'],
        SIS_VERSION,
        true
    );
    wp_enqueue_style(
        'sis-bulletin',
        SIS_PLUGIN_URL . 'assets/css/bulletin.css',
        [],
        SIS_VERSION
    );
});
