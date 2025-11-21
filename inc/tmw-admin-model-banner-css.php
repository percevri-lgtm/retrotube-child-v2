<?php
// Exit if accessed directly
if (!defined('ABSPATH')) { exit; }

/**
 * Admin-only inline CSS so the model banner metabox preview matches the frontend.
 * No JS, no layout/offset changes — just the same height-only rule.
 */
add_action('admin_head', function () {
    if (!function_exists('get_current_screen')) return;
    $s = get_current_screen();
    if (!$s || $s->post_type !== 'model') return;

    echo '<style>
    .wp-admin .tmw-banner-frame {
      position: relative;
      overflow: hidden;
      background-color: #000;
    }
    .wp-admin .tmw-banner-frame > img,
    .wp-admin .tmw-banner-frame picture > img,
    .wp-admin .tmw-banner-frame .wp-post-image {
      width: 100% !important;
      height: 100% !important;
      object-fit: cover !important;
      display: block;
    }
    .wp-admin .tmw-banner-frame.tmw-bg-mode {
      background-size: cover !important;
      background-repeat: no-repeat !important;
      background-position:
        var(--offset-x, 50%)
        calc(var(--tmw-hero-vpos, 50%) + var(--offset-y, 0px)) !important;
    }
    .wp-admin .tmw-banner-frame.tmw-bg-mode > img,
    .wp-admin .tmw-banner-frame.tmw-bg-mode picture > img,
    .wp-admin .tmw-banner-frame.tmw-bg-mode .wp-post-image {
      opacity: 0 !important;
      pointer-events: none;
    }
    </style>';
});
