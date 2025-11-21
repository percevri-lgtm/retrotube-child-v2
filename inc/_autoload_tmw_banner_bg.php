<?php
if (!defined('ABSPATH')) { exit; }
// Minimal autoload for banner helpers without touching functions.php
$files = array(
    get_stylesheet_directory() . '/inc/tmw-admin-model-banner-css.php',
    get_stylesheet_directory() . '/inc/tmw-banner-bg-single.php',
);

foreach ($files as $inc) {
    if (file_exists($inc)) {
        require_once $inc;
    }
}
