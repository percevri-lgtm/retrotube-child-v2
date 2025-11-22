<?php

// Toggle this to true temporarily to inspect banner resolution in logs/comments.
if (!defined('TMW_BANNER_DEBUG')) {
  define('TMW_BANNER_DEBUG', false);
}

require_once get_stylesheet_directory() . '/assets/php/tmw-hybrid-model-scan.php';

if (!function_exists('tmw_debug_log')) {
  function tmw_debug_log(string $message): void {
    if (!defined('TMW_DEBUG') || !TMW_DEBUG) {
      return;
    }

    if (!is_string($message)) {
      $message = print_r($message, true);
    }

    error_log('[TMW] ' . $message);
  }
}

// === TMW v3.1.5 — Label alias for model tags ===
add_filter('the_content', function ($content) {
    if (!is_singular('model')) {
        return $content;
    }

    return preg_replace_callback(
        '/<a\b[^>]*\bclass="[^"]*\btag-link\b[^"]*"[^>]*>/i',
        function ($matches) {
            $tag = $matches[0];

            if (!preg_match('/class="([^"]*)"/i', $tag, $class_match)) {
                return $tag;
            }

            $classes = $class_match[1];

            if (preg_match('/\blabel\b/i', $classes)) {
                return $tag;
            }

            $normalized = trim(preg_replace('/\s+/', ' ', $classes));
            $updated = 'label' . ($normalized !== '' ? ' ' . $normalized : '');

            return str_replace($class_match[0], 'class="' . $updated . '"', $tag);
        },
        $content
    );
}, 12);


function tmw_bind_post_tag_to_model(): void {
    if (!taxonomy_exists('post_tag') || !post_type_exists('model')) {
        return;
    }

    if (!is_object_in_taxonomy('model', 'post_tag')) {
        register_taxonomy_for_object_type('post_tag', 'model');
    }
}

add_action('init', 'tmw_bind_post_tag_to_model', 20);
add_action('registered_post_type', function ($post_type) {
    if ('model' === $post_type) {
        tmw_bind_post_tag_to_model();
    }
}, 20);

function tmw_bind_models_taxonomy(): void {
    if (!taxonomy_exists('models')) {
        return;
    }

    $targets = ['post'];
    $detected = tmw_detect_livejasmin_post_type();
    if ($detected && 'video' !== $detected) {
        $targets[] = $detected;
    }

    foreach (array_unique($targets) as $post_type) {
        if (!post_type_exists($post_type)) {
            continue;
        }

        if (!is_object_in_taxonomy($post_type, 'models')) {
            register_taxonomy_for_object_type('models', $post_type);
        }
    }
}

add_action('init', 'tmw_bind_models_taxonomy', 30);
add_action('registered_post_type', function ($post_type) {
    if ($post_type === 'video') {
        return;
    }

    tmw_bind_models_taxonomy();
}, 30, 1);
add_action('registered_taxonomy', function ($taxonomy) {
    if ('models' === $taxonomy) {
        tmw_bind_models_taxonomy();
    }
}, 30, 1);

if (!function_exists('tmw_detect_livejasmin_post_type')) {
    function tmw_detect_livejasmin_post_type() {
        static $detected = null;

        if ($detected !== null) {
            return $detected;
        }

        $detected = post_type_exists('video') ? 'video' : 'post';

        global $wpdb;
        $meta_keys = ['wpslj_video_id', 'wpslj_model', 'wpslj_stream'];
        $placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
        $sql = "
            SELECT p.post_type
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
            WHERE m.meta_key IN ($placeholders)
            LIMIT 1
        ";

        $prepared = $wpdb->prepare($sql, $meta_keys);
        if ($prepared) {
            $found = $wpdb->get_var($prepared);
            if (!empty($found)) {
                $detected = $found;
            }
        }

        if ($detected && taxonomy_exists('models') && !is_object_in_taxonomy($detected, 'models')) {
            if ('video' === $detected) {
                if (function_exists('tmw_bind_models_to_video_once')) {
                    tmw_bind_models_to_video_once();
                }
            } else {
                register_taxonomy_for_object_type('models', $detected);
            }
        }

        return $detected;
    }
}

// === [TMW-MODEL-QUERY-FIX v2.6.8] Ensure model pages display related videos ===
if (!function_exists('tmw_get_videos_for_model')) {
    function tmw_get_videos_for_model($model_slug, $limit = 24) {
        if (empty($model_slug)) {
            return [];
        }

        $taxonomy = 'models';
        $post_type = tmw_detect_livejasmin_post_type();

        if ('video' !== $post_type && taxonomy_exists($taxonomy) && !is_object_in_taxonomy($post_type, $taxonomy)) {
            register_taxonomy_for_object_type($taxonomy, $post_type);
        }

        $args = [
            'post_type'      => $post_type,
            'posts_per_page' => $limit,
            'tax_query'      => [
                [
                    'taxonomy' => $taxonomy,
                    'field'    => 'slug',
                    'terms'    => $model_slug,
                ],
            ],
            'post_status'    => 'publish',
            'no_found_rows'  => true,
        ];

        $q = new WP_Query($args);

        return $q->have_posts() ? $q->posts : [];
    }
}

if (!function_exists('tmw_register_hybrid_scan_cli')) {
  function tmw_register_hybrid_scan_cli() {
    if (!defined('WP_CLI') || !WP_CLI) {
      return;
    }

    if (class_exists('WP_CLI')) {
      WP_CLI::add_command('tmw scan-model-videos', 'tmw_cli_scan_model_videos');
    }
  }
}

add_action('init', 'tmw_register_hybrid_scan_cli');

/**
 * 🧩 TMW Flipbox Force Ajax + Footer Fix (v2.9.3)
 * Ensures ajaxurl exists and wp_footer() output includes our ping script.
 */
add_action('wp_footer', function () {
    ?>
    <script>
    window.ajaxurl = window.ajaxurl || "<?php echo admin_url('admin-ajax.php'); ?>";
    console.log('[TMW-FLIPBOX-DEBUG] Footer ping initializing...');
    fetch(window.ajaxurl + '?action=tmw_flipbox_debug_ping_footer&t=' + Date.now())
        .then(r => r.text())
        .then(() => console.log('[TMW-FLIPBOX-DEBUG] Footer ping sent.'));
    </script>
    <?php
}, 9999);

add_action('wp_ajax_tmw_flipbox_debug_ping_footer', function(){
    tmw_debug_log('[TMW-FLIPBOX-DEBUG] ✅ Footer ping executed (js confirmed).');
    wp_die();
});
add_action('wp_ajax_nopriv_tmw_flipbox_debug_ping_footer', function(){
    tmw_debug_log('[TMW-FLIPBOX-DEBUG] ✅ Footer ping executed (js confirmed).');
    wp_die();
});

/**
 * Unified banner resolver used by both admin preview and the front-end.
 * Priority: post-level ACF/legacy sources -> taxonomy ACF & feed helpers -> featured image fallback.
 */
if (!function_exists('tmw_resolve_model_banner_url')) {
  function tmw_resolve_model_banner_url($post_id = 0, $term_id = 0) {
    $arg_count = func_num_args();
    $original_post_id = $post_id;

    $post_id = (int) $post_id;
    $term_id = (int) $term_id;

    if ($arg_count === 1 && $term_id === 0 && $post_id && !get_post($post_id)) {
      $term_id = $post_id;
      $post_id = 0;
    }

    $banner_url = '';

    if ($post_id) {
      if (function_exists('get_field')) {
        $banner_field = get_field('banner_image', $post_id);
        if (is_array($banner_field) && !empty($banner_field['url'])) {
          $banner_url = (string) $banner_field['url'];
        } elseif (is_string($banner_field) && filter_var($banner_field, FILTER_VALIDATE_URL)) {
          $banner_url = $banner_field;
        }
      }

      if (empty($banner_url)) {
        $legacy = get_post_meta($post_id, 'banner_image', true);
        if (is_array($legacy) && !empty($legacy['url']) && filter_var($legacy['url'], FILTER_VALIDATE_URL)) {
          $banner_url = (string) $legacy['url'];
        } elseif (is_string($legacy) && filter_var($legacy, FILTER_VALIDATE_URL)) {
          $banner_url = $legacy;
        } elseif (is_numeric($legacy)) {
          $maybe = wp_get_attachment_url((int) $legacy);
          if (is_string($maybe) && filter_var($maybe, FILTER_VALIDATE_URL)) {
            $banner_url = $maybe;
          }
        }
      }

      if (empty($banner_url)) {
        $legacy_url = get_post_meta($post_id, 'banner_image_url', true);
        if (is_array($legacy_url) && !empty($legacy_url['url']) && filter_var($legacy_url['url'], FILTER_VALIDATE_URL)) {
          $banner_url = (string) $legacy_url['url'];
        } elseif (is_string($legacy_url) && filter_var($legacy_url, FILTER_VALIDATE_URL)) {
          $banner_url = $legacy_url;
        }
      }
    }

    if ($term_id === 0 && $post_id) {
      $terms = wp_get_post_terms($post_id, 'models');
      if (!is_wp_error($terms) && !empty($terms)) {
        $term_id = (int) $terms[0]->term_id;
      }
    }

    if (empty($banner_url) && $term_id) {
      $acf_id = 'models_' . $term_id;
      $source = function_exists('get_field') ? (get_field('banner_source', $acf_id) ?: 'feed') : 'feed';

      if ($source === 'url' && function_exists('get_field')) {
        $maybe_url = (string) (get_field('banner_image_url', $acf_id) ?: '');
        if ($maybe_url) {
          $banner_url = $maybe_url;
        }
      }

      if (empty($banner_url) && $source === 'upload' && function_exists('get_field')) {
        $img = get_field('banner_image', $acf_id);
        if (is_array($img) && !empty($img['url'])) {
          $banner_url = (string) $img['url'];
        }
      }

      if (empty($banner_url) && function_exists('tmw_aw_find_by_candidates')) {
        $term = get_term($term_id, 'models');
        $candidates = [];
        $nick = get_term_meta($term_id, 'tmw_aw_nick', true);
        if ($nick) {
          $candidates[] = $nick;
        }
        if ($term && !is_wp_error($term)) {
          $candidates[] = $term->name;
          $candidates[] = $term->slug;
        }
        $row = tmw_aw_find_by_candidates(array_unique(array_filter($candidates)));
        if ($row) {
          $maybe = tmw_pick_banner_from_feed_row($row);
          if ($maybe) {
            $banner_url = $maybe;
          }
        }
      }

      if (empty($banner_url) && function_exists('get_field')) {
        $img = get_field('banner_image', $acf_id);
        if (is_array($img) && !empty($img['url'])) {
          $banner_url = (string) $img['url'];
        } elseif (!$banner_url) {
          $maybe_url = get_field('banner_image_url', $acf_id);
          if (is_string($maybe_url) && filter_var($maybe_url, FILTER_VALIDATE_URL)) {
            $banner_url = $maybe_url;
          }
        }
      }

      if (empty($banner_url) && function_exists('tmw_placeholder_image_url')) {
        $banner_url = (string) tmw_placeholder_image_url();
      }
    }

    if (empty($banner_url) && $post_id && has_post_thumbnail($post_id)) {
      $banner_url = wp_get_attachment_image_url(get_post_thumbnail_id($post_id), 'full');
    }

    if (!empty($banner_url)) {
      $banner_url = set_url_scheme($banner_url, 'https');
    }

    if ((defined('WP_DEBUG') && WP_DEBUG) || TMW_BANNER_DEBUG) {
      $debug_post = $post_id ?: (int) $original_post_id;
          }

    return $banner_url ? esc_url_raw($banner_url) : '';
  }
}

/**
 * Back-compat wrapper for existing calls.
 */
if (!function_exists('tmw_get_model_banner_url')) {
  function tmw_get_model_banner_url($post_id) {
    $banner = tmw_resolve_model_banner_url($post_id);

    if (empty($banner) && TMW_BANNER_DEBUG) {
          }

    return $banner;
  }
}

/**
 * Render the unified model banner markup for both the front-end and admin preview.
 *
 * @param int    $model_id Model post ID (falls back to current post when omitted).
 * @param string $context  Rendering context identifier (e.g. 'frontend' or 'backend').
 *
 * @return bool Whether the banner markup was rendered.
 */
if (!function_exists('tmw_render_model_banner')) {
  function tmw_render_model_banner($model_id = 0, $context = 'frontend') {
    if (!$model_id) {
      $model_id = get_the_ID();
    }

    $model_id = (int) $model_id;
    $context  = is_string($context) ? $context : 'frontend';
    $context  = $context ? sanitize_html_class($context) : 'frontend';

    if (!$model_id) {
      return false;
    }

    $url    = tmw_resolve_model_banner_url($model_id);
    $offset = (int) get_post_meta($model_id, '_banner_position_y', true);
    // Persist admin preview height as --offset-base for perfect scaling (fallback 350px).
    $offset_base = (int) get_post_meta($model_id, '_tmw_offset_base', true);
    if ($offset_base <= 0) {
      $offset_base = 350;
    }

    if ($url) {
      $classes = array_filter(['tmw-banner-frame', 'tmw-bg-mode', $context]);

      $style_parts = [
        sprintf('--offset-y:%dpx', (int) $offset),
        sprintf('--offset-base:%dpx', (int) $offset_base),
        sprintf('background-image:url("%s")', esc_url_raw($url)),
      ];

      $style = implode('; ', $style_parts);
      if ($style !== '') {
        $style .= ';';
      }

      $attachment_id = attachment_url_to_postid($url);
      $image_size    = apply_filters('tmw/model_banner/image_size', 'large');
      $dimensions    = function_exists('tmw_child_image_dimensions')
        ? tmw_child_image_dimensions($url, 1035, 350)
        : ['width' => 1035, 'height' => 350];

      $attrs = [
        'src'           => esc_url($url),
        'alt'           => '',
        'loading'       => 'eager',
        'fetchpriority' => 'high',
        'decoding'      => 'async',
        'width'         => (int) $dimensions['width'],
        'height'        => (int) $dimensions['height'],
      ];

      if ($attachment_id) {
        $src_data = wp_get_attachment_image_src($attachment_id, $image_size);
        if (is_array($src_data) && !empty($src_data[0])) {
          $attrs['src']    = esc_url($src_data[0]);
          $attrs['width']  = !empty($src_data[1]) ? (int) $src_data[1] : $attrs['width'];
          $attrs['height'] = !empty($src_data[2]) ? (int) $src_data[2] : $attrs['height'];
        }

        $meta = wp_get_attachment_metadata($attachment_id);
        if (is_array($meta) && isset($meta['sizes'][$image_size])) {
          $size_meta = $meta['sizes'][$image_size];

          if (!empty($size_meta['width'])) {
            $attrs['width'] = (int) $size_meta['width'];
          }

          if (!empty($size_meta['height'])) {
            $attrs['height'] = (int) $size_meta['height'];
          }
        }

        $srcset = wp_get_attachment_image_srcset($attachment_id, $image_size);
        if ($srcset) {
          $attrs['srcset'] = $srcset;
        }

        $sizes = wp_get_attachment_image_sizes($attachment_id, $image_size);
        if ($sizes) {
          $attrs['sizes'] = $sizes;
        }
      }

      $attr_html = '';
      foreach ($attrs as $key => $value) {
        if ($value === '' || $value === null) {
          continue;
        }
        $attr_html .= ' ' . $key . '="' . esc_attr($value) . '"';
      }

      echo '<div class="tmw-banner-container">';
      echo '  <div class="' . esc_attr(implode(' ', $classes)) . '" style="' . esc_attr($style) . '">';
      echo '    <img' . $attr_html . ' />';
      echo '  </div>';
      echo '</div>';

      return true;
    }

    return false;
  }
}

/**
 * Resolve vertical offset (in px). Prefers saved slider meta, then ACF taxonomy `banner_offset_y` if present.
 */
if (!function_exists('tmw_get_model_banner_offset_y')) {
  function tmw_get_model_banner_offset_y($post_id) {
    $raw_y   = get_post_meta($post_id, '_banner_position_y', true);
    $has_meta = $raw_y !== '' && $raw_y !== null;
    $y       = $has_meta ? (int)$raw_y : 0;

    if (!$has_meta) {
      // Try taxonomy ACF offset
      $terms = wp_get_post_terms($post_id, 'models');
      if (!is_wp_error($terms) && !empty($terms) && function_exists('get_field')) {
        $term_id = (int)$terms[0]->term_id;
        $acf_y   = get_field('banner_offset_y', 'models_' . $term_id);
        if (is_numeric($acf_y)) {
          $y = (int)$acf_y;
        }
      }
    }

    // Clamp to sensible range
    if ($y < -1000) {
      $y = -1000;
    }
    if ($y > 1000) {
      $y = 1000;
    }

    return $y;
  }
}

if (!function_exists('tmw_get_model_banner_height')) {
  function tmw_get_model_banner_height($post_id) {
    $height = 350;

    $terms = wp_get_post_terms($post_id, 'models');
    if (!is_wp_error($terms) && !empty($terms) && function_exists('get_field')) {
      $term_id = (int)$terms[0]->term_id;
      $pick    = get_field('banner_height', 'models_' . $term_id);

      if (is_array($pick) && isset($pick['value'])) {
        $pick = $pick['value'];
      }

      if (is_numeric($pick) || is_string($pick)) {
        $pick = (string)$pick;
        if ($pick === '350') {
          $height = 350;
        }
      }
    }

    return $height;
  }
}

if (!function_exists('tmw_get_banner_style')) {
  function tmw_get_banner_style($offset_y = 0, $height = 350, $context = []) {
    $offset_y = is_numeric($offset_y) ? (int) $offset_y : 0;
    $height   = is_numeric($height) ? (int) $height : 350;
    if ($height <= 0) {
      $height = 350;
    }

    if (!is_array($context)) {
      if (is_numeric($context)) {
        $context = ['post_id' => (int) $context];
      } else {
        $context = [];
      }
    }

    $render_context = 'frontend';
    if (!empty($context['render_context']) && is_string($context['render_context'])) {
      $render_context = strtolower($context['render_context']);
    } elseif (is_admin()) {
      $render_context = 'admin';
    }

    $frame_ratio = 1338 / 396;
    $image_ratio = 0.0;
    $image_url   = isset($context['image_url']) ? (string) $context['image_url'] : '';
    $post_id     = isset($context['post_id']) ? (int) $context['post_id'] : 0;
    $term_id     = isset($context['term_id']) ? (int) $context['term_id'] : 0;

    if (!$post_id && function_exists('get_the_ID')) {
      $maybe_post_id = get_the_ID();
      if ($maybe_post_id) {
        $post_id = (int) $maybe_post_id;
      }
    }

    if (!$post_id && isset($GLOBALS['post']) && $GLOBALS['post'] instanceof WP_Post) {
      $post_id = (int) $GLOBALS['post']->ID;
    }

    if (!$image_url) {
      if ($post_id && function_exists('tmw_resolve_model_banner_url')) {
        $image_url = (string) tmw_resolve_model_banner_url($post_id);
      } elseif ($term_id && function_exists('tmw_resolve_model_banner_url')) {
        $image_url = (string) tmw_resolve_model_banner_url(0, $term_id);
      }
    }

    $image_width        = null;
    $image_height       = null;
    $meta_image_width   = null;
    $meta_image_height  = null;
    $used_meta_fallback = false;

    if ($image_url) {
      $attachment_id = function_exists('attachment_url_to_postid') ? attachment_url_to_postid($image_url) : 0;
      if ($attachment_id) {
        $meta = wp_get_attachment_metadata($attachment_id);
        if (is_array($meta)) {
          if (!empty($meta['width'])) {
            $meta_image_width = (int) $meta['width'];
            $image_width      = $meta_image_width;
          }
          if (!empty($meta['height'])) {
            $meta_image_height = (int) $meta['height'];
            $image_height      = $meta_image_height;
          }
        }
      }

      $size = null;
      if (function_exists('wp_getimagesize')) {
        $size = wp_getimagesize($image_url);
      } else {
        $size = @getimagesize($image_url); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
      }

      if (is_array($size) && !empty($size[0]) && !empty($size[1])) {
        $image_width  = (int) $size[0];
        $image_height = (int) $size[1];
      } elseif ($meta_image_width && $meta_image_height) {
        $image_width        = $meta_image_width;
        $image_height       = $meta_image_height;
        $used_meta_fallback = true;
      }

      if ($image_width && $image_height) {
        $image_ratio = $image_width / $image_height;
      }
    }

    $object_fit            = 'cover';
    $ratio_within_tolerance = false;

    if ($image_ratio > 0) {
      $ratio_within_tolerance = (abs($image_ratio - $frame_ratio) <= ($frame_ratio * 0.12));
    }

    if ($image_ratio <= 0) {
      $object_fit = 'contain';
    } elseif ($ratio_within_tolerance) {
      $object_fit = 'contain';
    } elseif ($image_ratio > 0 && $image_ratio < $frame_ratio) {
      $object_fit = 'contain';
    }

    $max_offset = max(50, min(1000, abs($height)));
    if ($offset_y > $max_offset) {
      $offset_y = $max_offset;
    }
    if ($offset_y < -$max_offset) {
      $offset_y = -$max_offset;
    }

    $offset_base = 350;
    if (isset($context['offset_base']) && is_numeric($context['offset_base'])) {
      $offset_base = max(1, (int) $context['offset_base']);
    } elseif ($post_id) {
      $maybe_base = (int) get_post_meta($post_id, '_tmw_offset_base', true);
      if ($maybe_base > 0) {
        $offset_base = $maybe_base;
      }
    }

    $style_parts   = [];
    $style_parts[] = sprintf('--offset-y:%s;', $offset_y . 'px');
    $style_parts[] = sprintf('--offset-base:%s;', $offset_base . 'px');
    $style_parts[] = '--offset-x:50%;';
    $style_parts[] = '--banner-object-fit:' . $object_fit . ';';
    $style_parts[] = '--banner-img-width:auto;';
    $style_parts[] = '--banner-img-height:100%;';
    $style_parts[] = '--banner-img-max-width:none;';
    $style_parts[] = '--banner-img-max-height:none;';
    $style_parts[] = '--banner-translate-x:0;';
    $style_parts[] = '--banner-position:static;';
    $style_parts[] = '--banner-left:auto;';

    if (($used_meta_fallback || $image_ratio <= 0) && $image_url && ((defined('WP_DEBUG') && WP_DEBUG) || TMW_BANNER_DEBUG)) {
          }

    if ((defined('WP_DEBUG') && WP_DEBUG) || TMW_BANNER_DEBUG) {
      $ratio_display = $image_ratio > 0 ? number_format((float) $image_ratio, 4) : 'n/a';
          }

    return implode('', $style_parts);
  }
}

/* ======================================================================
 * ONE-TIME MIGRATIONS
 * ====================================================================== */
/**
 * Move all legacy model_bio posts into the model CPT.
 */
add_action('init', function(){
  global $wpdb;
  if (get_option('tmw_migrated_model_bio')) return;

  $wpdb->query("UPDATE {$wpdb->posts} SET post_type = 'model' WHERE post_type = 'model_bio'");

  update_option('tmw_migrated_model_bio', 1);
  flush_rewrite_rules();
}, 5);

/* ======================================================================
 * LEGACY CPT CLEANUP
 * ====================================================================== */
add_action('init', function(){
  global $wp_post_types;
  if (isset($wp_post_types['model_bio'])) {
    unset($wp_post_types['model_bio']);
  }
}, 20);

/* ======================================================================
 * MODEL CPT NORMALIZATION
 * ====================================================================== */
/**
 * Normalize 'model' CPT so breadcrumbs are correct.
 * Works even if CPT is registered by parent theme or plugin.
 */
add_filter('register_post_type_args', function ($args, $post_type) {
  if ($post_type !== 'model') return $args;

  // Labels used by theme breadcrumbs
  $args['labels']                 = isset($args['labels']) ? $args['labels'] : [];
  $args['labels']['name']         = 'Models';
  $args['labels']['menu_name']    = 'Models';
  $args['labels']['singular_name'] = isset($args['labels']['singular_name']) ? $args['labels']['singular_name'] : 'Model';
  $args['labels']['archives']     = 'Models';

  // Archive should be /models/
  // Singles should remain /model/%postname%/
  $args['has_archive'] = 'models';
  $args['rewrite'] = [
    'slug'       => 'model',
    'with_front' => false,
  ];

  // Ensure public so archive link is generated
  $args['public'] = true;

  return $args;
}, 10, 2);

/**
 * One-time flush so the new /models/ archive starts working immediately.
 */
add_action('init', function () {
  if (get_option('tmw_flushed_cpt_rewrites_models')) return;
  flush_rewrite_rules();
  update_option('tmw_flushed_cpt_rewrites_models', 1);
});

/**
 * Redirect legacy /model/ archive to /models/
 */
add_action('template_redirect', function () {
  $req = isset($_SERVER['REQUEST_URI']) ? trailingslashit(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) : '';
  if ($req === '/model/' && !is_singular('model')) {
    wp_redirect(home_url('/models/'), 301);
    exit;
  }
});

/**
 * Add "Edit Models Page" link to admin bar when viewing /models/.
 */
add_action('admin_bar_menu', function ($admin_bar) {
  if (!is_post_type_archive('model') || !current_user_can('edit_pages')) return;

  $models_page = get_page_by_path('models');
  if (!$models_page instanceof WP_Post) return;

  $admin_bar->add_menu([
    'id'    => 'edit-models-page',
    'title' => 'Edit Models Page',
    'href'  => get_edit_post_link($models_page->ID),
  ]);
}, 100);

/* ======================================================================
 * MODEL TERM → CPT SYNC
 * ====================================================================== */
if (!function_exists('tmw_sync_model_term_to_post')) {
  function tmw_sync_model_term_to_post($term_id, $tt_id) {
    $term = get_term($term_id, 'models');
    if (is_wp_error($term) || !$term) return;

    $slug     = sanitize_title(isset($term->slug) ? $term->slug : $term->name);
    $title    = sanitize_text_field($term->name);
    $desc     = term_description($term_id, 'models');
    $content  = wp_strip_all_tags($desc);
    $existing = get_page_by_path($slug, OBJECT, 'model');

    if ($existing instanceof WP_Post) {
      $needs_update = false;
      $update_data  = ['ID' => $existing->ID];

      if ($title && $existing->post_title !== $title) {
        $update_data['post_title'] = $title;
        $needs_update              = true;
      }

      if ($content && $existing->post_content !== $content) {
        $update_data['post_content'] = $content;
        $needs_update                = true;
      }

      if ($needs_update) {
        $result = wp_update_post($update_data, true);
        if (!is_wp_error($result)) {
          tmw_debug_log("[ModelSync] Updated CPT model for {$title} (slug: {$slug})");
        } else {
          tmw_debug_log('[ModelSync] Failed to update model post for ' . $slug . ': ' . $result->get_error_message());
        }
      } else {
        tmw_debug_log("[ModelSync] Model post already up to date for {$slug}");
      }

      return;
    }

    $post_id = wp_insert_post([
      'post_title'   => $title,
      'post_name'    => $slug,
      'post_content' => $content,
      'post_type'    => 'model',
      'post_status'  => 'publish',
    ]);

    if (!is_wp_error($post_id)) {
      tmw_debug_log("[ModelSync] Created CPT model for term {$title} (slug: {$slug})");
    } else {
      tmw_debug_log('[ModelSync] Failed to create model post for ' . $slug . ': ' . $post_id->get_error_message());
    }
  }
}

add_action('created_models', 'tmw_sync_model_term_to_post', 10, 2);
add_action('edited_models',  'tmw_sync_model_term_to_post', 10, 2);

add_action('init', function () {
  if (get_option('tmw_models_synced')) return;

  $terms = get_terms([
    'taxonomy'   => 'models',
    'hide_empty' => false,
  ]);

  if (is_wp_error($terms)) {
    tmw_debug_log('[ModelSync] Failed to fetch models terms for retroactive sync: ' . $terms->get_error_message());
    return;
  }

  foreach ($terms as $term) {
    tmw_sync_model_term_to_post($term->term_id, $term->term_taxonomy_id);
  }

  update_option('tmw_models_synced', true);
  tmw_debug_log('[ModelSync] Retroactive sync completed for existing taxonomy terms.');
}, 20);

/* ======================================================================
 * SAFE PLACEHOLDER (never 404s)
 * ====================================================================== */
if (!function_exists('tmw_placeholder_image_url')) {
  function tmw_placeholder_image_url() {
    $path = get_stylesheet_directory() . '/assets/img/placeholders/model-card.jpg';
    if (file_exists($path)) {
      return get_stylesheet_directory_uri() . '/assets/img/placeholders/model-card.jpg';
    }
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="1200"><rect fill="#121212" width="100%" height="100%"/><text x="50%" y="50%" fill="#666" font-size="40" font-family="system-ui,Arial" text-anchor="middle">Model</text></svg>';
    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
  }
}

/* ======================================================================
 * DISTINCT-IMAGE HELPERS (ignore size folders, queries, host)
 * ====================================================================== */
if (!function_exists('tmw_img_fingerprint')) {
  function tmw_img_fingerprint($url) {
    if (!$url) return '';
    $u = explode('?', $url, 2)[0];
    $u = preg_replace('~/(?:\d{3,4}x\d{3,4})/~', '/', $u);
    $u = preg_replace('~([-_]\d{3,4}x\d{3,4})(?=\.[a-z]+$)~i', '', $u);
    $p = @parse_url($u);
    $path = isset($p['path']) ? $p['path'] : $u;
    return strtolower($path);
  }
}
if (!function_exists('tmw_same_image')) {
  function tmw_same_image($a, $b) {
    if (!$a || !$b) return false;
    return tmw_img_fingerprint($a) === tmw_img_fingerprint($b);
  }
}

/* Preserve data: URLs in inline background-image styles */
if (!function_exists('tmw_bg_style')) {
  function tmw_bg_style($url){
    if (!$url) return '';
    $safe = (strpos($url, 'data:image') === 0) ? $url : esc_url($url);
    return 'background-image:url('. $safe .');';
  }
}

/* ======================================================================
 * EXPLICIT vs NON-EXPLICIT CLASSIFIER + PORTRAIT HELPERS
 * ====================================================================== */
if (!function_exists('tmw_is_portrait')) {
  function tmw_is_portrait($url) {
    $url = (string)$url;
    if (strpos($url, '600x800') !== false || strpos($url, '504x896') !== false) return true;
    if (strpos($url, '800x600') !== false || strpos($url, '896x504') !== false) return false;
    return false;
  }
}
if (!function_exists('tmw_classify_image')) {
  function tmw_classify_image($url) {
    $explicit_re    = defined('TMW_EXPLICIT_RE')    ? TMW_EXPLICIT_RE    : '~(explicit|nsfw|xxx|nude|naked|topless|boobs|tits|pussy|ass|anal|hard|sex|cum|dildo)~i';
    $nonexplicit_re = defined('TMW_NONEXPLICIT_RE') ? TMW_NONEXPLICIT_RE : '~(cover|poster|teaser|profile|safe|thumb|avatar|portrait)~i';
    $explicit_re    = apply_filters('tmw_explicit_regex',    $explicit_re);
    $nonexplicit_re = apply_filters('tmw_nonexplicit_regex', $nonexplicit_re);
    if (@preg_match($explicit_re, (string)$url))    return 'explicit';
    if (@preg_match($nonexplicit_re, (string)$url)) return 'safe';
    return 'unknown';
  }
}

/* ======================================================================
 * MODELS ⇄ AWE mapping UI + save + list columns
 * ====================================================================== */
add_action('models_add_form_fields', function(){
  ?>
  <div class="form-field term-group">
    <label for="tmw_aw_nick">AWE Nickname / Performer ID</label>
    <input type="text" name="tmw_aw_nick" id="tmw_aw_nick" value="" class="regular-text" />
    <p class="description">Must match <code>performerId</code>, <code>displayName</code>, or <code>nickname</code> in your AWE feed.</p>
  </div>
  <div class="form-field term-group">
    <label for="tmw_aw_subaff">AWE SubAff (optional)</label>
    <input type="text" name="tmw_aw_subaff" id="tmw_aw_subaff" value="" class="regular-text" />
    <p class="description">Used as <code>subAffId</code> ({SUBAFFID}) in tracking links.</p>
  </div>
  <?php wp_nonce_field('tmw_aw_term_meta', 'tmw_aw_term_meta_nonce'); ?>
  <?php
});
add_action('models_edit_form_fields', function($term){
  $nick = get_term_meta($term->term_id,'tmw_aw_nick',true);
  $sub  = get_term_meta($term->term_id,'tmw_aw_subaff',true);
  ?>
  <tr class="form-field">
    <th scope="row"><label for="tmw_aw_nick">AWE Nickname / Performer ID</label></th>
    <td>
      <input type="text" name="tmw_aw_nick" id="tmw_aw_nick" value="<?php echo esc_attr($nick); ?>" class="regular-text" />
      <p class="description">Must match <code>performerId</code>, <code>displayName</code>, or <code>nickname</code> in your AWE feed.</p>
      <?php wp_nonce_field('tmw_aw_term_meta', 'tmw_aw_term_meta_nonce'); ?>
    </td>
  </tr>
  <tr class="form-field">
    <th scope="row"><label for="tmw_aw_subaff">AWE SubAff (optional)</label></th>
    <td>
      <input type="text" name="tmw_aw_subaff" id="tmw_aw_subaff" value="<?php echo esc_attr($sub); ?>" class="regular-text" />
      <p class="description">Used as <code>subAffId</code> ({SUBAFFID}) in tracking links.</p>
    </td>
  </tr>
  <?php
});
add_action('created_models', 'tmw_save_models_aw_meta', 10);
add_action('edited_models',  'tmw_save_models_aw_meta', 10);
if (!function_exists('tmw_save_models_aw_meta')) {
  function tmw_save_models_aw_meta($term_id){
    if (!isset($_POST['tmw_aw_term_meta_nonce']) ||
        !wp_verify_nonce($_POST['tmw_aw_term_meta_nonce'], 'tmw_aw_term_meta')) {
      return;
    }
    if (isset($_POST['tmw_aw_nick']))   update_term_meta($term_id, 'tmw_aw_nick',   sanitize_text_field(wp_unslash($_POST['tmw_aw_nick'])));
    if (isset($_POST['tmw_aw_subaff'])) update_term_meta($term_id, 'tmw_aw_subaff', sanitize_text_field(wp_unslash($_POST['tmw_aw_subaff'])));
    delete_transient('tmw_aw_feed_v1'); // refresh cache after edits
  }
}
add_filter('manage_edit-models_columns', function($cols){
  $cols['tmw_aw_nick']   = 'AWE Nick/ID';
  $cols['tmw_aw_subaff'] = 'SubAff';
  return $cols;
});
add_filter('manage_models_custom_column', function($out, $col, $term_id){
  if ($col === 'tmw_aw_nick')   $out = esc_html(get_term_meta($term_id,'tmw_aw_nick',true));
  if ($col === 'tmw_aw_subaff') $out = esc_html(get_term_meta($term_id,'tmw_aw_subaff',true));
  return $out;
}, 10, 3);

/* ======================================================================
 * TEMPLATE HELPERS
 * ====================================================================== */
if (!function_exists('tmw_try_parent_template')) {
  function tmw_try_parent_template(array $candidates): bool {
    $parent_dir = trailingslashit(get_template_directory());

    foreach ($candidates as $candidate) {
      $path = $parent_dir . ltrim($candidate, '/');
      if (!file_exists($path)) {
        continue;
      }

      ob_start();
      include $path;
      $output = ob_get_clean();

      if ($output !== false) {
        echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        return true;
      }
    }

    return false;
  }
}

if (!function_exists('tmw_render_sidebar_layout')) {
  function tmw_render_sidebar_layout(string $context_class, callable $callback): void {
    $context_class = trim($context_class);
    $primary_class = 'content-area with-sidebar-right';
    if ($context_class !== '') {
      $primary_class .= ' ' . $context_class;
    }

    echo '<div id="content" class="site-content row">' . PHP_EOL;
    echo '  <div id="primary" class="' . esc_attr($primary_class) . '">' . PHP_EOL;
    echo '    <main id="main" class="site-main with-sidebar-right" role="main">' . PHP_EOL;

    call_user_func($callback);

    echo '    </main>' . PHP_EOL;
    echo '  </div>' . PHP_EOL;
    echo '  <aside id="sidebar" class="widget-area with-sidebar-right" role="complementary">' . PHP_EOL;
    get_sidebar();
    echo '  </aside>' . PHP_EOL;
    echo '</div>' . PHP_EOL;
  }
}

/* ======================================================================
 * GRID/FLIP CSS (base 2:3 + rotate)
 * ====================================================================== */

/**
 * Override parent query mods for /videos/?filter=longest to prevent 404.
 */
function tmw_videos_page_override( $query ) {
  if ( ! is_admin() && $query->is_main_query() && is_page( 'videos' ) ) {
    // Neutralize parent meta query that causes empty results
    $query->set( 'meta_key', '' );
    $query->set( 'orderby', 'none' );
    $query->is_page = true;
    $query->is_home = false;
    $query->is_404  = false;
  }
}
add_action( 'pre_get_posts', 'tmw_videos_page_override', 20 );


add_filter('wp_resource_hints', function($urls, $relation_type){
  if ('preconnect' === $relation_type) $urls[] = 'https://galleryn3.vcmdawe.com';
  if ('dns-prefetch' === $relation_type) $urls[] = '//galleryn3.vcmdawe.com';
  return $urls;
}, 10, 2);

add_action('after_setup_theme', function () {
  // Keep for completeness – not used directly by the banner
  add_image_size('tmw-model-hero-land', 1440, 810, true);   // 16:9
  add_image_size('tmw-model-hero-banner', 1200, 350, true); // ~3.43:1
});

/* ======================================================================
 * TMW TOOLS INTEGRATION (front/back overrides + alignment/zoom)
 * ====================================================================== */
if (!function_exists('tmw_tools_settings')) {
  function tmw_tools_settings(): array {
    $opt = get_option('tmw_mf_settings', []);
    return is_array($opt) ? $opt : [];
  }
}
if (!function_exists('tmw_get_model_keys')) {
  function tmw_get_model_keys(int $term_id): array {
    $keys = [];
    $aw = get_term_meta($term_id, 'tmw_aw_nick', true);
    $lj = get_term_meta($term_id, 'tmw_lj_nick', true);
    if ($aw) $keys[] = $aw;
    if ($lj) $keys[] = $lj;
    $t = get_term($term_id, 'models');
    if ($t && !is_wp_error($t)) { $keys[] = $t->name; $keys[] = $t->slug; }
    $norm = [];
    foreach ($keys as $k) {
      $k = trim((string)$k);
      if ($k === '') continue;
      $norm[] = strtolower($k);
      $norm[] = preg_replace('~[ _-]+~', '', strtolower($k));
    }
    $out = [];
    foreach (array_merge($keys, $norm) as $k) if ($k !== '' && !in_array($k, $out, true)) $out[] = $k;
    return $out;
  }
}
if (!function_exists('tmw_tools_pick_from_map')) {
  function tmw_tools_pick_from_map($map, array $cands) {
    if (!is_array($map) || empty($cands)) return null;
    foreach ($cands as $k) if (isset($map[$k]) && $map[$k] !== '') return $map[$k];
    $lower = []; foreach ($map as $k=>$v) $lower[strtolower((string)$k)] = $v;
    foreach ($cands as $k) { $lk = strtolower((string)$k); if (isset($lower[$lk]) && $lower[$lk] !== '') return $lower[$lk]; }
    $norm = []; foreach ($map as $k=>$v) $norm[preg_replace('~[ _-]+~','', strtolower((string)$k))] = $v;
    foreach ($cands as $k) { $nk = preg_replace('~[ _-]+~','', strtolower((string)$k)); if (isset($norm[$nk]) && $norm[$nk] !== '') return $norm[$nk]; }
    return null;
  }
}
if (!function_exists('tmw_bg_align_css')) {
  function tmw_bg_align_css($pos_percent = 50, $zoom = 1.0): string {
    $pos = max(0, min(100, (float)$pos_percent));
    $z   = max(1.0, min(2.5, (float)$zoom));
    $bgsize = ($z > 1.0) ? sprintf('%.2f%% auto', $z * 100.0) : 'cover';
    return sprintf(
      'background-position: %.2f%% 50%% !important; background-size: %s !important; --tmw-bgpos: %.2f%% 50%%; --tmw-bgsize: %s;',
      $pos, $bgsize, $pos, $bgsize
    );
  }
}
if (!function_exists('tmw_tools_overrides_for_term')) {
  function tmw_tools_overrides_for_term(int $term_id): array {
    $s      = tmw_tools_settings();
    $cands  = tmw_get_model_keys($term_id);

    $front_url = tmw_tools_pick_from_map($s['front_overrides'] ?? [], $cands);
    $back_url  = tmw_tools_pick_from_map($s['back_overrides']  ?? [], $cands);

    $pos_f = tmw_tools_pick_from_map($s['object_pos_front'] ?? [], $cands);
    $pos_b = tmw_tools_pick_from_map($s['object_pos_back']  ?? [], $cands);
    $zoom_f= tmw_tools_pick_from_map($s['zoom_front']       ?? [], $cands);
    $zoom_b= tmw_tools_pick_from_map($s['zoom_back']        ?? [], $cands);

    $css_front = tmw_bg_align_css($pos_f !== null ? $pos_f : 50, $zoom_f !== null ? $zoom_f : 1.0);
    $css_back  = tmw_bg_align_css($pos_b !== null ? $pos_b : 50, $zoom_b !== null ? $zoom_b : 1.0);

    return [
      'front_url' => is_string($front_url) ? $front_url : '',
      'back_url'  => is_string($back_url)  ? $back_url  : '',
      'css_front' => $css_front,
      'css_back'  => $css_back,
    ];
  }
}

/* ======================================================================
 * AWE FEED HELPERS
 * ====================================================================== */
if (!function_exists('tmw_normalize_nick')) {
  function tmw_normalize_nick($s){
    $s = strtolower($s);
    $s = preg_replace('~[^\pL\d]+~u', '', $s);
    return $s;
  }
}
if (!function_exists('tmw_aw_get_feed')) {
  function tmw_aw_get_feed($ttl_minutes = 10) {
    $key = 'tmw_aw_feed_v1';
    $cached = get_transient($key);
    if ($cached !== false) return $cached;
    if (!defined('AWEMPIRE_FEED_URL') || !AWEMPIRE_FEED_URL) return [];
    $resp = wp_remote_get(AWEMPIRE_FEED_URL, ['timeout' => 15]);
    if (is_wp_error($resp)) return [];
    $data = json_decode(wp_remote_retrieve_body($resp), true);
    if (is_array($data)) {
      if (isset($data['data']['models']) && is_array($data['data']['models'])) $data = $data['data']['models'];
      elseif (isset($data['models']) && is_array($data['models']))             $data = $data['models'];
    }
    if (!is_array($data)) $data = [];
    set_transient($key, $data, $ttl_minutes * MINUTE_IN_SECONDS);
    return $data;
  }
}
if (!function_exists('tmw_aw_find_by_candidates')) {
  function tmw_aw_find_by_candidates($cands){
    $feed = tmw_aw_get_feed();
    if (empty($feed) || !is_array($feed)) return null;
    $norms = array_map('tmw_normalize_nick', array_filter(array_unique((array)$cands)));
    foreach ($feed as $row){
      $vals = [];
      foreach (['performerId','displayName','nickname','name','uniqueModelId'] as $k) {
        if (!empty($row[$k])) $vals[] = $row[$k];
      }
      foreach ($vals as $v) {
        if (in_array(tmw_normalize_nick($v), $norms, true)) return $row;
      }
    }
    return null;
  }
}

/* ======================================================================
 * AWE image picker — prefer PORTRAIT. FRONT=safe, BACK=explicit
 * ====================================================================== */
if (!function_exists('tmw_try_portrait_variant')) {
  function tmw_try_portrait_variant($url) {
    $try = preg_replace_callback('~/(800x600|896x504)/~', function($m){
      return '/'.($m[1]==='800x600' ? '600x800' : '504x896').'/';
    }, $url, 1);
    if ($try && $try !== $url) return $try;
    $try2 = preg_replace_callback('~([-_])(800x600|896x504)(?=\.[a-z]+$)~i', function($m){
      return $m[1].($m[2]==='800x600' ? '600x800' : '504x896');
    }, $url, 1);
    return ($try2 && $try2 !== $url) ? $try2 : null;
  }
}
if (!function_exists('tmw_aw_pick_images_from_row')) {
  function tmw_aw_pick_images_from_row($row) {
    $all = [];
    $walk = function($v) use (&$walk, &$all) {
      if (is_string($v) && preg_match('~https?://[^\s"]+\.(?:jpe?g|png|webp)(?:\?[^\s"]*)?$~i', $v)) {
        $all[] = $v;
      } elseif (is_array($v)) {
        foreach ($v as $vv) $walk($vv);
      }
    };
    $walk($row);
    $all = array_values(array_unique($all));
    if (!$all) return [null, null];

    $by_pic = [];
    foreach ($all as $u) {
      $fp = function_exists('tmw_img_fingerprint') ? tmw_img_fingerprint($u) : $u;
      if (!isset($by_pic[$fp])) $by_pic[$fp] = [];
      $by_pic[$fp][] = $u;
    }

    $pick_best_url = function($urls){
      usort($urls, function($a,$b){
        $sa = (tmw_is_portrait($a) ? 2 : 0) + (strpos($a,'600x800')!==false ? 1 : 0) + (strpos($a,'504x896')!==false ? 1 : 0);
        $sb = (tmw_is_portrait($b) ? 2 : 0) + (strpos($b,'600x800')!==false ? 1 : 0) + (strpos($b,'504x896')!==false ? 1 : 0);
        return $sb <=> $sa;
      });
      return $urls[0];
    };

    $shots = [];
    foreach ($by_pic as $urls) $shots[] = $pick_best_url($urls);

    $portrait_safe = []; $portrait_exp = []; $portrait_unk = []; $land_any = [];
    foreach ($shots as $u) {
      $cls = tmw_classify_image($u);
      if (tmw_is_portrait($u)) {
        if     ($cls === 'safe')     $portrait_safe[] = $u;
        elseif ($cls === 'explicit') $portrait_exp[]  = $u;
        else                         $portrait_unk[]  = $u;
      } else {
        $land_any[] = $u;
      }
    }

    $front = $portrait_safe[0] ?? ($portrait_unk[0] ?? null);
    $back  = null;
    foreach ($portrait_exp as $u) { if (!tmw_same_image($u, $front)) { $back = $u; break; } }
    if (!$back) { foreach ($portrait_unk as $u) { if (!tmw_same_image($u, $front)) { $back = $u; break; } } }

    if (!$front && !empty($land_any)) {
      $front_try = tmw_try_portrait_variant($land_any[0]);
      $front = $front_try ? $front_try : $land_any[0];
    }
    if (!$back && !empty($land_any)) {
      foreach ($land_any as $u) {
        if (!tmw_same_image($u, $front)) {
          $back_try = tmw_try_portrait_variant($u);
          $back = $back_try ? $back_try : $u;
          break;
        }
      }
    }

    if (!$front && !$back && !empty($shots)) $front = $shots[0];
    if (!$back) $back = $front;

    return [$front, $back];
  }
}
if (!function_exists('tmw_aw_build_link')) {
  function tmw_aw_build_link($base, $sub = '') {
    if (!$base) return '#';
    if ($sub) {
      if (strpos($base, '{SUBAFFID}') !== false) return str_replace('{SUBAFFID}', rawurlencode($sub), $base);
      $sep = (strpos($base, '?') !== false) ? '&' : '?';
      return $base . $sep . 'subAffId=' . rawurlencode($sub);
    }
    return str_replace('{SUBAFFID}', '', $base);
  }
}

/* ======================================================================
 * CARD DATA
 * ====================================================================== */
if (!function_exists('tmw_aw_card_data')) {
  function tmw_aw_card_data($term_id) {
    $placeholder = tmw_placeholder_image_url();
    $front = $back = ''; $link  = '';

    // 1) ACF overrides (taxonomy: models)
    if (function_exists('get_field')) {
      $acf_front = get_field('actor_card_front', 'models_' . $term_id); // keeping field names for data continuity
      $acf_back  = get_field('actor_card_back',  'models_' . $term_id);
      if (is_array($acf_front) && !empty($acf_front['url'])) $front = $acf_front['url'];
      if (is_array($acf_back)  && !empty($acf_back['url']))  $back  = $acf_back['url'];
    }

    // 2) Candidate keys
    $term = get_term($term_id, 'models');
    $cands = [];
    $explicit = get_term_meta($term_id, 'tmw_aw_nick', true);
    if (!$explicit) $explicit = get_term_meta($term_id, 'tm_lj_nick', true);
    if ($explicit) $cands[] = $explicit;
    if ($term && !is_wp_error($term)) {
      $cands[] = $term->slug;
      $cands[] = $term->name;
      $cands[] = str_replace(['-','_',' '], '', $term->slug);
      $cands[] = str_replace(['-','_',' '], '', $term->name);
    }

    // 3) Find feed row
    $row = tmw_aw_find_by_candidates(array_unique(array_filter($cands)));
    $sub = get_term_meta($term_id, 'tmw_aw_subaff', true);
    if (!$sub && $term && !is_wp_error($term)) $sub = $term->slug;

    if ($row) {
      if (!$front || !$back) {
        list($f, $b) = tmw_aw_pick_images_from_row($row);
        if (!$front) $front = $f;
        if (!$back)  $back  = $b;
      }
      $link = tmw_aw_build_link(($row['tracking_url'] ?? ($row['url'] ?? '')), $sub ?: ($explicit ?: ($term ? $term->slug : '')));
    }

    // 4) Fallback
    if (!$front || !$back) {
      $feed = tmw_aw_get_feed();
      if (is_array($feed) && !empty($feed)) {
        $ix = crc32((string)$term_id) % count($feed);
        $alt = $feed[$ix];
        list($f2, $b2) = tmw_aw_pick_images_from_row($alt);
        if (!$front) $front = $f2 ?: $placeholder;
        if (!$back)  $back  = $b2 ?: $front;
        if (!$link)  $link  = tmw_aw_build_link(($alt['tracking_url'] ?? ($alt['url'] ?? '')), $sub ?: ($term ? $term->slug : ''));
      }
    }

    // 5) Enforce portrait when possible
    if ($front && !tmw_is_portrait($front)) { $try = tmw_try_portrait_variant($front); if ($try) $front = $try; }
    if ($back  && !tmw_is_portrait($back))  { $try = tmw_try_portrait_variant($back);  if ($try) $back  = $try; }

    if (!$front) $front = $placeholder;
    if (!$back)  $back  = $front;

    return ['front' => $front, 'back' => $back, 'link' => $link];
  }
}

/* Admin bar button to purge AWE cache */
add_action('admin_bar_menu', function($bar){
  if (!current_user_can('manage_options')) return;
  $bar->add_node([
    'id'    => 'tmw_aw_clear_cache',
    'title' => 'Purge AWEmpire Cache',
    'href'  => wp_nonce_url(admin_url('?tmw_aw_clear_cache=1'), 'tmw_aw_clear_cache'),
  ]);
}, 100);
add_action('admin_init', function(){
  if ( current_user_can('manage_options') && isset($_GET['tmw_aw_clear_cache']) && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'tmw_aw_clear_cache') ) {
    delete_transient('tmw_aw_feed_v1');
    wp_safe_redirect(remove_query_arg(['tmw_aw_clear_cache','_wpnonce']));
    exit;
  }
});

/* ======================================================================
 * ACF FIELD GROUPS (now target TAXONOMY=models)
 * ====================================================================== */
add_action('acf/init', function () {
  if (!function_exists('acf_add_local_field_group')) return;

  // Content group (bio etc.) — no hero
  acf_add_local_field_group([
    'key'      => 'group_tmw_model_content_only',
    'title'    => 'Model Content',
    'location' => [[['param' => 'taxonomy', 'operator' => '==', 'value' => 'models']]],
    'position' => 'normal',
    'fields'   => [
      ['key'=>'fld_tmw_bio','label'=>'Biography','name'=>'bio','type'=>'wysiwyg','tabs'=>'all'],
      ['key'=>'fld_tmw_lines','label'=>'Read more: visible lines','name'=>'readmore_lines','type'=>'number','default_value'=>20,'min'=>5,'max'=>100,'step'=>1],
      ['key'=>'fld_tmw_live','label'=>'Live link (optional)','name'=>'live_link','type'=>'url','placeholder'=>'https://...'],
      ['key'=>'fld_tmw_feat_sc','label'=>'Featured models shortcode','name'=>'featured_models_shortcode','type'=>'text','default_value'=>'[featured_models count="4" mode="select-or-random" layout="flipbox"]'],
    ],
  ]);

  // Banner group (new key for models)
  acf_add_local_field_group([
    'key'      => 'group_tmw_model_banner_v2',
    'title'    => 'Banner (1200×350)',
    'location' => [[['param' => 'taxonomy', 'operator' => '==', 'value' => 'models']]],
    'position' => 'normal',
    'fields'   => [
      [
        'key' => 'fld_tmw_banner_source', 'label' => 'Banner source', 'name' => 'banner_source', 'type' => 'button_group',
        'choices' => ['feed'=>'From AWE feed','upload'=>'Upload','url'=>'External URL'], 'default_value'=>'feed',
      ],
      [
        'key'=>'fld_tmw_banner_height','label'=>'Banner height','name'=>'banner_height','type'=>'button_group',
        'choices'=> ['350'=>'1200×350'], 'default_value'=>'350',
      ],
      [
        'key'=>'fld_tmw_banner_url','label'=>'External banner URL','name'=>'banner_image_url','type'=>'url',
        'placeholder'=>'https://example.com/banner.jpg',
        'conditional_logic'=>[[['field'=>'fld_tmw_banner_source','operator'=>'==','value'=>'url']]],
      ],
      [
        'key'=>'fld_tmw_banner_upload','label'=>'Upload banner','name'=>'banner_image','type'=>'image',
        'return_format'=>'array','preview_size'=>'large',
        'conditional_logic'=>[[['field'=>'fld_tmw_banner_source','operator'=>'==','value'=>'upload']]],
      ],
      ['key'=>'fld_tmw_banner_x','label'=>'Position X','name'=>'banner_offset_x','type'=>'range','default_value'=>0,'min'=>-100,'max'=>100,'step'=>1,'append'=>'%'],
      ['key'=>'fld_tmw_banner_y','label'=>'Position Y','name'=>'banner_offset_y','type'=>'range','default_value'=>0,'min'=>-350,'max'=>350,'step'=>1,'append'=>'px'],
    ],
  ]);
});

/* Hide default description & any field literally named "Short Bio" on models */
add_action('admin_head-term.php', function () {
  $screen = get_current_screen();
  if (!$screen || $screen->taxonomy !== 'models') return;
  echo '<style>.term-description-wrap, .form-field.term-description-wrap { display:none !important; }</style>';
});
add_action('admin_footer', function () {
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || $screen->base !== 'term' || $screen->taxonomy !== 'models') return;
  ?>
  <script>
  jQuery(function($){
    $('.acf-label label').each(function(){
      var t = $(this).text().trim().toLowerCase();
      if(t === 'short bio'){ $(this).closest('.acf-field').hide(); }
    });
  });
  </script>
  <?php
});

/* 3) Admin: two-column layout (right sidebar) */
add_action('admin_enqueue_scripts', function ($hook) {
  if ($hook !== 'term.php') return;
  $screen = get_current_screen();
  if (!$screen || $screen->taxonomy !== 'models') return;

  add_action('admin_head', function () {
    echo '<style>
      .tmw-term-two-col{display:flex; gap:24px; align-items:flex-start}
      .tmw-term-two-col .tmw-term-right{width:330px; flex:0 0 330px}
      .tmw-term-two-col .tmw-term-main{flex:1 1 auto; min-width:0}
      @media(max-width:1024px){ .tmw-term-two-col{display:block} .tmw-term-right{width:auto} }
      /* Full-width banner preview */
      #tmw-banner-preview{margin:12px 0 0;}
      #tmw-banner-note{margin:6px 0 0; color:#666}
    </style>';
  });

  add_action('admin_footer', function () {
    ?>
    <script>
    jQuery(function($){
      // Build columns (form left, SEO box right)
      var $wrap = $('#wpbody-content .wrap').first();
      var $form = $wrap.find('form#edittag, form#addtag').first();
      if(!$form.length) return;

      var $main = $('<div class="tmw-term-main"></div>');
      var $right = $('<div class="tmw-term-right"></div>');
      var $cols = $('<div class="tmw-term-two-col"></div>').append($main).append($right);

      $form.appendTo($main);
      $wrap.append($cols);

      var $rank = $('#rank_math_metabox, .rank-math-metabox, #wpseo_meta');
      if ($rank.length){ $rank.appendTo($right).show(); }

      // Insert banner preview UNDER the whole form for full width
      if (!$('#tmw-banner-preview').length){
        $main.append(
          '<div id="tmw-banner-preview" class="tmw-banner-preview">'+
            '<div class="tmw-banner-container">'+
              '<div class="tmw-banner-frame backend" data-banner-height="350">'+
                '<img class="tmw-banner-preview-img" src="data:image/gif;base64,R0lGODlhAQABAAAAACwAAAAAAQABAAA=" alt="Banner preview" style="display:none;" />'+
              '</div>'+
            '</div>'+
            '<div class="ph">Banner preview (1039×350)</div>'+
          '</div>'+
          '<div id="tmw-banner-note">Tip: drag the sliders to fine-tune the banner offset. Preview matches the front-end.</div>'
        );
      }

      var $preview = $('#tmw-banner-preview');
      var $frame = $preview.find('.tmw-banner-frame');
      var $ph = $preview.find('.ph');
      if (typeof window.tmwSetBannerOffsetY !== 'function') {
        window.tmwSetBannerOffsetY = function(pxValue) {
          var v = Number(pxValue) || 0;
          var root = document.documentElement;
          if (root && root.style) {
            root.style.setProperty('--offset-y', v + 'px');
          }
          var defaultWrap = document.getElementById('tmw-banner-preview') || document.getElementById('tmwBannerPreview');
          var defaultFrame = null;
          if (defaultWrap) {
            if (defaultWrap.classList && defaultWrap.classList.contains('tmw-banner-frame')) {
              defaultFrame = defaultWrap;
            } else {
              defaultFrame = defaultWrap.querySelector('.tmw-banner-frame');
            }
          }
          if (defaultFrame && defaultFrame.style) {
            defaultFrame.style.setProperty('--offset-y', v + 'px');
          }
          return v;
        };
      }
      window.tmwUpdateBannerOffset = window.tmwUpdateBannerOffset || function(preview, value, posX){
        var numeric = parseInt(value, 10);
        if (isNaN(numeric)) numeric = 0;
        var clamped = Math.max(-1000, Math.min(1000, numeric));
        var applied = (typeof window.tmwSetBannerOffsetY === 'function') ? window.tmwSetBannerOffsetY(clamped) : clamped;
        var defaultWrap = document.getElementById('tmw-banner-preview') || document.getElementById('tmwBannerPreview');
        var defaultFrame = null;
        if (defaultWrap) {
          if (defaultWrap.classList && defaultWrap.classList.contains('tmw-banner-frame')) {
            defaultFrame = defaultWrap;
          } else {
            defaultFrame = defaultWrap.querySelector('.tmw-banner-frame');
          }
        }
        var target = preview;
        if (target && target.classList && !target.classList.contains('tmw-banner-frame')) {
          target = target.querySelector ? target.querySelector('.tmw-banner-frame') : null;
        }
        if (!target) {
          target = defaultFrame;
        }
        if (target && target.style && target !== defaultFrame) {
          target.style.setProperty('--offset-y', applied + 'px');
        }
        if (target && target.style) {
          if (typeof posX === 'number' && !isNaN(posX)) {
            target.style.setProperty('--offset-x', posX + '%');
          } else if (!target.style.getPropertyValue('--offset-x')) {
            target.style.setProperty('--offset-x', '50%');
          }
        }
        return clamped;
      };
      // ===== TMW: Dock Height / X / Y controls directly above the preview =====
(function(){
  // small toolbar styling
  if(!document.getElementById('tmw-controls-dock-css')){
    $('head').append(
      '<style id="tmw-controls-dock-css">\
        #tmw-controls-dock{background:#fff;border:1px solid #e5e5e5;border-radius:6px;margin:10px 0 8px;padding:10px 12px;box-shadow:0 1px 2px rgba(0,0,0,.04)}\
        #tmw-controls-dock .acf-field{margin:6px 0;padding:0;border:0}\
        #tmw-controls-dock .acf-label{min-width:160px}\
        #tmw-controls-dock .acf-input input[type=range]{width:220px}\
        #tmw-controls-dock .acf-input input[type=number]{max-width:90px}\
        @media (max-width: 782px){#tmw-controls-dock .acf-label{min-width:auto}}\
      </style>'
    );
  }

  // helper to find an ACF field by key/name (you already use this selector pattern)
  function fieldSel(key,name){
    var $f = $('.acf-field[data-key="'+key+'"]');
    if(!$f.length && name) $f = $('.acf-field[data-name="'+name+'"]');
    return $f;
  }

  // create the dock and move fields into it (keeps original inputs, so saving still works)
  if(!$('#tmw-controls-dock').length){
    var $dock = $('<div id="tmw-controls-dock" class="tmw-controls-dock"></div>').insertBefore($preview);

    // Detach the existing ACF fields
    var $h = fieldSel('fld_tmw_banner_height','banner_height').detach();  // Height (radio group)
    var $x = fieldSel('fld_tmw_banner_x','banner_offset_x').detach();     // Position X (range)
    var $y = fieldSel('fld_tmw_banner_y','banner_offset_y').detach();     // Position Y (range)

    // Order: Height, X, Y
    if($h.length) $dock.append($h);
    if($x.length) $dock.append($x);
    if($y.length) $dock.append($y);

    // Optional: if you also want the Source switch here, uncomment next line:
    // fieldSel('fld_tmw_banner_source','banner_source').detach().prependTo($dock);
  }
})();


      function fieldSel(key,name){
        var $f = $('.acf-field[data-key="'+key+'"]');
        if(!$f.length && name) $f = $('.acf-field[data-name="'+name+'"]');
        return $f;
      }
      function readSrc(){
        var $s = fieldSel('fld_tmw_banner_source','banner_source');
        var v = $s.find('input[type="radio"]:checked').val();
        if(!v) v = $s.find('input[type="hidden"]').val();
        return (v||'feed').trim();
      }
      function readHeight(){
        return 350;
      }
      function readURL(){
        var $u = fieldSel('fld_tmw_banner_url','banner_image_url');
        return ($u.find('input[type="url"], input[type="text"]').val() || '').trim();
      }
      function readUpload(){
        var $up = fieldSel('fld_tmw_banner_upload','banner_image');
        var src = $up.find('.image-wrap img').attr('src');
        return (src || '').trim();
      }
      function readX(){ var $x=fieldSel('fld_tmw_banner_x','banner_offset_x'); return parseFloat($x.find('input').val()||0); }
      function readY(){ var $y=fieldSel('fld_tmw_banner_y','banner_offset_y'); return parseFloat($y.find('input').val()||0); }

      function apply(url){
        var x = readX(), y = readY(), h = readHeight();
        var posX = Math.max(0, Math.min(100, 50 + x));
        var yPx = Math.max(-1000, Math.min(1000, y));
      $frame = $('#tmw-banner-preview .tmw-banner-frame');
      $frame.attr('data-banner-height', h);
      var $img = $frame.find('img.tmw-banner-preview-img');
      $('#tmw-banner-preview .ph').text('Banner preview (1039×'+h+')');
      if(url){
        var safeUrl = String(url).replace(/"/g, '\\"');
        if ($img.length){
          $img.attr('src', safeUrl).attr('aria-hidden', 'false').show();
        }
        if (typeof window.tmwUpdateBannerOffset === 'function' && $frame.length){
          window.tmwUpdateBannerOffset($frame[0], yPx, posX);
        }
        $ph.hide();
      }else{
        if ($img.length){
          $img.removeAttr('src').attr('aria-hidden', 'true').hide();
        }
        if (typeof window.tmwUpdateBannerOffset === 'function' && $frame.length){
          window.tmwUpdateBannerOffset($frame[0], 0);
        }
        $ph.show();
      }
      }

      function refresh(){
        var s = readSrc();
        if(s === 'upload'){ apply(readUpload()); }
        else if(s === 'url'){ apply(readURL()); }
        else {
          // from feed
          $.get(ajaxurl, {action:'tmw_preview_banner', term_id: ($('#tag_ID').val()||0)})
           .done(function(r){ apply(r && r.success && r.data ? r.data.url : ''); })
           .fail(function(){ apply(''); });
        }
      }

      // Initial draw
      refresh();

      // Live updates
      $(document).on('input change keyup blur',
        '.acf-field[data-key="fld_tmw_banner_source"] input,'+
        '.acf-field[data-key="fld_tmw_banner_height"] input,'+
        '.acf-field[data-key="fld_tmw_banner_url"] input,'+
        '.acf-field[data-key="fld_tmw_banner_upload"] input,'+
        '.acf-field[data-key="fld_tmw_banner_x"] input,'+
        '.acf-field[data-key="fld_tmw_banner_y"] input', refresh);

      // After picking image
      $(document).on('click', '.acf-field[data-key="fld_tmw_banner_upload"] .acf-button, .media-modal .media-button-select', function(){
        setTimeout(refresh, 400);
      });
    });
    </script>
    <?php
  });
}, 11);

/* 4) Banner helpers */
if (!function_exists('tmw_pick_banner_from_feed_row')) {
  function tmw_pick_banner_from_feed_row($row) {
    if (!is_array($row)) return '';
    $urls = [];
    $walk = function($v) use (&$walk,&$urls){
      if (is_string($v) && preg_match('~https?://[^\s"]+\.(?:jpe?g|png|webp)(?:\?[^\s"]*)?$~i',$v)) $urls[]=$v;
      elseif (is_array($v)) foreach($v as $vv) $walk($vv);
    };
    $walk($row);
    $urls = array_values(array_unique($urls));
    if (!$urls) return '';
    $score = function($u){
      $s = 0;
      if (strpos($u,'800x600')!==false || strpos($u,'896x504')!==false) $s += 6;   // landscape
      if (strpos($u,'600x800')!==false || strpos($u,'504x896')!==false) $s -= 4;   // portrait
      if (preg_match('~(\d{3,4})x(\d{3,4})~',$u,$m)) $s += max((int)$m[1],(int)$m[2])/1200;
      return $s;
    };
    usort($urls, function($a,$b) use($score){ return $score($b) <=> $score($a); });
    return $urls[0];
  }
}
add_action('wp_ajax_tmw_preview_banner', function(){
  if (!current_user_can('manage_categories')) wp_send_json_error('forbidden', 403);
  $term_id = (int)($_GET['term_id'] ?? 0);
  if (!$term_id) wp_send_json_error('no term', 400);
  $url = tmw_resolve_model_banner_url(0, $term_id);
  wp_send_json_success(['url'=>$url]);
});

/* ======================================================================
 * MODEL PAGE – VIRTUAL TEMPLATE (NO HERO)
 * ====================================================================== */
add_action('widgets_init', function () {
  register_sidebar([
    'name'          => __('Model Sidebar', 'retrotube-child'),
    'id'            => 'model-sidebar',
    'description'   => __('Widgets on single model pages', 'retrotube-child'),
    'before_widget' => '<section class="widget %2$s">',
    'after_widget'  => '</section>',
    'before_title'  => '<h2 class="widget-title">',
    'after_title'   => '</h2>',
  ]);
});

add_action('template_redirect', function(){
  if (!is_tax('models')) return;

  $term    = get_queried_object();
  $term_id = isset($term->term_id) ? (int)$term->term_id : 0;
  $acf_id  = 'models_' . $term_id;

  // Data
  $bio         = function_exists('get_field') ? (get_field('bio', $acf_id) ?: '') : '';
  $read_lines  = function_exists('get_field') ? (int) (get_field('readmore_lines', $acf_id) ?: 20) : 20;
  $featured_sc = function_exists('get_field') ? (get_field('featured_models_shortcode', $acf_id) ?: '[tmw_featured_models count="4"]') : '[tmw_featured_models count="4"]';

  $banner_src  = function_exists('tmw_resolve_model_banner_url') ? tmw_resolve_model_banner_url(0, $term_id) : '';
  $bx          = function_exists('get_field') ? (float)(get_field('banner_offset_x', $acf_id) ?: 0) : 0;
  $by          = function_exists('get_field') ? (float)(get_field('banner_offset_y', $acf_id) ?: 0) : 0;
  $banner_h    = 350;

  $pos_x = max(0, min(100, 50 + $bx));
  $offset_y = (int) round($by);
  if ($offset_y < -1000) {
    $offset_y = -1000;
  }
  if ($offset_y > 1000) {
    $offset_y = 1000;
  }
  get_header();
  ?>
  <div class="tmw-model-page">
    <style>
      .page-header, .entry-header, .single__header, .post__header, .rt-top-banner, .hero, .tmw-model-hero { display:none !important; }

      .tmw-model-grid{display:grid;grid-template-columns:1fr;gap:24px}
      @media(min-width: 992px){ .tmw-model-grid{grid-template-columns:2fr 1fr} }
      .tmw-model-main{min-width:0}

      .tmw-model-banner{
        margin:10px 0 20px;
        border-radius:12px;
      }
      .tmw-model-banner .tmw-model-banner-frame{
        border-radius:12px;
      }
      .tmw-model-title{margin:10px 0 12px}
      .tmw-bio.js-clamp{display:-webkit-box;-webkit-box-orient:vertical;overflow:hidden;-webkit-line-clamp:<?php echo (int)$read_lines; ?>}
      .tmw-bio-toggle{margin-top:.5rem}
      .tmw-featured-flipboxes{margin-top:24px}
    </style>

    <div class="container tmw-model-grid">
      <main class="tmw-model-main">
        <?php if ($banner_src): ?>
          <?php
          $banner_style = function_exists('tmw_get_banner_style') ? tmw_get_banner_style($offset_y, $banner_h, [
            'term_id'        => $term_id,
            'image_url'      => $banner_src,
            'render_context' => 'frontend',
          ]) : '';
          ?>
          <div class="tmw-model-banner">
            <div class="tmw-model-banner-frame tmw-banner-frame" style="<?php echo esc_attr($banner_style); ?>">
              <img src="<?php echo esc_url($banner_src); ?>" alt="<?php echo esc_attr($term->name); ?>" />
            </div>
          </div>
        <?php endif; ?>

        <h1 class="tmw-model-title"><?php echo esc_html($term->name); ?></h1>

        <div class="tmw-bio-wrap">
          <div id="tmw-bio" class="tmw-bio js-clamp">
            <?php
              if ($bio) echo wpautop($bio);
              else echo '<p>'.esc_html__('No biography provided yet.','retrotube-child').'</p>';
            ?>
          </div>
          <?php if ($bio): ?>
            <p class="tmw-bio-toggle">
              <a class="morelink" href="#" aria-controls="tmw-bio" aria-expanded="false">
                <?php esc_html_e('Read more','retrotube-child'); ?> <i class="fa fa-chevron-down"></i>
              </a>
            </p>
          <?php endif; ?>
        </div>

        <section class="tmw-featured-flipboxes">
          <?php echo do_shortcode($featured_sc); ?>
        </section>
      </main>

      <aside class="tmw-model-sidebar">
        <?php
          if (is_active_sidebar('model-sidebar')) { dynamic_sidebar('model-sidebar'); }
          else { get_sidebar(); }
        ?>
      </aside>
    </div>
  </div>

  <script>
  (function(){
    var bio = document.getElementById('tmw-bio');
    var wrap = document.querySelector('.tmw-bio-toggle');
    if (!bio || !wrap) return;

    var clone = bio.cloneNode(true);
    clone.style.visibility='hidden'; clone.style.position='absolute';
    clone.style.webkitLineClamp='unset'; clone.classList.remove('js-clamp');
    document.body.appendChild(clone);
    var needs = clone.scrollHeight > bio.clientHeight + 5;
    document.body.removeChild(clone);
    if (!needs) { wrap.style.display='none'; return; }

    var link = wrap.querySelector('a.morelink');
    link.addEventListener('click', function(e){
      e.preventDefault();
      var expanded = link.getAttribute('aria-expanded') === 'true';
      if (expanded) {
        bio.classList.add('js-clamp');
        link.setAttribute('aria-expanded','false');
        link.innerHTML = 'Read more <i class="fa fa-chevron-down"></i>';
      } else {
        bio.classList.remove('js-clamp');
        link.setAttribute('aria-expanded','true');
        link.innerHTML = 'Close <i class="fa fa-chevron-up"></i>';
      }
    });
  })();
  </script>
  <?php
  get_footer();
  exit;
});

/* ======================================================================
 * CONTENT CLEANUPS (remove video players from post content area)
 * ====================================================================== */
if (!function_exists('tmw_strip_video_in_content_active')) {
  function tmw_strip_video_in_content_active() {
    if (is_admin()) return false;
    if (!is_singular()) return false;
    $pt = get_post_type();
    $video_types = ['post','video','videos','wpsc-video','wp-script-video','wpws_video'];
    return in_array($pt, $video_types, true);
  }
}
add_filter('pre_do_shortcode_tag', function($return, $tag){
  if (!tmw_strip_video_in_content_active()) return $return;
  $video_tags = ['video','playlist','audio','embed','wpvideo','wp_playlist','youtube','vimeo','dailymotion','jwplayer','videojs','fvplayer','plyr','wpsc_video','wps_video','wpws_video','flowplayer','jetpack_video'];
  return in_array(strtolower($tag), $video_tags, true) ? '' : $return;
}, 10, 2);
add_filter('embed_oembed_html', function($html){ return tmw_strip_video_in_content_active() ? '' : $html; }, 10);
add_filter('oembed_dataparse',  function($r){    return tmw_strip_video_in_content_active() ? '' : $r;    }, 10);
add_filter('render_block', function($block_content, $block){
  if (!tmw_strip_video_in_content_active()) return $block_content;
  $name = isset($block['blockName']) ? $block['blockName'] : '';
  if ($name === 'core/video' || $name === 'core/embed' || strpos($name, 'core-embed/') === 0) return '';
  return $block_content;
}, 9, 2);
add_filter('the_content', function ($content) {
  if (!tmw_strip_video_in_content_active()) return $content;
  $patterns = [
    '#<iframe\\b[^>]*>.*?</iframe>#is',
    '#<video\\b[^>]*>.*?</video>#is',
    '#<audio\\b[^>]*>.*?</audio>#is',
    '#<object\\b[^>]*>.*?</object>#is',
    '#<embed\\b[^>]*>.*?</embed>#is',
    '#<figure[^>]*class="[^"]*(wp-block-embed|wp-block-video)[^"]*"[^>]*>.*?</figure>#is',
    '#<div[^>]*class="[^"]*(wp-block-embed|video-js|jwplayer|plyr|flowplayer|responsive-embed|embed-container)[^"]*"[^>]*>.*?</div>#is',
  ];
  foreach ($patterns as $rx) $content = preg_replace($rx, '', $content);
  $content = preg_replace('/\[[^\]]*?video[^\]]*\](?:.*?\[\/[^^\]]*?video[^\]]*\])?/is', '', $content);
  $content = preg_replace('/<p>\s*<\/p>/i', '', $content);
  return $content;
}, 99);
add_filter('the_content', function ($content) {
  if (!function_exists('tmw_strip_video_in_content_active') || !tmw_strip_video_in_content_active()) return $content;
  $content = preg_replace('#<div[^>]*class=(["\']).*?\bplayer\b.*?\1[^>]*>[\s\S]*?</div>#i', '', $content);
  $content = preg_replace('#<div[^>]*style=(["\']).*?aspect-ratio.*?\1[^>]*>\s*(?:<!--.*?-->\s*)*</div>#is', '', $content);
  return $content;
}, 98);
add_filter('body_class', function ($classes) {
  if (!function_exists('tmw_strip_video_in_content_active') || !tmw_strip_video_in_content_active()) return $classes;
  $post = get_queried_object();
  if ($post && is_object($post)) {
    if (stripos((string)$post->post_content, 'class="player"') !== false) $classes[] = 'tmw-no-embed';
  }
  return $classes;
}, 11);

/* ======================================================================
 * UTIL
 * ====================================================================== */
if (!function_exists('tmw_count_terms')) {
  function tmw_count_terms($taxonomy, $hide_empty=false){
    if (function_exists('wp_count_terms')) {
      $count = wp_count_terms(['taxonomy'=>$taxonomy,'hide_empty'=>$hide_empty]);
      if (!is_wp_error($count)) return (int)$count;
    }
    $ids = get_terms(['taxonomy'=>$taxonomy, 'fields'=>'ids', 'hide_empty'=>$hide_empty ]);
    return is_wp_error($ids) ? 0 : count($ids);
  }
}

/* ======================================================================
 * MODELS CUSTOM POST TYPE
 * ====================================================================== */
add_action('init', function () {
  $labels = [
    'name'                  => esc_html__('Models', 'retrotube-child'),
    'singular_name'         => esc_html__('Model', 'retrotube-child'),
    'menu_name'             => esc_html__('Models', 'retrotube-child'),
    'name_admin_bar'        => __('Model', 'retrotube-child'),
    'add_new'               => __('Add New', 'retrotube-child'),
    'add_new_item'          => __('Add New Model', 'retrotube-child'),
    'new_item'              => __('New Model', 'retrotube-child'),
    'edit_item'             => __('Edit Model', 'retrotube-child'),
    'view_item'             => __('View Model', 'retrotube-child'),
    'all_items'             => __('All Models', 'retrotube-child'),
    'search_items'          => __('Search Models', 'retrotube-child'),
    'parent_item_colon'     => __('Parent Models:', 'retrotube-child'),
    'not_found'             => __('No models found.', 'retrotube-child'),
    'not_found_in_trash'    => __('No models found in Trash.', 'retrotube-child'),
    'items_list'            => __('Models list', 'retrotube-child'),
    'items_list_navigation' => __('Models list navigation', 'retrotube-child'),
  ];

  $args = [
    'labels'             => $labels,
    'public'             => true,
    'publicly_queryable' => true,
    'show_ui'            => true,
    'show_in_menu'       => true,
    'show_in_rest'       => true,
    'has_archive'        => 'models',
    'rewrite'            => ['slug' => 'model', 'with_front' => false],
    'hierarchical'       => false,
    'supports'           => ['title', 'editor', 'thumbnail', 'comments'],
    'taxonomies'         => ['category', 'post_tag'],
    'menu_icon'          => 'dashicons-groups',
    'capability_type'    => 'post',
    'map_meta_cap'       => true,
  ];

  register_post_type('model', $args);
}, 5);

add_filter('rank_math/post_types', function ($post_types) {
  if (!is_array($post_types)) return $post_types;
  if (!in_array('model', $post_types, true)) {
    $post_types[] = 'model';
  }
  return $post_types;
});

/* ======================================================================
 * BREADCRUMBS (Rank Math)
 * ====================================================================== */
add_filter('rank_math/frontend/breadcrumb/items', function ($crumbs) {
  if (!function_exists('rank_math_the_breadcrumbs')) {
    return $crumbs;
  }

  $models_url = home_url('/models/');

  if (is_post_type_archive('model') || is_post_type_archive('model_bio')) {
    $crumbs = [
      ['label' => 'Home', 'url' => home_url('/')],
      ['label' => 'Models', 'url' => $models_url],
    ];
  } elseif (is_singular('model') || is_singular('model_bio')) {
    $crumbs = [
      ['label' => 'Home', 'url' => home_url('/')],
      ['label' => 'Models', 'url' => $models_url],
      ['label' => get_the_title(), 'url' => ''],
    ];
  }

  foreach ($crumbs as $key => $crumb) {
    if (!is_array($crumb) || !isset($crumb['label'])) {
      continue;
    }

    $label = strtolower(trim((string) $crumb['label']));
    if ($label === 'model' || $label === 'model bio') {
      $crumbs[$key]['label'] = 'Models';
      $crumbs[$key]['title'] = 'Models';
      $crumbs[$key]['url']   = $models_url;

      return $crumbs;
    }
  }

  return $crumbs;
});

/* ======================================================================
 * MODELS TAXONOMY (new internal slug) + redirects from old /actor/*
 * - Public URLs: /model/{term}/
 * - Keeps old /actor/* and /actors/* working with 301 to the new URL.
 * - Flushes permalinks once.
 * ====================================================================== */
if (!defined('TMW_TAX_SLUG')) define('TMW_TAX_SLUG', 'models');   // taxonomy key
if (!defined('TMW_URL_SLUG')) define('TMW_URL_SLUG', 'model-tag'); // public URL base (no CPT collision)

add_action('init', function () {
  $labels = [
    'name'                       => 'Models',
    'singular_name'              => 'Model',
    'menu_name'                  => 'Models',
    'all_items'                  => 'All Models',
    'edit_item'                  => 'Edit Model',
    'view_item'                  => 'View Model',
    'update_item'                => 'Update Model',
    'add_new_item'               => 'Add New Model',
    'new_item_name'              => 'New Model Name',
    'parent_item'                => 'Parent Model',
    'search_items'               => 'Search Models',
    'popular_items'              => 'Popular Models',
    'separate_items_with_commas' => 'Separate models with commas',
    'add_or_remove_items'        => 'Add or remove models',
    'choose_from_most_used'      => 'Choose from the most used models',
    'not_found'                  => 'No models found',
    'back_to_items'              => '← Back to Models',
  ];

  register_taxonomy(TMW_TAX_SLUG, ['model'], [
    'labels'            => $labels,
    'public'            => true,
    'show_ui'           => true,
    'show_admin_column' => true,
    'hierarchical'      => false,
    'query_var'         => TMW_TAX_SLUG,
    'rewrite'           => ['slug' => TMW_URL_SLUG, 'with_front' => false, 'hierarchical' => false],
    'show_in_rest'      => true,
    'rest_base'         => TMW_TAX_SLUG,
  ]);
}, 5);

add_action('init', function () {
  add_rewrite_rule('^actor/([^/]+)/?$',  'index.php?' . TMW_TAX_SLUG . '=$matches[1]', 'top');
  add_rewrite_rule('^actors/([^/]+)/?$', 'index.php?' . TMW_TAX_SLUG . '=$matches[1]', 'top');

  if (!get_option('tmw_models_flush_v3')) {
    flush_rewrite_rules(false);
    update_option('tmw_models_flush_v3', 1);
  }
}, 20);

// --- Force all single model pages to use single-model.php ---
add_filter('template_include', function($template) {
  if (is_singular('model')) {
    $child_template = get_stylesheet_directory() . '/single-model.php';
    if (file_exists($child_template)) {
      return $child_template;
    }
  }
  return $template;
}, 999);

add_action('template_redirect', function () {
  if (is_tax(TMW_TAX_SLUG)) {
    $term = get_queried_object();
    if (!is_wp_error($term) && !empty($term->slug)) {
      $maybe = get_page_by_path($term->slug, OBJECT, 'model');
      if ($maybe) {
        $to = get_permalink($maybe);
        tmw_debug_log('[ModelFix] Redirecting taxonomy term to CPT: ' . $term->slug . ' → ' . $to);
        wp_redirect($to, 301);
        exit;
      }
    }
  }
});

add_action('after_switch_theme', function () {
  flush_rewrite_rules();
  tmw_debug_log('[ModelFix] Flushed rewrite rules after theme switch.');
});

// 1) Always show a Model/Models line on single video pages.
// Prefers your 'models' taxonomy; falls back to legacy 'actors'.
add_filter('the_content', function ($content) {
  if ( ! is_singular(['post','video']) || ! in_the_loop() || ! is_main_query() ) return $content;

  // If the theme already prints its own block, do nothing.
  if (strpos($content, 'id="video-actors"') !== false ||
      strpos($content, 'id="video-models"') !== false) {
    return $content;
  }

  $post_id = get_the_ID();
  $terms = get_the_terms($post_id, 'models');
  if (empty($terms) || is_wp_error($terms)) {
    $terms = get_the_terms($post_id, 'actors');
  }
  if (empty($terms) || is_wp_error($terms)) return $content;

  $links = [];
  foreach ($terms as $t) {
    $model_link = tmw_get_model_link_for_term($t);
    if (!$model_link) {
      $fallback = get_term_link($t);
      $model_link = is_wp_error($fallback) ? '' : $fallback;
    }
    if ($model_link) {
      $links[] = sprintf('<a href="%s">%s</a>', esc_url($model_link), esc_html($t->name));
    }
  }

  // singular when one model, plural otherwise
  $label = (count($terms) === 1) ? 'Model' : 'Models';

  $block = '<div id="video-models"><i class="fa fa-star"></i> ' .
           $label . ': ' . implode(', ', $links) . '</div>';

  // Place it right under the date/meta if present; otherwise prepend.
  if (preg_match('~(<div[^>]+id="video-date"[^>]*>.*?</div>)~is', $content)) {
    $content = preg_replace('~(<div[^>]+id="video-date"[^>]*>.*?</div>)~is', '$1' . $block, $content, 1);
  } else {
    $content = $block . $content;
  }
  return $content;
}, 45);

// 2) Keep old ‘actors’ and new ‘models’ in sync on save.
add_action('save_post', function ($post_id) {
  if (wp_is_post_revision($post_id)) return;
  $pt = get_post_type($post_id);
  if ($pt !== 'post' && $pt !== 'video') return;

  if (!taxonomy_exists('models') || !is_object_in_taxonomy($pt, 'models')) {
    return;
  }

  $actors = wp_get_post_terms($post_id, 'actors', ['fields' => 'ids']);
  $models = wp_get_post_terms($post_id, 'models', ['fields' => 'ids']);

  // If plugin set actors, mirror to models
  if (!empty($actors) && !is_wp_error($actors)) {
    wp_set_post_terms($post_id, $actors, 'models', false);
  }
  // If you ever tag only in models, mirror to actors too
  if (!empty($models) && !is_wp_error($models)) {
    wp_set_post_terms($post_id, $models, 'actors', false);
  }
}, 20);
