<?php
// Mock WordPress environment
if (!defined('ABSPATH')) { define('ABSPATH', '/tmp/'); }
if (!defined('SH_REVIEW_MEMBERS_DIR')) { define('SH_REVIEW_MEMBERS_DIR', __DIR__ . '/../'); }
if (!defined('SH_REVIEW_MEMBERS_URL')) { define('SH_REVIEW_MEMBERS_URL', 'http://example.com/wp-content/plugins/sh-review-members/'); }
if (!defined('SH_REVIEW_MEMBERS_VERSION')) { define('SH_REVIEW_MEMBERS_VERSION', '1.0.0'); }

// Mock functions
$options_mock = [];
function get_option($key, $default = false) { global $options_mock; return $options_mock[$key] ?? $default; }
function update_option($key, $value) { global $options_mock; $options_mock[$key] = $value; }
function sanitize_key($key) { return $key; }
function is_user_logged_in() { global $is_logged_in; return $is_logged_in ?? false; }
function wp_safe_redirect($location) { echo "Redirecting to: $location\n"; }
function get_permalink($id) { return "http://example.com/?p=$id"; }
function home_url($path = '') { return "http://example.com" . $path; }
function add_query_arg($key, $val, $url) { return $url . (strpos($url, '?') ? '&' : '?') . "$key=$val"; }
function esc_url_raw($url) { return $url; }
function wp_unslash($val) { return $val; }
function is_ssl() { return false; }
function absint($val) { return (int)$val; }

// Include necessary files
require_once __DIR__ . '/../includes/traits/trait-prmp-options.php';
require_once __DIR__ . '/../includes/traits/trait-prmp-restrictions.php';

class MockPRMemberPages {
    const OPT_KEY = 'sh_review_members_pages';
    use PRMP_Options;
    use PRMP_Restrictions;
}

// Test cases
echo "Test 1: Redirect enabled, not logged in, no action\n";
$options_mock[MockPRMemberPages::OPT_KEY] = [
    'enabled' => 1,
    'redirect_wp_login' => 1,
    'page_ids' => ['login' => 10, 'register' => 11, 'dashboard' => 12]
];
$_REQUEST = [];
MockPRMemberPages::maybe_redirect_wp_login();

echo "\nTest 2: Redirect enabled, not logged in, register action\n";
$_REQUEST['action'] = 'register';
MockPRMemberPages::maybe_redirect_wp_login();

echo "\nTest 3: Redirect enabled, logged in\n";
$is_logged_in = true;
$_REQUEST = [];
MockPRMemberPages::maybe_redirect_wp_login();

echo "\nTest 4: Redirect disabled\n";
$options_mock[MockPRMemberPages::OPT_KEY]['redirect_wp_login'] = 0;
MockPRMemberPages::maybe_redirect_wp_login();

echo "\nTest 5: Redirect enabled, lostpassword action\n";
$options_mock[MockPRMemberPages::OPT_KEY]['redirect_wp_login'] = 1;
$_REQUEST['action'] = 'lostpassword';
MockPRMemberPages::maybe_redirect_wp_login();
