<?php
if (!defined('ABSPATH')) exit;

/**
 * TMW Email Activation — require email verification before login.
 * Logs: [TMW-ACT-SEND], [TMW-ACT-OK], [TMW-ACT-FAIL], [TMW-ACT-RESEND]
 */

if (!defined('TMW_ACTIVATION_EXPIRY_HOURS')) define('TMW_ACTIVATION_EXPIRY_HOURS', 72); // 3 days
if (!defined('TMW_ACTIVATION_AUTLOGIN'))     define('TMW_ACTIVATION_AUTLOGIN', true);   // auto-login after click

/**
 * Gate logins until verified.
 */
add_filter('wp_authenticate_user', function($user, $password){
    if (is_wp_error($user)) return $user;
    $verified = (int) get_user_meta($user->ID, 'tmw_email_verified', true);
    if ($verified !== 1) {
        return new WP_Error(
            'tmw_pending_verification',
            __('Please activate your account via the email we sent. Didn’t get it? Use “Forgot password” or request a new activation link.', 'retrotube')
        );
    }
    return $user;
}, 10, 2);

/**
 * Create & store hashed activation token, then send email.
 */
function tmw_send_activation_email($user_id){
    $user = get_user_by('id', $user_id);
    if (!$user) return false;

    // Skip if already verified
    if ((int) get_user_meta($user_id, 'tmw_email_verified', true) === 1) return true;

    // Rate-limit resends (min 5 minutes)
    $last = (int) get_user_meta($user_id, 'tmw_activation_last_sent', true);
    if ($last && (time() - $last) < 5 * MINUTE_IN_SECONDS) {
        return true;
    }

    // Generate token and store hash + timestamp
    $token = wp_generate_password(20, false);
    $hash  = wp_hash_password($token);
    update_user_meta($user_id, 'tmw_activation_hash', $hash);
    update_user_meta($user_id, 'tmw_activation_ts',   time());
    update_user_meta($user_id, 'tmw_email_verified',  0);
    update_user_meta($user_id, 'tmw_activation_last_sent', time());

    $args = array(
        'uid' => $user_id,
        'key' => $token,
    );
    // Use admin-ajax endpoint (bypasses cache/CDN nicely)
    $activate_url = add_query_arg(
        array_merge(array('action' => 'tmw_activate'), $args),
        admin_url('admin-ajax.php')
    );

    $site  = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    $subj  = sprintf(__('Activate your account at %s', 'retrotube'), $site);
    $lines = array(
        sprintf(__('Hi %s,', 'retrotube'), $user->user_login),
        __('Thanks for signing up. Please confirm your email to activate your account:', 'retrotube'),
        $activate_url,
        '',
        sprintf(__('This link will expire in %d hours.', 'retrotube'), (int) TMW_ACTIVATION_EXPIRY_HOURS),
        __('If you did not request this, you can ignore this email.', 'retrotube'),
    );
    $msg = implode("\n\n", $lines);

    // Let other plugins/themes adjust mail
    $headers = apply_filters('tmw_activation_mail_headers', array('Content-Type: text/plain; charset=UTF-8'));
    $sent = wp_mail($user->user_email, $subj, $msg, $headers);

    if (function_exists('error_log') && defined('TMW_DEBUG') && TMW_DEBUG) {
        error_log('[TMW-ACT-SEND] uid='.$user_id.' email='.sanitize_email($user->user_email).' sent='.($sent?'1':'0'));
    }

    return $sent;
}

/**
 * Activation handler (AJAX GET).
 * Example: /wp-admin/admin-ajax.php?action=tmw_activate&uid=123&key=abcdef
 */
add_action('wp_ajax_nopriv_tmw_activate', 'tmw_handle_activation');
add_action('wp_ajax_tmw_activate',        'tmw_handle_activation');
function tmw_handle_activation(){
    $uid = isset($_GET['uid']) ? absint($_GET['uid']) : 0;
    $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';

    if (!$uid || !$key) {
        tmw_activation_exit(__('Invalid activation link.', 'retrotube'), false);
    }

    $hash = (string) get_user_meta($uid, 'tmw_activation_hash', true);
    $ts   = (int) get_user_meta($uid, 'tmw_activation_ts', true);
    if (!$hash || !$ts) {
        tmw_activation_exit(__('Activation link is invalid or already used.', 'retrotube'), false);
    }

    // Expiry check
    if ((time() - $ts) > (HOURS_IN_SECONDS * (int) TMW_ACTIVATION_EXPIRY_HOURS)) {
        tmw_activation_exit(__('Activation link has expired. Please request a new one.', 'retrotube'), false);
    }

    // Verify token
    if (!wp_check_password($key, $hash, '')) {
        tmw_activation_exit(__('Invalid activation key.', 'retrotube'), false);
    }

    // Mark verified
    update_user_meta($uid, 'tmw_email_verified', 1);
    delete_user_meta($uid, 'tmw_activation_hash');
    delete_user_meta($uid, 'tmw_activation_ts');

    if (function_exists('error_log') && defined('TMW_DEBUG') && TMW_DEBUG) error_log('[TMW-ACT-OK] uid='.$uid);

    // Auto-login & redirect or show message
    if (TMW_ACTIVATION_AUTLOGIN) {
        wp_set_current_user($uid);
        wp_set_auth_cookie($uid);
    }

    $redirect = apply_filters('tmw_activation_redirect', home_url('/?activated=1'));
    wp_safe_redirect($redirect);
    exit;
}

function tmw_activation_exit($message, $success){
    if (function_exists('error_log') && defined('TMW_DEBUG') && TMW_DEBUG) error_log('[TMW-ACT-FAIL] '.sanitize_text_field($message));
    // Render a minimal message (avoids JSON & is shareable link)
    wp_die(
        esc_html($message),
        get_bloginfo('name'),
        array('response' => 200)
    );
}

/**
 * Resend endpoint (AJAX POST). Accepts email or username.
 */
add_action('wp_ajax_nopriv_tmw_resend_activation', 'tmw_resend_activation');
function tmw_resend_activation(){
    $login = isset($_POST['login']) ? sanitize_text_field(wp_unslash($_POST['login'])) : '';
    if (!$login) {
        wp_send_json_error(array('message' => __('Missing email or username.', 'retrotube')), 200);
    }

    $user = is_email($login) ? get_user_by('email', $login) : get_user_by('login', $login);
    if (!$user) {
        wp_send_json_error(array('message' => __('Account not found.', 'retrotube')), 200);
    }

    if ((int) get_user_meta($user->ID, 'tmw_email_verified', true) === 1) {
        wp_send_json_success(array('message' => __('Account already activated. You can log in.', 'retrotube')));
    }

    $ok = tmw_send_activation_email($user->ID);
    if (function_exists('error_log') && defined('TMW_DEBUG') && TMW_DEBUG) error_log('[TMW-ACT-RESEND] uid='.$user->ID.' ok='.($ok?'1':'0'));

    if ($ok) wp_send_json_success(array('message' => __('We’ve sent a new activation email.', 'retrotube')));
    wp_send_json_error(array('message' => __('Could not send activation email. Please try again later.', 'retrotube')));
}
