<?php
// includes/traits/trait-prmp-avatar.php
if (!defined('ABSPATH')) { exit; }

/**
 * Local avatar support.
 *
 * Pixel Review and WordPress generally use get_avatar()/get_avatar_url(). This
 * trait lets the addon store an explicit avatar image URL on the user and then
 * override get_avatar_url when present.
 */
trait PRMP_Avatar {

    /**
     * User meta key storing the custom avatar URL.
     */
    protected const PRMP_AVATAR_META_KEY = 'sh_review_author_avatar';

    /**
     * Register avatar filters.
     */
    public static function init_avatar() : void {
        add_filter('get_avatar_url', [__CLASS__, 'prmp_filter_avatar_url'], 10, 3);
    }

    /**
     * Return the stored custom avatar URL (if any).
     */
    protected static function prmp_get_custom_avatar_url(int $user_id) : string {
        $url = get_user_meta($user_id, self::PRMP_AVATAR_META_KEY, true);
        $url = is_string($url) ? trim($url) : '';
        if ($url === '') return '';

        $url = esc_url_raw($url);
        return $url ?: '';
    }

    /**
     * Save custom avatar URL from the profile form POST.
     */
    protected static function prmp_save_custom_avatar_from_post(int $user_id) : void {
        if (!array_key_exists('pr_author_avatar_url', $_POST)) return;

        $raw = trim((string) wp_unslash($_POST['pr_author_avatar_url']));
        if ($raw === '') {
            delete_user_meta($user_id, self::PRMP_AVATAR_META_KEY);
            return;
        }

        $url = esc_url_raw($raw);
        if ($url === '') {
            delete_user_meta($user_id, self::PRMP_AVATAR_META_KEY);
            return;
        }

        update_user_meta($user_id, self::PRMP_AVATAR_META_KEY, $url);
    }

    /**
     * Filter: override avatar URL when a custom avatar is stored.
     *
     * @param string          $url
     * @param int|object|mixed $id_or_email
     * @param array           $args
     */
    public static function prmp_filter_avatar_url(string $url, $id_or_email, array $args) : string {
        // Only affect output when the addon feature is enabled.
        if (method_exists(__CLASS__, 'get_options')) {
            $opt = self::get_options();
            if (empty($opt['enabled'])) {
                return $url;
            }
        }

        $user_id = self::prmp_resolve_user_id_from_avatar($id_or_email);
        if ($user_id <= 0) return $url;

        $custom = self::prmp_get_custom_avatar_url($user_id);
        return $custom !== '' ? $custom : $url;
    }

    /**
     * Resolve a user ID from the typical get_avatar() $id_or_email argument.
     */
    protected static function prmp_resolve_user_id_from_avatar($id_or_email) : int {
        if (is_numeric($id_or_email)) {
            return (int) $id_or_email;
        }

        if ($id_or_email instanceof WP_User) {
            return (int) $id_or_email->ID;
        }

        if ($id_or_email instanceof WP_Comment) {
            $email = $id_or_email->comment_author_email;
            if ($email && is_email($email)) {
                $u = get_user_by('email', $email);
                return $u ? (int) $u->ID : 0;
            }
            return 0;
        }

        if (is_string($id_or_email) && is_email($id_or_email)) {
            $u = get_user_by('email', $id_or_email);
            return $u ? (int) $u->ID : 0;
        }

        return 0;
    }
}
