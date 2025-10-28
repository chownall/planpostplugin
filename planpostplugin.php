<?php
/**
 * Plugin Name: PlanPost Plugin — Posts planifiés
 * Description: Affiche les articles planifiés (ID, Titre, Date, Date GMT) via une page d’admin et un shortcode.
\11.0.1
 * Author: PlanPost
 * Text Domain: planpostplugin
 * Requires at least: 5.2
 * Requires PHP: 7.4
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Ensure functions are declared only once
if (!function_exists('planpostplugin_register_admin_menu')) {
    /**
     * Registers the admin menu entry under Tools.
     */
    function planpostplugin_register_admin_menu(): void
    {
        add_management_page(
            __('Posts planifiés', 'planpostplugin'),
            __('Posts planifiés', 'planpostplugin'),
            'edit_posts',
            'planpostplugin-scheduled-posts',
            'planpostplugin_render_admin_page'
        );
    }
    add_action('admin_menu', 'planpostplugin_register_admin_menu');
}

if (!function_exists('planpostplugin_get_scheduled_posts_table_html')) {
    /**
     * Builds HTML table for scheduled posts similar to:
     * wp post list --post_status=future --fields=ID,post_title,post_date,post_date_gmt
     */
    function planpostplugin_get_scheduled_posts_table_html(): string
    {
        // Query scheduled posts (future)
        $query = new WP_Query([
            'post_type'      => 'post',
            'post_status'    => 'future',
            'orderby'        => 'post_date',
            'order'          => 'ASC',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'fields'         => 'ids',
        ]);

        ob_start();

        // Minimal inline styles for readability (kept inline to avoid enqueueing assets)
        echo '<div class="planpostplugin-table-wrap">';
        echo '<style>.planpostplugin-table{border-collapse:collapse;width:100%}.planpostplugin-table th,.planpostplugin-table td{border:1px solid #ddd;padding:8px;text-align:left}.planpostplugin-table th{background:#f7f7f7;font-weight:600}</style>';

        echo '<table class="planpostplugin-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('ID', 'planpostplugin') . '</th>';
        echo '<th>' . esc_html__('Titre', 'planpostplugin') . '</th>';
        echo '<th>' . esc_html__('Date', 'planpostplugin') . '</th>';
        echo '<th>' . esc_html__('Date GMT', 'planpostplugin') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        if (!empty($query->posts)) {
            foreach ($query->posts as $post_id) {
                $title        = get_the_title($post_id);
                $post_date    = get_post_field('post_date', $post_id);
                $post_date_gmt= get_post_field('post_date_gmt', $post_id);

                echo '<tr>';
                echo '<td>' . esc_html((string) $post_id) . '</td>';
                echo '<td>' . esc_html($title ?: __('(Sans titre)', 'planpostplugin')) . '</td>';
                echo '<td>' . esc_html($post_date ?: '') . '</td>';
                echo '<td>' . esc_html($post_date_gmt ?: '') . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="4">' . esc_html__('Aucun article planifié.', 'planpostplugin') . '</td></tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        return (string) ob_get_clean();
    }
}

if (!function_exists('planpostplugin_render_admin_page')) {
    /**
     * Renders the admin page content.
     */
    function planpostplugin_render_admin_page(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Vous n’avez pas les permissions nécessaires.', 'planpostplugin'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Posts planifiés', 'planpostplugin') . '</h1>';
        echo planpostplugin_get_scheduled_posts_table_html();
        echo '</div>';
    }
}

if (!function_exists('planpostplugin_shortcode')) {
    /**
     * Shortcode: [scheduled_posts_table]
     * Outputs the same table on the frontend.
     */
    function planpostplugin_shortcode(): string
    {
        return planpostplugin_get_scheduled_posts_table_html();
    }
    add_shortcode('scheduled_posts_table', 'planpostplugin_shortcode');
}


