<?php
if (!defined('ABSPATH')) { exit; }

add_action('after_setup_theme', function () {
    // Keep existing supports; do NOT add new.
    add_image_size('tmw-model-hero-land', 1440, 810, true);
    add_image_size('tmw-model-hero-banner', 1200, 350, true);
    add_image_size('tmw-front-optimized', 400, 600, true);
    // ... add any existing supports previously in functions.php (moved verbatim)
}, 10);
