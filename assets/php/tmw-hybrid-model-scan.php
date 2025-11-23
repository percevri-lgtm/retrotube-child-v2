<?php
if (!defined('ABSPATH')) {
  exit;
}

if (!function_exists('tmw_cli_scan_model_videos')) {
  function tmw_cli_scan_model_videos($args = [], $assoc_args = []) {
    if (!class_exists('WP_CLI')) {
      return;
    }

    $video_ids = get_posts([
      'post_type'      => 'video',
      'post_status'    => 'any',
      'fields'         => 'ids',
      'numberposts'    => -1,
      'orderby'        => 'ID',
      'order'          => 'ASC',
      'suppress_filters' => false,
    ]);

    if (empty($video_ids)) {
      WP_CLI::warning('No video posts found.');
      return;
    }

    $processed = 0;
    $matched   = 0;
    $directory = tmw_hybrid_scan_get_model_directory();

    foreach ($video_ids as $post_id) {
      $post_id = (int) $post_id;
      if ($post_id <= 0) {
        continue;
      }

      $slugs = tmw_hybrid_scan_video($post_id, $directory);
      $matched += count($slugs);
      tmw_hybrid_scan_log_result($post_id, $slugs, $directory);
      $processed++;
    }

    WP_CLI::success(sprintf('Hybrid scan complete. %d videos checked, %d model matches found.', $processed, $matched));
  }
}

if (!function_exists('tmw_hybrid_scan_video')) {
  function tmw_hybrid_scan_video($post_id, ?array $directory = null) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
      return [];
    }

    if ($directory === null) {
      $directory = tmw_hybrid_scan_get_model_directory();
    }

    if (empty($directory['slugs'])) {
      return [];
    }

    $matches = [];

    $meta_keys = [
      'model_name',
      'model_slug',
      'models',
      'actors',
      'actor',
      'performer',
      'performers',
    ];

    foreach ($meta_keys as $meta_key) {
      $values = tmw_hybrid_scan_collect_meta_values($post_id, $meta_key);
      foreach ($values as $value) {
        $slug = tmw_hybrid_scan_normalize_candidate($value, $directory);
        if ($slug !== '') {
          $matches[$slug] = true;
        }
      }
    }

    $taxonomies = ['model', 'models', 'actor', 'performer', 'actors'];
    foreach ($taxonomies as $taxonomy) {
      $terms = wp_get_post_terms($post_id, $taxonomy);
      if (is_wp_error($terms) || empty($terms)) {
        continue;
      }

      foreach ($terms as $term) {
        $slug = tmw_hybrid_scan_normalize_candidate($term->slug, $directory);
        if ($slug === '' && !empty($term->name)) {
          $slug = tmw_hybrid_scan_normalize_candidate($term->name, $directory);
        }

        if ($slug !== '') {
          $matches[$slug] = true;
        }
      }
    }

    if (empty($matches)) {
      $post_title = get_post_field('post_title', $post_id);
      if (is_string($post_title) && $post_title !== '') {
        $post_title = tmw_hybrid_scan_prepare_text($post_title);
        foreach ($directory['slugs'] as $slug => $record) {
          $model_name = isset($record['name']) ? $record['name'] : '';
          if ($model_name && tmw_hybrid_scan_title_contains_phrase($post_title, $model_name)) {
            $matches[$slug] = true;
            continue;
          }

          $slug_phrase = str_replace('-', ' ', $slug);
          if ($slug_phrase && tmw_hybrid_scan_title_contains_phrase($post_title, $slug_phrase)) {
            $matches[$slug] = true;
          }
        }
      }
    }

    if (empty($matches)) {
      return [];
    }

    $slugs = array_keys($matches);
    sort($slugs);

    return $slugs;
  }
}

if (!function_exists('tmw_hybrid_scan_get_model_directory')) {
  function tmw_hybrid_scan_get_model_directory() {
    static $directory = null;

    if (is_array($directory)) {
      return $directory;
    }

    $directory = [
      'slugs' => [],
      'ids'   => [],
      'names' => [],
    ];

    $models = get_posts([
      'post_type'      => 'model',
      'post_status'    => 'any',
      'posts_per_page' => -1,
      'orderby'        => 'title',
      'order'          => 'ASC',
      'suppress_filters' => false,
    ]);

    foreach ($models as $model) {
      if (!isset($model->ID, $model->post_name)) {
        continue;
      }

      $slug = (string) $model->post_name;
      if ($slug === '') {
        continue;
      }

      $name = isset($model->post_title) ? (string) $model->post_title : $slug;
      $normalized_name = tmw_hybrid_scan_prepare_text($name);

      $directory['slugs'][$slug] = [
        'id'   => (int) $model->ID,
        'name' => $name,
      ];
      $directory['ids'][(int) $model->ID] = $slug;
      $aliases = [];

      if ($normalized_name !== '') {
        $aliases[] = $normalized_name;
        $aliases[] = str_replace(' ', '', $normalized_name);
      }

      $slug_text = tmw_hybrid_scan_prepare_text($slug);
      if ($slug_text !== '') {
        $aliases[] = $slug_text;
      }

      $slug_words = tmw_hybrid_scan_prepare_text(str_replace('-', ' ', $slug));
      if ($slug_words !== '') {
        $aliases[] = $slug_words;
        $aliases[] = str_replace(' ', '', $slug_words);
      }

      $slug_compact = str_replace('-', '', $slug);
      if ($slug_compact !== '') {
        $aliases[] = $slug_compact;
      }

      foreach (array_unique(array_filter($aliases)) as $alias) {
        $directory['names'][$alias] = $slug;
      }
    }

    return $directory;
  }
}

if (!function_exists('tmw_hybrid_scan_collect_meta_values')) {
  function tmw_hybrid_scan_collect_meta_values($post_id, $meta_key) {
    $raw_values = get_post_meta($post_id, $meta_key, false);
    if (empty($raw_values)) {
      return [];
    }

    $collected = [];
    foreach ($raw_values as $raw_value) {
      $collected = array_merge($collected, tmw_hybrid_scan_normalize_raw_value($raw_value));
    }

    return $collected;
  }
}

if (!function_exists('tmw_hybrid_scan_normalize_raw_value')) {
  function tmw_hybrid_scan_normalize_raw_value($value) {
    if (is_array($value)) {
      $results = [];
      foreach ($value as $item) {
        $results = array_merge($results, tmw_hybrid_scan_normalize_raw_value($item));
      }
      return $results;
    }

    if (is_object($value)) {
      $value = (array) $value;
      return tmw_hybrid_scan_normalize_raw_value($value);
    }

    if ($value === null || $value === false) {
      return [];
    }

    $value = (string) $value;
    if ($value === '') {
      return [];
    }

    $value = wp_strip_all_tags($value);
    $parts = preg_split('/[|,;&\n\r]+/', $value);
    if ($parts === false || empty($parts)) {
      return [];
    }

    $normalized = [];
    foreach ($parts as $part) {
      $part = trim($part);
      if ($part === '') {
        continue;
      }
      $normalized[] = $part;
    }

    return $normalized;
  }
}

if (!function_exists('tmw_hybrid_scan_normalize_candidate')) {
  function tmw_hybrid_scan_normalize_candidate($candidate, array $directory) {
    if (is_numeric($candidate)) {
      $candidate = (int) $candidate;
      if (isset($directory['ids'][$candidate])) {
        return $directory['ids'][$candidate];
      }
    }

    if (!is_scalar($candidate)) {
      return '';
    }

    $candidate = tmw_hybrid_scan_prepare_text((string) $candidate);
    if ($candidate === '') {
      return '';
    }

    if (strpos($candidate, '(') !== false) {
      $candidate = tmw_hybrid_scan_prepare_text(preg_replace('/\(.+$/', '', $candidate));
      if ($candidate === '') {
        return '';
      }
    }

    if (isset($directory['names'][$candidate])) {
      return $directory['names'][$candidate];
    }

    $slug = sanitize_title(str_replace(['_', '.'], ' ', $candidate));
    if ($slug !== '' && isset($directory['slugs'][$slug])) {
      return $slug;
    }

    $raw_slug = str_replace(['_', '.'], '-', $candidate);
    if ($raw_slug !== '' && isset($directory['slugs'][$raw_slug])) {
      return $raw_slug;
    }

    return '';
  }
}

if (!function_exists('tmw_hybrid_scan_title_contains_phrase')) {
  function tmw_hybrid_scan_title_contains_phrase($haystack, $needle) {
    $haystack = tmw_hybrid_scan_prepare_text($haystack);
    $needle   = tmw_hybrid_scan_prepare_text($needle);

    if ($haystack === '' || $needle === '') {
      return false;
    }

    if (strpos($haystack, $needle) !== false) {
      return true;
    }

    $compact_haystack = str_replace(' ', '', $haystack);
    $compact_needle   = str_replace(' ', '', $needle);
    if ($compact_needle !== '' && strpos($compact_haystack, $compact_needle) !== false) {
      return true;
    }

    $hyphen_needle = str_replace(' ', '-', $needle);
    if ($hyphen_needle !== $needle && strpos($haystack, $hyphen_needle) !== false) {
      return true;
    }

    return false;
  }
}

if (!function_exists('tmw_hybrid_scan_prepare_text')) {
  function tmw_hybrid_scan_prepare_text($text) {
    $text = is_scalar($text) ? (string) $text : '';
    if ($text === '') {
      return '';
    }

    $text = wp_strip_all_tags($text);
    if (function_exists('remove_accents')) {
      $text = remove_accents($text);
    }
    $text = strtolower($text);
    $text = trim(preg_replace('/\s+/', ' ', $text));

    return $text;
  }
}

if (!function_exists('tmw_hybrid_scan_log_result')) {
  function tmw_hybrid_scan_log_result($post_id, array $model_slugs, array $directory) {
    $title = get_post_field('post_title', $post_id);
    $title = $title !== null ? (string) $title : '';
    if ($title === '') {
      $title = 'Post #' . (int) $post_id;
    }

    $title = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($title)));

    if (empty($model_slugs)) {
      $summary = 'NONE';
    } else {
      $summary = implode(', ', $model_slugs);
    }

    error_log(sprintf('[TMW-HYBRID-SCAN] %s → %s', $title, $summary));
  }
}
