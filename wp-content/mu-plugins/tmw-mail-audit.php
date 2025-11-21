<?php
if (!defined('TMW_DEBUG') || !TMW_DEBUG) {
    return;
}

add_filter('wp_mail', function($a){ error_log('[MAIL] to='.json_encode($a['to']).' subj='.$a['subject']); return $a; });
add_action('wp_mail_failed', function($e){ error_log('[MAIL-FAILED] '.$e->get_error_message()); });
