<?php
if (!defined('ABSPATH')) { exit; }

// Bridge: keep legacy flipbox shortcodes and helpers until refactored.
$flipboxes_legacy = TMW_CHILD_PATH . '/inc/tmw-video-hooks.php';
if (is_readable($flipboxes_legacy)) {
    require_once $flipboxes_legacy;
}

if (!function_exists('tmw_get_flipbox_link_guard')) {
    /**
     * Returns a flipbox link guard closure used by archive + template views.
     *
     * @param array $args {
     *   Optional arguments.
     *
     *   @type bool $disable_mobile When true, mobile requests disable the link entirely.
     * }
     *
     * @return callable
     */
    function tmw_get_flipbox_link_guard(array $args = []): callable {
        $disable_mobile = !empty($args['disable_mobile']);

        return static function ($link, $term) use ($disable_mobile) {
            if ($disable_mobile && function_exists('wp_is_mobile') && wp_is_mobile()) {
                return false;
            }

            $home_url = trailingslashit(home_url('/'));
            $current  = is_string($link) ? trailingslashit($link) : '';

            if ($current && $current !== $home_url) {
                return $link;
            }

            if (function_exists('tmw_get_model_post_for_term')) {
                $post = tmw_get_model_post_for_term($term);
                if ($post instanceof WP_Post) {
                    $post_link = get_permalink($post);
                    if ($post_link) {
                        return $post_link;
                    }
                }
            }

            if (!$current || $current === $home_url) {
                $term_obj = $term;
                if (is_numeric($term)) {
                    $term_obj = get_term((int) $term, 'models');
                }

                if ($term_obj && !is_wp_error($term_obj)) {
                    $term_link = get_term_link($term_obj);
                    if (!is_wp_error($term_link) && $term_link) {
                        return $term_link;
                    }
                }
            }

            return $link;
        };
    }
}
