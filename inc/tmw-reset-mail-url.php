<?php
/**
 * TMW Reset Email URL Normalizer
 * Ensures the email always contains:
 *   https://example.com/resetpass/?login=USER&key=KEY&wp_lang=LL_CC
 * We DO NOT change how the email is sent, only the link placed inside it.
 */
if (!defined('ABSPATH')) exit;

add_filter('retrieve_password_message', function ($message, $key, $user_login, $user_data = null) {

    // Find a language code to mirror the working link variant.
    $lang = '';
    if (isset($_REQUEST['wp_lang'])) {
        $lang = sanitize_text_field(wp_unslash($_REQUEST['wp_lang']));
    } elseif (isset($_COOKIE['wp_lang'])) {
        $lang = sanitize_text_field($_COOKIE['wp_lang']);
    } elseif (function_exists('determine_locale')) {
        $lang = determine_locale();
    } else {
        $lang = get_locale();
    }

    // Build the TML page URL (slug "resetpass" per your settings) with a fixed query order.
    $base = trailingslashit(home_url('resetpass'));
    $query = 'login=' . rawurlencode($user_login) . '&key=' . rawurlencode($key);
    if (!empty($lang)) {
        $query .= '&wp_lang=' . rawurlencode($lang);
    }
    $normalized = $base . '?' . $query;

    // Replace any core/TML link in the outgoing message with our normalized one.
    // 1) Core link: .../wp-login.php?action=rp&key=...&login=...
    $message = preg_replace('#https?://[^\s<>"\']+/wp-login\.php\?action=rp[^ \r\n<>"\']+#i', $normalized, $message, 1);
    // 2) TML link: .../resetpass/?key=...&login=...
    $message = preg_replace('#https?://[^\s<>"\']+/resetpass/\?[^ \r\n<>"\']+#i', $normalized, $message, 1);

    // If nothing matched, append our link without altering the rest of the message.
    if (stripos($message, $normalized) === false) {
        $message .= "\r\n\r\n" . sprintf(__('To reset your password, visit the following address: %s', 'default'), $normalized);
    }

    if (defined('TMW_DEBUG') && TMW_DEBUG) {
        // Optional trace
        error_log('[TMW-RP-LINK] normalized reset URL sent: ' . $normalized);
    }
    return $message;
}, 99, 4);

