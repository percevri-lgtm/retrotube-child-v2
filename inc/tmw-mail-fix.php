<?php
if (!defined('ABSPATH')) exit;
/**
 * TMW Mail Fix — align From and Return-Path to your domain for deliverability
 * Works with native PHP mail (smtp=0) and most SMTP plugins.
 */

add_filter('wp_mail_from', function ($from) {
    $host = parse_url(home_url(), PHP_URL_HOST);
    if (!$host) {
        return $from;
    }
    $want = 'no-reply@' . $host;
    // Keep a custom From if it already matches our domain
    if (!empty($from) && substr_compare(strtolower($from), '@' . $host, -strlen('@' . $host)) === 0) {
        return $from;
    }
    return $want;
}, 20);

add_filter('wp_mail_from_name', function ($name) {
    $bn = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
    return $bn ?: $name;
}, 20);

add_action('phpmailer_init', function ($phpmailer) {
    if (empty($phpmailer->Sender)) {
        $host = parse_url(home_url(), PHP_URL_HOST);
        if ($host) {
            $phpmailer->Sender = 'no-reply@' . $host; // sets Return-Path
        }
    }
}, 20);
