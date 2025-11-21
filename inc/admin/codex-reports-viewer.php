<?php
if (!defined('ABSPATH')) { exit; }

add_action('admin_menu', function () {
    add_management_page(
        'Codex Reports',
        'Codex Reports',
        'manage_options',
        'tmw-codex-reports',
        'tmw_render_codex_reports_admin'
    );
});

add_action('admin_post_tmw_download_codex_report', function () {
    if (!current_user_can('manage_options')) wp_die('Forbidden');
    check_admin_referer('tmw_codex_dl');

    $base = WP_CONTENT_DIR . '/codex-reports';
    $file = isset($_GET['file']) ? basename(wp_unslash($_GET['file'])) : '';
    $path = wp_normalize_path($base . '/' . $file);

    if ($file === '' || !file_exists($path) || strpos($path, wp_normalize_path($base)) !== 0) {
        wp_die('Invalid file');
    }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
});

function tmw_render_codex_reports_admin() {
    if (!current_user_can('manage_options')) { return; }

    $dir = WP_CONTENT_DIR . '/codex-reports';
    if (!is_dir($dir)) { wp_mkdir_p($dir); }

    $selected = isset($_GET['view']) ? basename(wp_unslash($_GET['view'])) : '';
    $files = array_values(array_filter(scandir($dir), function($n){ return $n !== '.' && $n !== '..'; }));
    usort($files, function($a,$b) use ($dir) {
        return filemtime("$dir/$b") <=> filemtime("$dir/$a");
    });

    echo '<div class="wrap"><h1>Codex Reports</h1>';
    echo '<p>Directory: <code>' . esc_html(wp_normalize_path($dir)) . '</code></p>';

    echo '<table class="widefat fixed striped"><thead><tr>';
    echo '<th>Filename</th><th>Size</th><th>Modified (server)</th><th>Actions</th>';
    echo '</tr></thead><tbody>';

    if (empty($files)) {
        echo '<tr><td colspan="4">No reports yet.</td></tr>';
    } else {
        foreach ($files as $f) {
            $path = wp_normalize_path("$dir/$f");
            $size = size_format(filesize($path));
            $mtime = date_i18n('Y-m-d H:i:s', filemtime($path));
            $view_url = add_query_arg(['page' => 'tmw-codex-reports', 'view' => $f], admin_url('tools.php'));
            $dl_url = wp_nonce_url(
                add_query_arg(['action' => 'tmw_download_codex_report', 'file' => $f], admin_url('admin-post.php')),
                'tmw_codex_dl'
            );
            echo '<tr>';
            echo '<td><code>' . esc_html($f) . '</code></td>';
            echo '<td>' . esc_html($size) . '</td>';
            echo '<td>' . esc_html($mtime) . '</td>';
            echo '<td><a class="button button-small" href="' . esc_url($view_url) . '">View</a> ';
            echo '<a class="button button-small" href="' . esc_url($dl_url) . '">Download</a></td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';

    if ($selected) {
        $sel_path = wp_normalize_path("$dir/$selected");
        if (file_exists($sel_path)) {
            $content = file_get_contents($sel_path);
            $lower = strtolower($selected);
            $is_json = substr($lower, -5) === '.json';
            echo '<h2 style="margin-top:24px">Preview: ' . esc_html($selected) . '</h2>';
            echo '<p><button id="tmw-copy" class="button">Copy to clipboard</button></p>';
            echo $is_json ? '<pre style="white-space:pre-wrap;">' . esc_html($content) . '</pre>'
                          : '<textarea id="tmw-preview" style="width:100%;height:400px;">' . esc_textarea($content) . '</textarea>';
            ?>
            <script>
            (function(){
                const btn = document.getElementById('tmw-copy');
                if (!btn) return;
                btn.addEventListener('click', async () => {
                    const ta = document.getElementById('tmw-preview');
                    const text = ta ? ta.value : document.querySelector('pre')?.innerText || '';
                    try { await navigator.clipboard.writeText(text); btn.textContent = 'Copied!'; }
                    catch(e){ btn.textContent = 'Copy failed'; }
                    setTimeout(()=>btn.textContent='Copy to clipboard', 1500);
                });
            })();
            </script>
            <?php
        }
    }

    echo '</div>';
}
