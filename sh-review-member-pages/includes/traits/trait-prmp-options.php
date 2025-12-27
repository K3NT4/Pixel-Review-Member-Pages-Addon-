<?php
// includes/traits/trait-prmp-options.php
if (!defined('ABSPATH')) { exit; }

trait PRMP_Options {

    /**
     * Default settings.
     */
    public static function defaults() : array {
        return [
            'enabled' => 1,
            'create_pages_on_activate' => 1,
            'redirect_wp_login' => 1,
            'page_ids' => [
                'login'     => 0,
                'register'  => 0,
                'dashboard' => 0,
                'profile'   => 0,
                'logout'    => 0,
                'post_edit' => 0,
            ],
            'redirect_after_login' => 'dashboard', // dashboard|profile|home
            'block_wp_admin' => 1,
            'blocked_roles' => ['subscriber', 'customer'],
            'disable_admin_bar' => 1,

            // Dashboard content
            'dashboard_post_types' => ['post'],
            'dashboard_posts_per_page' => 20,

            // Pixel Review coupling
            'dashboard_only_pixel_reviews' => 1,
            'dashboard_show_review_meta' => 1,

            // Create / edit behaviour (admin editor)
            'allow_frontend_create' => 1,
        ];
    }

    public static function get_options() : array {
        $opt = get_option(self::OPT_KEY, []);
        if (!is_array($opt)) $opt = [];
        return array_replace_recursive(self::defaults(), $opt);
    }

    public static function update_options(array $new) : void {
        $opt = array_replace_recursive(self::get_options(), $new);
        update_option(self::OPT_KEY, $opt);
    }

    /* =========================================================
     * Pixel Review meta helpers
     * ======================================================= */

    protected static function meta_key(string $key) : string {
        // Prefer SH_Review_Core constants when available, otherwise fall back.
        if (class_exists('SH_Review_Core')) {
            switch ($key) {
                case 'score':   return SH_Review_Core::META_SCORE;
                case 'sum':     return SH_Review_Core::META_SUM;
                case 'pros':    return SH_Review_Core::META_PROS;
                case 'cons':    return SH_Review_Core::META_CONS;
                case 'mode':    return SH_Review_Core::META_MODE;
                case 'pubdate': return SH_Review_Core::META_PUBDATE;
                default:        break;
            }
        }

        // Fallbacks (must match Pixel Review core plugin constants)
        return match ($key) {
            'score'   => '_sh_review_score',
            'sum'     => '_sh_review_summary',
            'pros'    => '_sh_review_pros',
            'cons'    => '_sh_review_cons',
            'mode'    => '_sh_review_mode',
            'pubdate' => '_sh_review_date',
            default   => '',
        };
    }

    protected static function has_pixel_review_meta(int $post_id) : bool {
        $k = self::meta_key('score');
        if (!$k) return false;
        return metadata_exists('post', $post_id, $k);
    }

    /* =========================================================
     * Core helpers
     * ======================================================= */

    protected static function page_url(string $key) : string {
        $opt = self::get_options();
        $id = absint($opt['page_ids'][$key] ?? 0);
        if (!$id) return '';
        $url = get_permalink($id);
        return is_string($url) ? $url : '';
    }

    protected static function current_url() : string {
        $scheme = is_ssl() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        if (!$host) return home_url('/');
        return esc_url_raw($scheme . '://' . $host . $uri);
    }

    protected static function redirect_after_login() : string {
        $opt = self::get_options();
        $choice = $opt['redirect_after_login'] ?? 'dashboard';
        if ($choice === 'profile') {
            return self::page_url('profile') ?: home_url('/');
        }
        if ($choice === 'home') {
            return home_url('/');
        }
        return self::page_url('dashboard') ?: home_url('/');
    }

    protected static function is_login_related_page() : bool {
        $opt = self::get_options();
        $ids = array_map('absint', (array)($opt['page_ids'] ?? []));
        $current = get_queried_object_id();
        return $current && in_array($current, $ids, true);
    }

    protected static function dashboard_post_types() : array {
        $opt = self::get_options();
        $types = array_values(array_filter(array_map('sanitize_key', (array)($opt['dashboard_post_types'] ?? ['post']))));

        // If a site uses a dedicated review post type (common name: "reviews"), include it automatically.
        if (post_type_exists('reviews') && !in_array('reviews', $types, true)) {
            $types[] = 'reviews';
        }

        // Keep only existing post types.
        $types = array_values(array_filter($types, 'post_type_exists'));
        return $types ?: ['post'];
    }

    protected static function user_is_blocked_role(?int $user_id = null) : bool {
        $user_id = $user_id ?: get_current_user_id();
        if (!$user_id) return false;

        $opt = self::get_options();
        $user = get_user_by('id', $user_id);
        if (!$user instanceof WP_User) return false;

        $blocked = (array)($opt['blocked_roles'] ?? []);
        foreach ((array)$user->roles as $r) {
            if (in_array($r, $blocked, true)) return true;
        }
        return false;
    }

    protected static function can_show_create_actions() : bool {
        $opt = self::get_options();
        if (empty($opt['enabled']) || empty($opt['allow_frontend_create'])) return false;
        if (!is_user_logged_in()) return false;

        // Allow WP editor navigation only for users who can edit their own posts.
        return current_user_can('edit_posts');
    }
}
