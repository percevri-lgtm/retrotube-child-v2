<?php

/* ======================================================================
 * ADMIN DEBUG
 * ====================================================================== */
if (defined('TMW_DEBUG') && TMW_DEBUG) {
  add_action('template_redirect', function () {
    $do_debug = isset($_GET['awdebug']) || isset($_GET['aw_diag']);
    if (!$do_debug) {
      return;
    }

    if (!is_user_logged_in() || !current_user_can('manage_options')) {
      status_header(403);
      exit('Forbidden');
    }

    header('Content-Type: text/plain; charset=utf-8');

    echo "AWEMPIRE_FEED_URL defined: " . (defined('AWEMPIRE_FEED_URL') ? 'YES' : 'NO') . "\n";
    if (defined('AWEMPIRE_FEED_URL')) {
      echo "Feed URL: " . AWEMPIRE_FEED_URL . "\n";
      $resp  = wp_remote_get(AWEMPIRE_FEED_URL, ['timeout' => 15]);
      $code  = wp_remote_retrieve_response_code($resp);
      $body  = wp_remote_retrieve_body($resp);
      $json  = json_decode($body, true);

      $count = 0;
      if (is_array($json)) {
        if (isset($json['data']['models']) && is_array($json['data']['models'])) {
          $count = count($json['data']['models']);
        } elseif (isset($json['models']) && is_array($json['models'])) {
          $count = count($json['models']);
        }
      }
      echo "HTTP status: {$code}\n";
      echo "Decoded items: {$count}\n";
      echo "Body preview: " . substr($body, 0, 200) . "\n\n";
    }

    $terms = get_terms(['taxonomy' => 'models', 'number' => 5, 'hide_empty' => false]);
    foreach ($terms as $t) {
      $card  = function_exists('tmw_aw_card_data') ? tmw_aw_card_data($t->term_id) : [];
      $front = $card['front'] ?? '';
      $back  = $card['back']  ?? '';
      echo "TERM: {$t->name} ({$t->slug})\n";
      echo "  front: {$front}\n";
      echo "  back : {$back}\n";
      if ($front && strpos($front, 'data:') !== 0) {
        $h = wp_remote_head($front);
        echo "  front HEAD: " . wp_remote_retrieve_response_code($h) . "\n";
      }
      if ($back && strpos($back, 'data:') !== 0) {
        $h = wp_remote_head($back);
        echo "  back  HEAD: " . wp_remote_retrieve_response_code($h) . "\n";
      }
      echo "\n";
    }
    exit;
  });

  add_action('template_redirect', function () {
    if (!isset($_GET['awfind'])) {
      return;
    }

    if (!is_user_logged_in() || !current_user_can('manage_options')) {
      status_header(403);
      exit('Forbidden');
    }

    header('Content-Type: text/plain; charset=utf-8');

    $q = strtolower(trim((string) $_GET['awfind']));
    $feed = function_exists('tmw_aw_get_feed') ? tmw_aw_get_feed() : [];
    $out = [];
    foreach ((array) $feed as $row) {
      $vals = [
        $row['performerId']   ?? '',
        $row['displayName']   ?? '',
        $row['nickname']      ?? '',
        $row['name']          ?? '',
        $row['uniqueModelId'] ?? '',
      ];
      foreach ($vals as $v) {
        if ($v && strpos(strtolower($v), $q) !== false) {
          $picked = function_exists('tmw_aw_pick_images_from_row') ? tmw_aw_pick_images_from_row($row) : [null, null];
          $out[] = [
            'performerId' => $row['performerId'] ?? '',
            'displayName' => $row['displayName'] ?? '',
            'nickname'    => $row['nickname'] ?? '',
            'front'       => $picked[0] ?? '',
            'back'        => $picked[1] ?? '',
            'link'        => $row['tracking_url'] ?? ($row['url'] ?? ''),
          ];
          break;
        }
      }
    }

    echo "Query: {$q}\nMatches: " . count($out) . "\n\n";
    foreach (array_slice($out, 0, 20) as $r) {
      echo "performerId : {$r['performerId']}\n";
      echo "displayName : {$r['displayName']}\n";
      echo "nickname    : {$r['nickname']}\n";
      echo "front       : {$r['front']}\n";
      echo "back        : {$r['back']}\n";
      echo "link        : {$r['link']}\n\n";
    }
    exit;
  });
}

/* ======================================================================
 * MODEL BANNER POSITION META BOX
 * ====================================================================== */
if (!function_exists('tmw_render_banner_position_box')) {
  function tmw_render_banner_position_box($post) {
    $stored_y = get_post_meta($post->ID, '_banner_position_y', true);
    $raw_y    = is_numeric($stored_y) ? (int) $stored_y : 0;
    $value    = max(-1000, min(1000, $raw_y));
    $banner   = function_exists('tmw_resolve_model_banner_url') ? tmw_resolve_model_banner_url($post->ID) : '';

    wp_nonce_field('tmw_save_banner_position', 'tmw_banner_position_nonce');

    ob_start();
    $rendered = function_exists('tmw_render_model_banner') ? tmw_render_model_banner($post->ID, 'backend') : false;
    $markup   = ob_get_clean();

    echo '<div id="tmw-banner-preview" class="tmw-banner-preview">';
    if ($rendered && $markup) {
      echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
    echo '</div>';

    if (!$rendered) {
      echo '<p><em>' . esc_html__('No banner found. Assign a “Models” term with a banner in ACF, or set a featured image.', 'retrotube-child') . '</em></p>';
    }

    echo '<input type="range" min="-350" max="350" step="1" value="' . esc_attr($value) . '" id="tmwBannerSlider" class="tmw-slider" name="banner_position_y">
    <p><small>Vertical offset (px): <span id="tmwBannerValue">' . esc_html($value) . '</span></small></p>';

    if (defined('TMW_BANNER_DEBUG') && TMW_BANNER_DEBUG) {
      echo '<p><code>Resolved URL: ' . esc_html($banner ? $banner : 'EMPTY') . '</code></p>';
    }

    ob_start();
    ?>
    <script>
        (function(){
            const slider = document.getElementById("tmwBannerSlider");
            const previewWrap = document.getElementById("tmw-banner-preview") || document.getElementById("tmwBannerPreview");
            const previewFrame = previewWrap ? (previewWrap.classList && previewWrap.classList.contains('tmw-banner-frame') ? previewWrap : previewWrap.querySelector('.tmw-banner-frame')) : null;
            const val = document.getElementById("tmwBannerValue");
            if (typeof window.tmwSetBannerOffsetY !== "function") {
                window.tmwSetBannerOffsetY = function(pxValue) {
                    var v = Number(pxValue) || 0;
                    var root = document.documentElement;
                    if (root && root.style) {
                        root.style.setProperty("--offset-y", v + "px");
                    }
                    var defaultWrap = document.getElementById("tmw-banner-preview") || document.getElementById("tmwBannerPreview");
                    var defaultFrame = null;
                    if (defaultWrap) {
                        if (defaultWrap.classList && defaultWrap.classList.contains('tmw-banner-frame')) {
                            defaultFrame = defaultWrap;
                        } else {
                            defaultFrame = defaultWrap.querySelector('.tmw-banner-frame');
                        }
                    }
                    if (defaultFrame && defaultFrame.style) {
                        defaultFrame.style.setProperty("--offset-y", v + "px");
                    }
                    return v;
                };
            }
            window.tmwUpdateBannerOffset = window.tmwUpdateBannerOffset || function(previewEl, value, posX){
                var numeric = parseInt(value, 10);
                if (isNaN(numeric)) { numeric = 0; }
                var clamped = Math.max(-1000, Math.min(1000, numeric));
                var applied = (typeof window.tmwSetBannerOffsetY === "function") ? window.tmwSetBannerOffsetY(clamped) : clamped;
                var defaultWrap = document.getElementById("tmw-banner-preview") || document.getElementById("tmwBannerPreview");
                var defaultFrame = null;
                if (defaultWrap) {
                    if (defaultWrap.classList && defaultWrap.classList.contains('tmw-banner-frame')) {
                        defaultFrame = defaultWrap;
                    } else {
                        defaultFrame = defaultWrap.querySelector('.tmw-banner-frame');
                    }
                }
                var target = previewEl;
                if (target && target.classList && !target.classList.contains('tmw-banner-frame')) {
                    target = target.querySelector ? target.querySelector('.tmw-banner-frame') : null;
                }
                if (!target) {
                    target = defaultFrame;
                }
                if (target && target.style && target !== defaultFrame) {
                    target.style.setProperty("--offset-y", applied + "px");
                }
                if (target && target.style) {
                    if (typeof posX === "number" && !isNaN(posX)) {
                        target.style.setProperty("--offset-x", posX + "%");
                    } else if (!target.style.getPropertyValue("--offset-x")) {
                        target.style.setProperty("--offset-x", "50%");
                    }
                }
                return clamped;
            };
            if (slider && previewFrame) {
                slider.addEventListener("input", function(e){
                    var current = parseInt(e.target.value, 10) || 0;
                    var clamped = Math.max(-1000, Math.min(1000, current));
                    if (clamped !== current) {
                        e.target.value = clamped;
                    }
                    if (val) {
                        val.textContent = clamped;
                    }
                    if (typeof window.tmwUpdateBannerOffset === "function") {
                        window.tmwUpdateBannerOffset(previewFrame, clamped);
                    }
                });
                if (typeof window.tmwUpdateBannerOffset === "function") {
                    var initial = parseInt(slider.value, 10) || 0;
                    window.tmwUpdateBannerOffset(previewFrame, initial);
                    if (val) {
                        val.textContent = Math.max(-1000, Math.min(1000, initial));
                    }
                }
            }
        })();
        document.addEventListener('input', function(e){
            if (!e || !e.target) {
                return;
            }
            if (e.target.id === 'banner_offset_y' || e.target.id === 'tmwBannerSlider') {
                var value = parseInt(e.target.value, 10) || 0;
                var frame = document.querySelector('#tmw-banner-preview .tmw-banner-frame, #tmwBannerPreview .tmw-banner-frame');
                if (frame && frame.style) {
                    frame.style.setProperty('--offset-y', value + 'px');
                }
                var previewImg = document.querySelector('#tmw-banner-preview .tmw-banner-frame img, #tmwBannerPreview .tmw-banner-frame img');
                if (previewImg && previewImg.style) {
                    previewImg.style.setProperty('--offset-y', value + 'px');
                }
            }
        });
    </script>
    <?php
    echo ob_get_clean();
  }
}

add_action('add_meta_boxes', function () {
  add_meta_box('model_banner_position', __('Banner Position (Vertical)', 'retrotube-child'), 'tmw_render_banner_position_box', 'model', 'normal', 'default');
});

add_action('save_post_model', function ($post_id) {
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
    return;
  }

  if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
    return;
  }

  if (!isset($_POST['tmw_banner_position_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tmw_banner_position_nonce'])), 'tmw_save_banner_position')) {
    return;
  }

  if (!current_user_can('edit_post', $post_id)) {
    return;
  }

  if (isset($_POST['banner_position_y'])) {
    $value = wp_unslash($_POST['banner_position_y']);
    $value = is_numeric($value) ? (int)$value : 0;
    if ($value < -1000) {
      $value = -1000;
    }
    if ($value > 1000) {
      $value = 1000;
    }
    update_post_meta($post_id, '_banner_position_y', $value);
  }
});

/* ======================================================================
 * FEATURED MODELS SHORTCODE META BOX
 * ====================================================================== */
if (!function_exists('tmw_featured_shortcode_meta_box_cb')) {
  function tmw_featured_shortcode_meta_box_cb($post) {
    $value = get_post_meta($post->ID, 'tmw_featured_shortcode', true);
    $value = is_string($value) ? $value : '';

    wp_nonce_field('tmw_featured_shortcode_save', 'tmw_featured_shortcode_nonce');
    ?>
    <p>
      <label for="tmw_featured_shortcode_field" class="screen-reader-text"><?php esc_html_e('Featured Models shortcode (optional)', 'retrotube-child'); ?></label>
      <input type="text" name="tmw_featured_shortcode" id="tmw_featured_shortcode_field" value="<?php echo esc_attr($value); ?>" class="widefat" />
    </p>
    <p class="description"><?php esc_html_e('Leave blank to use [tmw_featured_models].', 'retrotube-child'); ?></p>
    <?php
  }
}

add_action('add_meta_boxes', function () {
  $post_types = ['post', 'page', 'model', 'video', 'videos', 'wpsc-video', 'wp-script-video', 'wpws_video'];
  $post_types = array_unique($post_types);
  foreach ($post_types as $post_type) {
    if (!post_type_exists($post_type)) {
      continue;
    }

    add_meta_box(
      'tmw-featured-shortcode',
      __('Featured Models shortcode (optional)', 'retrotube-child'),
      'tmw_featured_shortcode_meta_box_cb',
      $post_type,
      'side',
      'default'
    );
  }
});

add_action('save_post', function ($post_id) {
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
    return;
  }

  if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
    return;
  }

  if (!isset($_POST['tmw_featured_shortcode_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tmw_featured_shortcode_nonce'])), 'tmw_featured_shortcode_save')) {
    return;
  }

  if (!current_user_can('edit_post', $post_id)) {
    return;
  }

  $value = '';
  if (isset($_POST['tmw_featured_shortcode'])) {
    $value = tmw_clean_featured_shortcode(wp_unslash($_POST['tmw_featured_shortcode']));
  }

  if ($value !== '') {
    update_post_meta($post_id, 'tmw_featured_shortcode', $value);
  } else {
    delete_post_meta($post_id, 'tmw_featured_shortcode');
  }
});

/* ======================================================================
 * FEATURED MODELS SHORTCODE TERM META
 * ====================================================================== */
if (!function_exists('tmw_featured_shortcode_term_add_field')) {
  function tmw_featured_shortcode_term_add_field($taxonomy) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
    wp_nonce_field('tmw_featured_shortcode_term_save', 'tmw_featured_shortcode_term_nonce');
    ?>
    <div class="form-field term-featured-shortcode-wrap">
      <label for="tmw_featured_shortcode_term_field"><?php esc_html_e('Featured Models shortcode (optional)', 'retrotube-child'); ?></label>
      <input type="text" name="tmw_featured_shortcode" id="tmw_featured_shortcode_term_field" value="" class="regular-text" />
      <p class="description"><?php esc_html_e('Leave blank to use [tmw_featured_models].', 'retrotube-child'); ?></p>
    </div>
    <?php
  }
}

if (!function_exists('tmw_featured_shortcode_term_edit_field')) {
  function tmw_featured_shortcode_term_edit_field($term) {
    $value = get_term_meta($term->term_id, 'tmw_featured_shortcode', true);
    $value = is_string($value) ? $value : '';

    wp_nonce_field('tmw_featured_shortcode_term_save', 'tmw_featured_shortcode_term_nonce');
    ?>
    <tr class="form-field term-featured-shortcode-wrap">
      <th scope="row"><label for="tmw_featured_shortcode_term_field"><?php esc_html_e('Featured Models shortcode (optional)', 'retrotube-child'); ?></label></th>
      <td>
        <input type="text" name="tmw_featured_shortcode" id="tmw_featured_shortcode_term_field" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description"><?php esc_html_e('Leave blank to use [tmw_featured_models].', 'retrotube-child'); ?></p>
      </td>
    </tr>
    <?php
  }
}

add_action('category_add_form_fields', 'tmw_featured_shortcode_term_add_field');
add_action('post_tag_add_form_fields', 'tmw_featured_shortcode_term_add_field');
add_action('category_edit_form_fields', 'tmw_featured_shortcode_term_edit_field');
add_action('post_tag_edit_form_fields', 'tmw_featured_shortcode_term_edit_field');

if (!function_exists('tmw_save_featured_shortcode_term_meta')) {
  function tmw_save_featured_shortcode_term_meta($term_id) {
    if (!is_admin()) {
      return;
    }

    if (!isset($_POST['tmw_featured_shortcode_term_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tmw_featured_shortcode_term_nonce'])), 'tmw_featured_shortcode_term_save')) {
      return;
    }

    if (!current_user_can('manage_categories')) {
      return;
    }

    $value = '';
    if (isset($_POST['tmw_featured_shortcode'])) {
      $value = tmw_clean_featured_shortcode(wp_unslash($_POST['tmw_featured_shortcode']));
    }

    if ($value !== '') {
      update_term_meta($term_id, 'tmw_featured_shortcode', $value);
    } else {
      delete_term_meta($term_id, 'tmw_featured_shortcode');
    }
  }
}

add_action('created_category', 'tmw_save_featured_shortcode_term_meta');
add_action('edited_category', 'tmw_save_featured_shortcode_term_meta');
add_action('created_post_tag', 'tmw_save_featured_shortcode_term_meta');
add_action('edited_post_tag', 'tmw_save_featured_shortcode_term_meta');
/**
 * Fix: Prevent PHP warnings in canonical.php
 * Avoids "Undefined array key host/scheme" and strtolower(null) notices.
 */
add_filter( 'redirect_canonical', function( $redirect_url, $requested_url ) {
    // If empty or not a string, cancel redirect
    if ( empty( $redirect_url ) || ! is_string( $redirect_url ) ) {
        return false;
    }

    $parts = wp_parse_url( $redirect_url );

    // Cancel redirect if host/scheme missing
    if ( empty( $parts['host'] ) || empty( $parts['scheme'] ) ) {
        return false;
    }

    return $redirect_url;
}, 10, 2 );

add_filter( 'widget_display_callback', function( $instance, $widget, $args ) {
  if ( $widget instanceof wpst_WP_Widget_Videos_Block ) {
    ob_start();
    $widget->widget( $args, $instance );
    $output = ob_get_clean();

    $host = preg_quote( wp_parse_url( home_url(), PHP_URL_HOST ), '#' );
    // Only rewrite links that point directly to ?filter= without the /videos/ prefix.
    $pattern = '#(href=["\'])(?:https?://'.$host.')?/\?filter=([^"\']+)#i';
    $output = preg_replace( $pattern, '$1' . home_url( '/videos/?filter=' ) . '$2', $output );

    echo $output;
    return false;
  }

  return $instance;
}, 10, 3 );

/**
 * Sync LiveJasmin performer profiles with Retrotube Model CPT
 * Triggered automatically after each imported video is linked to a model
 */
add_action( 'lvjm_model_profile_attached_video', 'rt_child_sync_model_profile', 10, 2 );

function rt_child_sync_model_profile( $model_post_id, $video_post_id ) {
    if ( ! $model_post_id || ! $video_post_id ) {
        return;
    }

    $model_post = get_post( $model_post_id );
    if ( ! $model_post || $model_post->post_status === 'trash' ) {
        return;
    }

    // Get performer terms attached to this video
    $performers = wp_get_post_terms( $video_post_id, 'models', [ 'fields' => 'names' ] );
    $performer_name = ! empty( $performers ) ? $performers[0] : $model_post->post_title;

    // Update title if needed
    if ( $model_post->post_title !== $performer_name ) {
        wp_update_post([
            'ID'         => $model_post_id,
            'post_title' => $performer_name,
        ]);
    }

    // Ensure featured image or placeholder
    if ( ! has_post_thumbnail( $model_post_id ) ) {
        $placeholder = get_post_meta( $model_post_id, 'lvjm_model_placeholder_image', true );
        if ( $placeholder && filter_var( $placeholder, FILTER_VALIDATE_URL ) ) {
            // Store placeholder as external featured image meta
            update_post_meta( $model_post_id, '_external_thumbnail_url', esc_url( $placeholder ) );
        }
    }

    // Link related videos (avoid duplicates)
    $related = (array) get_post_meta( $model_post_id, 'rt_model_videos', true );
    if ( ! in_array( $video_post_id, $related, true ) ) {
        $related[] = $video_post_id;
        update_post_meta( $model_post_id, 'rt_model_videos', $related );
    }

    tmw_debug_log('[ModelSync] Synced performer “' . $performer_name . '” (' . $model_post_id . ') with video ' . $video_post_id);
}

add_action('after_switch_theme', function () {
    flush_rewrite_rules();
    tmw_debug_log('[ModelFix] Flushed rewrite rules after theme activation.');
});

/**
 * === [TMW FIX] Restore Models Taxonomy + Auto-Link ===
 * Version: v1.5.6-taxonomy-link-fix
 * Date: 2025-10-19
 */

if (!function_exists('tmw_taxonomy_fix_log')) {
  function tmw_taxonomy_fix_log($message) {
    // Logging intentionally disabled during audit cleanup.
  }
}

add_action('init', function() {
  if (!taxonomy_exists('models')) {
    tmw_taxonomy_fix_log('Warning: "models" taxonomy not found.');
    return;
  }

  if (function_exists('tmw_bind_models_taxonomy')) {
    tmw_bind_models_taxonomy();
  }

  tmw_taxonomy_fix_log('Ensured "models" taxonomy is registered for video post type.');
}, 20);

if (!function_exists('tmw_extract_model_slug_from_title')) {
  function tmw_extract_model_slug_from_title($title) {
    $title = trim((string) $title);
    if ($title === '') return null;

    if (preg_match('/with\s+([A-Za-z][A-Za-z0-9\-]+)/i', $title, $match)) {
      return sanitize_title($match[1]);
    }

    if (preg_match('/\b([A-Za-z][A-Za-z0-9]+)\b/', $title, $match)) {
      return sanitize_title($match[1]);
    }

    return null;
  }
}

if (!function_exists('tmw_autolink_video_models')) {
  function tmw_autolink_video_models($post_id, $post, $update) {
    if (wp_is_post_revision($post_id)) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!taxonomy_exists('models')) return;

    $title = get_the_title($post_id);
    if ($title === '') return;

    $slug = tmw_extract_model_slug_from_title(strtolower($title));
    if (!$slug) {
      tmw_taxonomy_fix_log("No model slug detected for video ID {$post_id}. Title: {$title}");
      return;
    }

    $term = get_term_by('slug', $slug, 'models');
    if (!$term instanceof WP_Term) {
      $term = get_term_by('name', ucwords(str_replace('-', ' ', $slug)), 'models');
    }

    if ($term instanceof WP_Term) {
      wp_set_post_terms($post_id, [$term->term_id], 'models', true);
      tmw_taxonomy_fix_log("Auto-linked video ID {$post_id} to model term {$term->slug} (#{$term->term_id}).");
    } else {
      tmw_taxonomy_fix_log("No matching model term found for slug {$slug} (video ID {$post_id}).");
    }
  }
}

add_action('save_post_video', 'tmw_autolink_video_models', 20, 3);

add_action('admin_init', function() {
  if (!is_admin() || !current_user_can('manage_options')) return;
  if (!taxonomy_exists('models')) return;
  if (get_option('tmw_models_relinked_v156')) return;

  $video_ids = get_posts([
    'post_type'      => 'video',
    'fields'         => 'ids',
    'posts_per_page' => -1,
    'no_found_rows'  => true,
  ]);

  foreach ($video_ids as $video_id) {
    $post = get_post($video_id);
    if ($post instanceof WP_Post) {
      tmw_autolink_video_models($video_id, $post, true);
    }
  }

  update_option('tmw_models_relinked_v156', 1);
  tmw_taxonomy_fix_log('Retroactive relinker completed.');
}, 20);
// === [TMW-MODEL-COMMENTS] Enable comments for model post type ===
add_action( 'init', function() {
    add_post_type_support( 'model', 'comments' );
    tmw_debug_log('[TMW-MODEL-COMMENTS] Comment support enabled for post type: model');
});


// === [TMW-MODEL-COMMENTS-FORCE] Always keep comments open for model pages ===
add_filter( 'comments_open', function( $open, $post_id ) {
    $post = get_post( $post_id );
    if ( $post && $post->post_type === 'model' ) {
        tmw_debug_log('[TMW-MODEL-COMMENTS-FORCE] Forcing comments open for ' . $post->post_title);
        return true;
    }
    return $open;
}, 99, 2 );

add_filter( 'pings_open', function( $open, $post_id ) {
    $post = get_post( $post_id );
    if ( $post && $post->post_type === 'model' ) {
        return true;
    }
    return $open;
}, 99, 2 );

// === [TMW-MODEL-COMMENTS-FORCE] Bulk enable for existing models ===
add_action( 'init', function() {
    $models = get_posts([
        'post_type' => 'model',
        'numberposts' => -1,
        'post_status' => 'publish',
    ]);
    foreach ( $models as $model ) {
        if ( get_post_field( 'comment_status', $model->ID ) !== 'open' ) {
            wp_update_post([
                'ID' => $model->ID,
                'comment_status' => 'open',
            ]);
            tmw_debug_log('[TMW-MODEL-COMMENTS-FORCE] Comment status set to open for ' . $model->post_title);
        }
    }
});


if (defined('TMW_DEBUG') && TMW_DEBUG) {
  add_action('wp_ajax_tmw_flipbox_audit_log', 'tmw_flipbox_audit_log');
  add_action('wp_ajax_nopriv_tmw_flipbox_audit_log', 'tmw_flipbox_audit_log');
  function tmw_flipbox_audit_log() {
      if (isset($_POST['msg'])) {
          $msg = sanitize_text_field($_POST['msg']);
          tmw_debug_log($msg);
      }
      wp_die();
  }

  add_action('wp_ajax_tmw_flipbox_deep_audit_log', 'tmw_flipbox_deep_audit_log');
  add_action('wp_ajax_nopriv_tmw_flipbox_deep_audit_log', 'tmw_flipbox_deep_audit_log');
  function tmw_flipbox_deep_audit_log() {
      if (isset($_POST['msg'])) {
          tmw_debug_log(sanitize_text_field($_POST['msg']));
      }
      wp_die();
  }

  add_action('wp_ajax_tmw_flipbox_intercept_log', 'tmw_flipbox_intercept_log');
  add_action('wp_ajax_nopriv_tmw_flipbox_intercept_log', 'tmw_flipbox_intercept_log');
  function tmw_flipbox_intercept_log() {
      if (isset($_POST['msg'])) {
          tmw_debug_log(sanitize_text_field($_POST['msg']));
      }
      wp_die();
  }

  add_action('wp_enqueue_scripts', function () {
      wp_dequeue_script('tmw-flipbox-mobile-fix');
      wp_dequeue_script('flipbox-mobile-fix');
      wp_dequeue_script('tmw-flipbox-intercept');
      wp_dequeue_script('tmw-flipbox-audit');
      wp_dequeue_script('tmw-flipbox-deep-audit');

      wp_enqueue_script(
          'tmw-flipbox-debug',
          get_stylesheet_directory_uri() . '/js/tmw-flipbox-debug.js',
          ['jquery'],
          time(),
          true
      );

      wp_localize_script('tmw-flipbox-debug', 'tmwDebug', [
          'ajaxurl' => admin_url('admin-ajax.php'),
          'nonce'   => wp_create_nonce('tmw_flipbox_debug')
      ]);
  }, 9999);

  add_action('wp_ajax_tmw_flipbox_debug_log', 'tmw_flipbox_debug_log');
  add_action('wp_ajax_nopriv_tmw_flipbox_debug_log', 'tmw_flipbox_debug_log');
  function tmw_flipbox_debug_log() {
      check_ajax_referer('tmw_flipbox_debug', 'nonce');
      if (!empty($_POST['msg'])) {
          tmw_debug_log('[TMW-FLIPBOX-DEBUG] ' . sanitize_text_field($_POST['msg']));
      }
      wp_die();
  }

  add_action('wp_enqueue_scripts', function () {
      foreach ([
          'tmw-flipbox-mobile-fix',
          'flipbox-mobile-fix',
          'tmw-flipbox-intercept',
          'tmw-flipbox-audit',
          'tmw-flipbox-deep-audit'
      ] as $handle) {
          if (wp_script_is($handle, 'enqueued')) {
              wp_dequeue_script($handle);
              tmw_debug_log("[TMW-FLIPBOX-AUDIT] Dequeued old script: {$handle}");
          }
      }

      $src = get_stylesheet_directory_uri() . '/js/tmw-flipbox-debug.js';
      wp_enqueue_script('tmw-flipbox-debug', $src, ['jquery'], time(), true);

      $inline = <<<JS
jQuery(function($){
  console.log('[TMW-FLIPBOX-DEBUG] Inline ping triggered.');
  $.post(ajaxurl,{action:'tmw_flipbox_debug_ping',t:(new Date()).toISOString()});
});
JS;
      wp_add_inline_script('tmw-flipbox-debug', $inline, 'after');
  }, 9999);

  add_action('wp_ajax_tmw_flipbox_debug_ping', function(){
      tmw_debug_log('[TMW-FLIPBOX-DEBUG] ✅ Script executed and ping received.');
      wp_die();
  });
  add_action('wp_ajax_nopriv_tmw_flipbox_debug_ping', function(){
      tmw_debug_log('[TMW-FLIPBOX-DEBUG] ✅ Script executed and ping received.');
      wp_die();
  });
}

