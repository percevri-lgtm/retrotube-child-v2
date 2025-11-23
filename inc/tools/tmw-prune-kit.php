<?php
if (!defined('ABSPATH')) { exit; }

/**
 * TMW Theme Prune Kit (v3.7.0)
 * - Admin-only “report then prune” flow.
 * - Report: finds unreferenced PHP/templates/assets in the child theme.
 * - Apply: moves selected files into /{theme}/.trash/YYYYmmdd-HHMMSS/ (reversible).
 *
 * Usage:
 *   1) Visit any front-end URL as admin with:  ?tmw_prune=report
 *      -> Shows a small on-screen summary and writes a JSON report to /uploads/tmw-prune/.
 *   2) Click the “Apply (known-safe)” link or visit: ?tmw_prune=apply_known&_wpnonce=...
 *      -> Moves only hardcoded safe legacy files (already deleted by PR) if any reappear.
 *   3) (Optional, after review) Apply all suggested removals:
 *        ?tmw_prune=apply_all&_wpnonce=...
 *      -> Moves every “unused” file found in latest report to .trash/. Use with care.
 */

add_action('admin_bar_menu', function($wp_admin_bar){
    if (!current_user_can('manage_options')) return;

    $report_url = add_query_arg('tmw_prune', 'report', home_url('/'));
    $wp_admin_bar->add_node([
        'id'    => 'tmw-prune',
        'title' => 'TMW Prune',
        'href'  => $report_url,
        'meta'  => ['title' => 'Generate prune report']
    ]);
}, 100);

add_action('template_redirect', function () {
    if (!current_user_can('manage_options')) return;

    $action = isset($_GET['tmw_prune']) ? sanitize_key($_GET['tmw_prune']) : '';
    if (!$action) return;

    if ($action === 'report') {
        $report = tmw_prune_build_report();
        tmw_prune_emit_footer($report, 'report');
        return;
    }

    if (in_array($action, ['apply_known','apply_all'], true)) {
        check_admin_referer('tmw_prune_nonce');
        $report = tmw_prune_build_report(); // fresh snapshot
        $result = tmw_prune_apply($report, $action === 'apply_all');
        tmw_prune_emit_footer($result, $action);
        return;
    }
}, 1);

/* ---------- core ---------- */

function tmw_prune_build_report() {
    $theme_dir = trailingslashit(get_stylesheet_directory());
    $theme_uri = trailingslashit(get_stylesheet_directory_uri());

    // 1) Gather all files
    $all = [];
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($theme_dir, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $file) {
        /** @var SplFileInfo $file */
        $path = str_replace($theme_dir, '', $file->getPathname());
        // Ignore .trash/ and dotfiles
        if (strpos($path, '.trash/') === 0 || substr($file->getFilename(), 0, 1) === '.') continue;
        $all[] = $path;
    }

    // 2) Build keep lists from:
    //    - Must-keep baseline
    $keep = [
        'functions.php', 'style.css', 'screenshot.png',
        'page-videos.php', // we know this is in use
    ];

    //    - Page templates actually assigned in WP
    $templates_in_use = [];
    $pages = get_posts(['post_type'=>'page','post_status'=>'any','numberposts'=>-1,'fields'=>'ids']);
    foreach ($pages as $pid) {
        $tpl = get_post_meta($pid, '_wp_page_template', true);
        if ($tpl && $tpl !== 'default') { $templates_in_use[] = $tpl; }
    }
    $keep = array_merge($keep, $templates_in_use);

    //    - PHP includes/get_template_part/locate_template/enqueues (static scan)
    $referenced = tmw_prune_static_scan_references($theme_dir);
    $keep = array_merge($keep, $referenced);

    // 3) Normalise
    $keep = array_values(array_unique(array_filter($keep)));
    $all  = array_values(array_unique($all));

    // 4) Compute unused
    $unused = array_values(array_diff($all, $keep));

    // 5) Known-safe legacy (will be moved on "apply_known")
    $known_safe = array_values(array_filter($all, function($p){
        return in_array($p, [
            'inc/tmw-filter-canonical.php',
            'inc/tmw-filter-links.php',
            'CODEX_video_link_guard.php',
            'inc/slot-width-audit.php',
            'inc/slot-width-audit-dev.php',
            'inc/mobile-banner-audit.php',
        ], true);
    }));

    // 6) Persist report
    $upload = wp_upload_dir();
    $outdir = trailingslashit($upload['basedir']) . 'tmw-prune';
    if (!is_dir($outdir)) wp_mkdir_p($outdir);
    $fname  = $outdir . '/report-' . date('Ymd-His') . '.json';
    $data = [
        'generated_at' => current_time('mysql'),
        'theme_dir'    => $theme_dir,
        'total_files'  => count($all),
        'keep'         => $keep,
        'unused'       => $unused,
        'known_safe'   => $known_safe,
    ];
    file_put_contents($fname, wp_json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

    // 7) Return with helper links
    $nonce = wp_create_nonce('tmw_prune_nonce');
    return [
        'json'        => $fname,
        'keep'        => $keep,
        'unused'      => $unused,
        'known_safe'  => $known_safe,
        'apply_known' => add_query_arg(['tmw_prune'=>'apply_known','_wpnonce'=>$nonce], home_url('/')),
        'apply_all'   => add_query_arg(['tmw_prune'=>'apply_all','_wpnonce'=>$nonce], home_url('/')),
    ];
}

function tmw_prune_static_scan_references($theme_dir) {
    $refs = [];

    $php_files = [];
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($theme_dir, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $file) {
        if (strtolower($file->getExtension()) === 'php') {
            $php_files[] = $file->getPathname();
        }
    }

    $add = function($path) use (&$refs, $theme_dir) {
        $path = trim($path);
        if (!$path) return;
        // Normalise relative paths
        $path = ltrim(str_replace(['\\'], ['/'], $path), '/');
        if (file_exists($theme_dir . $path)) $refs[] = $path;
    };

    foreach ($php_files as $php) {
        $code = @file_get_contents($php);
        if ($code === false) continue;

        // include/require
        if (preg_match_all('#\b(?:require|require_once|include|include_once)\s*\(\s*[\'\"]([^\'\"]+)[\'\"]\s*\)#i', $code, $m)) {
            foreach ($m[1] as $p) { $add($p); }
        }

        // get_template_part('slug','name')
        if (preg_match_all('#\bget_template_part\(\s*[\'\"]([^\'\"]+)[\'\"]\s*(?:,\s*[\'\"]([^\'\"]+)[\'\"])?\s*\)#i', $code, $m, PREG_SET_ORDER)) {
            foreach ($m as $g) {
                $slug = $g[1];
                $name = isset($g[2]) ? $g[2] : '';
                $candidates = [];
                if ($name !== '') $candidates[] = "$slug-$name.php";
                $candidates[] = "$slug.php";
                foreach ($candidates as $cand) { $add($cand); }
            }
        }

        // locate_template([...])
        if (preg_match_all('#\blocate_template\(\s*\[\s*(.*?)\s*\]#is', $code, $m)) {
            foreach ($m[1] as $arr) {
                if (preg_match_all('#[\'\"]([^\'\"]+)[\'\"]#', $arr, $mm)) {
                    foreach ($mm[1] as $p) { $add($p); }
                }
            }
        }

        // wp_enqueue_style/script URL literals (best effort)
        if (preg_match_all('#wp_enqueue_(?:style|script)\s*\([^;]*[\'\"]([^\'\"]+\.(?:css|js))[\'\"]#i', $code, $m)) {
            foreach ($m[1] as $asset) {
                // try to map '/assets/...' to file
                $asset = preg_replace('#^https?://[^/]+/#','',$asset);
                $add($asset);
            }
        }
    }

    return array_values(array_unique($refs));
}

function tmw_prune_apply($report, $all = false) {
    $theme_dir = trailingslashit(get_stylesheet_directory());
    $stamp     = date('Ymd-His');
    $trash_dir = $theme_dir . '.trash/' . $stamp . '/';

    if (!is_dir($trash_dir)) wp_mkdir_p($trash_dir);

    $targets = $all ? $report['unused'] : $report['known_safe'];
    $moved = [];
    $failed = [];

    foreach ($targets as $rel) {
        $src = $theme_dir . $rel;
        if (!file_exists($src)) continue;

        $dst = $trash_dir . $rel;
        $dst_dir = dirname($dst);
        if (!is_dir($dst_dir)) wp_mkdir_p($dst_dir);

        if (@rename($src, $dst)) {
            $moved[] = $rel;
        } else {
            $failed[] = $rel;
        }
    }

    return [
        'trash_dir' => $trash_dir,
        'moved'     => $moved,
        'failed'    => $failed,
    ];
}

function tmw_prune_emit_footer($data, $mode) {
    add_action('wp_footer', function () use ($data, $mode) {
        echo '<div style="position:fixed;z-index:999999;bottom:12px;right:12px;background:#111;color:#fff;padding:14px 16px;border-radius:8px;max-width:520px;font:14px/1.4 sans-serif;box-shadow:0 8px 24px rgba(0,0,0,.35)">';
        if ($mode === 'report') {
            echo '<strong>✅ TMW Prune Report generated</strong><br>';
            echo 'JSON: <code>'.esc_html($data['json']).'</code><br>';
            echo '<div style="margin-top:6px">';
            echo '<a href="'.esc_url($data['apply_known']).'" style="color:#0bf">Apply (known-safe)</a>&nbsp;&nbsp;|&nbsp;&nbsp;';
            echo '<a href="'.esc_url($data['apply_all']).'" style="color:#f66" onclick="return confirm(\'Apply ALL suggested removals? Files will be moved to .trash.\')">Apply ALL (dangerous)</a>';
            echo '</div>';
            echo '<details style="margin-top:8px"><summary>Keep list ('.count($data['keep']).')</summary><pre style="white-space:pre-wrap">'.esc_html(print_r($data['keep'], true)).'</pre></details>';
            echo '<details><summary>Unused candidates ('.count($data['unused']).')</summary><pre style="white-space:pre-wrap">'.esc_html(print_r($data['unused'], true)).'</pre></details>';
            echo '<details><summary>Known-safe legacy ('.count($data['known_safe']).')</summary><pre style="white-space:pre-wrap">'.esc_html(print_r($data['known_safe'], true)).'</pre></details>';
        } else {
            echo '<strong>🧹 TMW Prune: '.esc_html($mode).'</strong><br>';
            if (!empty($data['moved'])) {
                echo 'Moved to: <code>'.esc_html($data['trash_dir']).'</code><br>';
                echo 'Files moved ('.count($data['moved']).')';
                echo '<details><summary>Show</summary><pre style="white-space:pre-wrap">'.esc_html(print_r($data['moved'], true)).'</pre></details>';
            }
            if (!empty($data['failed'])) {
                echo '<div style="color:#f88">Failed (check permissions):</div>';
                echo '<pre style="white-space:pre-wrap">'.esc_html(print_r($data['failed'], true)).'</pre>';
            }
            echo '<div style="margin-top:6px"><a href="'.esc_url(add_query_arg('tmw_prune','report',home_url('/'))).'" style="color:#0bf">Back to Report</a></div>';
        }
        echo '</div>';
    }, 10000);
}
