<?php
if (!defined('ABSPATH')) { exit; }

// Temporary bridge: keep existing meta box logic active until Phase 2 migration.
$legacy_metabox = __DIR__ . '/model-banner-meta-box.php';
if (is_readable($legacy_metabox)) {
    require_once $legacy_metabox;
}
