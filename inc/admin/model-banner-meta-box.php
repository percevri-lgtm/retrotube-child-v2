<?php
/**
 * Shared banner frame helpers for the model banner meta box.
 */

if (!defined('ABSPATH')) {
  exit;
}

add_action('admin_init', function () {
  $base = get_stylesheet_directory();
  $uri  = get_stylesheet_directory_uri();

  $banner_path = $base . '/admin/css/admin-banners.css';
  if (file_exists($banner_path)) {
    wp_register_style(
      'tmw-admin-banner-style',
      $uri . '/admin/css/admin-banners.css',
      [],
      filemtime($banner_path) ?: null
    );
  }

  $align_path = $base . '/admin/css/tmw-banner-admin.css';
  if (file_exists($align_path)) {
    wp_register_style(
      'tmw-banner-admin-align',
      $uri . '/admin/css/tmw-banner-admin.css',
      [],
      filemtime($align_path) ?: null
    );
  }
});

add_action('admin_enqueue_scripts', function ($hook) {
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;

  $should_enqueue = false;

  if ($hook === 'term.php' && $screen && $screen->taxonomy === 'models') {
    $should_enqueue = true;
  } elseif ($hook === 'post.php') {
    $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ($post_id && get_post_type($post_id) === 'model') {
      $should_enqueue = true;
    }
  }

  if (!$should_enqueue) {
    return;
  }

  if (wp_style_is('tmw-admin-banner-style', 'registered')) {
    wp_enqueue_style('tmw-admin-banner-style');
  }

  if (wp_style_is('tmw-banner-admin-align', 'registered')) {
    wp_enqueue_style('tmw-banner-admin-align');
  }
});
