<?php
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
 *  - [pr_post_edit] (valfritt: enkel frontend-redigering av egna inlägg)
 */
class PR_Member_Pages {

    private const OPT_KEY = 'sh_review_members_pages';

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
            'dashboard_post_types' => ['post'],
            'dashboard_posts_per_page' => 20,
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

        // Optional: redirect wp-login.php to front-end pages
        add_action('login_init', [__CLASS__, 'maybe_redirect_wp_login']);

        // Admin restrictions for members
        add_action('admin_init', [__CLASS__, 'maybe_block_wp_admin']);
        add_filter('show_admin_bar', [__CLASS__, 'maybe_hide_admin_bar']);

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

    /* =========================================================
     * Admin: settings page
     * ======================================================= */

    public static function register_admin_menu() : void {
        // Add as submenu under Pixel Review top-level if it exists.
        add_submenu_page(
            'sh-review',
            __('Member Pages', 'sh-review-members'),
            __('Member Pages', 'sh-review-members'),
            'manage_options',
            'sh-review-member-pages',
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function register_settings() : void {
        register_setting('sh_review_members_pages_group', self::OPT_KEY, [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_options'],
            'default' => self::defaults(),
        ]);

        add_settings_section(
            'sh_review_members_main',
            __('Member Pages', 'sh-review-members'),
            function () {
                echo '<p>' . esc_html__('Konfigurera front-end inloggning/registrering och “Mina sidor” utan att medlemmar behöver se wp-admin.', 'sh-review-members') . '</p>';
            },
            'sh-review-member-pages'
        );

        add_settings_field(
            'enabled',
            __('Aktivera funktion', 'sh-review-members'),
            [__CLASS__, 'field_enabled'],
            'sh-review-member-pages',
            'sh_review_members_main'
        );

        add_settings_field(
            'pages',
            __('Sidor & shortcodes', 'sh-review-members'),
            [__CLASS__, 'field_pages'],
            'sh-review-member-pages',
            'sh_review_members_main'
        );

        add_settings_field(
            'admin_block',
            __('Begränsa wp-admin', 'sh-review-members'),
            [__CLASS__, 'field_admin_block'],
            'sh-review-member-pages',
            'sh_review_members_main'
        );

        add_settings_field(
            'dashboard',
            __('Dashboard', 'sh-review-members'),
            [__CLASS__, 'field_dashboard'],
            'sh-review-member-pages',
            'sh_review_members_main'
        );
    }

    public static function sanitize_options($input) : array {
        $out = self::get_options();
        if (!is_array($input)) return $out;

        $out['enabled'] = !empty($input['enabled']) ? 1 : 0;
        $out['create_pages_on_activate'] = !empty($input['create_pages_on_activate']) ? 1 : 0;
        $out['redirect_wp_login'] = !empty($input['redirect_wp_login']) ? 1 : 0;

        $out['redirect_after_login'] = in_array(($input['redirect_after_login'] ?? ''), ['dashboard', 'profile', 'home'], true)
            ? $input['redirect_after_login']
            : $out['redirect_after_login'];

        $out['block_wp_admin'] = !empty($input['block_wp_admin']) ? 1 : 0;
        $out['disable_admin_bar'] = !empty($input['disable_admin_bar']) ? 1 : 0;

        $roles = (array)($input['blocked_roles'] ?? []);
        $roles = array_values(array_filter(array_map('sanitize_text_field', $roles)));
        $out['blocked_roles'] = $roles;

        // Page IDs
        if (isset($input['page_ids']) && is_array($input['page_ids'])) {
            foreach ($out['page_ids'] as $k => $_) {
                $out['page_ids'][$k] = !empty($input['page_ids'][$k]) ? absint($input['page_ids'][$k]) : 0;
            }
        }

        // Dashboard post types
        $post_types = (array)($input['dashboard_post_types'] ?? []);
        $post_types = array_values(array_filter(array_map('sanitize_key', $post_types)));
        $out['dashboard_post_types'] = $post_types ?: $out['dashboard_post_types'];

        $out['dashboard_posts_per_page'] = max(1, min(100, absint($input['dashboard_posts_per_page'] ?? $out['dashboard_posts_per_page'])));

        return $out;
    }

    public static function render_settings_page() : void {
        if (!current_user_can('manage_options')) return;

        $opt = self::get_options();

        // Handle "Create pages" action.
        if (!empty($_POST['sh_review_members_create_pages']) && check_admin_referer('sh_review_members_create_pages')) {
            self::maybe_create_pages(true);
            $opt = self::get_options();
            echo '<div class="notice notice-success"><p>' . esc_html__('Sidor skapade/uppdaterade.', 'sh-review-members') . '</p></div>';
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Pixel Review — Member Pages', 'sh-review-members') . '</h1>';

        echo '<form method="post" action="options.php">';
        settings_fields('sh_review_members_pages_group');
        do_settings_sections('sh-review-member-pages');
        submit_button();
        echo '</form>';

        echo '<hr />';
        echo '<h2>' . esc_html__('Snabbåtgärd', 'sh-review-members') . '</h2>';
        echo '<p>' . esc_html__('Skapa (eller uppdatera) standard-sidorna med rätt shortcodes.', 'sh-review-members') . '</p>';
        echo '<form method="post">';
        wp_nonce_field('sh_review_members_create_pages');
        echo '<input type="hidden" name="sh_review_members_create_pages" value="1" />';
        submit_button(__('Skapa standard-sidor', 'sh-review-members'), 'secondary', 'submit', false);
        echo '</form>';

        echo '<p style="margin-top:16px;">' . esc_html__('Shortcodes du kan använda manuellt:', 'sh-review-members') . '</p>';
        echo '<code>[pr_login]</code> <code>[pr_register]</code> <code>[pr_profile]</code> <code>[pr_dashboard]</code> <code>[pr_logout]</code> <code>[pr_post_edit]</code>';

        echo '</div>';
    }

    public static function field_enabled() : void {
        $opt = self::get_options();

        printf(
            '<label><input type="checkbox" name="%1$s[enabled]" value="1" %2$s> %3$s</label>',
            esc_attr(self::OPT_KEY),
            checked(1, (int)$opt['enabled'], false),
            esc_html__('Aktivera member pages på frontend', 'sh-review-members')
        );
        echo '<p class="description">' . esc_html__('När avstängt kommer shortcodes fortsatt finnas, men admin-blockering och redirects görs inte.', 'sh-review-members') . '</p>';

        printf(
            '<label style="display:block;margin-top:10px;"><input type="checkbox" name="%1$s[create_pages_on_activate]" value="1" %2$s> %3$s</label>',
            esc_attr(self::OPT_KEY),
            checked(1, (int)$opt['create_pages_on_activate'], false),
            esc_html__('Skapa standard-sidor vid aktivering', 'sh-review-members')
        );

        printf(
            '<label style="display:block;margin-top:10px;"><input type="checkbox" name="%1$s[redirect_wp_login]" value="1" %2$s> %3$s</label>',
            esc_attr(self::OPT_KEY),
            checked(1, (int)($opt['redirect_wp_login'] ?? 1), false),
            esc_html__('Skicka wp-login.php till den snygga login-sidan', 'sh-review-members')
        );
        echo '<p class="description">' . esc_html__('Detta påverkar inte lösenordsåterställning (lost password/reset).', 'sh-review-members') . '</p>';
    }

    public static function field_pages() : void {
        $opt = self::get_options();
        $pages = get_pages(['post_status' => ['publish', 'draft', 'private']]);

        echo '<table class="widefat striped" style="max-width:900px;">';
        echo '<thead><tr><th>' . esc_html__('Funktion', 'sh-review-members') . '</th><th>' . esc_html__('Sida', 'sh-review-members') . '</th><th>' . esc_html__('Shortcode', 'sh-review-members') . '</th></tr></thead>';
        echo '<tbody>';

        $rows = [
            'login'     => ['label' => __('Logga in', 'sh-review-members'),     'sc' => '[pr_login]'],
            'register'  => ['label' => __('Registrering', 'sh-review-members'),  'sc' => '[pr_register]'],
            'dashboard' => ['label' => __('Mina sidor', 'sh-review-members'),    'sc' => '[pr_dashboard]'],
            'profile'   => ['label' => __('Profil', 'sh-review-members'),       'sc' => '[pr_profile]'],
            'logout'    => ['label' => __('Logga ut', 'sh-review-members'),     'sc' => '[pr_logout]'],
            'post_edit' => ['label' => __('Redigera inlägg', 'sh-review-members'),'sc' => '[pr_post_edit]'],
        ];

        foreach ($rows as $key => $row) {
            $current = absint($opt['page_ids'][$key] ?? 0);
            echo '<tr>';
            echo '<td><strong>' . esc_html($row['label']) . '</strong></td>';
            echo '<td>';
            printf('<select name="%s[page_ids][%s]">', esc_attr(self::OPT_KEY), esc_attr($key));
            echo '<option value="0">' . esc_html__('— Välj —', 'sh-review-members') . '</option>';
            foreach ($pages as $p) {
                printf(
                    '<option value="%d" %s>%s%s</option>',
                    (int)$p->ID,
                    selected($current, (int)$p->ID, false),
                    esc_html($p->post_title),
                    $p->post_status !== 'publish' ? ' (' . esc_html($p->post_status) . ')' : ''
                );
            }
            echo '</select>';
            echo '</td>';
            echo '<td><code>' . esc_html($row['sc']) . '</code></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        echo '<p class="description" style="max-width:900px;">' . esc_html__('Tips: Sätt “Mina sidor” (dashboard) som landningssida efter inloggning för bäst UX.', 'sh-review-members') . '</p>';

        echo '<p style="margin-top:10px;">';
        echo '<label>' . esc_html__('Redirect efter inloggning:', 'sh-review-members') . ' ';
        printf(
            '<select name="%s[redirect_after_login]">',
            esc_attr(self::OPT_KEY)
        );
        $opts = [
            'dashboard' => __('Mina sidor', 'sh-review-members'),
            'profile'   => __('Profil', 'sh-review-members'),
            'home'      => __('Startsida', 'sh-review-members'),
        ];
        foreach ($opts as $k => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($opt['redirect_after_login'], $k, false), esc_html($label));
        }
        echo '</select></label></p>';
    }

    public static function field_admin_block() : void {
        $opt = self::get_options();
        global $wp_roles;
        $roles = $wp_roles ? $wp_roles->roles : [];

        printf(
            '<label><input type="checkbox" name="%1$s[block_wp_admin]" value="1" %2$s> %3$s</label>',
            esc_attr(self::OPT_KEY),
            checked(1, (int)$opt['block_wp_admin'], false),
            esc_html__('Blockera wp-admin för valda roller', 'sh-review-members')
        );

        echo '<p class="description">' . esc_html__('Rekommendation: blockera Subscriber/Customer så de aldrig ser adminpanelen.', 'sh-review-members') . '</p>';

        echo '<div style="margin-top:10px;">';
        echo '<strong>' . esc_html__('Blockerade roller:', 'sh-review-members') . '</strong><br />';
        foreach ($roles as $role_key => $role) {
            $checked = in_array($role_key, (array)$opt['blocked_roles'], true);
            printf(
                '<label style="display:inline-block;min-width:220px;"><input type="checkbox" name="%1$s[blocked_roles][]" value="%2$s" %3$s> %4$s</label>',
                esc_attr(self::OPT_KEY),
                esc_attr($role_key),
                checked(true, $checked, false),
                esc_html($role['name'])
            );
        }
        echo '</div>';

        printf(
            '<label style="display:block;margin-top:10px;"><input type="checkbox" name="%1$s[disable_admin_bar]" value="1" %2$s> %3$s</label>',
            esc_attr(self::OPT_KEY),
            checked(1, (int)$opt['disable_admin_bar'], false),
            esc_html__('Dölj admin-bar på frontend för blockerade roller', 'sh-review-members')
        );
    }

    public static function field_dashboard() : void {
        $opt = self::get_options();
        $public_post_types = get_post_types(['public' => true], 'objects');

        echo '<p><strong>' . esc_html__('Post types som visas i “Mina sidor”:', 'sh-review-members') . '</strong></p>';
        foreach ($public_post_types as $pt) {
            $checked = in_array($pt->name, (array)$opt['dashboard_post_types'], true);
            printf(
                '<label style="display:inline-block;min-width:240px;"><input type="checkbox" name="%1$s[dashboard_post_types][]" value="%2$s" %3$s> %4$s</label>',
                esc_attr(self::OPT_KEY),
                esc_attr($pt->name),
                checked(true, $checked, false),
                esc_html($pt->labels->singular_name)
            );
        }

        echo '<p style="margin-top:10px;">';
        echo '<label>' . esc_html__('Antal inlägg per sida:', 'sh-review-members') . ' ';
        printf(
            '<input type="number" min="1" max="100" name="%1$s[dashboard_posts_per_page]" value="%2$d" style="width:90px;">',
            esc_attr(self::OPT_KEY),
            (int)$opt['dashboard_posts_per_page']
        );
        echo '</label></p>';
    }

    /* =========================================================
     * Front-end: Admin-blockering
     * ======================================================= */


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

        $action = isset($_REQUEST['action']) ? sanitize_key((string)$_REQUEST['action']) : '';
        // Don't interfere with reset flows or post password protected.
        $allow = ['lostpassword', 'retrievepassword', 'resetpass', 'rp', 'postpass', 'confirmaction'];
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

    private static function user_is_blocked_role() : bool {
        if (!is_user_logged_in()) return false;
        $opt = self::get_options();
        $user = wp_get_current_user();
        $blocked = (array)($opt['blocked_roles'] ?? []);
        foreach ((array)$user->roles as $r) {
            if (in_array($r, $blocked, true)) return true;
        }
        return false;
    }

    public static function maybe_block_wp_admin() : void {
        $opt = self::get_options();
        if (empty($opt['enabled']) || empty($opt['block_wp_admin'])) return;

        if (defined('DOING_AJAX') && DOING_AJAX) return;
        if (defined('DOING_CRON') && DOING_CRON) return;

        if (!self::user_is_blocked_role()) return;

        // Allow admin-post.php for safe actions and REST for editor integrations.
        $script = basename($_SERVER['PHP_SELF'] ?? '');
        if (in_array($script, ['admin-post.php', 'admin-ajax.php', 'async-upload.php'], true)) {
            return;
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

    /* =========================================================
     * Core helpers
     * ======================================================= */

    private static function page_url(string $key) : string {
        $opt = self::get_options();
        $id = absint($opt['page_ids'][$key] ?? 0);
        if (!$id) return '';
        $url = get_permalink($id);
        return is_string($url) ? $url : '';
    }

    private static function current_url() : string {
        $scheme = is_ssl() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        if (!$host) return home_url('/');
        return esc_url_raw($scheme . '://' . $host . $uri);
    }

    private static function redirect_after_login() : string {
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

    private static function is_login_related_page() : bool {
        $opt = self::get_options();
        $ids = array_map('absint', (array)($opt['page_ids'] ?? []));
        $current = get_queried_object_id();
        return $current && in_array($current, $ids, true);
    }

    /* =========================================================
     * Actions (POST handling)
     * ======================================================= */

    public static function handle_actions() : void {
        $opt = self::get_options();
        if (empty($opt['enabled'])) return;

        // Friendly redirects when already logged in.
        if (is_user_logged_in() && self::is_login_related_page()) {
            $obj_id = get_queried_object_id();
            $login_id = absint($opt['page_ids']['login'] ?? 0);
            $register_id = absint($opt['page_ids']['register'] ?? 0);
            if ($obj_id && in_array($obj_id, [$login_id, $register_id], true)) {
                wp_safe_redirect(self::redirect_after_login());
                exit;
            }
        }

        // Logout action
        if (!empty($_GET['pr_action']) && $_GET['pr_action'] === 'logout') {
            // Nonce check if present (recommended). If missing, allow only for logged-in users.
            if (is_user_logged_in()) {
                if (!empty($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), 'pr_logout')) {
                    wp_logout();
                } else {
                    // If nonce missing/invalid, require explicit POST for safety.
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['_wpnonce']) && wp_verify_nonce(sanitize_text_field($_POST['_wpnonce']), 'pr_logout')) {
                        wp_logout();
                    }
                }
            }
            $login = self::page_url('login') ?: home_url('/');
            wp_safe_redirect($login);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        // Login submit
        if (!empty($_POST['pr_login_submit'])) {
            self::handle_login_submit();
            return;
        }

        // Register submit
        if (!empty($_POST['pr_register_submit'])) {
            self::handle_register_submit();
            return;
        }

        // Profile submit
        if (!empty($_POST['pr_profile_submit'])) {
            self::handle_profile_submit();
            return;
        }

        // Frontend post edit submit
        if (!empty($_POST['pr_post_edit_submit'])) {
            self::handle_post_edit_submit();
            return;
        }

        // Logout via POST
        if (!empty($_POST['pr_logout_submit'])) {
            if (!empty($_POST['_wpnonce']) && wp_verify_nonce(sanitize_text_field($_POST['_wpnonce']), 'pr_logout')) {
                wp_logout();
            }
            $login = self::page_url('login') ?: home_url('/');
            wp_safe_redirect($login);
            exit;
        }
    }

    private static function handle_login_submit() : void {
        if (empty($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field($_POST['_wpnonce']), 'pr_login')) {
            self::set_flash('error', __('Ogiltig säkerhetstoken. Försök igen.', 'sh-review-members'));
            return;
        }

        $login = sanitize_text_field($_POST['pr_user_login'] ?? '');
        $pass  = (string)($_POST['pr_user_pass'] ?? '');
        $remember = !empty($_POST['pr_remember']);

        $creds = [
            'user_login'    => $login,
            'user_password' => $pass,
            'remember'      => $remember,
        ];

        $user = wp_signon($creds, is_ssl());
        if (is_wp_error($user)) {
            self::set_flash('error', $user->get_error_message());
            return;
        }

        wp_safe_redirect(self::redirect_after_login());
        exit;
    }

    private static function handle_register_submit() : void {
        if (empty($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field($_POST['_wpnonce']), 'pr_register')) {
            self::set_flash('error', __('Ogiltig säkerhetstoken. Försök igen.', 'sh-review-members'));
            return;
        }

        if (!get_option('users_can_register')) {
            self::set_flash('error', __('Registrering är avstängd på denna webbplats.', 'sh-review-members'));
            return;
        }

        $username = sanitize_user($_POST['pr_user_login'] ?? '', true);
        $email    = sanitize_email($_POST['pr_user_email'] ?? '');
        $pass1    = (string)($_POST['pr_user_pass'] ?? '');
        $pass2    = (string)($_POST['pr_user_pass2'] ?? '');

        if (empty($username) || empty($email) || empty($pass1)) {
            self::set_flash('error', __('Fyll i användarnamn, e-post och lösenord.', 'sh-review-members'));
            return;
        }
        if (!is_email($email)) {
            self::set_flash('error', __('Ogiltig e-postadress.', 'sh-review-members'));
            return;
        }
        if ($pass1 !== $pass2) {
            self::set_flash('error', __('Lösenorden matchar inte.', 'sh-review-members'));
            return;
        }
        if (username_exists($username)) {
            self::set_flash('error', __('Användarnamnet är upptaget.', 'sh-review-members'));
            return;
        }
        if (email_exists($email)) {
            self::set_flash('error', __('E-postadressen används redan.', 'sh-review-members'));
            return;
        }

        $user_id = wp_create_user($username, $pass1, $email);
        if (is_wp_error($user_id)) {
            self::set_flash('error', $user_id->get_error_message());
            return;
        }

        // Auto-login after successful registration.
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        wp_safe_redirect(self::redirect_after_login());
        exit;
    }

    private static function handle_profile_submit() : void {
        if (!is_user_logged_in()) {
            self::set_flash('error', __('Du måste vara inloggad för att uppdatera din profil.', 'sh-review-members'));
            return;
        }

        if (empty($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field($_POST['_wpnonce']), 'pr_profile')) {
            self::set_flash('error', __('Ogiltig säkerhetstoken. Försök igen.', 'sh-review-members'));
            return;
        }

        $user = wp_get_current_user();

        $display_name = sanitize_text_field($_POST['pr_display_name'] ?? '');
        $first_name   = sanitize_text_field($_POST['pr_first_name'] ?? '');
        $last_name    = sanitize_text_field($_POST['pr_last_name'] ?? '');
        $email        = sanitize_email($_POST['pr_user_email'] ?? '');
        $bio          = sanitize_textarea_field($_POST['pr_description'] ?? '');

        $pass1 = (string)($_POST['pr_new_pass'] ?? '');
        $pass2 = (string)($_POST['pr_new_pass2'] ?? '');

        if ($email && !is_email($email)) {
            self::set_flash('error', __('Ogiltig e-postadress.', 'sh-review-members'));
            return;
        }

        if (($pass1 || $pass2) && $pass1 !== $pass2) {
            self::set_flash('error', __('De nya lösenorden matchar inte.', 'sh-review-members'));
            return;
        }

        $userdata = [
            'ID'           => $user->ID,
            'display_name' => $display_name ?: $user->display_name,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'user_email'   => $email ?: $user->user_email,
            'description'  => $bio,
        ];

        if ($pass1) {
            $userdata['user_pass'] = $pass1;
        }

        $res = wp_update_user($userdata);
        if (is_wp_error($res)) {
            self::set_flash('error', $res->get_error_message());
            return;
        }

        // If password changed, refresh auth cookie.
        if ($pass1) {
            wp_set_auth_cookie($user->ID, true);
        }

        self::set_flash('success', __('Profilen har uppdaterats.', 'sh-review-members'));

        // Redirect to avoid resubmission.
        wp_safe_redirect(self::page_url('profile') ?: self::current_url());
        exit;
    }

    private static function handle_post_edit_submit() : void {
        if (!is_user_logged_in()) {
            self::set_flash('error', __('Du måste vara inloggad.', 'sh-review-members'));
            return;
        }

        if (empty($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field($_POST['_wpnonce']), 'pr_post_edit')) {
            self::set_flash('error', __('Ogiltig säkerhetstoken. Försök igen.', 'sh-review-members'));
            return;
        }

        $post_id = absint($_POST['pr_post_id'] ?? 0);
        if (!$post_id) {
            self::set_flash('error', __('Ogiltigt inlägg.', 'sh-review-members'));
            return;
        }

        $post = get_post($post_id);
        if (!$post) {
            self::set_flash('error', __('Inlägget hittades inte.', 'sh-review-members'));
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            self::set_flash('error', __('Du saknar behörighet att redigera detta inlägg.', 'sh-review-members'));
            return;
        }

        $title = sanitize_text_field($_POST['pr_post_title'] ?? '');
        $content = wp_kses_post($_POST['pr_post_content'] ?? '');

        if (!$title) {
            self::set_flash('error', __('Titel får inte vara tom.', 'sh-review-members'));
            return;
        }

        $update = [
            'ID'           => $post_id,
            'post_title'   => $title,
            'post_content' => $content,
        ];

        $res = wp_update_post($update, true);
        if (is_wp_error($res)) {
            self::set_flash('error', $res->get_error_message());
            return;
        }

        self::set_flash('success', __('Inlägget har uppdaterats.', 'sh-review-members'));

        $dashboard = self::page_url('dashboard') ?: home_url('/');
        wp_safe_redirect(add_query_arg(['updated' => 1], $dashboard));
        exit;
    }

    /* =========================================================
     * Flash messages (sessionless via cookie)
     * ======================================================= */

    private static function set_flash(string $type, string $message) : void {
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

    private static function read_flash() : array {
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

    private static function render_flash() : string {
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

    /* =========================================================
     * Shortcodes
     * ======================================================= */

    public static function sc_login($atts = []) : string {
        $opt = self::get_options();
        if (empty($opt['enabled'])) return '';

        if (is_user_logged_in()) {
            $url = self::redirect_after_login();
            return '<div class="pr-card">' . self::render_flash() . '<p>' . esc_html__('Du är redan inloggad.', 'sh-review-members') . '</p><p><a class="pr-button" href="' . esc_url($url) . '">' . esc_html__('Gå till Mina sidor', 'sh-review-members') . '</a></p></div>';
        }

        $register_url = self::page_url('register');

        ob_start();
        echo '<div class="pr-card pr-card--auth">';
        echo self::render_flash();
        echo '<h2>' . esc_html__('Logga in', 'sh-review-members') . '</h2>';
        echo '<form method="post" class="pr-form">';
        wp_nonce_field('pr_login');
        echo '<p><label>' . esc_html__('Användarnamn eller e-post', 'sh-review-members') . '<br />';
        echo '<input type="text" name="pr_user_login" autocomplete="username" required></label></p>';

        echo '<p><label>' . esc_html__('Lösenord', 'sh-review-members') . '<br />';
        echo '<input type="password" name="pr_user_pass" autocomplete="current-password" required></label></p>';

        echo '<p><label><input type="checkbox" name="pr_remember" value="1"> ' . esc_html__('Kom ihåg mig', 'sh-review-members') . '</label></p>';

        echo '<p><button type="submit" name="pr_login_submit" class="pr-button">' . esc_html__('Logga in', 'sh-review-members') . '</button></p>';
        echo '</form>';

        if ($register_url && get_option('users_can_register')) {
            echo '<p class="pr-muted">' . esc_html__('Har du inget konto?', 'sh-review-members') . ' <a href="' . esc_url($register_url) . '">' . esc_html__('Registrera dig här', 'sh-review-members') . '</a></p>';
        }

        echo '</div>';
        return (string)ob_get_clean();
    }

    public static function sc_register($atts = []) : string {
        $opt = self::get_options();
        if (empty($opt['enabled'])) return '';

        if (is_user_logged_in()) {
            $url = self::redirect_after_login();
            return '<div class="pr-card">' . self::render_flash() . '<p>' . esc_html__('Du är redan inloggad.', 'sh-review-members') . '</p><p><a class="pr-button" href="' . esc_url($url) . '">' . esc_html__('Gå till Mina sidor', 'sh-review-members') . '</a></p></div>';
        }

        if (!get_option('users_can_register')) {
            return '<div class="pr-card">' . self::render_flash() . '<p>' . esc_html__('Registrering är avstängd på denna webbplats.', 'sh-review-members') . '</p></div>';
        }

        $login_url = self::page_url('login');

        ob_start();
        echo '<div class="pr-card pr-card--auth">';
        echo self::render_flash();
        echo '<h2>' . esc_html__('Skapa konto', 'sh-review-members') . '</h2>';
        echo '<form method="post" class="pr-form">';
        wp_nonce_field('pr_register');

        echo '<p><label>' . esc_html__('Användarnamn', 'sh-review-members') . '<br />';
        echo '<input type="text" name="pr_user_login" autocomplete="username" required></label></p>';

        echo '<p><label>' . esc_html__('E-post', 'sh-review-members') . '<br />';
        echo '<input type="email" name="pr_user_email" autocomplete="email" required></label></p>';

        echo '<p><label>' . esc_html__('Lösenord', 'sh-review-members') . '<br />';
        echo '<input type="password" name="pr_user_pass" autocomplete="new-password" required></label></p>';

        echo '<p><label>' . esc_html__('Upprepa lösenord', 'sh-review-members') . '<br />';
        echo '<input type="password" name="pr_user_pass2" autocomplete="new-password" required></label></p>';

        echo '<p><button type="submit" name="pr_register_submit" class="pr-button">' . esc_html__('Registrera', 'sh-review-members') . '</button></p>';
        echo '</form>';

        if ($login_url) {
            echo '<p class="pr-muted">' . esc_html__('Har du redan ett konto?', 'sh-review-members') . ' <a href="' . esc_url($login_url) . '">' . esc_html__('Logga in', 'sh-review-members') . '</a></p>';
        }

        echo '</div>';
        return (string)ob_get_clean();
    }

    public static function sc_logout($atts = []) : string {
        $opt = self::get_options();
        if (empty($opt['enabled'])) return '';

        if (!is_user_logged_in()) {
            $login = self::page_url('login') ?: home_url('/');
            return '<div class="pr-card">' . self::render_flash() . '<p>' . esc_html__('Du är inte inloggad.', 'sh-review-members') . '</p><p><a class="pr-button" href="' . esc_url($login) . '">' . esc_html__('Logga in', 'sh-review-members') . '</a></p></div>';
        }

        $action_url = add_query_arg([
            'pr_action' => 'logout',
            '_wpnonce'  => wp_create_nonce('pr_logout'),
        ], self::current_url());

        ob_start();
        echo '<div class="pr-card">';
        echo self::render_flash();
        echo '<h2>' . esc_html__('Logga ut', 'sh-review-members') . '</h2>';
        echo '<p>' . esc_html__('Klicka på knappen nedan för att logga ut.', 'sh-review-members') . '</p>';
        echo '<p><a class="pr-button" href="' . esc_url($action_url) . '">' . esc_html__('Logga ut', 'sh-review-members') . '</a></p>';
        echo '</div>';
        return (string)ob_get_clean();
    }

    public static function sc_profile($atts = []) : string {
        $opt = self::get_options();
        if (empty($opt['enabled'])) return '';

        if (!is_user_logged_in()) {
            $login = self::page_url('login') ?: wp_login_url(self::current_url());
            return '<div class="pr-card">' . self::render_flash() . '<p>' . esc_html__('Du måste logga in för att se din profil.', 'sh-review-members') . '</p><p><a class="pr-button" href="' . esc_url($login) . '">' . esc_html__('Logga in', 'sh-review-members') . '</a></p></div>';
        }

        $user = wp_get_current_user();

        ob_start();
        echo '<div class="pr-card pr-card--profile">';
        echo self::render_flash();
        echo '<h2>' . esc_html__('Min profil', 'sh-review-members') . '</h2>';

        echo '<form method="post" class="pr-form">';
        wp_nonce_field('pr_profile');

        echo '<div class="pr-grid">';

        echo '<p><label>' . esc_html__('Visningsnamn', 'sh-review-members') . '<br />';
        echo '<input type="text" name="pr_display_name" value="' . esc_attr($user->display_name) . '"></label></p>';

        echo '<p><label>' . esc_html__('E-post', 'sh-review-members') . '<br />';
        echo '<input type="email" name="pr_user_email" value="' . esc_attr($user->user_email) . '"></label></p>';

        echo '<p><label>' . esc_html__('Förnamn', 'sh-review-members') . '<br />';
        echo '<input type="text" name="pr_first_name" value="' . esc_attr(get_user_meta($user->ID, 'first_name', true)) . '"></label></p>';

        echo '<p><label>' . esc_html__('Efternamn', 'sh-review-members') . '<br />';
        echo '<input type="text" name="pr_last_name" value="' . esc_attr(get_user_meta($user->ID, 'last_name', true)) . '"></label></p>';

        echo '</div>';

        echo '<p><label>' . esc_html__('Bio', 'sh-review-members') . '<br />';
        echo '<textarea name="pr_description" rows="5">' . esc_textarea(get_user_meta($user->ID, 'description', true)) . '</textarea></label></p>';

        echo '<hr class="pr-hr" />';
        echo '<h3>' . esc_html__('Byt lösenord', 'sh-review-members') . '</h3>';
        echo '<div class="pr-grid">';
        echo '<p><label>' . esc_html__('Nytt lösenord', 'sh-review-members') . '<br />';
        echo '<input type="password" name="pr_new_pass" autocomplete="new-password"></label></p>';

        echo '<p><label>' . esc_html__('Upprepa nytt lösenord', 'sh-review-members') . '<br />';
        echo '<input type="password" name="pr_new_pass2" autocomplete="new-password"></label></p>';
        echo '</div>';

        echo '<p><button type="submit" name="pr_profile_submit" class="pr-button">' . esc_html__('Spara profil', 'sh-review-members') . '</button></p>';
        echo '</form>';

        echo '</div>';
        return (string)ob_get_clean();
    }

    public static function sc_dashboard($atts = []) : string {
        $opt = self::get_options();
        if (empty($opt['enabled'])) return '';

        if (!is_user_logged_in()) {
            $login = self::page_url('login') ?: wp_login_url(self::current_url());
            return '<div class="pr-card">' . self::render_flash() . '<p>' . esc_html__('Du måste logga in för att se Mina sidor.', 'sh-review-members') . '</p><p><a class="pr-button" href="' . esc_url($login) . '">' . esc_html__('Logga in', 'sh-review-members') . '</a></p></div>';
        }

        $user = wp_get_current_user();
        $post_types = (array)($opt['dashboard_post_types'] ?? ['post']);
        $per_page = (int)($opt['dashboard_posts_per_page'] ?? 20);
        $paged = max(1, absint(get_query_var('paged') ?: (get_query_var('page') ?: 1)));

        $q = new WP_Query([
            'post_type'      => $post_types,
            'author'         => $user->ID,
            'post_status'    => ['publish', 'pending', 'draft', 'future', 'private'],
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'no_found_rows'  => false,
        ]);

        $profile_url = self::page_url('profile');
        $logout_url = self::page_url('logout');
        $post_edit_url = self::page_url('post_edit');

        ob_start();
        echo '<div class="pr-card pr-card--dashboard">';
        echo self::render_flash();

        echo '<div class="pr-dashboard__header">';
        echo '<div>';
        echo '<h2>' . esc_html__('Mina sidor', 'sh-review-members') . '</h2>';
        echo '<p class="pr-muted">' . sprintf(esc_html__('Inloggad som %s', 'sh-review-members'), '<strong>' . esc_html($user->display_name) . '</strong>') . '</p>';
        echo '</div>';
        echo '<div class="pr-dashboard__actions">';
        if ($profile_url) {
            echo '<a class="pr-button pr-button--secondary" href="' . esc_url($profile_url) . '">' . esc_html__('Redigera profil', 'sh-review-members') . '</a>';
        }
        if ($logout_url) {
            echo '<a class="pr-button pr-button--secondary" href="' . esc_url($logout_url) . '">' . esc_html__('Logga ut', 'sh-review-members') . '</a>';
        }
        echo '</div>';
        echo '</div>';

        echo '<h3>' . esc_html__('Mina artiklar', 'sh-review-members') . '</h3>';

        if ($q->have_posts()) {
            echo '<div class="pr-table-wrap">';
            echo '<table class="pr-table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Titel', 'sh-review-members') . '</th>';
            echo '<th>' . esc_html__('Status', 'sh-review-members') . '</th>';
            echo '<th>' . esc_html__('Datum', 'sh-review-members') . '</th>';
            echo '<th>' . esc_html__('Åtgärd', 'sh-review-members') . '</th>';
            echo '</tr></thead><tbody>';

            while ($q->have_posts()) {
                $q->the_post();
                $pid = get_the_ID();
                $status = get_post_status($pid);
                $date = get_the_date('Y-m-d');
                echo '<tr>';
                echo '<td><a href="' . esc_url(get_permalink($pid)) . '">' . esc_html(get_the_title()) . '</a></td>';
                echo '<td>' . esc_html($status) . '</td>';
                echo '<td>' . esc_html($date) . '</td>';
                echo '<td>';
                if ($post_edit_url && current_user_can('edit_post', $pid)) {
                    $edit_link = add_query_arg(['post_id' => $pid], $post_edit_url);
                    echo '<a class="pr-link" href="' . esc_url($edit_link) . '">' . esc_html__('Redigera', 'sh-review-members') . '</a>';
                } else {
                    echo '<span class="pr-muted">—</span>';
                }
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</div>';

            // Pagination
            $big = 999999999;
            $base = str_replace($big, '%#%', esc_url(get_pagenum_link($big)));
            $links = paginate_links([
                'base'      => $base,
                'format'    => '?paged=%#%',
                'current'   => $paged,
                'total'     => max(1, (int)$q->max_num_pages),
                'type'      => 'list',
                'prev_text' => '«',
                'next_text' => '»',
            ]);
            if ($links) {
                echo '<nav class="pr-pagination" aria-label="' . esc_attr__('Sidnavigering', 'sh-review-members') . '">' . $links . '</nav>';
            }

            wp_reset_postdata();
        } else {
            echo '<p class="pr-muted">' . esc_html__('Inga inlägg hittades.', 'sh-review-members') . '</p>';
        }

        echo '</div>';
        return (string)ob_get_clean();
    }

    public static function sc_post_edit($atts = []) : string {
        $opt = self::get_options();
        if (empty($opt['enabled'])) return '';

        if (!is_user_logged_in()) {
            $login = self::page_url('login') ?: wp_login_url(self::current_url());
            return '<div class="pr-card">' . self::render_flash() . '<p>' . esc_html__('Du måste logga in.', 'sh-review-members') . '</p><p><a class="pr-button" href="' . esc_url($login) . '">' . esc_html__('Logga in', 'sh-review-members') . '</a></p></div>';
        }

        $post_id = absint($_GET['post_id'] ?? 0);
        if (!$post_id) {
            $dash = self::page_url('dashboard') ?: home_url('/');
            return '<div class="pr-card">' . self::render_flash() . '<p>' . esc_html__('Välj ett inlägg att redigera från “Mina sidor”.', 'sh-review-members') . '</p><p><a class="pr-button" href="' . esc_url($dash) . '">' . esc_html__('Tillbaka', 'sh-review-members') . '</a></p></div>';
        }

        if (!current_user_can('edit_post', $post_id)) {
            return '<div class="pr-card">' . self::render_flash() . '<p>' . esc_html__('Du saknar behörighet att redigera detta inlägg.', 'sh-review-members') . '</p></div>';
        }

        $post = get_post($post_id);
        if (!$post) {
            return '<div class="pr-card">' . self::render_flash() . '<p>' . esc_html__('Inlägget hittades inte.', 'sh-review-members') . '</p></div>';
        }

        $dash = self::page_url('dashboard') ?: home_url('/');

        ob_start();
        echo '<div class="pr-card pr-card--post-edit">';
        echo self::render_flash();
        echo '<h2>' . esc_html__('Redigera inlägg', 'sh-review-members') . '</h2>';
        echo '<p class="pr-muted"><a class="pr-link" href="' . esc_url($dash) . '">← ' . esc_html__('Tillbaka till Mina sidor', 'sh-review-members') . '</a></p>';

        echo '<form method="post" class="pr-form">';
        wp_nonce_field('pr_post_edit');
        echo '<input type="hidden" name="pr_post_id" value="' . (int)$post_id . '">';

        echo '<p><label>' . esc_html__('Titel', 'sh-review-members') . '<br />';
        echo '<input type="text" name="pr_post_title" value="' . esc_attr($post->post_title) . '" required></label></p>';

        echo '<p><label>' . esc_html__('Innehåll', 'sh-review-members') . '<br />';
        echo '<textarea name="pr_post_content" rows="12">' . esc_textarea($post->post_content) . '</textarea></label></p>';

        echo '<p><button type="submit" name="pr_post_edit_submit" class="pr-button">' . esc_html__('Spara', 'sh-review-members') . '</button></p>';
        echo '</form>';

        echo '</div>';
        return (string)ob_get_clean();
    }

    /* =========================================================
     * Page creation
     * ======================================================= */

    public static function maybe_create_pages(bool $force = false) : void {
        $opt = self::get_options();
        $page_ids = (array)($opt['page_ids'] ?? []);

        $spec = [
            'login' => [
                'title' => __('Logga in', 'sh-review-members'),
                'content' => '[pr_login]',
                'slug' => 'logga-in',
            ],
            'register' => [
                'title' => __('Registrera', 'sh-review-members'),
                'content' => '[pr_register]',
                'slug' => 'registrera',
            ],
            'dashboard' => [
                'title' => __('Mina sidor', 'sh-review-members'),
                'content' => '[pr_dashboard]',
                'slug' => 'mina-sidor',
            ],
            'profile' => [
                'title' => __('Min profil', 'sh-review-members'),
                'content' => '[pr_profile]',
                'slug' => 'min-profil',
            ],
            'logout' => [
                'title' => __('Logga ut', 'sh-review-members'),
                'content' => '[pr_logout]',
                'slug' => 'logga-ut',
            ],
            'post_edit' => [
                'title' => __('Redigera inlägg', 'sh-review-members'),
                'content' => '[pr_post_edit]',
                'slug' => 'redigera-inlagg',
            ],
        ];

        foreach ($spec as $key => $s) {
            $existing_id = absint($page_ids[$key] ?? 0);

            if ($existing_id && get_post($existing_id) && !$force) {
                continue;
            }

            // Try to find by slug first if not set.
            if (!$existing_id) {
                $found = get_page_by_path($s['slug']);
                if ($found instanceof WP_Post) {
                    $existing_id = (int)$found->ID;
                }
            }

            if ($existing_id && get_post($existing_id)) {
                // Update content to ensure shortcode is present.
                wp_update_post([
                    'ID' => $existing_id,
                    'post_content' => $s['content'],
                ]);
                $page_ids[$key] = $existing_id;
                continue;
            }

            $new_id = wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => wp_strip_all_tags($s['title']),
                'post_name' => $s['slug'],
                'post_content' => $s['content'],
            ], true);

            if (!is_wp_error($new_id) && $new_id) {
                $page_ids[$key] = (int)$new_id;
            }
        }

        $opt['page_ids'] = $page_ids;
        update_option(self::OPT_KEY, $opt);
    }
}
