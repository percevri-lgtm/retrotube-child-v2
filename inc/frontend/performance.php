<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Front-end performance trims scoped primarily to the homepage.
 */

/**
 * Remove jQuery migrate unless explicitly required.
 */
add_action('wp_default_scripts', function ($scripts) {
    if (!($scripts instanceof WP_Scripts)) {
        return;
    }

    if (!empty($scripts->registered['jquery'])) {
        $scripts->registered['jquery']->deps = array_diff(
            (array) $scripts->registered['jquery']->deps,
            ['jquery-migrate']
        );
    }
});

function tmw_child_is_heavy_media_view(): bool {
    return is_front_page()
        || is_singular('model')
        || is_post_type_archive('model')
        || is_tax('models')
        || is_page_template('page-models-grid.php')
        || is_page_template('template-models-flipboxes.php');
}

/**
 * Dequeue non-critical styles on heavy media views.
 */
add_action('wp_enqueue_scripts', function () {
    if (!tmw_child_is_heavy_media_view()) {
        return;
    }

    if (!is_user_logged_in()) {
        if (wp_style_is('dashicons', 'enqueued')) {
            wp_dequeue_style('dashicons');
            wp_deregister_style('dashicons');
        }
    }

    if (is_front_page()) {
        $tml_handles = ['theme-my-login', 'tml', 'theme-my-login-widget'];
        foreach ($tml_handles as $handle) {
            if (wp_style_is($handle, 'enqueued')) {
                wp_dequeue_style($handle);
            }
        }
    }

    $fancybox_handles = ['jquery-fancybox', 'fancybox', 'fancybox-css'];
    foreach ($fancybox_handles as $handle) {
        if (wp_style_is($handle, 'enqueued')) {
            wp_dequeue_style($handle);
        }
    }
}, 99);

/**
 * Delay non-critical styles on heavy media views without changing final appearance.
 */
add_filter('style_loader_tag', function ($html, $handle, $href, $media) {
    if (is_admin() || !tmw_child_is_heavy_media_view()) {
        return $html;
    }

    $host = parse_url($href, PHP_URL_HOST);

    $critical_handles = [
        'retrotube-parent',
        'retrotube-child-style',
        'rt-child-flip',
    ];

    if (in_array($handle, $critical_handles, true)) {
        return $html;
    }

    $delay_handles = [
        'jquery-fancybox',
        'fancybox',
        'fancybox-css',
        'font-awesome',
        'fontawesome',
        'fontawesome-all',
        'videojs',
        'video-js',
        'videojs-quality',
        'videojs-quality-selector',
    ];

    $delay_prefixes = [
        'autoptimize_',
        'autoptimize-',
        'ao-',
    ];

    $delay_hosts = [
        'vjs.zencdn.net',
        'unpkg.com',
    ];

    $should_delay = in_array($handle, $delay_handles, true);

    if (!$should_delay) {
        foreach ($delay_prefixes as $prefix) {
            if (strpos($handle, $prefix) === 0) {
                $should_delay = true;
                break;
            }
        }
    }

    if (!$should_delay) {
        if ($host && in_array($host, $delay_hosts, true)) {
            $should_delay = true;
        }
    }

    if (!$should_delay) {
        return $html;
    }

    $media_attr = $media ?: 'all';
    $escaped_href = esc_url($href);
    $escaped_id = esc_attr($handle) . '-css';

    return '<link rel="preload" as="style" href="' . $escaped_href . '" />'
        . '<link rel="stylesheet" id="' . $escaped_id . '" href="' . $escaped_href . '" media="print" onload="this.media=\'all\'">'
        . '<noscript><link rel="stylesheet" id="' . $escaped_id . '" href="' . $escaped_href . '" media="' . esc_attr($media_attr) . '"></noscript>';
}, 20, 4);

add_action('wp_footer', function () {
    if (!is_singular('model')) {
        return;
    }
    ?>
    <script>
    (function () {
        function stabilizeSlotImages() {
            var imgs = document.querySelectorAll('.tmw-slot-machine img');
            imgs.forEach(function (img) {
                if (!img.hasAttribute('loading')) {
                    img.setAttribute('loading', 'lazy');
                }
                if (!img.hasAttribute('decoding')) {
                    img.setAttribute('decoding', 'async');
                }
                if (!img.hasAttribute('width') && img.naturalWidth) {
                    img.setAttribute('width', img.naturalWidth);
                }
                if (!img.hasAttribute('height') && img.naturalHeight) {
                    img.setAttribute('height', img.naturalHeight);
                }
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', stabilizeSlotImages, { once: true });
        } else {
            stabilizeSlotImages();
        }
    })();
    </script>
    <?php
}, 60);

/**
 * Add defer to non-critical heavy-view scripts and delay third-party tags until interaction.
 */
add_filter('script_loader_tag', function ($tag, $handle, $src) {
    if (is_admin()) {
        return $tag;
    }

    if (!(is_front_page() || is_singular('model'))) {
        return $tag;
    }

    $defer_handles = [
        'jquery-bxslider',
        'bxslider',
        'jquery-fancybox',
        'fancybox',
        'jquery-touchSwipe',
        'jquery-touchswipe',
        'cookie-consent',
        'tmw-tml-links',
        'retrotube-main',
        'tmw-main-js',
        'videojs',
        'video-js',
        'videojs-quality',
        'videojs-quality-selector',
    ];

    if (in_array($handle, $defer_handles, true)) {
        return '<script src="' . esc_url($src) . '" defer></script>';
    }

    $host = parse_url($src, PHP_URL_HOST);
    $delay_hosts = [
        'www.googletagmanager.com',
        'pagead2.googlesyndication.com',
        'pagead2.g.doubleclick.net',
        'analytics.google.com',
        'static.cloudflareinsights.com',
        'cdn.gtranslate.net',
        'connect.facebook.net',
        'vk.com',
        'unpkg.com',
        'vjs.zencdn.net',
    ];

    if ($host && in_array($host, $delay_hosts, true)) {
        return '<script type="text/plain" data-tmw-delay="true" data-tmw-defer="true" data-src="' . esc_url($src) . '"></script>';
    }

    $is_videojs = stripos($handle, 'videojs') !== false || stripos($handle, 'video-js') !== false || $host === 'vjs.zencdn.net';

    if ($is_videojs) {
        return '<script src="' . esc_url($src) . '" defer></script>';
    }

    return $tag;
}, 10, 3);

add_action('wp_footer', function () {
    if (!(is_front_page() || is_singular('model'))) {
        return;
    }
    ?>
    <script>
    (function () {
        var loaded = false;
        function loadDelayedScripts() {
            if (loaded) { return; }
            loaded = true;
            var delayed = document.querySelectorAll('script[data-tmw-delay]');
            delayed.forEach(function (node) {
                var src = node.getAttribute('data-src');
                if (!src) { return; }
                var s = document.createElement('script');
                s.src = src;
                s.async = true;
                if (node.getAttribute('data-tmw-defer') === 'true') {
                    s.defer = true;
                }
                node.parentNode.insertBefore(s, node.nextSibling);
            });
        }

        ['scroll', 'pointerdown', 'click', 'touchstart', 'keydown'].forEach(function (eventName) {
            window.addEventListener(eventName, loadDelayedScripts, { once: true, passive: true });
        });
    })();
    </script>
    <?php
});

/**
 * Utility: fetch image dimensions from attachment metadata or headers.
 */
function tmw_child_image_dimensions(string $url, int $fallback_width = 364, int $fallback_height = 546): array {
    $width  = null;
    $height = null;

    if ($url !== '') {
        $attachment_id = attachment_url_to_postid($url);
        if ($attachment_id) {
            $meta = wp_get_attachment_metadata($attachment_id);
            if (is_array($meta)) {
                $width  = isset($meta['width']) ? (int) $meta['width'] : $width;
                $height = isset($meta['height']) ? (int) $meta['height'] : $height;
            }

            if (!$width || !$height) {
                $full = wp_get_attachment_image_src($attachment_id, 'full');
                if (is_array($full)) {
                    $width  = isset($full[1]) ? (int) $full[1] : $width;
                    $height = isset($full[2]) ? (int) $full[2] : $height;
                }
            }
        }

        if (!$width || !$height) {
            $info = @getimagesize($url);
            if (is_array($info) && isset($info[0], $info[1])) {
                $width  = (int) $info[0];
                $height = (int) $info[1];
            }
        }
    }

    return [
        'width'  => $width ?: $fallback_width,
        'height' => $height ?: $fallback_height,
    ];
}

/**
 * Resolve the first front-page model image for preload/fetchpriority.
 */
function tmw_child_front_page_lcp_image(): array {
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $cache = [];

    if (!is_front_page()) {
        return $cache;
    }

    $terms = get_terms([
        'taxonomy'   => 'models',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
        'number'     => 1,
    ]);

    if (is_wp_error($terms) || empty($terms)) {
        return $cache;
    }

    $term = $terms[0];
    $front_url = '';
    $back_url  = '';
    $attachment_id = 0;

    if (function_exists('tmw_aw_card_data')) {
        $card = tmw_aw_card_data($term->term_id);
        if (!empty($card['front'])) {
            $front_url = $card['front'];
        }
        if (!empty($card['back'])) {
            $back_url = $card['back'];
        }
    }

    if (($front_url === '' || $back_url === '') && function_exists('get_field')) {
        $acf_front = get_field('actor_card_front', 'models_' . $term->term_id);
        $acf_back  = get_field('actor_card_back', 'models_' . $term->term_id);
        if ($front_url === '' && is_array($acf_front) && !empty($acf_front['url'])) {
            $front_url = $acf_front['url'];
        }
        if ($back_url === '' && is_array($acf_back) && !empty($acf_back['url'])) {
            $back_url = $acf_back['url'];
        }
    }

    $ov = function_exists('tmw_tools_overrides_for_term') ? tmw_tools_overrides_for_term($term->term_id) : ['front_url' => '', 'back_url' => '', 'css_front' => '', 'css_back' => ''];
    $front_url = ($ov['front_url'] ?: $front_url) ?: (function_exists('tmw_placeholder_image_url') ? tmw_placeholder_image_url() : '');
    $back_url  = ($ov['back_url'] ?: $back_url) ?: $front_url;

    if (function_exists('tmw_same_image') && tmw_same_image($back_url, $front_url) && function_exists('tmw_aw_find_by_candidates')) {
        $cands = [];
        $explicit = get_term_meta($term->term_id, 'tmw_aw_nick', true);
        if (!$explicit) {
            $explicit = get_term_meta($term->term_id, 'tm_lj_nick', true);
        }
        if ($explicit) {
            $cands[] = $explicit;
        }
        $cands[] = $term->slug;
        $cands[] = $term->name;
        $cands[] = str_replace(['-', '_', ' '], '', $term->slug);
        $cands[] = str_replace(['-', '_', ' '], '', $term->name);
        $row = tmw_aw_find_by_candidates(array_unique(array_filter($cands)));
        if ($row && function_exists('tmw_aw_pick_images_from_row')) {
            list($_f, $_b) = tmw_aw_pick_images_from_row($row);
            if ($_b && !tmw_same_image($_b, $front_url)) {
                $back_url = $_b;
            }
        }
    }

    if ($front_url === '') {
        return $cache;
    }

    $dims = tmw_child_image_dimensions($front_url);

    $attachment_id = function_exists('attachment_url_to_postid') ? attachment_url_to_postid($front_url) : 0;
    if ($attachment_id) {
        $optimized = wp_get_attachment_image_src($attachment_id, 'tmw-front-optimized');
        if (is_array($optimized) && !empty($optimized[0])) {
            $front_url = $optimized[0];
            if (!empty($optimized[1])) {
                $dims['width'] = (int) $optimized[1];
            }
            if (!empty($optimized[2])) {
                $dims['height'] = (int) $optimized[2];
            }
        }
    }

    $cache = [
        'url'    => $front_url,
        'alt'    => $term->name,
        'width'  => $dims['width'],
        'height' => $dims['height'],
        'attachment_id' => $attachment_id,
    ];

    return $cache;
}

/**
 * Determines whether the current flipbox should expose the inline <img> for LCP.
 */
function tmw_child_should_use_lcp_image(): bool {
    static $done = false;

    if (!is_front_page() || is_paged()) {
        return false;
    }

    if ($done) {
        return false;
    }

    $done = true;
    return true;
}
