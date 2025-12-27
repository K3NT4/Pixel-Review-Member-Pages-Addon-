<?php
// includes/traits/trait-prmp-admin-settings.php
if (!defined('ABSPATH')) { exit; }

trait PRMP_Admin_Settings {

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
            'captcha',
            __('Spam-skydd', 'sh-review-members'),
            [__CLASS__, 'field_captcha'],
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

        add_settings_field(
            'social_login',
            __('Social Inloggning', 'sh-review-members'),
            [__CLASS__, 'field_social_login'],
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

        // Pixel Review coupling
        $out['dashboard_only_pixel_reviews'] = !empty($input['dashboard_only_pixel_reviews']) ? 1 : 0;
        $out['dashboard_show_review_meta'] = !empty($input['dashboard_show_review_meta']) ? 1 : 0;

        // Admin editor links
        $out['allow_frontend_create'] = !empty($input['allow_frontend_create']) ? 1 : 0;

        // Social Login
        $out['google_client_id'] = sanitize_text_field($input['google_client_id'] ?? '');
        $out['google_client_secret'] = sanitize_text_field($input['google_client_secret'] ?? '');
        $out['wordpress_client_id'] = sanitize_text_field($input['wordpress_client_id'] ?? '');
        $out['wordpress_client_secret'] = sanitize_text_field($input['wordpress_client_secret'] ?? '');

        // Captcha
        $valid_providers = ['none', 'native', 'cloudflare', 'google'];
        $out['captcha_provider'] = in_array(($input['captcha_provider'] ?? ''), $valid_providers, true)
            ? $input['captcha_provider']
            : ($out['captcha_provider'] ?? 'native');

        $out['cf_site_key'] = sanitize_text_field($input['cf_site_key'] ?? '');
        $out['cf_secret_key'] = sanitize_text_field($input['cf_secret_key'] ?? '');
        $out['google_recaptcha_site_key'] = sanitize_text_field($input['google_recaptcha_site_key'] ?? '');
        $out['google_recaptcha_secret_key'] = sanitize_text_field($input['google_recaptcha_secret_key'] ?? '');

        return $out;
    }

    public static function render_settings_page() : void {
        if (!current_user_can('manage_options')) return;

        // Handle "Create pages" action.
        if (!empty($_POST['sh_review_members_create_pages']) && check_admin_referer('sh_review_members_create_pages')) {
            self::maybe_create_pages(true);
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
            'login'     => ['label' => __('Logga in', 'sh-review-members'),      'sc' => '[pr_login]'],
            'register'  => ['label' => __('Registrering', 'sh-review-members'),  'sc' => '[pr_register]'],
            'dashboard' => ['label' => __('Mina sidor', 'sh-review-members'),    'sc' => '[pr_dashboard]'],
            'profile'   => ['label' => __('Profil', 'sh-review-members'),       'sc' => '[pr_profile]'],
            'logout'    => ['label' => __('Logga ut', 'sh-review-members'),     'sc' => '[pr_logout]'],
            'post_edit' => ['label' => __('Redigera/Skapa inlägg', 'sh-review-members'), 'sc' => '[pr_post_edit]'],
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
        printf('<select name="%s[redirect_after_login]">', esc_attr(self::OPT_KEY));
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

        echo '<p class="description">' . esc_html__('Rekommendation: blockera Subscriber/Customer så de aldrig ser adminpanelen (förutom när de redigerar/skapaar innehåll).', 'sh-review-members') . '</p>';

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

    public static function field_captcha() : void {
        $opt = self::get_options();
        $provider = $opt['captcha_provider'] ?? 'native';

        echo '<p><label>' . esc_html__('Metod', 'sh-review-members') . ' ';
        echo '<select name="' . esc_attr(self::OPT_KEY) . '[captcha_provider]">';
        $opts = [
            'none'       => __('Ingen', 'sh-review-members'),
            'native'     => __('Native (Honeypot + Tid)', 'sh-review-members'),
            'cloudflare' => __('Cloudflare Turnstile', 'sh-review-members'),
            'google'     => __('Google reCAPTCHA v2', 'sh-review-members'),
        ];
        foreach ($opts as $k => $label) {
            printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($provider, $k, false), esc_html($label));
        }
        echo '</select></label></p>';

        echo '<div style="margin-left:20px; border-left:2px solid #ddd; padding-left:15px; margin-top:10px;">';

        echo '<p><strong>Cloudflare Turnstile</strong></p>';
        echo '<p><label>' . esc_html__('Site Key', 'sh-review-members') . '<br />';
        echo '<input type="text" name="' . esc_attr(self::OPT_KEY) . '[cf_site_key]" value="' . esc_attr($opt['cf_site_key'] ?? '') . '" class="regular-text"></label></p>';

        echo '<p><label>' . esc_html__('Secret Key', 'sh-review-members') . '<br />';
        echo '<input type="password" name="' . esc_attr(self::OPT_KEY) . '[cf_secret_key]" value="' . esc_attr($opt['cf_secret_key'] ?? '') . '" class="regular-text"></label></p>';

        echo '<hr>';

        echo '<p><strong>Google reCAPTCHA v2 (Checkbox)</strong></p>';
        echo '<p><label>' . esc_html__('Site Key', 'sh-review-members') . '<br />';
        echo '<input type="text" name="' . esc_attr(self::OPT_KEY) . '[google_recaptcha_site_key]" value="' . esc_attr($opt['google_recaptcha_site_key'] ?? '') . '" class="regular-text"></label></p>';

        echo '<p><label>' . esc_html__('Secret Key', 'sh-review-members') . '<br />';
        echo '<input type="password" name="' . esc_attr(self::OPT_KEY) . '[google_recaptcha_secret_key]" value="' . esc_attr($opt['google_recaptcha_secret_key'] ?? '') . '" class="regular-text"></label></p>';

        echo '</div>';
    }

    public static function field_social_login() : void {
        $opt = self::get_options();

        echo '<p><strong>Google</strong></p>';
        echo '<p><label>' . esc_html__('Client ID', 'sh-review-members') . '<br />';
        echo '<input type="text" name="' . esc_attr(self::OPT_KEY) . '[google_client_id]" value="' . esc_attr($opt['google_client_id'] ?? '') . '" class="regular-text"></label></p>';

        echo '<p><label>' . esc_html__('Client Secret', 'sh-review-members') . '<br />';
        echo '<input type="password" name="' . esc_attr(self::OPT_KEY) . '[google_client_secret]" value="' . esc_attr($opt['google_client_secret'] ?? '') . '" class="regular-text"></label></p>';

        echo '<p><strong>WordPress.com</strong></p>';
        echo '<p><label>' . esc_html__('Client ID', 'sh-review-members') . '<br />';
        echo '<input type="text" name="' . esc_attr(self::OPT_KEY) . '[wordpress_client_id]" value="' . esc_attr($opt['wordpress_client_id'] ?? '') . '" class="regular-text"></label></p>';

        echo '<p><label>' . esc_html__('Client Secret', 'sh-review-members') . '<br />';
        echo '<input type="password" name="' . esc_attr(self::OPT_KEY) . '[wordpress_client_secret]" value="' . esc_attr($opt['wordpress_client_secret'] ?? '') . '" class="regular-text"></label></p>';

        $redirect_uri = home_url('/');
        echo '<p class="description">' . sprintf(esc_html__('Redirect URI att ange hos leverantören: %s', 'sh-review-members'), '<code>' . esc_html($redirect_uri) . '</code>') . '</p>';
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

        echo '<hr />';
        echo '<p><strong>' . esc_html__('Pixel Review-koppling', 'sh-review-members') . '</strong></p>';

        printf(
            '<label style="display:block;margin-top:6px;"><input type="checkbox" name="%1$s[dashboard_only_pixel_reviews]" value="1" %2$s> %3$s</label>',
            esc_attr(self::OPT_KEY),
            checked(1, (int)$opt['dashboard_only_pixel_reviews'], false),
            esc_html__('Visa endast inlägg som har Pixel Review-meta (t.ex. betyg)', 'sh-review-members')
        );

        printf(
            '<label style="display:block;margin-top:6px;"><input type="checkbox" name="%1$s[dashboard_show_review_meta]" value="1" %2$s> %3$s</label>',
            esc_attr(self::OPT_KEY),
            checked(1, (int)$opt['dashboard_show_review_meta'], false),
            esc_html__('Visa Pixel Review-fält i listan (betyg, typ, recensionsdatum)', 'sh-review-members')
        );

        echo '<hr />';
        echo '<p><strong>' . esc_html__('Editor-länkar', 'sh-review-members') . '</strong></p>';

        printf(
            '<label style="display:block;margin-top:6px;"><input type="checkbox" name="%1$s[allow_frontend_create]" value="1" %2$s> %3$s</label>',
            esc_attr(self::OPT_KEY),
            checked(1, (int)$opt['allow_frontend_create'], false),
            esc_html__('Visa “Skapa nytt inlägg” och “Skapa recension” (öppnar WordPress editorn)', 'sh-review-members')
        );
    }
}
