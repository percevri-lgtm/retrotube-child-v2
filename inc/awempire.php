<?php
/**
 * AWEmpire integration helpers.
 */

/** Fetch the AWEmpire feed rows with transient caching. */
function tmw_aw_fetch_rows(){
  $url = defined('AWEMPIRE_FEED_URL') ? AWEMPIRE_FEED_URL : '';
  if (!$url) return [];
  $cache_key = 'tmw_aw_rows';
  $rows = get_transient($cache_key);
  if (false === $rows){
    $res = wp_remote_get($url);
    if (is_wp_error($res)) return [];
    $body = wp_remote_retrieve_body($res);
    $json = json_decode($body, true);
    $rows = $json && isset($json['models']) ? $json['models'] : ($json ?: []);
    set_transient($cache_key, $rows, 600); // 10 min cache
  }
  return is_array($rows) ? $rows : [];
}

// Alias for backward compatibility
if (!function_exists('tmw_aw_get_feed')) {
  function tmw_aw_get_feed(){
    return tmw_aw_fetch_rows();
  }
}

/** Find a row by nickname (case-insensitive). */
function tmw_aw_find_row($nick){
  $nick = strtolower(trim($nick));
  foreach (tmw_aw_fetch_rows() as $row){
    if (isset($row['nickname']) && strtolower($row['nickname']) === $nick){
      return $row;
    }
  }
  return null;
}

/** Pick front/back images from a feed row. */
function tmw_aw_pick_images_from_row($row){
  $front = $row['image_large'] ?? ($row['image'] ?? '');
  $back  = $row['image_back']  ?? $front;
  return [$front, $back];
}

/** Build outbound link optionally appending subAffId. */
function tmw_aw_build_link($url, $subaff=''){
  if (!$url) return '';
  $sep = strpos($url, '?') !== false ? '&' : '?';
  if ($subaff) $url .= $sep.'subAffId='.rawurlencode($subaff);
  return esc_url($url);
}

// ---------- ADD (if not present): normalization + candidate finder ----------

// Normalize any string to a feed key: lowercase, remove accents, drop non a-z0-9.
if (!function_exists('tmw_aw_norm')) {
  function tmw_aw_norm($s){
    $s = strtolower(trim((string)$s));
    if (function_exists('remove_accents')) $s = remove_accents($s);
    $s = preg_replace('~[^a-z0-9]+~', '', $s); // remove spaces, hyphens, underscores, punctuation
    return $s;
  }
}

// Find a performer row by multiple candidates (nickname meta, slug, name, no-dash/no-space versions)
if (!function_exists('tmw_aw_find_by_candidates')) {
  function tmw_aw_find_by_candidates(array $candidates){
    static $index = null;

    if ($index === null){
      $index = [];
      foreach (tmw_aw_get_feed() as $row){
        $nick = $row['nickname'] ?? ($row['name'] ?? '');
        $key  = tmw_aw_norm($nick);
        if ($key) $index[$key] = $row;
      }
    }
    foreach ($candidates as $cand){
      $key = tmw_aw_norm($cand);
      if ($key && isset($index[$key])) return $index[$key];
    }
    return null;
  }
}

// ---------- REPLACE this whole function with the version below ----------
if (!function_exists('tmw_aw_card_data')) {
  // if function doesn't exist yet, it's defined later in file; this guard avoids fatal.
}

// Admin bar button to purge the AWE feed transient cache
add_action('admin_bar_menu', function($bar){
  if (!current_user_can('manage_options')) return;
  $bar->add_node([
    'id'    => 'tmw_aw_clear_cache',
    'title' => 'Purge AWEmpire Cache',
    'href'  => wp_nonce_url(admin_url('?tmw_aw_clear_cache=1'), 'tmw_aw_clear_cache')
  ]);
}, 100);

add_action('admin_init', function(){
  if (
    current_user_can('manage_options')
    && isset($_GET['tmw_aw_clear_cache'])
    && isset($_GET['_wpnonce'])
    && wp_verify_nonce($_GET['_wpnonce'],'tmw_aw_clear_cache')
  ) {
    // IMPORTANT: this key must match the one used in tmw_aw_get_feed()
    delete_transient('tmw_aw_feed_v1');
    wp_safe_redirect(remove_query_arg(['tmw_aw_clear_cache','_wpnonce']));
    exit;
  }
});

if (function_exists('tmw_aw_pick_images_from_row') && function_exists('tmw_aw_build_link')):
  /**
   * Get front/back/link for a model term using:
   *   ACF overrides → AWE feed (auto-matched) → placeholder.
   * Uses both tmw_aw_nick and (legacy) tm_lj_nick meta as explicit nickname if present.
   */
  function tmw_aw_card_data($term_id){
    $place = get_stylesheet_directory_uri().'/assets/img/placeholders/model-card.jpg';

    // ACF overrides first
    $front = $back = '';
    if (function_exists('get_field')) {
      $acf_front = get_field('actor_card_front', 'actors_'.$term_id);
      $acf_back  = get_field('actor_card_back',  'actors_'.$term_id);
      if (is_array($acf_front) && !empty($acf_front['url'])) $front = $acf_front['url'];
      if (is_array($acf_back)  && !empty($acf_back['url']))  $back  = $acf_back['url'];
    }

    // Build candidate list for feed matching
    $term = get_term($term_id);
    $nick_explicit = get_term_meta($term_id,'tmw_aw_nick',true);
    if (!$nick_explicit) {
      // legacy key support
      $nick_explicit = get_term_meta($term_id,'tm_lj_nick',true);
    }

    $cands = [];
    if (!empty($nick_explicit)) $cands[] = $nick_explicit;
    if ($term && !is_wp_error($term)) {
      $cands[] = $term->slug;                               // e.g. abby-murray
      $cands[] = $term->name;                               // e.g. Abby Murray
      $cands[] = str_replace(['-','_',' '],'',$term->slug); // abbymurray
      $cands[] = str_replace(['-','_',' '],'',$term->name); // aellenagrace, etc.
    }
    $cands = array_unique(array_filter($cands));

    // This relies on tmw_aw_find_by_candidates() you added earlier
    $row = function_exists('tmw_aw_find_by_candidates') ? tmw_aw_find_by_candidates($cands) : null;

    // subAffId defaults to slug if not set
    $sub = get_term_meta($term_id,'tmw_aw_subaff',true);
    if (!$sub) $sub = get_term_meta($term_id,'tm_subaff',true); // legacy key
    if (!$sub && $term && !is_wp_error($term)) $sub = $term->slug;

    $link = '';
    if ($row){
      if (!$front || !$back){
        list($f,$b) = tmw_aw_pick_images_from_row($row);
        if (!$front) $front = $f;
        if (!$back)  $back  = ($b ?: $front);
      }
      $link = tmw_aw_build_link(
        ($row['tracking_url'] ?? ($row['url'] ?? '')),
        $sub ?: ($nick_explicit ?: ($term->slug ?? ''))
      );
    }

    // Final fallbacks (avoid empty/black tiles)
    if (!$front) $front = $place;
    if (!$back)  $back  = $front;

    return ['front'=>$front,'back'=>$back,'link'=>$link];
  }
endif;

// Admin bar button to purge the AWE feed transient cache
add_action('admin_bar_menu', function($bar){
  if (!current_user_can('manage_options')) return;
  $bar->add_node([
    'id'=>'tmw_aw_clear_cache',
    'title'=>'Purge AWEmpire Cache',
    'href'=> wp_nonce_url(admin_url('?tmw_aw_clear_cache=1'), 'tmw_aw_clear_cache')
  ]);
}, 100);

add_action('admin_init', function(){
  if (current_user_can('manage_options') && isset($_GET['tmw_aw_clear_cache']) && wp_verify_nonce($_GET['_wpnonce'],'tmw_aw_clear_cache')) {
    delete_transient('tmw_aw_feed_v1');
    wp_safe_redirect(remove_query_arg(['tmw_aw_clear_cache','_wpnonce']));
    exit;
  }
});

