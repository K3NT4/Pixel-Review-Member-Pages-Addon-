<?php
// includes/traits/trait-prmp-social-login.php
if (!defined('ABSPATH')) { exit; }

trait PRMP_Social_Login {

    /**
     * Get the social login URL for a provider.
     */
    public static function get_social_login_url(string $provider) : string {
        $opt = self::get_options();
        $redirect_uri = home_url('/?pr_social_login=' . $provider);

        if ($provider === 'google') {
            $client_id = $opt['google_client_id'] ?? '';
            if (!$client_id) return '';

            return 'https://accounts.google.com/o/oauth2/v2/auth' . '?' . http_build_query([
                'response_type' => 'code',
                'client_id'     => $client_id,
                'redirect_uri'  => $redirect_uri,
                'scope'         => 'openid email profile',
                'access_type'   => 'online',
            ]);
        }

        if ($provider === 'wordpress') {
            $client_id = $opt['wordpress_client_id'] ?? '';
            if (!$client_id) return '';

            return 'https://public-api.wordpress.com/oauth2/authorize' . '?' . http_build_query([
                'response_type' => 'code',
                'client_id'     => $client_id,
                'redirect_uri'  => $redirect_uri,
                'scope'         => 'auth',
            ]);
        }

        return '';
    }

    /**
     * Initialize social login handler.
     */
    public static function init_social_login() : void {
        add_action('init', [__CLASS__, 'handle_social_login_callback']);
    }

    /**
     * Handle the callback from social providers.
     */
    public static function handle_social_login_callback() : void {
        if (!isset($_GET['pr_social_login'])) return;

        $provider = sanitize_key($_GET['pr_social_login']);
        $code = sanitize_text_field($_GET['code'] ?? '');

        if (!$code) return;

        $user_email = '';
        $user_name = '';
        $first_name = '';
        $last_name = '';

        if ($provider === 'google') {
             $data = self::verify_google_token($code);
             if (!$data) {
                 self::set_flash('error', __('Kunde inte verifiera inloggning med Google.', 'sh-review-members'));
                 wp_safe_redirect(self::page_url('login'));
                 exit;
             }
             $user_email = $data['email'];
             $first_name = $data['given_name'] ?? '';
             $last_name = $data['family_name'] ?? '';
        } elseif ($provider === 'wordpress') {
             $data = self::verify_wordpress_token($code);
             if (!$data) {
                 self::set_flash('error', __('Kunde inte verifiera inloggning med WordPress.com.', 'sh-review-members'));
                 wp_safe_redirect(self::page_url('login'));
                 exit;
             }
             $user_email = $data['email'];
             $user_name = $data['username'];
             $first_name = ''; // WordPress.com doesn't always provide name split
        } else {
            return;
        }

        if (!$user_email) {
            self::set_flash('error', __('Kunde inte hämta e-postadress från leverantören.', 'sh-review-members'));
            wp_safe_redirect(self::page_url('login'));
            exit;
        }

        // Login or Register
        $user = get_user_by('email', $user_email);

        if (!$user) {
            // Registration
            if (!get_option('users_can_register')) {
                self::set_flash('error', __('Registrering är avstängd.', 'sh-review-members'));
                wp_safe_redirect(self::page_url('login'));
                exit;
            }

            $username = $user_name ?: sanitize_user(explode('@', $user_email)[0], true);

            // Ensure unique username
            $original_username = $username;
            $i = 1;
            while (username_exists($username)) {
                $username = $original_username . $i;
                $i++;
            }

            $password = wp_generate_password();
            $user_id = wp_create_user($username, $password, $user_email);

            if (is_wp_error($user_id)) {
                self::set_flash('error', $user_id->get_error_message());
                wp_safe_redirect(self::page_url('login'));
                exit;
            }

            if ($first_name) update_user_meta($user_id, 'first_name', $first_name);
            if ($last_name) update_user_meta($user_id, 'last_name', $last_name);

            $user = get_user_by('id', $user_id);
        }

        // Log the user in
        wp_set_current_user($user->ID, $user->user_login);
        wp_set_auth_cookie($user->ID, true);
        do_action('wp_login', $user->user_login, $user);

        wp_safe_redirect(self::redirect_after_login());
        exit;
    }

    private static function verify_google_token(string $code) : ?array {
        $opt = self::get_options();
        $client_id = $opt['google_client_id'] ?? '';
        $client_secret = $opt['google_client_secret'] ?? '';
        $redirect_uri = home_url('/?pr_social_login=google');

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'code' => $code,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code'
            ]
        ]);

        if (is_wp_error($response)) return null;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token'])) return null;

        // Get user info
        $info_response = wp_remote_get('https://www.googleapis.com/oauth2/v2/userinfo', [
            'headers' => [
                'Authorization' => 'Bearer ' . $body['access_token']
            ]
        ]);

        if (is_wp_error($info_response)) return null;

        return json_decode(wp_remote_retrieve_body($info_response), true);
    }

    private static function verify_wordpress_token(string $code) : ?array {
        $opt = self::get_options();
        $client_id = $opt['wordpress_client_id'] ?? '';
        $client_secret = $opt['wordpress_client_secret'] ?? '';
        $redirect_uri = home_url('/?pr_social_login=wordpress');

        $response = wp_remote_post('https://public-api.wordpress.com/oauth2/token', [
            'body' => [
                'code' => $code,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code'
            ]
        ]);

        if (is_wp_error($response)) return null;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token'])) return null;

        // Get user info
        $info_response = wp_remote_get('https://public-api.wordpress.com/rest/v1/me', [
            'headers' => [
                'Authorization' => 'Bearer ' . $body['access_token']
            ]
        ]);

        if (is_wp_error($info_response)) return null;

        return json_decode(wp_remote_retrieve_body($info_response), true);
    }
}
