<?php
defined('ABSPATH') || exit;

class SIS_Post_Types {

    public function __construct() {
        add_action('init', [$this, 'register']);
    }

    public function register(): void {
        // Generation bulletin
        register_post_type('solar_gen_index', [
            'labels' => [
                'name'          => 'Generation Bulletins',
                'singular_name' => 'Generation Bulletin',
                'add_new_item'  => 'Add Generation Bulletin',
                'edit_item'     => 'Edit Generation Bulletin',
            ],
            'public'        => true,
            'has_archive'   => false,
            'supports'      => ['title', 'custom-fields'],
            'rewrite'       => ['slug' => 'solar-generation'],
            'show_in_rest'  => false,
            'menu_icon'     => 'dashicons-chart-line',
            'menu_position' => 31,
        ]);

        // Capacity bulletin
        register_post_type('solar_cap_index', [
            'labels' => [
                'name'          => 'Capacity Bulletins',
                'singular_name' => 'Capacity Bulletin',
                'add_new_item'  => 'Add Capacity Bulletin',
                'edit_item'     => 'Edit Capacity Bulletin',
            ],
            'public'        => true,
            'has_archive'   => false,
            'supports'      => ['title', 'custom-fields'],
            'rewrite'       => ['slug' => 'solar-capacity'],
            'show_in_rest'  => false,
            'menu_icon'     => 'dashicons-chart-bar',
            'menu_position' => 32,
        ]);
    }
}
