<?php
// includes/traits/trait-prmp-captcha.php
if (!defined('ABSPATH')) { exit; }

trait PRMP_Captcha {

    public static function init_captcha() : void {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_captcha_scripts']);
    }

    public static function enqueue_captcha_scripts() : void {
        $opt = self::get_options();
        $provider = $opt['captcha_provider'] ?? 'native';

        if (!self::is_login_related_page()) return;

        if ($provider === 'cloudflare' && !empty($opt['cf_site_key'])) {
            wp_enqueue_script('turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', [], null, true);
        } elseif ($provider === 'google' && !empty($opt['google_recaptcha_site_key'])) {
            wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js', [], null, true);
        }
    }

    public static function render_captcha() : void {
        $opt = self::get_options();
        $provider = $opt['captcha_provider'] ?? 'native';

        if ($provider === 'native') {
            // Honeypot
            echo '<input type="text" name="pr_hp" value="" style="display:none !important;" tabindex="-1" autocomplete="off">';
            // Time trap
            echo '<input type="hidden" name="pr_ts" value="' . time() . '">';
            return;
        }

        if ($provider === 'cloudflare') {
            $key = $opt['cf_site_key'] ?? '';
            if ($key) {
                echo '<div class="cf-turnstile" data-sitekey="' . esc_attr($key) . '" style="margin-bottom:1rem;"></div>';
            }
            return;
        }

        if ($provider === 'google') {
            $key = $opt['google_recaptcha_site_key'] ?? '';
            if ($key) {
                echo '<div class="g-recaptcha" data-sitekey="' . esc_attr($key) . '" style="margin-bottom:1rem;"></div>';
            }
            return;
        }
    }

    /**
     * @return true|WP_Error
     */
    public static function verify_captcha() {
        $opt = self::get_options();
        $provider = $opt['captcha_provider'] ?? 'native';

        if ($provider === 'none') {
            return true;
        }

        if ($provider === 'native') {
            // Honeypot
            if (!empty($_POST['pr_hp'])) {
                return new WP_Error('spam_honeypot', __('Otillåten åtgärd.', 'sh-review-members'));
            }
            // Time trap (min 1s for login, 2s for register - let's standardise on 1s to be safe for both, or use context)
            $ts = (int)($_POST['pr_ts'] ?? 0);
            $min_time = 1;
            // If strictly needed, we could pass context, but 1s is safe baseline.
            // Since this is shared, let's say 1 second. Fast bots are < 100ms.
            if (time() - $ts < $min_time) {
                return new WP_Error('spam_timetrap', __('Försök igen långsammare.', 'sh-review-members'));
            }
            return true;
        }

        if ($provider === 'cloudflare') {
            return self::verify_cloudflare();
        }

        if ($provider === 'google') {
            return self::verify_google_recaptcha();
        }

        return true;
    }

    protected static function verify_cloudflare() {
        $token = $_POST['cf-turnstile-response'] ?? '';
        if (empty($token)) {
            return new WP_Error('captcha_missing', __('Verifiera att du är en människa (Cloudflare).', 'sh-review-members'));
        }

        $opt = self::get_options();
        $secret = $opt['cf_secret_key'] ?? '';
        if (!$secret) return true; // Config error, fail open or closed? Usually fail open if misconfigured to not block users.

        $response = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'body' => [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('captcha_error', $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['success'])) {
             return new WP_Error('captcha_failed', __('Captcha-verifiering misslyckades.', 'sh-review-members'));
        }

        return true;
    }

    protected static function verify_google_recaptcha() {
        $token = $_POST['g-recaptcha-response'] ?? '';
        if (empty($token)) {
            return new WP_Error('captcha_missing', __('Verifiera att du är en människa (reCAPTCHA).', 'sh-review-members'));
        }

        $opt = self::get_options();
        $secret = $opt['google_recaptcha_secret_key'] ?? '';
        if (!$secret) return true;

        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('captcha_error', $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['success'])) {
             return new WP_Error('captcha_failed', __('Captcha-verifiering misslyckades.', 'sh-review-members'));
        }

        return true;
    }
}
