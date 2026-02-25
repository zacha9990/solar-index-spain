<?php
defined('ABSPATH') || exit;

/**
 * ACF field registration — uses plain post meta so ACF plugin is NOT required.
 * Fields are registered via ACF if available, otherwise the plugin
 * reads/writes directly with get_post_meta / update_post_meta.
 */
class SIS_ACF_Fields {

    // Meta keys used throughout the plugin
    const KEYS = [
        'sis_period_year',    // int  — e.g. 2026
        'sis_period_month',   // int  — 1-12
        'sis_data_through',   // date — e.g. 2026-01-31
        'sis_published_date', // date — bulletin publish date
        'sis_update_log',     // text — public revision log
        'sis_revision_note',  // text — private admin note
        'sis_bulletin_type',  // string — 'generation' | 'capacity'
    ];

    public function __construct() {
        // Register ACF field group only if ACF plugin is active
        if (function_exists('acf_add_local_field_group')) {
            add_action('acf/init', [$this, 'register_acf_groups']);
        }
        // Always expose meta keys via REST if needed (currently disabled)
        add_action('init', [$this, 'register_meta']);
    }

    public function register_meta(): void {
        $post_types = ['solar_gen_index', 'solar_cap_index'];
        foreach ($post_types as $pt) {
            register_post_meta($pt, 'sis_period_year',    ['type' => 'integer', 'single' => true, 'show_in_rest' => false]);
            register_post_meta($pt, 'sis_period_month',   ['type' => 'integer', 'single' => true, 'show_in_rest' => false]);
            register_post_meta($pt, 'sis_data_through',   ['type' => 'string',  'single' => true, 'show_in_rest' => false]);
            register_post_meta($pt, 'sis_published_date', ['type' => 'string',  'single' => true, 'show_in_rest' => false]);
            register_post_meta($pt, 'sis_update_log',     ['type' => 'string',  'single' => true, 'show_in_rest' => false]);
            register_post_meta($pt, 'sis_revision_note',  ['type' => 'string',  'single' => true, 'show_in_rest' => false]);
            register_post_meta($pt, 'sis_bulletin_type',  ['type' => 'string',  'single' => true, 'show_in_rest' => false]);
        }
    }

    public function register_acf_groups(): void {
        $common_fields = [
            [
                'key'   => 'field_sis_period_year',
                'label' => 'Period Year',
                'name'  => 'sis_period_year',
                'type'  => 'number',
                'min'   => 2021,
                'max'   => 2040,
            ],
            [
                'key'   => 'field_sis_period_month',
                'label' => 'Period Month',
                'name'  => 'sis_period_month',
                'type'  => 'number',
                'min'   => 1,
                'max'   => 12,
            ],
            [
                'key'   => 'field_sis_data_through',
                'label' => 'Data Through',
                'name'  => 'sis_data_through',
                'type'  => 'date_picker',
                'return_format' => 'Y-m-d',
            ],
            [
                'key'   => 'field_sis_published_date',
                'label' => 'Published Date',
                'name'  => 'sis_published_date',
                'type'  => 'date_picker',
                'return_format' => 'Y-m-d',
            ],
            [
                'key'   => 'field_sis_update_log',
                'label' => 'Update Log (public)',
                'name'  => 'sis_update_log',
                'type'  => 'textarea',
            ],
            [
                'key'   => 'field_sis_revision_note',
                'label' => 'Revision Note (admin only)',
                'name'  => 'sis_revision_note',
                'type'  => 'textarea',
            ],
        ];

        acf_add_local_field_group([
            'key'      => 'group_sis_bulletin',
            'title'    => 'Solar Index Spain — Bulletin Fields',
            'fields'   => $common_fields,
            'location' => [
                [['param' => 'post_type', 'operator' => '==', 'value' => 'solar_gen_index']],
                [['param' => 'post_type', 'operator' => '==', 'value' => 'solar_cap_index']],
            ],
            'menu_order' => 0,
            'position'   => 'normal',
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public static function get(int $post_id, string $key): mixed {
        if (function_exists('get_field')) {
            return get_field($key, $post_id);
        }
        return get_post_meta($post_id, $key, true);
    }

    public static function set(int $post_id, string $key, mixed $value): void {
        if (function_exists('update_field')) {
            update_field($key, $value, $post_id);
        } else {
            update_post_meta($post_id, $key, $value);
        }
    }
}
