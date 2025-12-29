<?php
// includes/traits/trait-prmp-actions.php
if (!defined('ABSPATH')) { exit; }

trait PRMP_Actions {

    public static function handle_actions() : void {
        $opt = self::get_options();
        if (empty($opt['enabled'])) return;

        if (!empty($_GET['pr_action']) && $_GET['pr_action'] === 'logout') {
            wp_logout();
            $login = self::page_url('login') ?: home_url('/');
            wp_safe_redirect($login);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        $form = $_POST['pr_form'] ?? '';

        if ($form === 'login') {
            self::handle_login();
            return;
        }

        if ($form === 'register') {
            self::handle_register();
            return;
        }

        if ($form === 'profile_update') {
            self::handle_profile_update();
            return;
        }

        if ($form === 'privacy_request') {
            self::handle_privacy_request();
            return;
        }
    }

    protected static function handle_login() : void {
        if (empty($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field($_POST['_wpnonce']), 'pr_login')) {
            self::set_flash('error', __('Invalid security token. Please try again.', 'sh-review-members'));
            return;
        }

        $username = sanitize_text_field($_POST['log'] ?? '');
        $password = (string)($_POST['pwd'] ?? '');

        if (!$username || !$password) {
            self::set_flash('error', __('Please fill in all fields.', 'sh-review-members'));
            return;
        }

        if (self::check_rate_limit()) {
            self::set_flash('error', __('Too many login attempts. Please wait and try again.', 'sh-review-members'));
            return;
        }

        $creds = [
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => !empty($_POST['rememberme']),
        ];

        $user = wp_signon($creds, is_ssl());
        if (is_wp_error($user)) {
            self::increment_failed_attempts();
            self::set_flash('error', $user->get_error_message());
            return;
        }

        self::clear_rate_limit_attempts();

        $redirect = self::page_url('dashboard') ?: home_url('/');
        wp_safe_redirect($redirect);
        exit;
    }

    protected static function handle_register() : void {
        if (empty($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field($_POST['_wpnonce']), 'pr_register')) {
            self::set_flash('error', __('Invalid security token. Please try again.', 'sh-review-members'));
            return;
        }

        $opt = self::get_options();
        if (empty($opt['allow_register'])) {
            self::set_flash('error', __('Registration is disabled.', 'sh-review-members'));
            return;
        }

        $email    = sanitize_email($_POST['user_email'] ?? '');
        $username = sanitize_user($_POST['user_login'] ?? '');
        $pass1    = (string)($_POST['pass1'] ?? '');
        $pass2    = (string)($_POST['pass2'] ?? '');

        if (!$email || !$username || !$pass1 || !$pass2) {
            self::set_flash('error', __('Please fill in all fields.', 'sh-review-members'));
            return;
        }

        if (!is_email($email)) {
            self::set_flash('error', __('Please provide a valid email address.', 'sh-review-members'));
            return;
        }

        if ($pass1 !== $pass2) {
            self::set_flash('error', __('Passwords do not match.', 'sh-review-members'));
            return;
        }

        if (username_exists($username)) {
            self::set_flash('error', __('Username already exists.', 'sh-review-members'));
            return;
        }

        if (email_exists($email)) {
            self::set_flash('error', __('Email already exists.', 'sh-review-members'));
            return;
        }

        $user_id = wp_create_user($username, $pass1, $email);
        if (is_wp_error($user_id)) {
            self::set_flash('error', $user_id->get_error_message());
            return;
        }

        self::set_flash('success', __('Account created. Please log in.', 'sh-review-members'));
        $login = self::page_url('login') ?: wp_login_url();
        wp_safe_redirect($login);
        exit;
    }

    protected static function handle_profile_update() : void {
        if (!is_user_logged_in()) {
            self::set_flash('error', __('You must be logged in.', 'sh-review-members'));
            return;
        }

        if (empty($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field($_POST['_wpnonce']), 'pr_profile')) {
            self::set_flash('error', __('Invalid security token. Please try again.', 'sh-review-members'));
            return;
        }

        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            self::set_flash('error', __('User not found.', 'sh-review-members'));
            return;
        }

        $display_name = sanitize_text_field($_POST['display_name'] ?? '');

        $update = [
            'ID'           => $user->ID,
            'display_name' => $display_name ?: $user->display_name,
        ];

        if (isset($_POST['first_name'])) {
            $update['first_name'] = sanitize_text_field($_POST['first_name']);
        }

        if (isset($_POST['last_name'])) {
            $update['last_name'] = sanitize_text_field($_POST['last_name']);
        }

        $res = wp_update_user($update);
        if (is_wp_error($res)) {
            self::set_flash('error', $res->get_error_message());
            return;
        }

        if (method_exists(__CLASS__, 'sync_author_profile_from_profile_form')) {
            self::sync_author_profile_from_profile_form($user->ID);
        }

        $pass1 = (string)($_POST['pass1'] ?? '');
        $pass2 = (string)($_POST['pass2'] ?? '');
        if ($pass1 || $pass2) {
            if ($pass1 !== $pass2) {
                self::set_flash('error', __('Passwords do not match.', 'sh-review-members'));
                return;
            }
            if (strlen($pass1) < 8) {
                self::set_flash('error', __('Password must be at least 8 characters.', 'sh-review-members'));
                return;
            }
            wp_set_password($pass1, $user->ID);
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID, true);
        }

        self::set_flash('success', __('Profile updated.', 'sh-review-members'));
        wp_safe_redirect(self::page_url('profile') ?: self::current_url());
        exit;
    }

    protected static function handle_privacy_request() : void {
        if (!is_user_logged_in()) {
            self::set_flash('error', __('You must be logged in.', 'sh-review-members'));
            return;
        }

        if (empty($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field($_POST['_wpnonce']), 'pr_privacy')) {
            self::set_flash('error', __('Invalid security token. Please try again.', 'sh-review-members'));
            return;
        }

        $action = sanitize_key($_POST['privacy_action'] ?? '');
        $opt = self::get_options();
        $user = wp_get_current_user();

        if ($action === 'export_personal_data' && empty($opt['enable_data_export'])) {
            self::set_flash('error', __('Data export is not enabled.', 'sh-review-members'));
            return;
        }
        if ($action === 'remove_personal_data' && empty($opt['enable_data_deletion'])) {
            self::set_flash('error', __('Account deletion is not enabled.', 'sh-review-members'));
            return;
        }

        if (!in_array($action, ['export_personal_data', 'remove_personal_data'], true)) {
            return;
        }

        $request_id = wp_create_user_request($user->user_email, $action);

        if (is_wp_error($request_id)) {
            self::set_flash('error', $request_id->get_error_message());
        } else {
            $sent = self::prmp_send_privacy_confirmation_email((int) $request_id);

            if ($sent) {
                self::set_flash('success', __('A confirmation email has been sent to your address. Please click the link in the email to confirm your request.', 'sh-review-members'));
            } else {
                self::set_flash('success', __('Your request has been created. If you do not receive a confirmation email, please contact the site administrator.', 'sh-review-members'));
            }
        }

        wp_safe_redirect(self::page_url('profile') ?: self::current_url());
        exit;
    }

    protected static function prmp_send_privacy_confirmation_email(int $request_id) : bool {
        if ($request_id <= 0) return false;

        if (function_exists('wp_send_user_request')) {
            $res = wp_send_user_request((string) $request_id);
            return !is_wp_error($res);
        }

        if (!function_exists('wp_send_user_request_confirmation_email')) {
            $inc_user = ABSPATH . 'wp-admin/includes/user.php';
            $inc_priv = ABSPATH . 'wp-admin/includes/privacy.php';

            if (is_readable($inc_user)) require_once $inc_user;
            if (is_readable($inc_priv)) require_once $inc_priv;
        }

        if (function_exists('wp_send_user_request_confirmation_email')) {
            wp_send_user_request_confirmation_email($request_id);
            return true;
        }

        return false;
    }

    protected static function check_rate_limit() : bool {
        $opt = self::get_options();
        if (empty($opt['enable_rate_limit'])) return false;

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = 'prmp_login_fails_' . md5($ip);
        $fails = (int)get_transient($key);

        $limit = max(3, absint($opt['max_login_attempts'] ?? 5));

        return $fails >= $limit;
    }

    protected static function increment_failed_attempts() : void {
        $opt = self::get_options();
        if (empty($opt['enable_rate_limit'])) return;

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = 'prmp_login_fails_' . md5($ip);
        $fails = (int)get_transient($key);

        set_transient($key, $fails + 1, 30 * MINUTE_IN_SECONDS);
    }

    protected static function clear_rate_limit_attempts() : void {
        $opt = self::get_options();
        if (empty($opt['enable_rate_limit'])) return;

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = 'prmp_login_fails_' . md5($ip);
        delete_transient($key);
    }
}
