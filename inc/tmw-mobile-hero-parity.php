<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mobile hero parity — late inline CSS + optional auditor.
 * Activation (query params):
 *   ?tmw-hero-fix=1      → print late mobile-only parity CSS (≤840px)
 *   ?tmw-hero-audit=1    → enqueue auditor JS (logs computed values)
 *   ?tmw-hero-force=1    → auditor will force inline !important for diagnosis
 */
function tmw_mobile_hero_page_is_single_model() {
    // Adjust if your CPT slug differs. This matches single-model.php usage.
    return function_exists('is_singular') && is_singular('model');
}

function tmw_mobile_hero_is_enabled() {
    return isset($_GET['tmw-hero-fix']) || isset($_GET['tmw-hero-audit']) || isset($_GET['tmw-hero-force']);
}

// Late, last-in CSS to beat any previous mobile overrides (desktop untouched)
add_action('wp_head', function () {
    if (!tmw_mobile_hero_page_is_single_model() || !isset($_GET['tmw-hero-fix'])) {
        return;
    }
    ?>
    <style id="tmw-mobile-hero-fix" data-source="tmw" media="all">
    /* v3.7.5 — Late inline parity CSS (≤840px); desktop untouched */
    @media (max-width: 840px) {
      .single-model .tmw-banner-container,
      .single-model .tmw-model-hero,
      .single-model .tmw-banner-frame { overflow: hidden; }

      .single-model .entry-header .tmw-banner-frame > img,
      .single-model .entry-header .tmw-banner-frame picture > img,
      .single-model .tmw-banner-frame > img,
      .single-model .tmw-banner-frame picture > img,
      .single-model .tmw-model-hero > img,
      .single-model .tmw-model-hero picture > img,
      .single-model .entry-header .wp-post-image,
      .single-model .tmw-banner-frame .wp-post-image {
        width: 100%; height: 100%; object-fit: cover;
        object-position: 50% var(--tmw-hero-vpos, 50%) !important;
        transform: translateY(calc(var(--offset-y, 0px) * var(--offset-scale, 1))) !important;
      }
    }
    </style>
    <?php
}, 99999);

// Optional auditor JS (only when asked)
add_action('wp_enqueue_scripts', function () {
    if (!tmw_mobile_hero_page_is_single_model() || !(isset($_GET['tmw-hero-audit']) || isset($_GET['tmw-hero-force']))) {
        return;
    }

    wp_enqueue_script(
        'tmw-mobile-hero-audit',
        get_stylesheet_directory_uri() . '/js/tmw-mobile-hero-audit.js',
        [],
        null,
        true
    );

    // Pass flags to JS
    wp_add_inline_script(
        'tmw-mobile-hero-audit',
        'window.TMW_MOBILE_HERO_FORCE_INLINE = ' . (isset($_GET['tmw-hero-force']) ? 'true' : 'false') . ';',
        'before'
    );
}, 99);

// === Admin editor preview: ensure the image fills the frame height (no black bars) ===
if (!function_exists('tmw_hero_admin_fill_frame_css')) {
    add_action('admin_head', 'tmw_hero_admin_fill_frame_css');
    function tmw_hero_admin_fill_frame_css() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'model') {
            return;
        }
        ?>
        <style id="tmw-hero-admin-fill-frame">
          /* Admin-only: ensure the preview image fills the banner frame. */
          .wp-admin .tmw-banner-frame{ position:relative; overflow:hidden; }
          /* High specificity + !important to beat editor resets like img{height:auto} */
          #poststuff .tmw-banner-frame img,
          #poststuff .tmw-banner-frame picture>img,
          #poststuff .tmw-banner-frame .wp-post-image{
            position:absolute; inset:0;
            width:100% !important;
            height:100% !important;
            display:block;
            object-fit:cover !important;
            /* mirror front-end: translateY uses scaled offset */
            transform: translateY(calc(var(--offset-y, 0px) * var(--offset-scale, 1)));
            object-position: 50% var(--tmw-hero-vpos, 50%);
            will-change: transform;
          }
        </style>
        <?php
    }
}
