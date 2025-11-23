<?php
if (!defined('ABSPATH')) { exit; }

// Bridge: load legacy model hooks (taxonomies, banner helpers, AW integration).
$models_legacy = TMW_CHILD_PATH . '/inc/tmw-model-hooks.php';
if (is_readable($models_legacy)) {
    require_once $models_legacy;
}
