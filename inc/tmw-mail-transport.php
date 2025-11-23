<?php
if (!defined('ABSPATH')) exit;

if (!defined('TMW_DEBUG') || !TMW_DEBUG) {
    return;
}

/**
 * TMW Mail Transport
 * - Opt-in SMTP via constants
 * - Consistent From headers
 * - Detailed logging on failures
 * - Test endpoint
 * Logs: [TMW-MAIL-CONFIG], [TMW-MAIL-FAIL], [TMW-MAIL-TEST]
 *
 * Configure in wp-config.php (recommended):
 *   define('TMW_SMTP_HOST', 'smtp.yourprovider.com');
 *   define('TMW_SMTP_PORT', 587);
 *   define('TMW_SMTP_USER', 'apikey-or-user');
 *   define('TMW_SMTP_PASS', 'secret');
 *   define('TMW_SMTP_SECURE', 'tls'); // 'tls' or 'ssl' or ''
 *   define('TMW_SMTP_FROM', 'noreply@top-models.webcam');
 *   define('TMW_SMTP_FROM_NAME', 'Top Models Webcam');
 */

add_action('init', function () {
    $using_smtp = defined('TMW_SMTP_HOST') && TMW_SMTP_HOST;
    if (function_exists('error_log')) {
        error_log('[TMW-MAIL-CONFIG] smtp=' . ($using_smtp ? '1' : '0') . ' from=' . (defined('TMW_SMTP_FROM') ? TMW_SMTP_FROM : 'default'));
    }
});

/** Default From headers (overridable by wp_mail() headers) */
add_filter('wp_mail_from', function ($from) {
    if (defined('TMW_SMTP_FROM') && TMW_SMTP_FROM) return TMW_SMTP_FROM;
    // fallback to site admin email
    $admin = get_option('admin_email');
    $host  = parse_url(home_url(), PHP_URL_HOST);
    if ($admin && strpos($admin, '@') !== false) return $admin;
    return 'noreply@' . $host;
});
add_filter('wp_mail_from_name', function ($name) {
    if (defined('TMW_SMTP_FROM_NAME') && TMW_SMTP_FROM_NAME) return TMW_SMTP_FROM_NAME;
    return wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
});

/** Switch PHPMailer to SMTP when constants are set */
add_action('phpmailer_init', function ($phpmailer) {
    if (! (defined('TMW_SMTP_HOST') && TMW_SMTP_HOST)) return;

    $phpmailer->isSMTP();
    $phpmailer->Host       = TMW_SMTP_HOST;
    $phpmailer->Port       = defined('TMW_SMTP_PORT') ? (int) TMW_SMTP_PORT : 587;
    $phpmailer->SMTPAuth   = true;
    $phpmailer->Username   = defined('TMW_SMTP_USER') ? TMW_SMTP_USER : '';
    $phpmailer->Password   = defined('TMW_SMTP_PASS') ? TMW_SMTP_PASS : '';
    $phpmailer->SMTPSecure = defined('TMW_SMTP_SECURE') ? TMW_SMTP_SECURE : 'tls';
    $phpmailer->Timeout    = 15;

    // Set envelope sender to match From to help SPF/DMARC
    if (defined('TMW_SMTP_FROM') && TMW_SMTP_FROM) {
        $phpmailer->setFrom(TMW_SMTP_FROM, (defined('TMW_SMTP_FROM_NAME') ? TMW_SMTP_FROM_NAME : ''), false);
        $phpmailer->Sender = TMW_SMTP_FROM;
    }
});

/** Log mail failures with PHPMailer detail */
add_action('wp_mail_failed', function ($wp_error) {
    $msg = implode(' | ', $wp_error->get_error_messages());
    $data = $wp_error->get_error_data();
    if (is_object($data) && isset($data->ErrorInfo)) {
        $msg .= ' | PHPMailer: ' . $data->ErrorInfo;
    }
    error_log('[TMW-MAIL-FAIL] ' . $msg);
});

/** Minimal test endpoint: /wp-admin/admin-ajax.php?action=tmw_mail_test&to=you@example.com */
add_action('wp_ajax_tmw_mail_test', 'tmw_mail_test');
add_action('wp_ajax_nopriv_tmw_mail_test', 'tmw_mail_test');
function tmw_mail_test() {
    $requested = isset($_GET['to']) ? sanitize_email(wp_unslash($_GET['to'])) : '';
    $to = $requested ? $requested : sanitize_email(get_option('admin_email'));
    $message = "This is a test email from TMW Mail Transport.\nTime: " . gmdate('c');
    $ok = $to ? wp_mail($to, 'TMW mail test', $message) : false;
    error_log('[TMW-MAIL-TEST] to=' . ($to ? $to : 'invalid') . ' ok=' . ($ok ? '1' : '0'));
    if (!$to) {
        wp_die('FAIL');
    }
    wp_die($ok ? 'OK' : 'FAIL');
}
