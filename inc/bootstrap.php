<?php
if (!defined('ABSPATH')) { exit; }

/** Lightweight autoload for namespaced classes (optional future use) */
spl_autoload_register(function($class){
    $pfx = 'TMW\\Child\\';
    if (strpos($class, $pfx) !== 0) return;
    $rel = str_replace('\\\\', '/', substr($class, strlen($pfx)));
    $file = __DIR__ . '/classes/' . $rel . '.php';
    if (is_readable($file)) require $file;
});

/** Constants shared across modules */
require_once __DIR__ . '/constants.php';

// Shared CLI/helpers for hybrid model scan.
$hybrid_scan = TMW_CHILD_PATH . '/assets/php/tmw-hybrid-model-scan.php';
if (is_readable($hybrid_scan)) {
    require_once $hybrid_scan;
}

/** Setup & assets */
require_once __DIR__ . '/setup.php';
require_once __DIR__ . '/enqueue.php';
require_once __DIR__ . '/frontend/performance.php';

/** Front-end features */
require_once __DIR__ . '/frontend/accessibility.php';
require_once __DIR__ . '/frontend/model-banner.php';
require_once __DIR__ . '/frontend/flipboxes.php';
require_once __DIR__ . '/frontend/comments.php';
require_once __DIR__ . '/frontend/taxonomies.php';
require_once __DIR__ . '/frontend/shortcodes.php';
require_once __DIR__ . '/frontend/template-tags.php';

/** SEO helpers */
require_once __DIR__ . '/seo/schema.php';

/** Admin-only */
if (is_admin()) {
    require_once __DIR__ . '/admin/metabox-model-banner.php';
    require_once __DIR__ . '/admin/editor-tweaks.php';
}

/** Debug toggle (harmless log pings) */
add_action('init', function () {
    if (defined('TMW_DEBUG') && TMW_DEBUG) {
        error_log('[TMW-V410] bootstrap loaded');
    }
}, 1);
