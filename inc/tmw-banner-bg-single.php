<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Single renderer (background) enqueue — front-end + model editor.
 * No offset/layout math changes; we only paint the frame's background and
 * hide the inner <img> so there's exactly one visual layer.
 */
add_action('wp_enqueue_scripts', function () {
    if (is_singular('model')) {
        wp_enqueue_script(
            'tmw-banner-bg-single',
            get_stylesheet_directory_uri() . '/js/tmw-banner-bg-single.js',
            array(),
            'v4.0.1',
            true
        );
    }
}, 100);

add_action('admin_enqueue_scripts', function () {
    if (!function_exists('get_current_screen')) return;
    $s = get_current_screen();
    if ($s && $s->post_type === 'model') {
        wp_enqueue_script(
            'tmw-banner-bg-single',
            get_stylesheet_directory_uri() . '/js/tmw-banner-bg-single.js',
            array(),
            'v4.0.1',
            true
        );
    }
}, 100);
