<?php
if (!defined('ABSPATH')) exit;

if (!defined('TMW_DEBUG') || !TMW_DEBUG) {
    return;
}

/**
 * TMW Register Flow Audit (Audit-Only)
 * Logs front/back signals for signup without changing behavior.
 * Log tags: [TMW-REG-BOOT] [TMW-REG-FRONT] [TMW-REG-AJAX] [TMW-REG-SETTINGS] [TMW-REG-ALLHOOK]
 */

if (!defined('TMW_REG_AUDIT') || !TMW_REG_AUDIT) return;

add_action('plugins_loaded', function () {
    error_log('[TMW-REG-BOOT] Register audit booted.');
});

/* 1) Settings sanity (admin notice + log) */
add_action('admin_init', function () {
    $can = (int) get_option('users_can_register', 0);
    $role = get_option('default_role', 'subscriber');
    error_log(sprintf('[TMW-REG-SETTINGS] users_can_register=%d default_role=%s', $can, $role));
});
add_action('admin_notices', function () {
    if (!(int) get_option('users_can_register', 0)) {
        echo '<div class="notice notice-error"><p><strong>[TMW]</strong> Registration is disabled (Settings → General → “Anyone can register”). Audit active.</p></div>';
    }
});

/* 2) Front-end script to observe form events (non-blocking) */
add_action('wp_enqueue_scripts', function () {
    if (is_user_logged_in()) return; // only for visitors

    $ver = '0.9.0';
    wp_enqueue_script(
        'tmw-register-audit',
        get_stylesheet_directory_uri() . '/js/tmw-register-audit.js',
        array(),
        $ver,
        true
    );
    wp_localize_script('tmw-register-audit', 'TMW_REG_AUD', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('tmw_reg_audit'),
        'usersCanRegister' => (int) get_option('users_can_register', 0),
    ));
}, 9999);

/* 3) Lightweight AJAX sink for front logs */
add_action('wp_ajax_nopriv_tmw_reg_audit_ping', 'tmw_reg_audit_ping');
add_action('wp_ajax_tmw_reg_audit_ping', 'tmw_reg_audit_ping');
function tmw_reg_audit_ping() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'tmw_reg_audit')) {
        wp_send_json_error(array('msg' => 'bad_nonce'), 403);
    }
    // Redact sensitive fields
    $payload_raw = isset($_POST['payload']) ? wp_unslash($_POST['payload']) : '';
    $payload = $payload_raw ? json_decode($payload_raw, true) : array();
    if (!is_array($payload)) {
        $payload = array('raw' => substr((string) $payload_raw, 0, 200));
    }
    if (isset($payload['email'])) {
        $email = $payload['email'];
        $hash  = substr(md5(strtolower($email)), 0, 8);
        $domain = (strpos($email, '@') !== false) ? substr(strrchr($email, "@"), 1) : 'unknown';
        $payload['email'] = 'hash:' . $hash . '@' . $domain;
    }
    if (isset($payload['password'])) unset($payload['password']);

    error_log('[TMW-REG-FRONT] ' . wp_json_encode($payload));
    wp_send_json_success(array('ok' => true));
}

/* 4) Server-side AJAX trace: log any nopriv admin-ajax action that looks like register */
add_action('muplugins_loaded', function () {
    if (defined('DOING_AJAX') && DOING_AJAX && !is_user_logged_in()) {
        $action = isset($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : '';
        if ($action) {
            $looks_register = (false !== stripos($action, 'reg')) || (false !== stripos($action, 'signup')) || (false !== stripos($action, 'register'));
            if ($looks_register) {
                $log = array(
                    'action' => $action,
                    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                    'has_nonce' => isset($_REQUEST['_wpnonce']) || isset($_REQUEST['nonce']),
                    'keys' => array_keys($_REQUEST), // never dump raw values
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'n/a',
                    'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'n/a', 0, 120),
                    'referer' => $_SERVER['HTTP_REFERER'] ?? 'n/a',
                );
                error_log('[TMW-REG-AJAX] inbound ' . wp_json_encode($log));
            }
        }
    }
}, 1);

/* 5) Nuclear but safe: capture which ajax handler actually fired (wildcard) */
add_action('all', function ($hook) {
    if (!(defined('DOING_AJAX') && DOING_AJAX)) return;
    if (is_user_logged_in()) return;
    if (strpos($hook, 'wp_ajax_nopriv_') !== 0) return;

    static $seen = array();
    if (isset($seen[$hook])) return; // log once per request
    $seen[$hook] = true;

    error_log('[TMW-REG-ALLHOOK] ' . $hook);
}, 1);
