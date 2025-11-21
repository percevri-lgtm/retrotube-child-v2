<?php

/* ======================================================================
 * FEATURED / FLIPBOX SHORTCODES (taxonomy=models)
 * ====================================================================== */

/* Safe image picker (AW → ACF → term thumb → placeholder) + portrait fix */
if (!function_exists('tmw_pick_images_for_term')) {
  function tmw_pick_images_for_term(int $term_id): array {
    $front = ''; $back = '';

    if (function_exists('tmw_aw_card_data')) {
      $cd = tmw_aw_card_data($term_id);
      $front = isset($cd['front']) ? (string)$cd['front'] : '';
      $back  = isset($cd['back'])  ? (string)$cd['back']  : '';
    }

    // ACF overrides (taxonomy: models)
    if ((!$front || !$back) && function_exists('get_field')) {
      $acf_id    = 'models_' . $term_id;
      $acf_front = get_field('actor_card_front', $acf_id); // keep names
      $acf_back  = get_field('actor_card_back',  $acf_id);
      if (!$front && is_array($acf_front) && !empty($acf_front['url'])) $front = esc_url_raw($acf_front['url']);
      if (!$back  && is_array($acf_back)  && !empty($acf_back['url']))  $back  = esc_url_raw($acf_back['url']);
    }

    if ((!$front || !$back)) {
      $thumb_id = (int) get_term_meta($term_id, 'thumbnail_id', true);
      if ($thumb_id) {
        $thumb_url = wp_get_attachment_image_url($thumb_id, 'large');
        if (!$front && $thumb_url) $front = $thumb_url;
        if (!$back  && $thumb_url) $back  = $thumb_url;
      }
    }

    if ($front && function_exists('tmw_is_portrait') && !tmw_is_portrait($front) && function_exists('tmw_try_portrait_variant')) {
      $try = tmw_try_portrait_variant($front); if ($try) $front = $try;
    }
    if ($back  && function_exists('tmw_is_portrait') && !tmw_is_portrait($back)  && function_exists('tmw_try_portrait_variant')) {
      $try = tmw_try_portrait_variant($back);  if ($try) $back  = $try;
    }

    if (!$front) $front = function_exists('tmw_placeholder_image_url') ? tmw_placeholder_image_url() : '';
    if (!$back)  $back  = $front;

    return [$front, $back];
  }
}

if (!function_exists('tmw_child_flipbox_front_image_markup')) {
  function tmw_child_flipbox_front_image_markup(string $front_url, string $name, bool $is_lcp): string {
    if ($front_url === '') {
      return '';
    }

    $attachment_id = function_exists('attachment_url_to_postid') ? attachment_url_to_postid($front_url) : 0;
    $sizes_attr = '(max-width: 600px) 100vw, 369px';

    if ($is_lcp) {
      $dims = function_exists('tmw_child_image_dimensions')
        ? tmw_child_image_dimensions($front_url)
        : ['width' => 364, 'height' => 546];

      if (function_exists('tmw_child_front_page_lcp_image')) {
        $lcp_image = tmw_child_front_page_lcp_image();
        if (!empty($lcp_image['attachment_id'])) {
          $attachment_id = (int) $lcp_image['attachment_id'];
        }
        if (!empty($lcp_image['url'])) {
          $front_url = $lcp_image['url'];
        }
        if (!empty($lcp_image['width'])) {
          $dims['width'] = (int) $lcp_image['width'];
        }
        if (!empty($lcp_image['height'])) {
          $dims['height'] = (int) $lcp_image['height'];
        }
      }

      $width  = isset($dims['width']) ? (int) $dims['width'] : 364;
      $height = isset($dims['height']) ? (int) $dims['height'] : 546;

      $attrs = [
        'class'         => 'tmw-lcp-image',
        'alt'           => $name,
        'loading'       => 'eager',
        'fetchpriority' => 'high',
        'decoding'      => 'async',
        'sizes'         => $sizes_attr,
        'width'         => $width,
        'height'        => $height,
      ];

      if ($attachment_id) {
        $image = wp_get_attachment_image($attachment_id, 'tmw-front-optimized', false, $attrs);
        if ($image) {
          return $image;
        }
      }

      return sprintf(
        '<img class="tmw-lcp-image" src="%s" alt="%s" width="%d" height="%d" loading="eager" fetchpriority="high" decoding="async" sizes="%s" />',
        esc_url($front_url),
        esc_attr($name),
        $width,
        $height,
        esc_attr($sizes_attr)
      );
    }

    $lazy_dims = function_exists('tmw_child_image_dimensions')
      ? tmw_child_image_dimensions($front_url)
      : ['width' => 364, 'height' => 546];
    $lazy_srcset = $attachment_id ? wp_get_attachment_image_srcset($attachment_id, 'tmw-front-optimized') : '';

    $attrs = [
      'class'         => 'tmw-sr-only',
      'alt'           => $name,
      'loading'       => 'lazy',
      'fetchpriority' => 'low',
      'decoding'      => 'async',
      'sizes'         => $sizes_attr,
      'width'         => isset($lazy_dims['width']) ? (int) $lazy_dims['width'] : 364,
      'height'        => isset($lazy_dims['height']) ? (int) $lazy_dims['height'] : 546,
    ];

    if ($lazy_srcset) {
      $attrs['srcset'] = $lazy_srcset;
    }

    if ($attachment_id) {
      $image = wp_get_attachment_image($attachment_id, 'tmw-front-optimized', false, $attrs);
      if ($image) {
        return $image;
      }
    }

    return sprintf(
      '<img class="tmw-sr-only" src="%s"%s alt="%s" width="%d" height="%d" loading="lazy" fetchpriority="low" decoding="async" sizes="%s" />',
      esc_url($front_url),
      $lazy_srcset ? ' srcset="' . esc_attr($lazy_srcset) . '"' : '',
      esc_attr($name),
      isset($lazy_dims['width']) ? (int) $lazy_dims['width'] : 364,
      isset($lazy_dims['height']) ? (int) $lazy_dims['height'] : 546,
      esc_attr($sizes_attr)
    );
  }
}

if (!function_exists('tmw_get_model_post_for_term')) {
  function tmw_get_model_post_for_term($term) {
    if (is_numeric($term)) {
      $term = get_term((int)$term, 'models');
    }
    if (!$term || is_wp_error($term)) return null;

    $stored_id = (int) get_term_meta($term->term_id, 'tmw_model_post_id', true);
    if ($stored_id) {
      $stored_post = get_post($stored_id);
      if ($stored_post && $stored_post->post_type === 'model' && $stored_post->post_status !== 'trash') {
        return $stored_post;
      }
    }

    if (!post_type_exists('model')) {
      return null;
    }

    $post = get_page_by_path($term->slug, OBJECT, 'model');
    if (!$post) {
      $post = get_page_by_title($term->name, OBJECT, 'model');
    }

    if (!$post || is_wp_error($post)) {
      $legacy = get_page_by_path($term->slug, OBJECT, 'model_bio');
      if (!$legacy) {
        $legacy = get_page_by_title($term->name, OBJECT, 'model_bio');
      }
      if ($legacy && !is_wp_error($legacy)) {
        $converted = wp_update_post([
          'ID'        => $legacy->ID,
          'post_type' => 'model',
        ], true);
        if (!is_wp_error($converted)) {
          $post = get_post($legacy->ID);
        }
      }
    }

    if ($post && !is_wp_error($post) && $post->post_type === 'model' && $post->post_status !== 'trash') {
      update_term_meta($term->term_id, 'tmw_model_post_id', (int) $post->ID);
      return $post;
    }

    if ($stored_id) {
      delete_term_meta($term->term_id, 'tmw_model_post_id');
    }

    return null;
  }
}

if (!function_exists('tmw_get_model_link_for_term')) {
  function tmw_get_model_link_for_term($term): string {
    if (is_numeric($term)) {
      $term = get_term((int)$term, 'models');
    }
    if (!$term || is_wp_error($term)) return '';

    $post = tmw_get_model_post_for_term($term);
    if ($post) {
      $url = get_permalink($post);
      if ($url) return $url;
    }

    $link = get_term_link($term);
    return is_wp_error($link) ? '' : (string) $link;
  }
}

/* Renderer used everywhere for one flipbox card */
if (!function_exists('tmw_render_flipbox_card')) {
  function tmw_render_flipbox_card($term): string {
    if (is_numeric($term)) { $term = get_term((int)$term, 'models'); }
    if (!$term || is_wp_error($term)) return '';

    list($front_url, $back_url) = tmw_pick_images_for_term((int)$term->term_id);

    $ov         = function_exists('tmw_tools_overrides_for_term') ? tmw_tools_overrides_for_term((int)$term->term_id) : ['front_url'=>'','back_url'=>'','css_front'=>'','css_back'=>''];
    $front_url  = ($ov['front_url'] ?: $front_url) ?: (function_exists('tmw_placeholder_image_url') ? tmw_placeholder_image_url() : '');
    $back_url   = ($ov['back_url']  ?: $back_url)  ?: $front_url;

    $front_style = (function_exists('tmw_bg_style') ? tmw_bg_style($front_url) : 'background-image:url('.esc_url($front_url).');') . ($ov['css_front'] ?? '');
    $back_style  = (function_exists('tmw_bg_style') ? tmw_bg_style($back_url)  : 'background-image:url('.esc_url($back_url ).');') . ($ov['css_back']  ?? '');

    $base_link = tmw_get_model_link_for_term($term);
    $link      = apply_filters('tmw_model_flipbox_link', $base_link, $term);
    $cta_link  = $link ?: $base_link;
    $name      = $term->name;

    $card_attrs = [];
    if (function_exists('tmw_flipbox_a11y_attrs')) {
        $card_attrs = tmw_flipbox_a11y_attrs([
            'aria_label' => $name,
            'tabindex'   => 0,
        ]);
    }

    if ($cta_link) {
        $card_attrs[] = 'data-href="' . esc_url($cta_link) . '"';
    }

    $card_attr_html = '';
    if (!empty($card_attrs)) {
        $card_attr_html = ' ' . implode(' ', array_map('trim', $card_attrs));
    }

    $sr_label = function_exists('tmw_sr_text')
        ? tmw_sr_text(sprintf(__('Open %s profile', 'retrotube-child'), $name))
        : '';

    $use_lcp_img = function_exists('tmw_child_should_use_lcp_image') && tmw_child_should_use_lcp_image();
    $front_img   = function_exists('tmw_child_flipbox_front_image_markup')
      ? tmw_child_flipbox_front_image_markup($front_url, $name, $use_lcp_img)
      : '';

    ob_start(); ?>
    <div class="tmw-flip"<?php echo $card_attr_html; ?>>
      <div class="tmw-flip-inner">
        <div class="tmw-flip-front" style="<?php echo esc_attr($front_style); ?>">
          <?php echo $front_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
          <span class="tmw-name"><?php echo esc_html($name); ?></span>
        </div>
        <div class="tmw-flip-back" style="<?php echo esc_attr($back_style); ?>">
          <?php if ($cta_link) : ?>
            <a href="<?php echo esc_url($cta_link); ?>" data-href="<?php echo esc_url($cta_link); ?>" class="tmw-view" style="display:inline-block; text-decoration:none; color:inherit;"><?php echo $sr_label; ?>View profile &raquo;&raquo;&raquo;</a>
          <?php else : ?>
            <span class="tmw-view"><?php echo $sr_label; ?>View profile &raquo;&raquo;&raquo;</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php
    return (string) ob_get_clean();
  }
}

/* Featured settings/picker/shortcode */
if (!function_exists('tmw_featured_settings')) {
  function tmw_featured_settings(): array {
    $a = get_option('tmw_mf_featured', []);
    $b = get_option('tmw_mf_settings', []);
    $s = is_array($a) ? $a : [];
    if (is_array($b)) $s = array_merge($s, $b);

    return [
      'source'    => isset($s['random_source']) ? (string)$s['random_source'] : 'all',
      'pool'      => isset($s['curated_pool']) ? (array)$s['curated_pool'] : (isset($s['pool']) ? (array)$s['pool'] : []),
      'show_name' => isset($s['show_names']) ? (bool)$s['show_names'] : true,
      'show_cta'  => isset($s['show_cta'])   ? (bool)$s['show_cta']   : true,
      'cta_text'  => isset($s['cta_text'])   ? (string)$s['cta_text'] : 'View profile »»»',
      'target'    => isset($s['target'])     ? (string)$s['target']    : '_self',
    ];
  }
}
if (!function_exists('tmw_featured_pick_terms')) {
  function tmw_featured_pick_terms(string $mode, int $count, array $settings): array {
    $count = max(1, $count);

    if ($mode === 'pool') {
      $ids = array_values(array_unique(array_map('intval', $settings['pool'] ?? [])));
      if ($ids) {
        shuffle($ids);
        $ids = array_slice($ids, 0, $count);
        $terms = get_terms(['taxonomy'=>'models','hide_empty'=>false,'include'=>$ids]);
        return is_wp_error($terms) ? [] : $terms;
      }
      $mode = 'all';
    }

    $all_ids = get_terms(['taxonomy'=>'models','hide_empty'=>false,'fields'=>'ids']);
    if (is_wp_error($all_ids) || !$all_ids) return [];
    shuffle($all_ids);
    $pick = array_slice($all_ids, 0, $count);
    $terms = get_terms(['taxonomy'=>'models','hide_empty'=>false,'include'=>$pick]);
    return is_wp_error($terms) ? [] : $terms;
  }
}
if (!function_exists('tmw_featured_models_shortcode')) {
  function tmw_featured_models_shortcode($atts = []): string {
    if (!wp_style_is('retrotube-child-style', 'enqueued')) {
      wp_enqueue_style(
        'retrotube-child-style',
        get_stylesheet_uri(),
        [],
        tmw_child_style_version()
      );
    }

    if (!wp_style_is('rt-child-flip', 'enqueued')) {
      if (!wp_style_is('rt-child-flip', 'registered')) {
        $flipboxes_path = get_stylesheet_directory() . '/assets/flipboxes.css';
        $flipboxes_ver  = file_exists($flipboxes_path) ? filemtime($flipboxes_path) : tmw_child_style_version();

        wp_register_style(
          'rt-child-flip',
          get_stylesheet_directory_uri() . '/assets/flipboxes.css',
          ['retrotube-child-style'],
          $flipboxes_ver
        );
      }

      wp_enqueue_style('rt-child-flip');
    }

    if (!wp_style_is('tmw-flip-a11y', 'registered')) {
      $a11y_style_path = get_stylesheet_directory() . '/css/a11y.css';
      if (file_exists($a11y_style_path)) {
        $a11y_style_ver = filemtime($a11y_style_path) ?: tmw_child_style_version();
        wp_register_style(
          'tmw-flip-a11y',
          get_stylesheet_directory_uri() . '/css/a11y.css',
          ['retrotube-child-style'],
          $a11y_style_ver
        );
      }
    }

    if (wp_style_is('tmw-flip-a11y', 'registered')) {
      wp_enqueue_style('tmw-flip-a11y');
    }

    if (!wp_script_is('tmw-flip-a11y', 'registered')) {
      $a11y_script_path = get_stylesheet_directory() . '/js/tmw-flip-a11y.js';
      if (file_exists($a11y_script_path)) {
        $a11y_script_ver = filemtime($a11y_script_path) ?: null;
        wp_register_script('tmw-flip-a11y', get_stylesheet_directory_uri() . '/js/tmw-flip-a11y.js', [], $a11y_script_ver, true);
      }
    }

    if (wp_script_is('tmw-flip-a11y', 'registered')) {
      wp_enqueue_script('tmw-flip-a11y');
    }

    $s   = tmw_featured_settings();
    $atts = shortcode_atts([
      'count'  => 4,
      'mode'   => $s['source'] ?: 'all',
      'names'  => $s['show_name'] ? '1' : '0',
      'cta'    => $s['show_cta']  ? '1' : '0',
      'text'   => $s['cta_text'],
      'target' => $s['target'],
      'title'  => 'FEATURED MODELS',
    ], (array)$atts, 'featured_models');

    $count = (int)$atts['count'];
    $mode  = strtolower((string)$atts['mode']);
    if ($mode !== 'pool' && $mode !== 'all') $mode = 'all';

    $terms = tmw_featured_pick_terms($mode, $count, $s);
    if (!$terms) return '';

    ob_start();
    echo '<div class="tmwfm-wrap">';
    if (!empty($atts['title'])) {
      echo '<h3 class="tmwfm-heading">'.esc_html($atts['title']).'</h3>';
    }
    echo '<div class="tmwfm-grid">';
    foreach ($terms as $t) {
      echo tmw_render_flipbox_card($t);
    }
    echo '</div></div>';

    $hide_css = '';
    if (!$atts['names'] || $atts['names']==='0') $hide_css .= '.tmwfm-grid .tmw-name{display:none!important;}';
    if (!$atts['cta']   || $atts['cta']==='0')   $hide_css .= '.tmwfm-grid .tmw-view{display:none!important;}';
    if ($hide_css && function_exists('tmw_enqueue_inline_css')) {
      tmw_enqueue_inline_css($hide_css);
    }

    return ob_get_clean();
  }
}
/* Back-compat shortcode tag */
add_filter('pre_do_shortcode_tag', function($return, $tag, $attr){
  $t = strtolower($tag);
  if ($t === 'featured_models' || $t === 'tmw_featured_models') {
    return tmw_featured_models_shortcode(is_array($attr) ? $attr : []);
  }
  return $return;
}, 8, 3);

/* ======================================================================
 * MODELS GRID – shortcode [models_flipboxes]  (+ alias [actors_flipboxes])
 * ====================================================================== */
function tmw_models_flipboxes_cb($atts){
  if (wp_script_is('tmw-flip-guard', 'registered')) {
    wp_enqueue_script('tmw-flip-guard');
  }

  if (!wp_style_is('rt-child-flip', 'enqueued')) {
    if (!wp_style_is('rt-child-flip', 'registered')) {
      $flipboxes_path = get_stylesheet_directory() . '/assets/flipboxes.css';
      $flipboxes_ver  = file_exists($flipboxes_path) ? filemtime($flipboxes_path) : tmw_child_style_version();

      wp_register_style(
        'rt-child-flip',
        get_stylesheet_directory_uri() . '/assets/flipboxes.css',
        ['retrotube-child-style'],
        $flipboxes_ver
      );
    }

    wp_enqueue_style('rt-child-flip');
  }

  if (!wp_style_is('tmw-flip-a11y', 'registered')) {
    $a11y_style_path = get_stylesheet_directory() . '/css/a11y.css';
    if (file_exists($a11y_style_path)) {
      $a11y_style_ver = filemtime($a11y_style_path) ?: tmw_child_style_version();
      wp_register_style(
        'tmw-flip-a11y',
        get_stylesheet_directory_uri() . '/css/a11y.css',
        ['retrotube-child-style'],
        $a11y_style_ver
      );
    }
  }

  if (wp_style_is('tmw-flip-a11y', 'registered')) {
    wp_enqueue_style('tmw-flip-a11y');
  }

  if (!wp_script_is('tmw-flip-a11y', 'registered')) {
    $a11y_script_path = get_stylesheet_directory() . '/js/tmw-flip-a11y.js';
    if (file_exists($a11y_script_path)) {
      $a11y_script_ver = filemtime($a11y_script_path) ?: null;
      wp_register_script('tmw-flip-a11y', get_stylesheet_directory_uri() . '/js/tmw-flip-a11y.js', [], $a11y_script_ver, true);
    }
  }

  if (wp_script_is('tmw-flip-a11y', 'registered')) {
    wp_enqueue_script('tmw-flip-a11y');
  }

  $a = shortcode_atts([
    'per_page'       => 16,
    'cols'           => 4,
    'orderby'        => 'name',
    'order'          => 'ASC',
    'hide_empty'     => false,
    'banner_img'     => '',
    'banner_url'     => '',
    'banner_alt'     => 'Sponsored',
    'banner_html'    => '',
    'show_pagination'=> true,
    'page_var'       => 'pg',
    'img_source'     => 'auto',
  ], $atts);

  $paged = isset($_GET[$a['page_var']]) ? max(1, intval($_GET[$a['page_var']])) : max(1, intval(get_query_var('paged')), intval(get_query_var('page')));
  $per_page   = max(1, (int)$a['per_page']);
  $offset     = ($paged - 1) * $per_page;
  $hide_empty = filter_var($a['hide_empty'], FILTER_VALIDATE_BOOLEAN);

  $terms = get_terms([
    'taxonomy'   => 'models',
    'hide_empty' => $hide_empty,
    'orderby'    => $a['orderby'],
    'order'      => $a['order'],
    'number'     => $per_page,
    'offset'     => $offset,
  ]);
  if (is_wp_error($terms)) return '';

  $total   = (function_exists('tmw_count_terms') ? tmw_count_terms('models', $hide_empty) : 0);
  $total_p = max(1, (int)ceil($total / $per_page));

  ob_start();
  printf('<div class="tmw-grid tmw-cols-%d">', (int)$a['cols']);

  tmw_debug_log('[TMW-FLIPBOX] Sponsored slot removed between flipbox 8 and 9.');

  $schema_items = [];

  foreach ($terms as $term){
    $base_link = tmw_get_model_link_for_term($term);
    $link      = apply_filters('tmw_model_flipbox_link', $base_link, $term);
    $cta_link  = $link ?: $base_link;

    $front_url = '';
    $back_url  = '';

    if (function_exists('tmw_aw_card_data')) {
      $card = tmw_aw_card_data($term->term_id);
      if (!empty($card['front'])) $front_url = $card['front'];
      if (!empty($card['back']))  $back_url  = $card['back'];
    }

    if (($front_url === '' || $back_url === '') && function_exists('get_field')) {
      $acf_front = get_field('actor_card_front', 'models_'.$term->term_id); // keep field names
      $acf_back  = get_field('actor_card_back',  'models_'.$term->term_id);
      if ($front_url === '' && is_array($acf_front) && !empty($acf_front['url'])) $front_url = $acf_front['url'];
      if ($back_url  === '' && is_array($acf_back)  && !empty($acf_back['url']))  $back_url  = $acf_back['url'];
    }

    $ov = function_exists('tmw_tools_overrides_for_term') ? tmw_tools_overrides_for_term($term->term_id) : ['front_url'=>'','back_url'=>'','css_front'=>'','css_back'=>''];
    $front_url = ($ov['front_url'] ?: $front_url) ?: tmw_placeholder_image_url();
    $back_url  = ($ov['back_url']  ?: $back_url)  ?: $front_url;

    if (function_exists('tmw_same_image') && tmw_same_image($back_url, $front_url) && function_exists('tmw_aw_find_by_candidates')) {
      $cands = [];
      $explicit = get_term_meta($term->term_id, 'tmw_aw_nick', true);
      if (!$explicit) $explicit = get_term_meta($term->term_id, 'tm_lj_nick', true);
      if ($explicit) $cands[] = $explicit;
      $cands[] = $term->slug; $cands[] = $term->name;
      $cands[] = str_replace(['-','_',' '], '', $term->slug);
      $cands[] = str_replace(['-','_',' '], '', $term->name);
      $row = tmw_aw_find_by_candidates(array_unique(array_filter($cands)));
      if ($row && function_exists('tmw_aw_pick_images_from_row')) {
        list($_f, $_b) = tmw_aw_pick_images_from_row($row);
        if ($_b && !tmw_same_image($_b, $front_url)) $back_url = $_b;
      }
    }

    $front_style = (function_exists('tmw_bg_style') ? tmw_bg_style($front_url) : 'background-image:url('.esc_url($front_url).');') . ($ov['css_front'] ?? '');
    $back_style  = (function_exists('tmw_bg_style') ? tmw_bg_style($back_url)  : 'background-image:url('.esc_url($back_url ).');') . ($ov['css_back']  ?? '');

    $card_attrs = [];
    if (function_exists('tmw_flipbox_a11y_attrs')) {
      $card_attrs = tmw_flipbox_a11y_attrs([
        'aria_label' => $term->name,
        'tabindex'   => 0,
      ]);
    }

    if ($cta_link) {
      $card_attrs[] = 'data-href="' . esc_url($cta_link) . '"';
    }

    $card_attr_html = '';
    if (!empty($card_attrs)) {
      $card_attr_html = ' ' . implode(' ', array_map('trim', $card_attrs));
    }

    $sr_label = function_exists('tmw_sr_text')
      ? tmw_sr_text(sprintf(__('Open %s profile', 'retrotube-child'), $term->name))
      : '';

    $use_lcp_img = function_exists('tmw_child_should_use_lcp_image') && tmw_child_should_use_lcp_image();
    $front_img   = function_exists('tmw_child_flipbox_front_image_markup')
      ? tmw_child_flipbox_front_image_markup($front_url, $term->name, $use_lcp_img)
      : '';

    echo '<div class="tmw-flip"' . $card_attr_html . '>';
    echo   '<div class="tmw-flip-inner">';
    echo     '<div class="tmw-flip-front" style="' . esc_attr($front_style) . '">';
    echo       $front_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo       '<span class="tmw-name">' . esc_html($term->name) . '</span>';
    echo     '</div>';
    echo     '<div class="tmw-flip-back"  style="' . esc_attr($back_style) . '">';
    if ($cta_link) {
      echo '<a href="' . esc_url($cta_link) . '" data-href="' . esc_url($cta_link) . '" class="tmw-view" style="display:inline-block; text-decoration:none; color:inherit;">' . $sr_label . 'View profile &raquo;&raquo;&raquo;</a>';
    } else {
      echo '<span class="tmw-view">' . $sr_label . 'View profile &raquo;&raquo;&raquo;</span>';
    }
    echo     '</div>';
    echo   '</div>';
    echo '</div>';

    $schema_url = esc_url_raw($cta_link ?: $base_link);
    if ($schema_url !== '') {
      $schema_items[$schema_url] = [
        'url'  => $schema_url,
        'name' => wp_strip_all_tags($term->name),
      ];
    }
  }
  echo '</div>';

  if (filter_var($a['show_pagination'], FILTER_VALIDATE_BOOLEAN) && $total_p > 1){
    $base  = remove_query_arg($a['page_var']);
    $base  = add_query_arg($a['page_var'], '%#%', $base);
    $links = paginate_links([
      'base'      => $base,
      'format'    => '',
      'current'   => $paged,
      'total'     => $total_p,
      'type'      => 'array',
      'prev_text' => '« Prev',
      'next_text' => 'Next »',
    ]);
    if (!empty($links)) {
      echo '<nav class="tmw-pagination" aria-label="Pagination">';
      foreach ($links as $l) echo $l;
      echo '</nav>';
    }
  }

  $output = ob_get_clean();

  if (!empty($schema_items) && function_exists('tmw_schema_itemlist')) {
    ob_start();
    tmw_schema_itemlist(array_values($schema_items));
    $output .= ob_get_clean();
  }

  return $output;
}
add_shortcode('models_flipboxes', 'tmw_models_flipboxes_cb');      // new
add_shortcode('actors_flipboxes', 'tmw_models_flipboxes_cb');      // alias / back-compat

/* ======================================================================
 * FEATURED MODELS SHORTCODE HELPERS
 * ====================================================================== */
if (!function_exists('tmw_clean_featured_shortcode')) {
  function tmw_clean_featured_shortcode($value) {
    $value = is_string($value) ? trim($value) : '';
    if ($value === '') {
      return '';
    }
    if (substr($value, 0, 1) !== '[' || substr($value, -1) !== ']') {
      return '';
    }

    return $value;
  }
}

if (!function_exists('tmw_get_default_featured_shortcode')) {
  function tmw_get_default_featured_shortcode() {
    $value = get_theme_mod('tmw_featured_shortcode_default', '[tmw_featured_models]');
    $value = tmw_clean_featured_shortcode($value);
    if ($value === '') {
      $value = '[tmw_featured_models]';
    }

    return $value;
  }
}

if (!function_exists('tmw_customize_sanitize_featured_shortcode')) {
  function tmw_customize_sanitize_featured_shortcode($value) {
    $value = tmw_clean_featured_shortcode($value);

    if ($value === '') {
      return '[tmw_featured_models]';
    }

    return $value;
  }
}

add_action('customize_register', function ($wp_customize) {
  $wp_customize->add_section('tmw_featured_models_block', [
    'title'    => __('Featured Models Block', 'retrotube-child'),
    'priority' => 160,
  ]);

  $wp_customize->add_setting('tmw_featured_shortcode_default', [
    'type'              => 'theme_mod',
    'default'           => '[tmw_featured_models]',
    'sanitize_callback' => 'tmw_customize_sanitize_featured_shortcode',
    'transport'         => 'refresh',
  ]);

  $wp_customize->add_control('tmw_featured_shortcode_default', [
    'section'     => 'tmw_featured_models_block',
    'type'        => 'text',
    'label'       => __('Featured Models shortcode', 'retrotube-child'),
    'description' => __('Enter the shortcode used when no override is provided.', 'retrotube-child'),
  ]);
});

if (!function_exists('tmw_get_featured_shortcode_for_context')) {
  function tmw_get_featured_shortcode_for_context() {
    $shortcode = tmw_get_default_featured_shortcode();

    if (is_singular()) {
      $post_id = get_queried_object_id();
      if ($post_id) {
        $meta = get_post_meta($post_id, 'tmw_featured_shortcode', true);
        $meta = tmw_clean_featured_shortcode($meta);
        if ($meta !== '') {
          $shortcode = $meta;
        } else {
          $legacy = get_post_meta($post_id, 'featured_flipbox_shortcode', true);
          $legacy = tmw_clean_featured_shortcode($legacy);
          if ($legacy !== '') {
            $shortcode = $legacy;
          }
        }
      }
    } elseif (is_category() || is_tag()) {
      $term = get_queried_object();
      if ($term instanceof WP_Term && !is_wp_error($term)) {
        $meta = get_term_meta($term->term_id, 'tmw_featured_shortcode', true);
        $meta = tmw_clean_featured_shortcode($meta);
        if ($meta !== '') {
          $shortcode = $meta;
        }
      }
    } else {
      $request_path = isset($_SERVER['REQUEST_URI']) ? trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') : '';
      if ($request_path === 'categories' || $request_path === 'tags') {
        $page = get_page_by_path($request_path);
        if ($page instanceof WP_Post) {
          $meta = get_post_meta($page->ID, 'tmw_featured_shortcode', true);
          $meta = tmw_clean_featured_shortcode($meta);
          if ($meta !== '') {
            $shortcode = $meta;
          } else {
            $legacy = get_post_meta($page->ID, 'featured_flipbox_shortcode', true);
            $legacy = tmw_clean_featured_shortcode($legacy);
            if ($legacy !== '') {
              $shortcode = $legacy;
            }
          }
        }
      }
    }

    return $shortcode;
  }
}

if (!function_exists('tmw_should_output_featured_block')) {
  function tmw_should_output_featured_block() {
    if (is_front_page() || is_home()) {
      return false;
    }

    if (is_post_type_archive('model')) {
      return false;
    }

    return true;
  }
}

if (!function_exists('tmw_featured_block_markup')) {
  function tmw_featured_block_markup() {
    if (!tmw_should_output_featured_block()) {
      return '';
    }

    if (!tmw_featured_block_dedup()) {
      return '';
    }

    $shortcode = tmw_get_featured_shortcode_for_context();
    $shortcode = tmw_clean_featured_shortcode($shortcode);
    if ($shortcode === '') {
      return '';
    }

    set_query_var('tmw_featured_shortcode', $shortcode);
    ob_start();
    get_template_part('partials/featured-models-block');
    $markup = ob_get_clean();
    set_query_var('tmw_featured_shortcode', null);

    return is_string($markup) ? trim($markup) : '';
  }
}

if (!function_exists('tmw_featured_block_output_buffer_start')) {
  function tmw_featured_block_output_buffer_start() {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
      return;
    }

    if (defined('REST_REQUEST') && REST_REQUEST) {
      return;
    }

    if (is_feed() || is_embed()) {
      return;
    }

    if (!tmw_should_output_featured_block()) {
      return;
    }

    if (!empty($GLOBALS['tmw_featured_block_buffer_started'])) {
      return;
    }

    $GLOBALS['tmw_featured_block_buffer_started'] = true;
    ob_start();

    add_action('get_footer', 'tmw_featured_block_inject_into_main', 0);
    add_action('shutdown', 'tmw_featured_block_output_buffer_shutdown', 0);
  }
}

add_action('template_redirect', 'tmw_featured_block_output_buffer_start', 0);

if (!function_exists('tmw_featured_block_inject_into_main')) {
  function tmw_featured_block_inject_into_main() {
    if (empty($GLOBALS['tmw_featured_block_buffer_started'])) {
      return;
    }

    $buffer = ob_get_clean();
    $GLOBALS['tmw_featured_block_buffer_started'] = false;
    remove_action('shutdown', 'tmw_featured_block_output_buffer_shutdown', 0);

    if (!is_string($buffer)) {
      echo $buffer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
      return;
    }

    $markup = tmw_featured_block_markup();

    if ($markup !== '') {
      if (strpos($buffer, '</main>') !== false) {
        $buffer = preg_replace('#</main>#', $markup . '</main>', $buffer, 1);
      } else {
        $buffer .= $markup;
      }
    }

    echo $buffer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
  }
}

if (!function_exists('tmw_featured_block_output_buffer_shutdown')) {
  function tmw_featured_block_output_buffer_shutdown() {
    if (empty($GLOBALS['tmw_featured_block_buffer_started'])) {
      return;
    }

    $buffer = ob_get_clean();
    $GLOBALS['tmw_featured_block_buffer_started'] = false;

    if (!is_string($buffer)) {
      echo $buffer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
      return;
    }

    echo $buffer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
  }
}

if (!function_exists('tmw_featured_block_dedup')) {
  function tmw_featured_block_dedup() {
    if (!is_singular()) {
      return true;
    }

    $shortcode = tmw_get_featured_shortcode_for_context();
    $post_id   = get_queried_object_id();
    if (!$post_id) {
      return true;
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
      return true;
    }

    return strpos($post->post_content, $shortcode) === false;
  }
}
