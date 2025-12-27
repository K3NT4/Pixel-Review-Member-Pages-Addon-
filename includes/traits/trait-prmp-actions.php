<?php
// includes/traits/trait-prmp-actions.php
if (!defined('ABSPATH')) { exit; }

trait PRMP_Actions {

    /* =========================================================
     * Actions (POST handling)
     * ======================================================= */

    public static function handle_actions() : void {
        $opt = self::get_options();
        if (empty($opt['enabled'])) return;

        // Friendly redirects when already logged in.
        if (is_user_logged_in() && self::is_login_related_page()) {
            $obj_id = get_queried_object_id();
            $login_id = absint($opt['page_ids']['login'] ?? 0);
            $register_id = absint($opt['page_ids']['register'] ?? 0);
            if ($obj_id && in_array($obj_id, [$login_id, $register_id], true)) {
                wp_safe_redirect(self::redirect_after_login());
                exit;
            }
        }

        // Logout action (GET)
        if (!empty($_GET['pr_action']) && $_GET['pr_action'] === 'logout') {
            // Nonce check if present (recommended). If missing, allow only for logged-in users.
            if (is_user_logged_in()) {
                if (!empty($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), 'pr_logout')) {
                    wp_logout();
                }
            }
            $login = self::page_url('login') ?: home_url('/');
            wp_safe_redirect($login);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        // Preferred routing: hidden form identifier (works even when users submit via Enter key).
        $form = isset($_POST['pr_form']) ? sanitize_key((string)wp_unslash($_POST['pr_form'])) : '';
        if ($form) {
            switch ($form) {
                case 'login':
                    self::handle_login_submit();
                    return;
                case 'register':
                    self::handle_register_submit();
                    return;
                case 'profile':
                    self::handle_profile_submit();
                    return;
                default:
                    // Fall through to legacy button-name checks.
                    break;
            }
        }

        // Legacy routing (button name).
        if (!empty($_POST['pr_login_submit'])) { self::handle_login_submit(); return; }
        if (!empty($_POST['pr_register_submit'])) { self::handle_register_submit(); return; }
        if (!empty($_POST['pr_profile_submit'])) { self::handle_profile_submit(); return; }

        // Logout via POST
        if (!empty($_POST['pr_logout_submit'])) {
            if (!empty($_POST['_wpnonce']) && wp_verify_nonce(sanitize_text_field($_POST['_wpnonce']), 'pr_logout')) {
                wp_logout();
            }
            $login = self::page_url('login') ?: home_url('/');
            wp_safe_redirect($login);
            exit;
        }
    }

    protected static function handle_login_submit() : void {
        if (self::check_rate_limit()) {
            self::set_flash('error', __('För många inloggningsförsök. Var god vänta en stund.', 'sh-review-members'));
            return;
        }

        if (empty($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field($_POST['_wpnonce']), 'pr_login')) {
            self::set_flash('error', __('Ogiltig säkerhetstoken. Försök igen.', 'sh-review-members'));
            return;
        }

        if (!self::verify_captcha()) {
            self::set_flash('error', __('Verifiering misslyckades (CAPTCHA). Försök igen.', 'sh-review-members'));
            return;
        }

        $login = sanitize_text_field($_POST['pr_user_login'] ?? '');
        $pass  = (string)($_POST['pr_user_pass'] ?? '');
        $remember = !empty($_POST['pr_remember']);

        $creds = [
            'user_login'    => $login,
            'user_password' => $pass,
            'remember'      => $remember,
        ];

        $user = wp_signon($creds, is_ssl());
        if (is_wp_error($user)) {
            self::increment_failed_attempts();
            self::set_flash('error', $user->get_error_message());
            return;
        }

        self::clear_rate_limit_attempts();
        wp_safe_redirect(self::redirect_after_login());
        exit;
    }

    protected static function handle_register_submit() : void {
        if (empty($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field($_POST['_wpnonce']), 'pr_register')) {
            self::set_flash('error', __('Ogiltig säkerhetstoken. Försök igen.', 'sh-review-members'));
            return;
        }

        if (!self::verify_captcha()) {
            self::set_flash('error', __('Verifiering misslyckades (CAPTCHA). Försök igen.', 'sh-review-members'));
            return;
        }

        if (!get_option('users_can_register')) {
            self::set_flash('error', __('Registrering är avstängd på denna webbplats.', 'sh-review-members'));
            return;
        }

        $username = sanitize_user($_POST['pr_user_login'] ?? '', true);
        $email    = sanitize_email($_POST['pr_user_email'] ?? '');
        $pass1    = (string)($_POST['pr_user_pass'] ?? '');
        $pass2    = (string)($_POST['pr_user_pass2'] ?? '');

        if (empty($username) || empty($email) || empty($pass1)) {
            self::set_flash('error', __('Fyll i användarnamn, e-post och lösenord.', 'sh-review-members'));
            return;
        }
        if (!is_email($email)) {
            self::set_flash('error', __('Ogiltig e-postadress.', 'sh-review-members'));
            return;
        }
        if ($pass1 !== $pass2) {
            self::set_flash('error', __('Lösenorden matchar inte.', 'sh-review-members'));
            return;
        }
        if (username_exists($username)) {
            self::set_flash('error', __('Användarnamnet är upptaget.', 'sh-review-members'));
            return;
        }
        if (email_exists($email)) {
            self::set_flash('error', __('E-postadressen används redan.', 'sh-review-members'));
            return;
        }

        $user_id = wp_create_user($username, $pass1, $email);
        if (is_wp_error($user_id)) {
            self::set_flash('error', $user_id->get_error_message());
            return;
        }

        // Auto-login after successful registration.
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        wp_safe_redirect(self::redirect_after_login());
        exit;
    }

    protected static function handle_profile_submit() : void {
        if (!is_user_logged_in()) {
            self::set_flash('error', __('Du måste vara inloggad för att uppdatera din profil.', 'sh-review-members'));
            return;
        }

        if (empty($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field($_POST['_wpnonce']), 'pr_profile')) {
            self::set_flash('error', __('Ogiltig säkerhetstoken. Försök igen.', 'sh-review-members'));
            return;
        }

        $user = wp_get_current_user();

        $display_name = sanitize_text_field($_POST['pr_display_name'] ?? '');
        $first_name   = sanitize_text_field($_POST['pr_first_name'] ?? '');
        $last_name    = sanitize_text_field($_POST['pr_last_name'] ?? '');
        $email        = sanitize_email($_POST['pr_user_email'] ?? '');

        $pass1 = (string)($_POST['pr_new_pass'] ?? '');
        $pass2 = (string)($_POST['pr_new_pass2'] ?? '');

        if ($email && !is_email($email)) {
            self::set_flash('error', __('Ogiltig e-postadress.', 'sh-review-members'));
            return;
        }

        if (($pass1 || $pass2) && $pass1 !== $pass2) {
            self::set_flash('error', __('De nya lösenorden matchar inte.', 'sh-review-members'));
            return;
        }

        $userdata = [
            'ID'           => $user->ID,
            'display_name' => $display_name ?: $user->display_name,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'user_email'   => $email ?: $user->user_email,
        ];

        if ($pass1) {
            $userdata['user_pass'] = $pass1;
        }

        $res = wp_update_user($userdata);
        if (is_wp_error($res)) {
            self::set_flash('error', $res->get_error_message());
            return;
        }

        // If password changed, refresh auth cookie.
        if ($pass1) {
            wp_set_auth_cookie($user->ID, true);
        }

        // Keep Pixel Review author fields in sync with WordPress biographical info.
        // This will also save "sh_author_long_bio" and mirror its text to user description.
        self::prmp_save_author_profile_from_post((int)$user->ID);

        self::set_flash('success', __('Profilen har uppdaterats.', 'sh-review-members'));

        // Redirect to avoid resubmission.
        wp_safe_redirect(self::page_url('profile') ?: self::current_url());
        exit;
    }

    /**
     * Check if the current IP is rate-limited.
     * Returns true if blocked.
     */
    protected static function check_rate_limit() : bool {
        $opt = self::get_options();
        if (empty($opt['enable_rate_limit'])) return false;

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = 'prmp_login_fails_' . md5($ip);
        $fails = (int)get_transient($key);

        $limit = max(3, absint($opt['max_login_attempts'] ?? 5));

        return $fails >= $limit;
    }

    /**
     * Increment failed attempts counter.
     */
    protected static function increment_failed_attempts() : void {
        $opt = self::get_options();
        if (empty($opt['enable_rate_limit'])) return;

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = 'prmp_login_fails_' . md5($ip);
        $fails = (int)get_transient($key);

        set_transient($key, $fails + 1, 30 * MINUTE_IN_SECONDS);
    }

    /**
     * Clear rate limit on successful login.
     */
    protected static function clear_rate_limit_attempts() : void {
        $opt = self::get_options();
        if (empty($opt['enable_rate_limit'])) return;

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = 'prmp_login_fails_' . md5($ip);
        delete_transient($key);
    }

    /**
     * Verify CAPTCHA token.
     */
    protected static function verify_captcha() : bool {
        $opt = self::get_options();
        $provider = $opt['captcha_provider'] ?? '';

        $secret = '';
        if ($provider === 'turnstile') {
             $secret = $opt['turnstile_secret_key'] ?? '';
        } elseif ($provider === 'recaptcha_v3') {
             $secret = $opt['recaptcha_secret_key'] ?? '';
        }

        if (!$provider || !$secret) return true; // Pass if not configured.

        $token = '';
        if ($provider === 'turnstile') {
            $token = $_POST['cf-turnstile-response'] ?? '';
        } elseif ($provider === 'recaptcha_v3') {
            $token = $_POST['g-recaptcha-response'] ?? '';
        }

        if (!$token) return false;

        $verify_url = '';
        if ($provider === 'turnstile') {
            $verify_url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        } elseif ($provider === 'recaptcha_v3') {
            $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
        }

        $response = wp_remote_post($verify_url, [
            'body' => [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]
        ]);

        if (is_wp_error($response)) return false;

        $body = json_decode(wp_remote_retrieve_body($response), true);

        // For reCAPTCHA v3, we should also check the score, but for now success=true is the baseline.
        // Turnstile also returns success=true.
        return !empty($body['success']);
    }
}
