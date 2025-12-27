<?php
/**
 * Plugin Name: Pixel Review — Member Pages (Addon)
 * Plugin URI: https://spelhubben.se/plugins/pixel-review
 * Description: Adds branded front-end member pages (login, register, profile, dashboard) for Pixel Review sites.
 * Version: 1.0.0
 * Author: Spel Hubben
 * Author URI: https://spelhubben.se
 * Text Domain: sh-review-members
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) { exit; }

// --- Constants ---
define('SH_REVIEW_MEMBERS_FILE', __FILE__);
define('SH_REVIEW_MEMBERS_DIR', plugin_dir_path(__FILE__));
define('SH_REVIEW_MEMBERS_URL', plugin_dir_url(__FILE__));
define('SH_REVIEW_MEMBERS_VERSION', '1.1.1');

require_once SH_REVIEW_MEMBERS_DIR . 'includes/class-pr-member-pages.php';

/**
 * Bootstrap.
 */
add_action('plugins_loaded', function () {
    // Hard dependency: Pixel Review must be active.
    if (!defined('SH_REVIEW_VERSION')) {
        add_action('admin_notices', function () {
            if (!current_user_can('activate_plugins')) return;
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('Pixel Review — Member Pages kräver att huvudpluginet "Pixel Review" är aktiverat.', 'sh-review-members');
            echo '</p></div>';
        });
        return;
    }

    PR_Member_Pages::init();
});

register_activation_hook(__FILE__, ['PR_Member_Pages', 'activate']);
register_deactivation_hook(__FILE__, ['PR_Member_Pages', 'deactivate']);
