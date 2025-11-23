<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Inject an inline icon for the “Videos” top-nav item.
 * Changes:
 * - No restriction by theme_location (works with any slug).
 * - Top-level items only (depth === 0) so footer/secondary menus stay untouched.
 * - Icon injected *inside* the <a> tag.
 * - Skips items that already contain an icon.
 * - Matches /videos (and /xx/videos/) by last path segment.
 * - Includes FA4 + FA5 classes for maximum compatibility.
 */
add_filter('walker_nav_menu_start_el', function ($item_output, $item, $depth, $args) {
    // Header menus are usually top-level items; ignore submenus.
    if ($depth !== 0) {
        return $item_output;
    }

    // If the item already contains an icon (<i> or <svg>), do nothing (avoid duplicates).
    if (stripos($item_output, '<i ') !== false || stripos($item_output, '<svg') !== false) {
        return $item_output;
    }

    // Robust detector: is this URL the "Videos" page?
    $is_videos = static function ($url): bool {
        if (!$url) return false;
        $home = trailingslashit(home_url('/'));
        $url  = preg_replace('#^' . preg_quote($home, '#') . '#i', '/', $url);
        $url  = strtok($url, '?#');
        $path = trim(urldecode((string) $url), '/');
        if ($path === '') return false;
        $segments = explode('/', $path);
        $last     = strtolower(end($segments));
        return ($last === 'videos');
    };

    if (!$is_videos($item->url ?? '')) {
        return $item_output;
    }

    // Find the opening <a ...> and inject right after its '>'.
    $a_open = stripos($item_output, '<a ');
    if ($a_open === false) {
        return $item_output; // unexpected markup; fail safe
    }
    $a_gt = strpos($item_output, '>', $a_open);
    if ($a_gt === false) {
        return $item_output; // unexpected markup; fail safe
    }

    // Both FA4 and FA5 classes; whichever stack is present will render.
    $icon_html = '<i class="fa fa-video-camera fas fa-video" aria-hidden="true" role="img"></i> ';
    return substr($item_output, 0, $a_gt + 1) . $icon_html . substr($item_output, $a_gt + 1);
}, 10, 4);
