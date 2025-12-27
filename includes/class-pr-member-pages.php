<?php
// includes/class-pr-member-pages.php
if (!defined('ABSPATH')) { exit; }

/**
 * Front-end member pages addon.
 *
 * Shortcodes:
 *  - [pr_login]
 *  - [pr_register]
 *  - [pr_logout]
 *  - [pr_profile]
 *  - [pr_dashboard]
 *  - [pr_post_edit] (redirect to wp-admin editor)
 */

// Split into smaller logical files.
require_once __DIR__ . '/traits/trait-prmp-options.php';
require_once __DIR__ . '/traits/trait-prmp-author-profile.php';
require_once __DIR__ . '/traits/trait-prmp-admin-editor.php';
require_once __DIR__ . '/traits/trait-prmp-restrictions.php';
require_once __DIR__ . '/traits/trait-prmp-admin-settings.php';
require_once __DIR__ . '/traits/trait-prmp-flash.php';
require_once __DIR__ . '/traits/trait-prmp-actions.php';
require_once __DIR__ . '/traits/trait-prmp-shortcodes.php';
require_once __DIR__ . '/traits/trait-prmp-pages.php';

class PR_Member_Pages {

    private const OPT_KEY = 'sh_review_members_pages';

    use PRMP_Options;
    use PRMP_Author_Profile;
    use PRMP_Admin_Editor;
    use PRMP_Restrictions;
    use PRMP_Admin_Settings;
    use PRMP_Flash;
    use PRMP_Actions;
    use PRMP_Shortcodes;
    use PRMP_Pages;

    public static function init() : void {
        // Assets
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        // Shortcodes
        add_shortcode('pr_login', [__CLASS__, 'sc_login']);
        add_shortcode('pr_register', [__CLASS__, 'sc_register']);
        add_shortcode('pr_logout', [__CLASS__, 'sc_logout']);
        add_shortcode('pr_profile', [__CLASS__, 'sc_profile']);
        add_shortcode('pr_dashboard', [__CLASS__, 'sc_dashboard']);
        add_shortcode('pr_post_edit', [__CLASS__, 'sc_post_edit']);

        // Form handlers
        add_action('init', [__CLASS__, 'handle_actions']);
        add_action('login_init', [__CLASS__, 'maybe_redirect_wp_login']);

        // Admin restrictions for members
        add_action('admin_init', [__CLASS__, 'maybe_block_wp_admin']);
        add_filter('show_admin_bar', [__CLASS__, 'maybe_hide_admin_bar']);

        // Ensure blocked roles can only edit their own content (even if they get extra caps later).
        add_filter('map_meta_cap', [__CLASS__, 'enforce_author_ownership_caps'], 20, 4);

        // Admin settings UI (under Pixel Review)
        add_action('admin_menu', [__CLASS__, 'register_admin_menu'], 50);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function enqueue_assets() : void {
        $opt = self::get_options();
        if (empty($opt['enabled'])) return;

        $css = SH_REVIEW_MEMBERS_DIR . 'assets/css/pr-member-pages.css';
        $ver = SH_REVIEW_MEMBERS_VERSION . '.' . (file_exists($css) ? filemtime($css) : time());
        wp_enqueue_style('sh-review-members', SH_REVIEW_MEMBERS_URL . 'assets/css/pr-member-pages.css', [], $ver);
    }

    /* =========================================================
     * Activation / Deactivation
     * ======================================================= */

    public static function activate() : void {
        $opt = self::get_options();

        // First install: persist defaults
        if (!get_option(self::OPT_KEY, null)) {
            update_option(self::OPT_KEY, $opt);
        }

        if (!empty($opt['create_pages_on_activate'])) {
            self::maybe_create_pages();
        }

        flush_rewrite_rules();
    }

    public static function deactivate() : void {
        flush_rewrite_rules();
    }
}
