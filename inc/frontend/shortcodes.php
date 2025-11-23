<?php
if (!defined('ABSPATH')) { exit; }

// General shortcodes will be migrated here in Phase 2.

add_action('init', function () {
    if (!defined('TMW_DEBUG') || !TMW_DEBUG) {
        return;
    }

    if (!shortcode_exists('tmw_slot_machine')) {
        error_log('[TMW-SLOT-AUDIT] shortcode tmw_slot_machine not registered on init');
        return;
    }

    error_log('[TMW-SLOT-AUDIT] shortcode tmw_slot_machine registered on init');

    add_filter('pre_do_shortcode_tag', function ($return, $tag, $attr, $m) {
        if ($tag !== 'tmw_slot_machine') {
            return $return;
        }

        $post_type = get_post_type();
        error_log('[TMW-SLOT-AUDIT] shortcode entry; allowed_on_post_type=' . ($post_type ?: 'null') . '; is_singular=' . (is_singular() ? '1' : '0'));

        return $return;
    }, 10, 4);

    add_filter('do_shortcode_tag', function ($output, $tag, $attr, $m) {
        if ($tag !== 'tmw_slot_machine') {
            return $output;
        }

        $stripped = trim(wp_strip_all_tags((string) $output));
        if ($stripped === '') {
            error_log('[TMW-SLOT-AUDIT] shortcode output empty after callback');
        } else {
            error_log('[TMW-SLOT-AUDIT] shortcode output length=' . strlen($stripped));
        }

        return $output;
    }, 10, 4);
}, 20);
