<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) { exit; }

/* === TMW Theme Prune Kit loader (v3.7.0) === */
add_action('after_setup_theme', function () {
    if (!is_user_logged_in() || !current_user_can('manage_options')) { return; }
    $tool = __DIR__ . '/inc/tools/tmw-prune-kit.php';
    if (file_exists($tool)) { require_once $tool; }
}, 99);

// [TMW-LINK-GUARD] loader (v3.6.2)
// Remove legacy guards if Codex finds them:
/*
DELETE_BLOCK_START TMW-LINK-GUARD v<=3.6.1
DELETE_BLOCK_END
*/
$__codex_link_guard = __DIR__ . '/CODEX_video_link_guard.php';
if (file_exists($__codex_link_guard)) {
    require_once $__codex_link_guard;
}

// [TMW-FILTER-LINKS] loader (v3.6.4)
/*
DELETE_BLOCK_START TMW-FILTER-CANONICAL v<=3.6.3
// require_once __DIR__ . '/inc/tmw-filter-canonical.php';
DELETE_BLOCK_END
*/
if (!defined('ABSPATH')) { exit; }
$__tmw_filter_links = __DIR__ . '/inc/tmw-filter-links.php';
if (file_exists($__tmw_filter_links)) { require_once $__tmw_filter_links; }

/**
 * RetroTube Child (Flipbox Edition) v2 — Bootstrap
 * v4.1.0: move logic into /inc without behavior change.
 */
define('TMW_CHILD_VERSION', '4.1.0');
define('TMW_CHILD_PATH', get_stylesheet_directory());
define('TMW_CHILD_URL',  get_stylesheet_directory_uri());

// Single include: all logic is now in /inc/bootstrap.php
require_once TMW_CHILD_PATH . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/tmw-tml-bridge.php';

// Ensure legacy experiments don't affect the default reset email contents.
remove_all_filters('retrieve_password_message');

// === TMW Reset URL normalizer (email message) ===
require_once __DIR__ . '/inc/tmw-reset-mail-url.php';

// === TMW Register Audit (audit-only) ===
if (!defined('TMW_REG_AUDIT')) {
    define('TMW_REG_AUDIT', defined('TMW_DEBUG') && TMW_DEBUG);
}
if (TMW_REG_AUDIT && defined('TMW_DEBUG') && TMW_DEBUG && file_exists(get_stylesheet_directory() . '/inc/tmw-register-audit.php')) {
    require_once get_stylesheet_directory() . '/inc/tmw-register-audit.php';
}

// TEMP: disable email activation module
// if (file_exists(get_stylesheet_directory() . '/inc/tmw-email-activation.php')) {
//     require_once get_stylesheet_directory() . '/inc/tmw-email-activation.php';
// }

// === TMW Mail Transport (SMTP + logging, debug-gated) ===
if (defined('TMW_DEBUG') && TMW_DEBUG && file_exists(get_stylesheet_directory() . '/inc/tmw-mail-transport.php')) {
    require_once get_stylesheet_directory() . '/inc/tmw-mail-transport.php';
}

// === [Codex] One-shot loader for the Structure Audit (admin-only, safe) ===
add_action('admin_init', function () {
    if (!is_user_logged_in() || !current_user_can('manage_options')) { return; }
    if (!isset($_GET['tmw_audit']) || $_GET['tmw_audit'] !== 'structure') { return; }

    $audit = get_stylesheet_directory() . '/CODEX_THEME_STRUCTURE_AUDIT.php';
    if (file_exists($audit)) {
        require_once $audit;
        if (function_exists('tmw_theme_structure_audit')) {
            tmw_theme_structure_audit(true); // echoes plain text summary
            exit;
        }
    }
});

// Load Codex Reports admin viewer (read-only)
if (is_admin()) {
    $viewer = get_stylesheet_directory() . '/inc/admin/codex-reports-viewer.php';
    if (file_exists($viewer)) { require_once $viewer; }
}

// Load header→H1 gap diagnostic (read-only)
$gap_audit = get_stylesheet_directory() . '/inc/audit-header-gap.php';
if (file_exists($gap_audit)) { require_once $gap_audit; }


// Load FULL audit for header→H1 gap (report only, admin + flag)
$tmw_full = get_stylesheet_directory() . '/inc/audit-header-gap-full.php';
if (file_exists($tmw_full)) { require_once $tmw_full; }


require_once get_stylesheet_directory() . '/inc/tmw-mail-fix.php';

add_action('wp_head', function () {
    if (!is_front_page()) {
        return;
    }

    if (!function_exists('tmw_child_front_page_lcp_image')) {
        return;
    }

    $lcp_image = tmw_child_front_page_lcp_image();
    if (empty($lcp_image['url'])) {
        return;
    }
    ?>
    <link rel="preload" as="image" href="<?php echo esc_url($lcp_image['url']); ?>">
    <?php
});

// Ensure logo images retain explicit dimensions and async decoding without moving markup.
add_filter('get_custom_logo_image_attributes', function ($attrs, $custom_logo_id) {
    if (!$custom_logo_id) {
        return $attrs;
    }

    $meta = wp_get_attachment_metadata($custom_logo_id);
    if (is_array($meta)) {
        if (empty($attrs['width']) && !empty($meta['width'])) {
            $attrs['width'] = (int) $meta['width'];
        }
        if (empty($attrs['height']) && !empty($meta['height'])) {
            $attrs['height'] = (int) $meta['height'];
        }
    }

    if (!isset($attrs['loading'])) {
        $attrs['loading'] = 'lazy';
    }

    if (empty($attrs['decoding'])) {
        $attrs['decoding'] = 'async';
    }

    return $attrs;
}, 10, 2);

// Ensure grayscale logo variants keep explicit dimensions without altering templates.
add_filter('wp_get_attachment_image_attributes', function ($attr, $attachment, $size) {
    $classes = isset($attr['class']) ? (string) $attr['class'] : '';
    if (strpos($classes, 'custom-logo') === false && strpos($classes, 'grayscale') === false) {
        return $attr;
    }

    $attachment_id = is_object($attachment) && isset($attachment->ID) ? (int) $attachment->ID : (int) $attachment;
    if (!$attachment_id) {
        return $attr;
    }

    $meta = wp_get_attachment_metadata($attachment_id);
    if (is_array($meta)) {
        if (empty($attr['width']) && !empty($meta['width'])) {
            $attr['width'] = (int) $meta['width'];
        }

        if (empty($attr['height']) && !empty($meta['height'])) {
            $attr['height'] = (int) $meta['height'];
        }
    }

    if (empty($attr['decoding'])) {
        $attr['decoding'] = 'async';
    }

    return $attr;
}, 10, 3);

// Disable updates for the Retrotube parent theme
add_filter('site_transient_update_themes', function($value) {

    $theme_to_block = 'retrotube'; // parent theme folder name

    if (isset($value->response[$theme_to_block])) {
        unset($value->response[$theme_to_block]);
    }

    return $value;
});

