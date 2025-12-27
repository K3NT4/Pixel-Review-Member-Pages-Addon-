<?php
// includes/traits/trait-prmp-flash.php
if (!defined('ABSPATH')) { exit; }

trait PRMP_Flash {

    /* =========================================================
     * Flash messages (sessionless via cookie)
     * ======================================================= */

    protected static function set_flash(string $type, string $message) : void {
        // Store a short-lived cookie for the next page load.
        $payload = wp_json_encode([
            't' => $type,
            'm' => wp_strip_all_tags($message),
        ]);

        if (!is_string($payload)) return;

        setcookie('pr_flash', $payload, [
            'expires' => time() + 60,
            'path' => COOKIEPATH ?: '/',
            'domain' => COOKIE_DOMAIN ?: '',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $_COOKIE['pr_flash'] = $payload;
    }

    protected static function read_flash() : array {
        if (empty($_COOKIE['pr_flash'])) return [];
        $raw = wp_unslash($_COOKIE['pr_flash']);
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['m']) || empty($data['t'])) return [];

        // Delete cookie after read.
        setcookie('pr_flash', '', [
            'expires' => time() - 3600,
            'path' => COOKIEPATH ?: '/',
            'domain' => COOKIE_DOMAIN ?: '',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        return [
            'type' => sanitize_key($data['t']),
            'message' => sanitize_text_field($data['m']),
        ];
    }

    protected static function render_flash() : string {
        $flash = self::read_flash();
        if (!$flash) return '';

        $type = $flash['type'] === 'success' ? 'success' : 'error';
        $cls = $type === 'success' ? 'pr-notice pr-notice--success' : 'pr-notice pr-notice--error';

        return sprintf(
            '<div class="%s" role="status">%s</div>',
            esc_attr($cls),
            esc_html($flash['message'])
        );
    }
}
