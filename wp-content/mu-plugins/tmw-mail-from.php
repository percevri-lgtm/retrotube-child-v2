<?php
add_filter('wp_mail_from',      fn() => 'no-reply@top-models.webcam');
add_filter('wp_mail_from_name', fn() => 'Top-Models Webcam');
add_action('phpmailer_init', function($phpmailer){ $phpmailer->Sender = 'no-reply@top-models.webcam'; });
