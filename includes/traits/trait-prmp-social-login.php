<?php
// includes/traits/trait-prmp-social-login.php
if (!defined('ABSPATH')) { exit; }

trait PRMP_Social_Login {

    public static function init_social_login() : void {
        add_action('init', [__CLASS__, 'handle_social_login_request']);
        add_action('init', [__CLASS__, 'handle_social_login_callback']);
    }

    protected static function get_google_login_url() : string {
        $opt = self::get_options();
        if (empty($opt['google_client_id'])) return '';

        $params = [
            'client_id'     => $opt['google_client_id'],
            'redirect_uri'  => home_url('/'),
            'response_type' => 'code',
            'scope'         => 'email profile openid',
            'state'         => wp_create_nonce('pr_social_google') . '|google',
            'access_type'   => 'online',
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    protected static function get_wordpress_login_url() : string {
        $opt = self::get_options();
        if (empty($opt['wordpress_client_id'])) return '';

        $params = [
            'client_id'     => $opt['wordpress_client_id'],
            'redirect_uri'  => home_url('/'),
            'response_type' => 'code',
            'scope'         => 'auth',
            'state'         => wp_create_nonce('pr_social_wordpress') . '|wordpress',
        ];

        return 'https://public-api.wordpress.com/oauth2/authorize?' . http_build_query($params);
    }

    public static function handle_social_login_request() : void {
        if (!isset($_GET['pr_social_login'])) return;

        $provider = sanitize_key($_GET['pr_social_login']);
        $url = '';

        if ($provider === 'google') {
            $url = self::get_google_login_url();
        } elseif ($provider === 'wordpress') {
            $url = self::get_wordpress_login_url();
        }

        if ($url) {
            wp_redirect($url);
            exit;
        }
    }

    public static function handle_social_login_callback() : void {
        if (!isset($_GET['code']) || !isset($_GET['state'])) return;

        $state_parts = explode('|', sanitize_text_field($_GET['state']));
        if (count($state_parts) !== 2) return;

        $nonce = $state_parts[0];
        $provider = $state_parts[1];

        if (!wp_verify_nonce($nonce, 'pr_social_' . $provider)) return;

        $code = sanitize_text_field($_GET['code']);

        if ($provider === 'google') {
            self::process_google_callback($code);
        } elseif ($provider === 'wordpress') {
            self::process_wordpress_callback($code);
        }
    }

    protected static function process_google_callback($code) : void {
        $opt = self::get_options();
        if (empty($opt['google_client_id']) || empty($opt['google_client_secret'])) return;

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'code'          => $code,
                'client_id'     => $opt['google_client_id'],
                'client_secret' => $opt['google_client_secret'],
                'redirect_uri'  => home_url('/'),
                'grant_type'    => 'authorization_code',
            ]
        ]);

        if (is_wp_error($response)) {
            self::set_flash('error', $response->get_error_message());
            wp_safe_redirect(self::page_url('login'));
            exit;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token'])) {
            self::set_flash('error', __('Kunde inte logga in med Google.', 'sh-review-members'));
            wp_safe_redirect(self::page_url('login'));
            exit;
        }

        // Get user info
        $info_response = wp_remote_get('https://www.googleapis.com/oauth2/v3/userinfo', [
            'headers' => [
                'Authorization' => 'Bearer ' . $body['access_token'],
            ]
        ]);

        if (is_wp_error($info_response)) {
            self::set_flash('error', $info_response->get_error_message());
            wp_safe_redirect(self::page_url('login'));
            exit;
        }

        $user_info = json_decode(wp_remote_retrieve_body($info_response), true);

        if (empty($user_info['email'])) {
            self::set_flash('error', __('Kunde inte hämta e-post från Google.', 'sh-review-members'));
            wp_safe_redirect(self::page_url('login'));
            exit;
        }

        self::login_or_register_oauth_user($user_info['email'], $user_info['name'] ?? '', $user_info['given_name'] ?? '', $user_info['family_name'] ?? '');
    }

    protected static function process_wordpress_callback($code) : void {
        $opt = self::get_options();
        if (empty($opt['wordpress_client_id']) || empty($opt['wordpress_client_secret'])) return;

        $response = wp_remote_post('https://public-api.wordpress.com/oauth2/token', [
            'body' => [
                'client_id'     => $opt['wordpress_client_id'],
                'client_secret' => $opt['wordpress_client_secret'],
                'redirect_uri'  => home_url('/'),
                'code'          => $code,
                'grant_type'    => 'authorization_code',
            ]
        ]);

        if (is_wp_error($response)) {
            self::set_flash('error', $response->get_error_message());
            wp_safe_redirect(self::page_url('login'));
            exit;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token'])) {
            self::set_flash('error', __('Kunde inte logga in med WordPress.com.', 'sh-review-members'));
            wp_safe_redirect(self::page_url('login'));
            exit;
        }

        // Get user info
        $info_response = wp_remote_get('https://public-api.wordpress.com/rest/v1/me', [
            'headers' => [
                'Authorization' => 'Bearer ' . $body['access_token'],
            ]
        ]);

        if (is_wp_error($info_response)) {
            self::set_flash('error', $info_response->get_error_message());
            wp_safe_redirect(self::page_url('login'));
            exit;
        }

        $user_info = json_decode(wp_remote_retrieve_body($info_response), true);

        if (empty($user_info['email'])) {
            self::set_flash('error', __('Kunde inte hämta e-post från WordPress.com.', 'sh-review-members'));
            wp_safe_redirect(self::page_url('login'));
            exit;
        }

        self::login_or_register_oauth_user($user_info['email'], $user_info['display_name'] ?? '', '', '');
    }

    protected static function login_or_register_oauth_user($email, $display_name, $first_name, $last_name) : void {
        $user = get_user_by('email', $email);

        if (!$user) {
            if (!get_option('users_can_register')) {
                self::set_flash('error', __('Registrering är avstängd och inget konto med denna e-post hittades.', 'sh-review-members'));
                wp_safe_redirect(self::page_url('login'));
                exit;
            }

            // Create user
            $username = sanitize_user(explode('@', $email)[0], true);
            // Ensure unique username
            $base_username = $username;
            $i = 1;
            while (username_exists($username)) {
                $username = $base_username . $i;
                $i++;
            }

            $password = wp_generate_password();
            $user_id = wp_create_user($username, $password, $email);

            if (is_wp_error($user_id)) {
                self::set_flash('error', $user_id->get_error_message());
                wp_safe_redirect(self::page_url('login'));
                exit;
            }

            $user = get_user_by('id', $user_id);

            // Update profile with names if available
            wp_update_user([
                'ID' => $user_id,
                'display_name' => $display_name ?: $username,
                'first_name' => $first_name,
                'last_name' => $last_name,
            ]);
        }

        // Log in
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);

        wp_safe_redirect(self::redirect_after_login());
        exit;
    }
}
