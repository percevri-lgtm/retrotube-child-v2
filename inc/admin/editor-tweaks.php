<?php
if (!defined('ABSPATH')) { exit; }

// Bridge: keep legacy admin tooling (metabox tweaks, audits) until refactored.
$admin_legacy = TMW_CHILD_PATH . '/inc/tmw-admin-tools.php';
if (is_readable($admin_legacy)) {
    require_once $admin_legacy;
}
