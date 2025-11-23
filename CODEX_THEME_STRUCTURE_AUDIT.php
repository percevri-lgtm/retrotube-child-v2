<?php
// Exit if accessed directly
if (!defined('ABSPATH')) { exit; }

/**
 * CODEX: Child Theme Structure & Hook Map (read-only audit)
 * - Scans the child theme dir and emits JSON + Markdown reports.
 * - NO front-end changes, admin-only on-demand, WP-CLI supported.
 *
 * Outputs:
 *   /wp-content/codex-reports/theme-structure-YYYYmmdd-HHMM.json
 *   /wp-content/codex-reports/theme-structure-YYYYmmdd-HHMM.md
 *
 * Debug log:
 *   [TMW-STRUCTURE-AUDIT] ...
 */

if (!function_exists('tmw_theme_structure_audit')) {
    function tmw_theme_structure_audit(bool $echo_summary = false) {
        if (!function_exists('wp_get_theme')) { return; }

        $root = wp_normalize_path(get_stylesheet_directory());
        $theme = wp_get_theme();
        $now_gmt = gmdate('c');
        $report_dir = WP_CONTENT_DIR . '/codex-reports';
        if (!is_dir($report_dir)) { wp_mkdir_p($report_dir); }

        // Helpers
        $rel = function(string $p) use ($root) {
            $p = wp_normalize_path($p);
            return ltrim(str_replace($root, '', $p), '/');
        };

        $should_skip = function(string $path) {
            $skip_bins = [
                '/.git/', '/.github/', '/node_modules/', '/vendor/', '/backups/',
                '/.cache/', '/.idea/', '/.vscode/', '/dist/', '/build/',
                '/wp-content/uploads/'
            ];
            foreach ($skip_bins as $frag) {
                if (strpos($path, $frag) !== false) {
                    return true;
                }
            }
            return false;
        };

        // Build inventory
        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $files = [];
        $dirs  = [];
        foreach ($rii as $item) {
            $path = wp_normalize_path($item->getPathname());
            if ($should_skip($path)) { continue; }
            if ($item->isDir()) { $dirs[] = $path; continue; }

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $size = (int) $item->getSize();
            $mtime = (int) $item->getMTime();
            $lines = null;

            if ($size <= 2 * 1024 * 1024) {
                $buf = @file_get_contents($path);
                if ($buf !== false) {
                    $lines = substr_count($buf, "\n") + 1;
                }
            }

            $files[] = [
                'path'  => $rel($path),
                'ext'   => $ext,
                'size'  => $size,
                'mtime' => gmdate('c', $mtime),
                'lines' => $lines,
            ];
        }

        // ASCII tree
        $tree = (function($base, $skip_cb) {
            $makeTree = function($dir, $prefix = '') use (&$makeTree, $skip_cb) {
                $items = array_values(array_filter(scandir($dir), function($n){
                    return $n !== '.' && $n !== '..';
                }));
                natcasesort($items);
                $lines = [];
                $count = count($items);
                $i = 0;
                foreach ($items as $name) {
                    $i++;
                    $full = wp_normalize_path($dir . '/' . $name);
                    if ($skip_cb($full)) { continue; }
                    $isLast = ($i === $count);
                    $branch = $isLast ? '└── ' : '├── ';
                    $pad    = $isLast ? '    ' : '│   ';
                    if (is_dir($full)) {
                        $lines[] = $prefix . $branch . $name . '/';
                        $lines = array_merge($lines, $makeTree($full, $prefix . $pad));
                    } else {
                        $lines[] = $prefix . $branch . $name;
                    }
                }
                return $lines;
            };
            $rootName = basename($base) . '/';
            return $rootName . "\n" . implode("\n", $makeTree($base));
        })($root, $should_skip);

        // Grep patterns across PHP files
        $rx = [
            'actions'     => '/add_action\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,/i',
            'filters'     => '/add_filter\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,/i',
            'enqueue_css' => '/wp_enqueue_style\s*\(\s*[\'"]([^\'"]+)[\'"]/i',
            'enqueue_js'  => '/wp_enqueue_script\s*\(\s*[\'"]([^\'"]+)[\'"]/i',
            'shortcodes'  => '/add_shortcode\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,/i',
            'rest'        => '/register_rest_route\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/i',
            'ajax_in'     => '/add_action\s*\(\s*[\'"]wp_ajax_([A-Za-z0-9_]+)[\'"]/i',
            'ajax_out'    => '/add_action\s*\(\s*[\'"]wp_ajax_nopriv_([A-Za-z0-9_]+)[\'"]/i',
            'cpt'         => '/register_post_type\s*\(\s*[\'"]([^\'"]+)[\'"]/i',
            'tax'         => '/register_taxonomy\s*\(\s*[\'"]([^\'"]+)[\'"]/i',
            'tpl_part'    => '/get_template_part\s*\(\s*[\'"]([^\'"]+)[\'"](\s*,\s*[\'"]([^\'"]+)[\'"])?/i',
            'locate_tpl'  => '/locate_template\s*\(\s*\[([^\]]+)\]/i',
            'opts'        => '/\b(get|update|delete)_option\s*\(\s*[\'"]([^\'"]+)[\'"]/i',
            'transients'  => '/\b(get|set|delete)_transient\s*\(\s*[\'"]([^\'"]+)[\'"]/i',
            'meta'        => '/\b(get|update|delete)_post_meta\s*\(\s*[^,]+,\s*[\'"]([^\'"]+)[\'"]/i',
            'head'        => '/add_action\s*\(\s*[\'"]wp_head[\'"]\s*,/i',
            'foot'        => '/add_action\s*\(\s*[\'"]wp_footer[\'"]\s*,/i',
        ];

        $col = [
            'actions'=>[], 'filters'=>[], 'enqueue'=>['styles'=>[], 'scripts'=>[]],
            'shortcodes'=>[], 'rest'=>[], 'ajax'=>['private'=>[], 'public'=>[]],
            'cpt'=>[], 'tax'=>[], 'templates'=>['parts'=>[], 'locate'=>[]],
            'options'=>[], 'transients'=>[], 'meta_keys'=>[], 'head_injectors'=>[], 'footer_injectors'=>[],
        ];

        foreach ($files as $f) {
            if ($f['ext'] !== 'php') { continue; }
            $path = $root . '/' . $f['path'];
            $buf = @file_get_contents($path);
            if ($buf === false) { continue; }

            if (preg_match_all($rx['actions'], $buf, $m)) {
                foreach ($m[1] as $h) {
                    $col['actions'][$h][] = $f['path'];
                }
            }
            if (preg_match_all($rx['filters'], $buf, $m)) {
                foreach ($m[1] as $h) {
                    $col['filters'][$h][] = $f['path'];
                }
            }
            if (preg_match_all($rx['enqueue_css'], $buf, $m)) {
                foreach ($m[1] as $h) {
                    $col['enqueue']['styles'][$h][] = $f['path'];
                }
            }
            if (preg_match_all($rx['enqueue_js'], $buf, $m)) {
                foreach ($m[1] as $h) {
                    $col['enqueue']['scripts'][$h][] = $f['path'];
                }
            }
            if (preg_match_all($rx['shortcodes'], $buf, $m)) {
                foreach ($m[1] as $tag) {
                    $col['shortcodes'][$tag][] = $f['path'];
                }
            }
            if (preg_match_all($rx['rest'], $buf, $m)) {
                $n = count($m[0]);
                for ($i = 0; $i < $n; $i++) {
                    $col['rest']["{$m[1][$i]} {$m[2][$i]}"][] = $f['path'];
                }
            }
            if (preg_match_all($rx['ajax_in'], $buf, $m)) {
                foreach ($m[1] as $act) {
                    $col['ajax']['private'][$act][] = $f['path'];
                }
            }
            if (preg_match_all($rx['ajax_out'], $buf, $m)) {
                foreach ($m[1] as $act) {
                    $col['ajax']['public'][$act][] = $f['path'];
                }
            }
            if (preg_match_all($rx['cpt'], $buf, $m)) {
                foreach ($m[1] as $pt) {
                    $col['cpt'][$pt][] = $f['path'];
                }
            }
            if (preg_match_all($rx['tax'], $buf, $m)) {
                foreach ($m[1] as $tx) {
                    $col['tax'][$tx][] = $f['path'];
                }
            }
            if (preg_match_all($rx['tpl_part'], $buf, $m, PREG_SET_ORDER)) {
                foreach ($m as $match) {
                    $slug = $match[1];
                    $name = $match[3] ?? '';
                    $col['templates']['parts']["$slug" . ($name !== '' ? ":$name" : '')][] = $f['path'];
                }
            }
            if (preg_match_all($rx['locate_tpl'], $buf, $m)) {
                foreach ($m[1] as $list) {
                    $col['templates']['locate'][] = [
                        'file' => $f['path'],
                        'candidates' => trim($list),
                    ];
                }
            }
            if (preg_match_all($rx['opts'], $buf, $m)) {
                $n = count($m[0]);
                for ($i = 0; $i < $n; $i++) {
                    $col['options'][$m[2][$i]][] = [$m[1][$i], $f['path']];
                }
            }
            if (preg_match_all($rx['transients'], $buf, $m)) {
                $n = count($m[0]);
                for ($i = 0; $i < $n; $i++) {
                    $col['transients'][$m[2][$i]][] = [$m[1][$i], $f['path']];
                }
            }
            if (preg_match_all($rx['meta'], $buf, $m)) {
                foreach ($m[2] as $key) {
                    $col['meta_keys'][$key][] = $f['path'];
                }
            }
            if (preg_match($rx['head'], $buf)) {
                $col['head_injectors'][] = $f['path'];
            }
            if (preg_match($rx['foot'], $buf)) {
                $col['footer_injectors'][] = $f['path'];
            }
        }

        // Assemble report
        $summary = [
            'theme' => [
                'name'    => $theme->get('Name'),
                'version' => $theme->get('Version'),
                'template'=> $theme->get('Template'),
                'stylesheet_dir' => $root,
            ],
            'generated_at_gmt' => $now_gmt,
            'counts' => [
                'dirs'      => count($dirs),
                'files'     => count($files),
                'php_files' => count(array_filter($files, fn($f) => $f['ext'] === 'php')),
                'css_files' => count(array_filter($files, fn($f) => $f['ext'] === 'css')),
                'tpl_parts' => count($col['templates']['parts']),
            ],
            'tree_ascii' => $tree,
            'inventory'  => $files,
            'map'        => $col,
        ];

        // Write files
        $stamp = gmdate('Ymd-His');
        $json_file = $report_dir . "/theme-structure-$stamp.json";
        $md_file   = $report_dir . "/theme-structure-$stamp.md";

        @file_put_contents($json_file, wp_json_encode($summary, JSON_PRETTY_PRINT));

        // Markdown view
        $md  = "# RetroTube Child — Structure & Hook Map\n";
        $md .= "- Generated (GMT): **$now_gmt**\n";
        $md .= "- Theme: **{$summary['theme']['name']}** v{$summary['theme']['version']} (template: {$summary['theme']['template']})\n";
        $md .= "- Files: {$summary['counts']['files']} (PHP: {$summary['counts']['php_files']}, CSS: {$summary['counts']['css_files']})\n\n";
        $md .= "## Directory Tree\n```\n{$summary['tree_ascii']}\n```\n";
        $md .= "## Hooks\n**Actions:** " . implode(', ', array_keys($col['actions'])) . "\n\n";
        $md .= "**Filters:** " . implode(', ', array_keys($col['filters'])) . "\n\n";
        $md .= "## Enqueues\nStyles: " . implode(', ', array_keys($col['enqueue']['styles'])) . "\n\n";
        $md .= "Scripts: " . implode(', ', array_keys($col['enqueue']['scripts'])) . "\n\n";
        $md .= "## Shortcodes\n" . implode(', ', array_keys($col['shortcodes'])) . "\n\n";
        $md .= "## REST Routes\n" . implode(', ', array_keys($col['rest'])) . "\n\n";
        $md .= "## AJAX\nPrivate: " . implode(', ', array_keys($col['ajax']['private'])) . "\n\n";
        $md .= "Public: " . implode(', ', array_keys($col['ajax']['public'])) . "\n\n";
        $md .= "## CPTs\n" . implode(', ', array_keys($col['cpt'])) . "\n\n";
        $md .= "## Taxonomies\n" . implode(', ', array_keys($col['tax'])) . "\n\n";
        $md .= "## Template Parts Used\n" . implode(', ', array_keys($col['templates']['parts'])) . "\n\n";
        $md .= "## Option Keys Touched\n" . implode(', ', array_keys($col['options'])) . "\n\n";
        $md .= "## Transients\n" . implode(', ', array_keys($col['transients'])) . "\n\n";
        $md .= "## Post Meta Keys\n" . implode(', ', array_keys($col['meta_keys'])) . "\n\n";
        $md .= "## Inline Injectors\nwp_head: " . implode(', ', array_unique($col['head_injectors'])) . "\n\n";
        $md .= "wp_footer: " . implode(', ', array_unique($col['footer_injectors'])) . "\n\n";

        @file_put_contents($md_file, $md);

        // Log & optional echo
        $log = sprintf(
            '[TMW-STRUCTURE-AUDIT] files=%d php=%d actions=%d filters=%d wrote=%s',
            $summary['counts']['files'],
            $summary['counts']['php_files'],
            count($col['actions']),
            count($col['filters']),
            wp_normalize_path($md_file)
        );
        error_log($log);

        if ($echo_summary) {
            header('Content-Type: text/plain; charset=utf-8');
            echo "OK: Theme Structure Audit\n";
            echo $log . "\n";
            echo "JSON: " . wp_normalize_path($json_file) . "\n";
            echo "MD:   " . wp_normalize_path($md_file) . "\n";
        }
    }
}

// WP-CLI support: wp tmw:theme-structure
if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
    WP_CLI::add_command('tmw:theme-structure', function() {
        tmw_theme_structure_audit(true);
    });
}

// Auto-run only when explicitly triggered by our loader in functions.php or by WP-CLI.
