<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX login/register helpers.
 *
 * This child override focuses on the registration handler so we can require
 * email activation before allowing a session.
 */

add_action('wp_ajax_nopriv_tmw_ajax_register', 'tmw_ajax_register_handler');
add_action('wp_ajax_tmw_ajax_register',        'tmw_ajax_register_handler');

/**
 * Handle AJAX registration.
 */
function tmw_ajax_register_handler() {
    $payload = wp_unslash($_POST);

    // Optional nonce check (respects older forms that may not send a nonce).
    $nonce = isset($payload['nonce']) ? sanitize_text_field($payload['nonce']) : '';
    if (!empty($nonce) && !wp_verify_nonce($nonce, 'tmw-ajax-register')) {
        wp_send_json_error(array(
            'message' => __('Security check failed. Please refresh and try again.', 'retrotube'),
            'code'    => 'bad_nonce',
        ), 403);
    }

    $username = '';
    if (isset($payload['username'])) {
        $username = sanitize_user($payload['username']);
    } elseif (isset($payload['user_login'])) {
        $username = sanitize_user($payload['user_login']);
    }

    $email = '';
    if (isset($payload['email'])) {
        $email = sanitize_email($payload['email']);
    } elseif (isset($payload['user_email'])) {
        $email = sanitize_email($payload['user_email']);
    }

    $password = '';
    if (isset($payload['password'])) {
        $password = (string) $payload['password'];
    } elseif (isset($payload['user_pass'])) {
        $password = (string) $payload['user_pass'];
    }

    $password_confirm = '';
    if (isset($payload['password_confirm'])) {
        $password_confirm = (string) $payload['password_confirm'];
    } elseif (isset($payload['confirm_password'])) {
        $password_confirm = (string) $payload['confirm_password'];
    } elseif (isset($payload['user_pass_confirm'])) {
        $password_confirm = (string) $payload['user_pass_confirm'];
    }

    $errors = array();

    if ($username === '') {
        $errors['username'] = __('Please choose a username.', 'retrotube');
    } elseif (!validate_username($username)) {
        $errors['username'] = __('That username is not allowed.', 'retrotube');
    } elseif (username_exists($username)) {
        $errors['username'] = __('That username is already taken.', 'retrotube');
    }

    if ($email === '') {
        $errors['email'] = __('Please provide your email address.', 'retrotube');
    } elseif (!is_email($email)) {
        $errors['email'] = __('That email looks invalid.', 'retrotube');
    } elseif (email_exists($email)) {
        $errors['email'] = __('An account already exists with that email.', 'retrotube');
    }

    if ($password === '') {
        $errors['password'] = __('Please choose a password.', 'retrotube');
    }

    if ($password_confirm !== '') {
        if ($password !== $password_confirm) {
            $errors['password_confirm'] = __('Passwords do not match.', 'retrotube');
        }
    }

    /**
     * Allow third-parties to amend validation.
     */
    $errors = apply_filters('tmw_ajax_register_errors', $errors, $payload);

    if (!empty($errors)) {
        $message = reset($errors);
        wp_send_json_error(array(
            'message' => $message,
            'errors'  => $errors,
        ));
    }

    $userdata = array(
        'user_login' => $username,
        'user_pass'  => $password,
        'user_email' => $email,
        'role'       => get_option('default_role', 'subscriber'),
    );

    if (!empty($payload['first_name'])) {
        $userdata['first_name'] = sanitize_text_field($payload['first_name']);
    }
    if (!empty($payload['last_name'])) {
        $userdata['last_name'] = sanitize_text_field($payload['last_name']);
    }

    $user_id = wp_insert_user($userdata);
    if (is_wp_error($user_id)) {
        wp_send_json_error(array(
            'message' => $user_id->get_error_message(),
            'errors'  => $user_id->errors,
        ));
    }

    do_action('tmw_ajax_register_success', $user_id, $userdata, $payload);

    // Mark unverified and send activation email
    update_user_meta($user_id, 'tmw_email_verified', 0);
    if (function_exists('tmw_send_activation_email')) {
        tmw_send_activation_email($user_id);
    }

    $response = array(
        'success' => true,
        'status'  => 'pending_activation',
        'user_id' => $user_id,
        'message' => __('Registration received. We’ve sent an activation link to your email. Please confirm to activate your account.', 'retrotube'),
    );

    /**
     * Filter the pending activation response before returning to the client.
     */
    $response = apply_filters('tmw_ajax_register_pending_activation_response', $response, $user_id, $payload);

    // Do NOT log in now; require email confirmation
    wp_send_json($response);
}
