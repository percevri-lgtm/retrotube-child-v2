<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Hardened binding: attach taxonomy "models" to CPT "video" after all CPTs are registered.
 * Idempotent and quiet (no duplicates). Logs once in WP_DEBUG environments.
 */

function tmw_bind_models_to_video_once() {
    static $done = false;
    if ($done) { return; }

    // Bail until both taxonomy and CPT exist
    if ( !taxonomy_exists('models') || !post_type_exists('video') ) { return; }

    // Only bind if not already bound
    $tax = get_taxonomy('models');
    $bound = is_object($tax) && in_array('video', (array) $tax->object_type, true);

    if (!$bound) {
        register_taxonomy_for_object_type('models', 'video');
        if (defined('TMW_DEBUG') && TMW_DEBUG) {
            error_log('[TMW FIX v1.6.0] models bound to video post type (idempotent)');
        }
    } else {
        if (defined('TMW_DEBUG') && TMW_DEBUG) {
            error_log('[TMW FIX v1.6.0] models already bound to video');
        }
    }

    $done = true; // ensure single run per request
}

/**
 * Run late enough to see CPTs from plugins, and also react if "video" registers even later.
 *  - init @ 200: after most plugins/themes.
 *  - registered_post_type: if "video" appears late, bind immediately.
 *  - wp_loaded @ 999: final safety net before rendering.
 */
add_action('init', 'tmw_bind_models_to_video_once', 200);
add_action('registered_post_type', function($pt){
    if ($pt === 'video') { tmw_bind_models_to_video_once(); }
}, 10);
add_action('wp_loaded', 'tmw_bind_models_to_video_once', 999);


/**
 * Quiet the audit noise: log a warning only if, at the end of wp_loaded, the binding is STILL missing.
 * (Does not affect UI; just reduces repeated logs.)
 */
add_action('wp_loaded', function () {
    if (!taxonomy_exists('models')) { return; }
    if (!post_type_exists('video')) { return; }
    $tax = get_taxonomy('models');
    $bound = is_object($tax) && in_array('video', (array) $tax->object_type, true);
    if (!$bound && defined('TMW_DEBUG') && TMW_DEBUG) {
        error_log('[TMW-TAX-AUDIT-UI] Final check: models not bound to video after wp_loaded — investigate CPT registration.');
    }
}, 1000);
