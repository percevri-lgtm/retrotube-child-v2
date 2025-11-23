<?php
if (!defined('ABSPATH')) { exit; }

/* ======================================================================
 * STYLE HELPERS
 * ====================================================================== */
if (!function_exists('tmw_child_style_version')) {
  function tmw_child_style_version() {
    static $version = null;
    if ($version !== null) {
      return $version;
    }

    $path = get_stylesheet_directory() . '/style.css';
    if (file_exists($path)) {
      $version = filemtime($path);
    } else {
      $theme   = wp_get_theme(get_stylesheet());
      $version = $theme instanceof WP_Theme ? $theme->get('Version') : null;
    }

    return $version ?: time();
  }
}

/* ======================================================================
 * STYLES + LIGHTWEIGHT OPTIMIZATIONS
 * ====================================================================== */
add_action('wp_enqueue_scripts', function () {
  $parent_version = wp_get_theme(get_template())->get('Version');
  $child_version  = tmw_child_style_version();

  wp_enqueue_style(
    'retrotube-parent',
    get_template_directory_uri() . '/style.css',
    [],
    $parent_version
  );

  wp_enqueue_style(
    'retrotube-child-style',
    get_stylesheet_uri(),
    ['retrotube-parent'],
    $child_version
  );

  $model_videos_path = get_stylesheet_directory() . '/assets/css/style.css';
  if (file_exists($model_videos_path)) {
    $model_videos_ver = filemtime($model_videos_path) ?: $child_version;
    wp_enqueue_style(
      'retrotube-model-videos',
      get_stylesheet_directory_uri() . '/assets/css/style.css',
      ['retrotube-child-style'],
      $model_videos_ver
    );
  }

  $flipboxes_path = get_stylesheet_directory() . '/assets/flipboxes.css';
  $flipboxes_ver  = file_exists($flipboxes_path) ? filemtime($flipboxes_path) : $child_version;

  wp_register_style(
    'rt-child-flip',
    get_stylesheet_directory_uri() . '/assets/flipboxes.css',
    ['retrotube-child-style'],
    $flipboxes_ver
  );

  $a11y_style_path = get_stylesheet_directory() . '/css/a11y.css';
  if (file_exists($a11y_style_path)) {
    $a11y_style_ver = filemtime($a11y_style_path) ?: $child_version;
    wp_register_style(
      'tmw-flip-a11y',
      get_stylesheet_directory_uri() . '/css/a11y.css',
      ['retrotube-child-style'],
      $a11y_style_ver
    );
  }

  $guard_path = get_stylesheet_directory() . '/js/tmw-flip-guard.js';
  $guard_src  = get_stylesheet_directory_uri() . '/js/tmw-flip-guard.js';
  $guard_ver  = file_exists($guard_path) ? filemtime($guard_path) : null;

  wp_register_script('tmw-flip-guard', $guard_src, [], $guard_ver, true);
  wp_script_add_data('tmw-flip-guard', 'defer', true);

  $a11y_script_path = get_stylesheet_directory() . '/js/tmw-flip-a11y.js';
  $a11y_script_src  = get_stylesheet_directory_uri() . '/js/tmw-flip-a11y.js';
  $a11y_script_ver  = file_exists($a11y_script_path) ? filemtime($a11y_script_path) : null;

  if (file_exists($a11y_script_path)) {
    wp_register_script('tmw-flip-a11y', $a11y_script_src, [], $a11y_script_ver, true);
  }

  $is_flip_context = (
    is_tax('models') ||
    is_post_type_archive('models') ||
    is_page_template('page-models-grid.php') ||
    is_page_template('template-models-flipboxes.php')
  );

  if ($is_flip_context) {
    wp_enqueue_style('rt-child-flip');

    if (wp_style_is('tmw-flip-a11y', 'registered')) {
      wp_enqueue_style('tmw-flip-a11y');
    }

    wp_dequeue_script('tmw-flipbox-mobile-fix');

    wp_enqueue_script('tmw-flip-guard');

    if (wp_script_is('tmw-flip-a11y', 'registered')) {
      wp_enqueue_script('tmw-flip-a11y');
    }
  }

  // Trim unused assets
  wp_dequeue_style('wp-block-library');
  wp_dequeue_style('wp-block-library-theme');
  wp_dequeue_style('wc-blocks-style');
  wp_deregister_script('wp-embed');
}, 20);

/**
 * Collect inline CSS declarations and output them once after the primary child stylesheet.
 */
function tmw_enqueue_inline_css(string $css): void {
  static $buffer = [];
  static $hooked = false;

  if (trim($css) === '') {
    return;
  }

  $buffer[] = $css;

  if ($hooked) {
    return;
  }

  add_action('wp_enqueue_scripts', function () use (&$buffer) {
    if (empty($buffer)) {
      return;
    }

    $styles = implode("\n", array_unique(array_map('trim', $buffer)));
    $buffer = [];

    if ($styles !== '') {
      wp_add_inline_style('retrotube-child-style', $styles);
    }
  }, 120);

  $hooked = true;
}

add_action('wp_enqueue_scripts', function () {
  if (is_singular('model')) {
    wp_dequeue_style('retrotube-rating');
    wp_dequeue_script('retrotube-rating');
  }
}, 100);

// Model tag styles now inherit from the global stylesheet to match video tags.

/* === TMW: Extend Video Widget for Model Filtering (Hybrid post/video) === */
add_filter('wpst_widget_videos_block_query_args', function ($args, $instance) {
    if (!isset($instance['video_type']) || 'model' !== $instance['video_type']) {
        return $args;
    }

    $current_model = get_queried_object();
    if (!$current_model || empty($current_model->slug)) {
        return $args;
    }

    // Hybrid query: support LiveJasmin imports stored as "post" + custom "video" CPT.
    $args['post_type'] = ['video', 'post'];
    $args['tax_query'][] = [
        'taxonomy' => 'models',
        'field'    => 'slug',
        'terms'    => $current_model->slug,
    ];

    return $args;
}, 10, 2);

add_filter('post_thumbnail_html', function($html){
  static $done = false;
  if (!$done && (is_home() || is_archive())) {
    $html = preg_replace('#\sloading=("|\')lazy("|\')#i', '', $html);
    $html = preg_replace('#<img\s#', '<img fetchpriority="high" decoding="async" ', $html, 1);
    $done = true;
  }
  return $html;
}, 10, 5);

add_action('wp_head', function(){
  if (!is_home() && !is_archive()) return;
  echo '<script>document.addEventListener("DOMContentLoaded",function(){var i=document.querySelector(".video-grid img, .tmw-grid img");if(i){i.setAttribute("fetchpriority","high");i.setAttribute("decoding","async");i.removeAttribute("loading");}});</script>';
});

add_action('wp_enqueue_scripts', function () {
  if (!defined('TMW_DEBUG') || !TMW_DEBUG || is_admin()) {
    return;
  }

  $post_type = get_post_type();
  $script_handles = [];
  $style_handles  = [];

  global $wp_scripts, $wp_styles;

  if ($wp_scripts instanceof WP_Scripts) {
    foreach ((array) $wp_scripts->queue as $handle) {
      if (stripos($handle, 'slot') !== false) {
        $script_handles[] = $handle;
      }
    }
  }

  if ($wp_styles instanceof WP_Styles) {
    foreach ((array) $wp_styles->queue as $handle) {
      if (stripos($handle, 'slot') !== false) {
        $style_handles[] = $handle;
      }
    }
  }

  $script_report = empty($script_handles) ? '(none)' : implode('|', $script_handles);
  $style_report  = empty($style_handles) ? '(none)' : implode('|', $style_handles);

  error_log('[TMW-SLOT-AUDIT] enqueue slot assets on post_type=' . ($post_type ?: 'null') . ' scripts=' . $script_report . ' styles=' . $style_report);
}, 200);

if ( file_exists( get_stylesheet_directory() . '/inc/tmw-tax-bind-models-video.php' ) ) {
    require_once get_stylesheet_directory() . '/inc/tmw-tax-bind-models-video.php';
}

if (file_exists(get_stylesheet_directory() . '/inc/tmw-mobile-hero-parity.php')) {
    require_once get_stylesheet_directory() . '/inc/tmw-mobile-hero-parity.php';
}

/**
 * Autoptimize: exclude hero handles from concatenation/reorder to keep cascade deterministic.
 * The filters are ignored automatically when Autoptimize is not active.
 */
add_filter('autoptimize_filter_css_exclude', function ($list) {
    // Keep the child stylesheet separate for predictable cascade, and ensure
    // the late inline hero fix stays untouched for debug toggles.
    $extra = ',retrotube-child-style,tmw-mobile-hero-fix';
    return is_string($list) ? $list . $extra : $extra;
});
add_filter('script_loader_tag', function ($tag, $handle, $src) {
    $defer_handles = ['tmw-flip-guard', 'tmw-flip-a11y'];

    if (in_array($handle, $defer_handles, true)) {
        if (strpos($tag, ' defer ') === false && strpos($tag, ' defer>') === false) {
            $tag = str_replace('<script ', '<script defer ', $tag);
        }
    }

    return $tag;
}, 10, 3);

/** Admin enqueues (editor/post type model) */
add_action('admin_enqueue_scripts', function ($hook) {
    // Move any admin-only assets here, same conditions as before.
    // Admin asset logic lives in inc/admin modules.
}, 20);

if ( file_exists( get_stylesheet_directory() . '/inc/slot-width-sync.php' ) ) {
    require_once get_stylesheet_directory() . '/inc/slot-width-sync.php';
}

