<?php
// includes/traits/trait-prmp-restrictions.php
if (!defined('ABSPATH')) { exit; }

trait PRMP_Restrictions {

    /**
     * Redirect wp-login.php to the configured front-end pages (login/registration) for improved UX.
     *
     * Notes:
     *  - We do not interfere with password reset flows.
     *  - We keep WordPress core available as fallback if no front-end pages are configured.
     */
    public static function maybe_redirect_wp_login() : void {
        $opt = self::get_options();
        if (empty($opt['enabled']) || empty($opt['redirect_wp_login'])) {
            return;
        }

        // Don't interfere with interim login (modal).
        if (!empty($_REQUEST['interim-login'])) {
            return;
        }

        // Don't interfere with the "Check email" confirmation screen.
        if (isset($_GET['checkemail'])) {
            return;
        }

        $action = isset($_REQUEST['action']) ? sanitize_key((string)$_REQUEST['action']) : '';
        // Don't interfere with reset flows or post password protected.
        $allow = ['lostpassword', 'retrievepassword', 'resetpass', 'rp', 'postpass', 'confirmaction', 'logout'];
        if ($action && in_array($action, $allow, true)) {
            return;
        }

        // If the user is already logged in, send them to the chosen destination.
        if (is_user_logged_in()) {
            wp_safe_redirect(self::redirect_after_login());
            exit;
        }

        $login_url = self::page_url('login');
        if (!$login_url) {
            return;
        }

        // Route register action to the register page if configured.
        if ($action === 'register') {
            $register_url = self::page_url('register');
            if ($register_url) {
                wp_safe_redirect($register_url);
                exit;
            }
        }

        // Preserve redirect_to if present.
        if (!empty($_REQUEST['redirect_to'])) {
            $rt = esc_url_raw(wp_unslash((string)$_REQUEST['redirect_to']));
            if ($rt) {
                $login_url = add_query_arg('redirect_to', $rt, $login_url);
            }
        }

        wp_safe_redirect($login_url);
        exit;
    }

    public static function maybe_block_wp_admin() : void {
        $opt = self::get_options();
        if (empty($opt['enabled']) || empty($opt['block_wp_admin'])) return;

        if (defined('DOING_AJAX') && DOING_AJAX) return;
        if (defined('DOING_CRON') && DOING_CRON) return;

        if (!self::user_is_blocked_role()) return;

        /**
         * Allow a few safe admin endpoints:
         *  - admin-post.php: safe POST actions
         *  - admin-ajax.php / async-upload.php: editor and media
         */
        $script = basename($_SERVER['PHP_SELF'] ?? '');
        if (in_array($script, ['admin-post.php', 'admin-ajax.php', 'async-upload.php'], true)) {
            return;
        }

        // Allow editing screens if the user can actually edit posts (the list is still restricted by caps).
        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && !empty($screen->base)) {
                $allow_bases = ['post', 'edit', 'upload'];
                if (in_array($screen->base, $allow_bases, true)) {
                    return;
                }
            }
        }

        $dashboard_url = self::page_url('dashboard');
        if (!$dashboard_url) $dashboard_url = home_url('/');
        wp_safe_redirect($dashboard_url);
        exit;
    }

    public static function maybe_hide_admin_bar($show) {
        $opt = self::get_options();
        if (empty($opt['enabled']) || empty($opt['disable_admin_bar'])) return $show;
        if (self::user_is_blocked_role()) return false;
        return $show;
    }

    /**
     * Ensure that blocked roles can only edit their own content.
     *
     * This makes the dashboard/editor links safe even if a role is later granted extra capabilities.
     * It does NOT affect administrators/editors unless you add those roles to the blocked list.
     */
    public static function enforce_author_ownership_caps(array $caps, string $cap, int $user_id, array $args) : array {
        $opt = self::get_options();
        if (empty($opt['enabled'])) return $caps;

        if (!$user_id || !self::user_is_blocked_role($user_id)) return $caps;

        // Only guard actions that accept a post ID.
        if (!in_array($cap, ['edit_post', 'delete_post', 'read_post'], true)) {
            return $caps;
        }

        $post_id = isset($args[0]) ? absint($args[0]) : 0;
        if (!$post_id) return $caps;

        $post = get_post($post_id);
        if (!$post) return $caps;

        if ((int)$post->post_author !== (int)$user_id) {
            return ['do_not_allow'];
        }

        return $caps;
    }
}
