<?php
if (!defined('ABSPATH')) exit;

final class TMW_TML_Bridge {
    const TAG = '[TMW-TML]';

    public static function boot() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue'], 20);
        // Optional: log once so we can verify in debug.log
        add_action('wp', function(){ error_log(self::TAG.' bridge active'); });
    }

    public static function enqueue() {
        $src = get_stylesheet_directory_uri().'/js/tmw-tml-links.js';
        wp_register_script('tmw-tml-links', $src, [], '1.0.0', true);
        wp_enqueue_script('tmw-tml-links');
        error_log(self::TAG.' tmw-tml-links enqueued');
    }
}
TMW_TML_Bridge::boot();
