<?php
/**
 * CODEX_AUDIT_mobile_hero_v3_7_1a.php (updated for parity v3.7.5)
 * Enhanced repo-wide audit for MOBILE hero parity conflicts (no front-end changes).
 *
 * Logs to /wp-content/debug.log using [TMW-MOBILE-HERO-AUDIT]
 *
 * Run:
 *   wp eval 'include get_stylesheet_directory()."/CODEX_AUDIT_mobile_hero_v3_7_1a.php"; tmw_mobile_hero_audit_v3_7_1a();'
 */

if (!function_exists('tmw_mobile_hero_audit_v3_7_1a')):

function tmw_mobile_hero_audit_v3_7_1a() {
    if (!function_exists('get_stylesheet_directory')) {
        error_log('[TMW-MOBILE-HERO-AUDIT] ERROR: WordPress not loaded. Run with wp-cli: wp eval \'include get_stylesheet_directory()."/CODEX_AUDIT_mobile_hero_v3_7_1a.php"; tmw_mobile_hero_audit_v3_7_1a();\'');
        return;
    }

    $root      = realpath(get_stylesheet_directory());
    $styleCss  = $root . '/style.css';
    $started   = date('c');

    log_audit('=== v3.7.5 START ' . $started . ' ===');
    log_audit('Child theme root: ' . $root);

    // 1) Verify style.css Version and parity block placement
    $version      = 'unknown';
    $parityTitle  = 'v3.7.5 — Keep parity block last so mobile offsets win';
    $style        = is_readable($styleCss) ? file_get_contents($styleCss) : '';
    if ($style) {
        if (preg_match('/^\s*Version:\s*([0-9.]+)/mi', $style, $m)) {
            $version = trim($m[1]);
        }
        log_audit("style.css Version header: {$version}");

        $parityPos = mb_strpos($style, $parityTitle);
        if ($parityPos === false) {
            log_audit('WARN: v3.7.0 parity block NOT found in style.css.');
        } else {
            $after = mb_substr($style, $parityPos + mb_strlen($parityTitle));
            $heroSelectorRegex = '/\.(?:tmw-banner-frame|tmw-model-hero|tmw-banner-container)\b.*?\{/s';
            if (preg_match($heroSelectorRegex, $after)) {
                log_audit('FAIL: Hero-related CSS appears AFTER the parity block (likely overriding it).');
            } else {
                log_audit('PASS: No hero CSS detected after parity block.');
            }
        }

        // Check variable overrides inside mobile media
        $mobileMediaRegex = '/@media\s*\((?:max-width\s*:\s*(?:840|768)px|pointer\s*:\s*coarse)\)\s*\{([\s\S]*?)\}/mi';
        $overrides = 0;
        if (preg_match_all($mobileMediaRegex, $style, $mm)) {
            foreach ($mm[1] as $block) {
                if (preg_match('/--tmw-hero-vpos\s*:/', $block) || preg_match('/--offset-y\s*:/', $block)) {
                    $overrides++;
                }
            }
        }
        if ($overrides > 0) log_audit("FAIL: Found {$overrides} var override(s) for --tmw-hero-vpos/--offset-y inside mobile media blocks.");
        else log_audit('PASS: No variable overrides inside mobile media blocks.');
    } else {
        log_audit('ERROR: style.css not readable.');
    }

    // 2) Recursive scan for mobile hero conflicts
    $patterns = [
        // Conflicts we care about (with !important)
        'bad_rules' => [
            // object-position center/50% 50% with !important
            '/object-position\s*:\s*(?:center|50%\s*50%)\s*!important\s*;/i',
            // background-position (x|y|shorthand) center/50% with !important
            '/background-position(?:-[xy])?\s*:\s*(?:center|50%(?:\s*50%)?)\s*!important\s*;/i',
            // background shorthand containing center AND !important
            '/background\s*:\s*[^;]*\bcenter\b[^;]*!important\s*;/i',
            // y=50% !important on hero containers
            '/background-position-y\s*:\s*50%\s*!important\s*;/i',

            // NEW: object-fit not cover in hero rules (any importance)
            '/object-fit\s*:\s*(?!cover\b)[a-z-]+\s*(?:!important)?\s*;/i',
            // NEW: background-size not cover (any importance)
            '/background-size\s*:\s*(?!cover\b)[a-z-]+\s*(?:!important)?\s*;/i',
            // NEW: numeric/keyword background-position-y with !important (top/bottom/px/% )
            '/background-position-y\s*:\s*(?:\d+(?:\.\d+)?%|[0-9.]+(?:px|rem|vh|svh)|top|bottom)\s*!important\s*;/i',
            // NEW: any object/background position still using var(--offset-y)
            '/object-position\s*:[^;]*var\(--offset-y/i',
            '/background-position(?:-[xy])?\s*:[^;]*var\(--offset-y/i',
        ],
        // Mobile media gates
        'mobile_media' => '/@media\s*\((?:\s*max-width\s*:\s*(?:840|768)px\s*|\s*pointer\s*:\s*coarse\s*)\)/i',
        // Hero selectors
        'hero_sel' => '/\.(?:tmw-banner-frame|tmw-model-hero|tmw-banner-container|wp-post-image)\b/i',
    ];

    $scanExt = ['css','php','html'];
    $files   = tmw_rglob($root, $scanExt);
    $totalFinds = 0; $mobileFinds = 0;

    foreach ($files as $file) {
        $rel   = str_replace($root . DIRECTORY_SEPARATOR, '', $file);
        $isCss = substr($file, -4) === '.css';
        $txt   = @file_get_contents($file);
        if ($txt === false || $txt === '') continue;

        // Build CSS blobs to scan
        $cssBlobs = [];

        // 2.a Stylesheet content
        if ($isCss) {
            $cssBlobs[] = ['css' => $txt, 'kind' => 'css', 'origin' => $rel];
        }

        // 2.b Inline CSS via wp_add_inline_style()
        if (substr($file, -4) === '.php') {
            if (preg_match_all('/wp_add_inline_style\s*\(\s*[^,]+,\s*(["\'])(.*?)\1\s*\)/is', $txt, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[2] as $i => $pair) {
                    $rawCss = stripcslashes($pair[0]);
                    $cssBlobs[] = ['css' => $rawCss, 'kind' => 'inline', 'origin' => $rel . ':inline#' . ($i+1), 'base_offset' => $pair[1]];
                }
            }
        }

        // 2.c Raw <style>…</style> blocks in PHP/HTML
        if (preg_match_all('/<style[^>]*>([\s\S]*?)<\/style>/i', $txt, $sm, PREG_OFFSET_CAPTURE)) {
            foreach ($sm[1] as $i => $pair) {
                $rawCss = $pair[0];
                $cssBlobs[] = ['css' => $rawCss, 'kind' => 'raw-style', 'origin' => $rel . ':style#' . ($i+1), 'base_offset' => $pair[1]];
            }
        }

        foreach ($cssBlobs as $blob) {
            $css = $blob['css'];
            $origin = $blob['origin'];
            $baseOffset = isset($blob['base_offset']) ? (int)$blob['base_offset'] : 0;

            // Skip non-hero unless style.css
            if ($origin !== 'style.css' && !preg_match($patterns['hero_sel'], $css)) continue;

            // 2.1 Scan mobile @media blocks only
            if (preg_match_all('/@media[^\{]+\{([\s\S]*?\})\s*\}/i', $css, $mediaBlocks, PREG_OFFSET_CAPTURE)) {
                foreach ($mediaBlocks[0] as $mbIdx => $blockTuple) {
                    $block      = $blockTuple[0];
                    $blockStart = $blockTuple[1];
                    $isMobile   = preg_match($patterns['mobile_media'], $block);
                    if (!$isMobile) continue;

                    foreach ($patterns['bad_rules'] as $rx) {
                        if (preg_match_all($rx, $block, $mm, PREG_OFFSET_CAPTURE)) {
                            foreach ($mm[0] as $hit) {
                                $absPos  = $baseOffset + $blockStart + $hit[1];
                                $line    = tmw_line_of($blob['kind']==='css' ? $txt : $txt, $absPos);
                                $snippet = tmw_trim_snippet($block, $hit[1], 200);
                                $mobileFinds++; $totalFinds++;
                                log_audit("MOBILE CONFLICT {$origin}:{$line} :: " . preg_replace('/\s+/', ' ', $snippet));
                            }
                        }
                    }
                }
            }

            // 2.2 Global scan: hero selectors with conflicting decls (outside explicit @media)
            if (preg_match_all('/([^{]+)\{([^}]+)\}/', $css, $rules, PREG_OFFSET_CAPTURE)) {
                foreach ($rules as $idx => $r) {
                    if (!isset($r[0][0])) continue;
                    $ruleText   = $r[0][0];
                    $ruleStart  = $r[0][1];
                    $selector   = $r[1][0];
                    $decls      = $r[2][0];
                    if (!preg_match($patterns['hero_sel'], $selector)) continue;
                    foreach ($patterns['bad_rules'] as $rx) {
                        if (preg_match($rx, $decls, $m, PREG_OFFSET_CAPTURE)) {
                            $absPos  = $baseOffset + $ruleStart + $m[0][1];
                            $line    = tmw_line_of($txt, $absPos);
                            $snippet = tmw_collapse_ws(trim($selector)) . ' { ' . tmw_collapse_ws(trim($m[0][0])) . ' }';
                            $totalFinds++;
                            log_audit("GLOBAL CONFLICT {$origin}:{$line} :: {$snippet}");
                        }
                    }
                }
            }
        }
    }

    log_audit("Summary: total_conflicts={$totalFinds}, mobile_scoped={$mobileFinds}");

    // 3) Parity block presence sanity
    if ($style) {
        $need = [
            'IMG anchor' => '/object-position\s*:\s*50%\s*var\(--tmw-hero-vpos,\s*50%\)\s*!important\s*;/',
            'IMG transform'  => '/transform\s*:\s*translateY\s*\(\s*calc\(\s*var\(--offset-y,\s*0px\)\s*\*\s*var\(--offset-scale,\s*1\)\s*\)\s*\)\s*!important\s*;/',
        ];
        foreach ($need as $label => $rx) {
            $ok = preg_match($rx, $style) ? 'present' : 'MISSING';
            log_audit("Parity block contains {$label}: {$ok}");
        }
    }

    log_audit('=== v3.7.5 END ===');
}

/** Helpers */
function tmw_rglob($root, array $exts) {
    $out = [];
    $it  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
        if (in_array($ext, $exts, true)) $out[] = $file->getPathname();
    }
    return $out;
}
function tmw_trim_snippet($text, $pos, $span = 120) {
    $start = max(0, $pos - $span);
    $end   = min(strlen($text), $pos + $span);
    $snip  = substr($text, $start, $end - $start);
    return tmw_collapse_ws($snip);
}
function tmw_collapse_ws($s) { return preg_replace('/\s+/',' ', $s); }
function tmw_line_of($text, $absPos) {
    $slice = substr($text, 0, max(0, $absPos));
    return substr_count($slice, "\n") + 1;
}
function log_audit($msg) { error_log('[TMW-MOBILE-HERO-AUDIT] ' . $msg); }

endif; // function guard
