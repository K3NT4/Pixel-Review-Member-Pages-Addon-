<?php
// includes/traits/trait-prmp-admin-settings.php
if (!defined('ABSPATH')) { exit; }

trait PRMP_Admin_Settings {

    /* =========================================================
     * Admin: settings page
     * ======================================================= */

    public static function register_admin_menu() : void {
        // Prefer adding as submenu under Pixel Review (SH Review) when available.
        if (self::prmp_parent_menu_exists('sh-review')) {
            add_submenu_page(
                'sh-review',
                __('Member Pages', 'sh-review-members'),
                __('Member Pages', 'sh-review-members'),
                'manage_options',
                'sh-review-member-pages',
                [__CLASS__, 'render_settings_page']
            );
            return;
        }

        // Fallback: standalone top-level menu (when SH Review is not active).
        add_menu_page(
            __('Member Pages', 'sh-review-members'),
            __('Member Pages', 'sh-review-members'),
            'manage_options',
            'sh-review-member-pages',
            [__CLASS__, 'render_settings_page'],
            'dashicons-groups'
        );
    }

    protected static function prmp_parent_menu_exists(string $slug) : bool {
        global $menu;
        if (!is_array($menu)) return false;
        foreach ($menu as $item) {
            if (!is_array($item) || !isset($item[2])) continue;
            if ((string) $item[2] === $slug) return true;
        }
        return false;
    }

    public static function register_settings() : void {
        register_setting('sh_review_members_pages_group', self::OPT_KEY, [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_options'],
            'default' => self::defaults(),
        ]);

        // -- Section: General --
        add_settings_section(
            'prmp_section_general',
            __('General Settings', 'sh-review-members'),
            function () {
                echo '<span class="prmp-section-marker" data-section="prmp_section_general"></span>';
                echo '<p>' . esc_html__('Configure core settings.', 'sh-review-members') . '</p>';
            },
            'sh-review-member-pages'
        );

        add_settings_field(
            'enabled',
            __('Enable Feature', 'sh-review-members'),
            [__CLASS__, 'field_enabled'],
            'sh-review-member-pages',
            'prmp_section_general'
        );

        add_settings_field(
            'pages',
            __('Pages & Shortcodes', 'sh-review-members'),
            [__CLASS__, 'field_pages'],
            'sh-review-member-pages',
            'prmp_section_general'
        );

        // -- Section: Access Control --
        add_settings_section(
            'prmp_section_access',
            __('Access Control', 'sh-review-members'),
            function () {
                echo '<span class="prmp-section-marker" data-section="prmp_section_access"></span>';
            },
            'sh-review-member-pages'
        );

        add_settings_field(
            'admin_block',
            __('Restrict wp-admin', 'sh-review-members'),
            [__CLASS__, 'field_admin_block'],
            'sh-review-member-pages',
            'prmp_section_access'
        );

        // -- Section: Security --
        add_settings_section(
            'prmp_section_security',
            __('Security', 'sh-review-members'),
            function () {
                 echo '<span class="prmp-section-marker" data-section="prmp_section_security"></span>';
            },
            'sh-review-member-pages'
        );

        add_settings_field(
            'security',
            __('Security Options', 'sh-review-members'),
            [__CLASS__, 'field_security'],
            'sh-review-member-pages',
            'prmp_section_security'
        );

        // -- Section: Privacy --
        add_settings_section(
            'prmp_section_privacy',
            __('Privacy & GDPR', 'sh-review-members'),
            function () {
                 echo '<span class="prmp-section-marker" data-section="prmp_section_privacy"></span>';
            },
            'sh-review-member-pages'
        );

        add_settings_field(
            'privacy',
            __('GDPR Settings', 'sh-review-members'),
            [__CLASS__, 'field_privacy'],
            'sh-review-member-pages',
            'prmp_section_privacy'
        );

        // -- Section: Social Login --
        add_settings_section(
            'prmp_section_social',
            __('Social Login', 'sh-review-members'),
            function () {
                 echo '<span class="prmp-section-marker" data-section="prmp_section_social"></span>';
            },
            'sh-review-member-pages'
        );

        add_settings_field(
            'social_login',
            __('Providers', 'sh-review-members'),
            [__CLASS__, 'field_social_login'],
            'sh-review-member-pages',
            'prmp_section_social'
        );

        // -- Section: Dashboard --
        add_settings_section(
            'prmp_section_dashboard',
            __('Dashboard', 'sh-review-members'),
            function () {
                 echo '<span class="prmp-section-marker" data-section="prmp_section_dashboard"></span>';
            },
            'sh-review-member-pages'
        );

        add_settings_field(
            'dashboard',
            __('Dashboard Content', 'sh-review-members'),
            [__CLASS__, 'field_dashboard'],
            'sh-review-member-pages',
            'prmp_section_dashboard'
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

        // Security
        $out['captcha_provider'] = sanitize_key($input['captcha_provider'] ?? '');
        $out['turnstile_site_key'] = sanitize_text_field($input['turnstile_site_key'] ?? '');
        $out['turnstile_secret_key'] = sanitize_text_field($input['turnstile_secret_key'] ?? '');
        $out['recaptcha_site_key'] = sanitize_text_field($input['recaptcha_site_key'] ?? '');
        $out['recaptcha_secret_key'] = sanitize_text_field($input['recaptcha_secret_key'] ?? '');
        $out['enable_rate_limit'] = !empty($input['enable_rate_limit']) ? 1 : 0;
        $out['max_login_attempts'] = absint($input['max_login_attempts'] ?? 5);

        // Privacy
        $out['enable_data_deletion'] = !empty($input['enable_data_deletion']) ? 1 : 0;
        $out['enable_data_export'] = !empty($input['enable_data_export']) ? 1 : 0;

        // Social Login
        $out['social_login_google'] = !empty($input['social_login_google']) ? 1 : 0;
        $out['google_client_id'] = sanitize_text_field($input['google_client_id'] ?? '');
        $out['google_client_secret'] = sanitize_text_field($input['google_client_secret'] ?? '');

        $out['social_login_wordpress'] = !empty($input['social_login_wordpress']) ? 1 : 0;
        $out['wordpress_client_id'] = sanitize_text_field($input['wordpress_client_id'] ?? '');
        $out['wordpress_client_secret'] = sanitize_text_field($input['wordpress_client_secret'] ?? '');

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

        return $out;
    }

    public static function render_settings_page() : void {
        if (!current_user_can('manage_options')) return;

        // Handle "Create pages" action.
        if (!empty($_POST['sh_review_members_create_pages']) && check_admin_referer('sh_review_members_create_pages')) {
            self::maybe_create_pages(false);
            echo '<div class="notice notice-success"><p>' . esc_html__('Pages created/updated.', 'sh-review-members') . '</p></div>';
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Pixel Review — Member Pages', 'sh-review-members') . '</h1>';

        // Tabs
        $tabs = [
            'general'  => __('General & Pages', 'sh-review-members'),
            'access'   => __('Access Control', 'sh-review-members'),
            'security' => __('Security', 'sh-review-members'),
            'privacy'  => __('Privacy & GDPR', 'sh-review-members'),
            'social'   => __('Social Login', 'sh-review-members'),
            'dashboard'=> __('Dashboard', 'sh-review-members'),
        ];
        $active_tab = 'general';

        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $key => $label) {
            $class = ($key === $active_tab) ? 'nav-tab nav-tab-active' : 'nav-tab';
            echo '<a href="#" class="' . esc_attr($class) . '" data-tab="' . esc_attr($key) . '">' . esc_html($label) . '</a>';
        }
        echo '</h2>';

        echo '<form method="post" action="options.php">';
        settings_fields('sh_review_members_pages_group');

        // We wrap the standard do_settings_sections in a way we can control via JS?
        // Actually, do_settings_sections outputs everything.
        // We will inject the tab containers via a callback hack OR just wrap specific fields manually.
        // Since we defined sections in register_settings, let's just output them and use JS to move them into tabs or hide/show.
        // Better: Let's create a JS structure that finds the relevant sections.
        // But WP settings API is rigid.
        // Alternative: Re-implement the rendering manually instead of do_settings_sections, but that breaks forward compatibility.

        // Plan B: Wrap do_settings_sections in a container and use JS to group fields based on the section IDs we added?
        // We added ONE section: 'sh_review_members_main'. All fields are in it. This is why it's messy.
        // We should split into multiple sections!

        // Wait, I cannot change register_settings easily without modifying how they are registered.
        // Let's modify register_settings() first to split into sections, then render them here.
        // But the previous code had only one section 'sh_review_members_main'.
        // I will change register_settings to have multiple sections corresponding to tabs.

        do_settings_sections('sh-review-member-pages');
        submit_button();
        echo '</form>';

        echo '<div id="prmp-tab-general-extra" class="prmp-tab-content" data-tab="general">';
        echo '<hr />';
        echo '<h2>' . esc_html__('Quick Action', 'sh-review-members') . '</h2>';
        echo '<p>' . esc_html__('Create missing standard pages with the correct shortcodes. (Will not overwrite existing pages)', 'sh-review-members') . '</p>';
        echo '<form method="post">';
        wp_nonce_field('sh_review_members_create_pages');
        echo '<input type="hidden" name="sh_review_members_create_pages" value="1" />';
        submit_button(__('Create missing pages', 'sh-review-members'), 'secondary', 'submit', false);
        echo '</form>';

        echo '<p style="margin-top:16px;">' . esc_html__('Shortcodes you can use manually:', 'sh-review-members') . '</p>';
        echo '<code>[pr_login]</code> <code>[pr_register]</code> <code>[pr_profile]</code> <code>[pr_dashboard]</code> <code>[pr_logout]</code> <code>[pr_post_edit]</code>';
        echo '</div>'; // End extra content for general tab

        // Add Javascript for tabs and conditional logic
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching logic
            var tabs = document.querySelectorAll('.nav-tab-wrapper .nav-tab');

            // Map tab keys to section markers (data-section attribute)
            var tabMap = {
                'general':  ['prmp_section_general', 'prmp-tab-general-extra'],
                'access':   ['prmp_section_access'],
                'security': ['prmp_section_security'],
                'privacy':  ['prmp_section_privacy'],
                'social':   ['prmp_section_social'],
                'dashboard':['prmp_section_dashboard']
            };

            function setDisplay(markerId, display) {
                // Special case for our custom div wrapper in General tab
                var customDiv = document.getElementById(markerId);
                if (customDiv && customDiv.classList.contains('prmp-tab-content')) {
                    customDiv.style.display = display;
                    return;
                }

                // For WP sections, we find the marker span/div
                var marker = document.querySelector('[data-section="' + markerId + '"]') || document.getElementById(markerId);
                if (!marker) return;

                // WP outputs: <h2>Title</h2> <p>Callback output (marker)</p> <table class="form-table">...</table>
                // The marker is inside the callback output, often wrapped in a <p> or just naked.
                // We need to find the Table (next sibling) and the H2 (previous sibling).

                // Find container (usually the form)
                var container = marker.closest('form') || document.querySelector('.wrap');

                // Find all elements in the container
                // This is brittle but standard WP Settings API structure usually holds.

                // 1. Find the table following this marker
                var table = marker.closest('table'); // If inside table? No, markers are in section description.
                if (!table) {
                    // Look forward until we find a table
                    var next = marker.parentElement; // Start from parent of span
                    while (next && next.tagName !== 'TABLE' && next.tagName !== 'H2' && next.tagName !== 'FORM') {
                        // Traverse next siblings of parent
                         var sib = next.nextElementSibling;
                         if (sib && sib.tagName === 'TABLE') {
                             table = sib;
                             break;
                         }
                         if (sib && sib.tagName === 'H2') break; // Next section
                         next = next.nextElementSibling;
                         if (!next && marker.parentElement.parentElement) {
                             // Go up one level
                             // This traversal is getting complex.
                         }
                         break; // Safety
                    }

                    // Simplified: WP renders sections sequentially.
                    // We can just iterate all children of the form.
                }
            }

            // Identify and group elements for each tab
            var form = document.querySelector('.wrap form');

            function resolveElements(markerId) {
                var els = [];
                // 1. Try ID direct (General tab extra content)
                var direct = document.getElementById(markerId);
                if (direct && direct.classList.contains('prmp-tab-content')) {
                    els.push(direct);
                    return els;
                }

                // 2. Try Marker
                var marker = document.querySelector('[data-section="' + markerId + '"]') || document.getElementById(markerId);
                if (marker) {
                    // Find the top-level container of the marker within the form
                    var container = marker;
                    while (container && container.parentElement !== form) {
                        container = container.parentElement;
                    }

                    if (container) {
                        // Find the preceding H2
                        var current = container.previousElementSibling;
                        while (current) {
                            if (current.tagName === 'H2') {
                                els.push(current);
                                break;
                            }
                            // If we hit another table or form element, stop looking back?
                            // Actually, WP structure is strictly sequential. H2 is immediately before the section content.
                            // But there might be other elements.
                            current = current.previousElementSibling;
                        }

                        // Collect everything from the H2 (found above) down to the next H2 or end of form.
                        // Wait, easier approach: find the H2 for this section.
                        // The H2 is the one we just found.
                        var startNode = els.length > 0 ? els[0] : container;

                        // Now collect all siblings starting from startNode until we hit another H2 or script/submit
                        var next = startNode;
                        while (next) {
                             if (next !== startNode && next.tagName === 'H2') break; // Next section
                             if (next.classList.contains('submit')) break; // Submit button
                             if (next.tagName === 'SCRIPT') break;

                             if (!els.includes(next)) els.push(next);
                             next = next.nextElementSibling;
                        }
                    }
                }
                return els;
            }

            function showTab(tabKey) {
                // Remove active class
                tabs.forEach(function(t) { t.classList.remove('nav-tab-active'); });
                // Add active class
                var link = document.querySelector('.nav-tab[data-tab="' + tabKey + '"]');
                if (link) link.classList.add('nav-tab-active');

                // Hide ALL mapped sections first
                for (var key in tabMap) {
                     tabMap[key].forEach(function(mid) {
                         resolveElements(mid).forEach(el => el.style.display = 'none');
                     });
                }

                // Show current
                if (tabMap[tabKey]) {
                    tabMap[tabKey].forEach(function(mid) {
                         resolveElements(mid).forEach(el => el.style.display = 'block');
                     });
                }
            }

            tabs.forEach(function(t) {
                t.addEventListener('click', function(e) {
                    e.preventDefault();
                    showTab(t.dataset.tab);
                });
            });

            // Initial load
            showTab('general');

            // --- Existing Conditional Logic ---
            function toggleVisibility(checkboxId, targetId) {
                var checkbox = document.getElementById(checkboxId);
                var target = document.getElementById(targetId);
                if (!checkbox || !target) return;

                function update() {
                    target.style.display = checkbox.checked ? 'block' : 'none';
                }
                checkbox.addEventListener('change', update);
                update();
            }

            function toggleSelectVisibility(selectId, targetMap) {
                var select = document.getElementById(selectId);
                if (!select) return;

                function update() {
                    var val = select.value;
                    for (var key in targetMap) {
                         var el = document.getElementById(targetMap[key]);
                         if (el) el.style.display = 'none';
                    }
                    if (targetMap[val]) {
                        var active = document.getElementById(targetMap[val]);
                        if (active) active.style.display = 'block';
                    }
                }
                select.addEventListener('change', update);
                update();
            }

            toggleVisibility('prmp_rate_limit_check', 'prmp_rate_limit_opts');
            toggleVisibility('prmp_sl_google_check', 'prmp_sl_google_opts');
            toggleVisibility('prmp_sl_wp_check', 'prmp_sl_wp_opts');

            toggleSelectVisibility('prmp_captcha_select', {
                'turnstile': 'prmp_captcha_turnstile_opts',
                'recaptcha_v3': 'prmp_captcha_recaptcha_opts'
            });
        });
        </script>
        <?php
        echo '</div>';
    }

    public static function render_addons_page() : void {
        if (!current_user_can('manage_options')) return;

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Pixel Review — Add-ons', 'sh-review-members') . '</h1>';
        echo '<p>' . esc_html__('Manage extensions for Pixel Review.', 'sh-review-members') . '</p>';

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Add-on', 'sh-review-members') . '</th>';
        echo '<th>' . esc_html__('Status', 'sh-review-members') . '</th>';
        echo '<th>' . esc_html__('Version', 'sh-review-members') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        // Member Pages (this plugin)
        echo '<tr>';
        echo '<td><strong>' . esc_html__('Member Pages', 'sh-review-members') . '</strong><br>';
        echo '<span class="description">' . esc_html__('Front-end login, registration, and user profiles.', 'sh-review-members') . '</span></td>';
        echo '<td><span class="dashicons dashicons-yes" style="color:#16a34a;"></span> ' . esc_html__('Installed', 'sh-review-members') . '</td>';
        echo '<td>' . esc_html(SH_REVIEW_MEMBERS_VERSION) . '</td>';
        echo '</tr>';

        // Placeholder for other potential addons
        /*
        echo '<tr>';
        echo '<td><strong>' . esc_html__('Advanced Reviews', 'sh-review-members') . '</strong></td>';
        echo '<td><span class="dashicons dashicons-minus" style="color:#ccc;"></span> ' . esc_html__('Not Installed', 'sh-review-members') . '</td>';
        echo '<td>—</td>';
        echo '</tr>';
        */

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    public static function field_enabled() : void {
        $opt = self::get_options();

        printf(
            '<label><input type="checkbox" name="%1$s[enabled]" value="1" %2$s> %3$s</label>',
            esc_attr(self::OPT_KEY),
            checked(1, (int)$opt['enabled'], false),
            esc_html__('Enable member pages on the frontend', 'sh-review-members')
        );
        echo '<p class="description">' . esc_html__('When disabled, shortcodes will still be available, but admin blocking and redirects will not be performed.', 'sh-review-members') . '</p>';

        printf(
            '<label style="display:block;margin-top:10px;"><input type="checkbox" name="%1$s[redirect_wp_login]" value="1" %2$s> %3$s</label>',
            esc_attr(self::OPT_KEY),
            checked(1, (int)($opt['redirect_wp_login'] ?? 1), false),
            esc_html__('Redirect wp-login.php to the nice login page', 'sh-review-members')
        );
        echo '<p class="description">' . esc_html__('This does not affect password reset (lost password/reset).', 'sh-review-members') . '</p>';
    }

    public static function field_pages() : void {
        $opt = self::get_options();
        $pages = get_pages(['post_status' => ['publish', 'draft', 'private']]);

        echo '<table class="widefat striped" style="max-width:900px;">';
        echo '<thead><tr><th>' . esc_html__('Function', 'sh-review-members') . '</th><th>' . esc_html__('Page', 'sh-review-members') . '</th><th>' . esc_html__('Shortcode', 'sh-review-members') . '</th></tr></thead>';
        echo '<tbody>';

        $rows = [
            'login'     => ['label' => __('Log in', 'sh-review-members'),      'sc' => '[pr_login]'],
            'register'  => ['label' => __('Register', 'sh-review-members'),  'sc' => '[pr_register]'],
            'dashboard' => ['label' => __('My Pages', 'sh-review-members'),    'sc' => '[pr_dashboard]'],
            'profile'   => ['label' => __('Profile', 'sh-review-members'),       'sc' => '[pr_profile]'],
            'logout'    => ['label' => __('Log out', 'sh-review-members'),     'sc' => '[pr_logout]'],
            'post_edit' => ['label' => __('Edit/Create Post', 'sh-review-members'), 'sc' => '[pr_post_edit]'],
        ];

        foreach ($rows as $key => $row) {
            $current = absint($opt['page_ids'][$key] ?? 0);
            echo '<tr>';
            echo '<td><strong>' . esc_html($row['label']) . '</strong></td>';
            echo '<td>';
            printf('<select name="%s[page_ids][%s]">', esc_attr(self::OPT_KEY), esc_attr($key));
            echo '<option value="0">' . esc_html__('— Select —', 'sh-review-members') . '</option>';
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

        echo '<p class="description" style="max-width:900px;">' . esc_html__('Tip: Set “My Pages” (dashboard) as the landing page after login for best UX.', 'sh-review-members') . '</p>';

        echo '<p style="margin-top:10px;">';
        echo '<label>' . esc_html__('Redirect after login:', 'sh-review-members') . ' ';
        printf('<select name="%s[redirect_after_login]">', esc_attr(self::OPT_KEY));
        $opts = [
            'dashboard' => __('My pages', 'sh-review-members'),
            'profile'   => __('Profile', 'sh-review-members'),
            'home'      => __('Home', 'sh-review-members'),
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
            esc_html__('Block wp-admin for selected roles', 'sh-review-members')
        );

        echo '<p class="description">' . esc_html__('Recommendation: block Subscriber/Customer so they never see the admin panel (except when editing/creating content).', 'sh-review-members') . '</p>';
        echo '<div style="margin-top:10px;">';
        echo '<strong>' . esc_html__('Blocked roles:', 'sh-review-members') . '</strong><br />';
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
            esc_html__('Hide admin bar on frontend for blocked roles', 'sh-review-members')
        );
    }

    public static function field_security() : void {
        $opt = self::get_options();

        echo '<p><strong>' . esc_html__('CAPTCHA', 'sh-review-members') . '</strong></p>';
        printf(
            '<select name="%s[captcha_provider]" id="prmp_captcha_select">',
            esc_attr(self::OPT_KEY)
        );
        echo '<option value="">' . esc_html__('None', 'sh-review-members') . '</option>';
        printf('<option value="turnstile" %s>%s</option>', selected($opt['captcha_provider'], 'turnstile', false), 'Cloudflare Turnstile');
        printf('<option value="recaptcha_v3" %s>%s</option>', selected($opt['captcha_provider'], 'recaptcha_v3', false), 'Google reCAPTCHA v3');
        echo '</select>';

        echo '<div id="prmp_captcha_turnstile_opts" style="display:none; margin-top:10px; padding:10px; background:#f0f0f1; border:1px solid #ccc;">';
        echo '<p><strong>Cloudflare Turnstile Settings</strong></p>';
        printf(
            '<p><label>Site Key<br /><input type="text" name="%1$s[turnstile_site_key]" value="%2$s" class="regular-text" /></label></p>',
            esc_attr(self::OPT_KEY),
            esc_attr($opt['turnstile_site_key'])
        );
        printf(
            '<p><label>Secret Key<br /><input type="password" name="%1$s[turnstile_secret_key]" value="%2$s" class="regular-text" /></label></p>',
            esc_attr(self::OPT_KEY),
            esc_attr($opt['turnstile_secret_key'])
        );
        echo '</div>';

        echo '<div id="prmp_captcha_recaptcha_opts" style="display:none; margin-top:10px; padding:10px; background:#f0f0f1; border:1px solid #ccc;">';
        echo '<p><strong>Google reCAPTCHA v3 Settings</strong></p>';
        printf(
            '<p><label>Site Key<br /><input type="text" name="%1$s[recaptcha_site_key]" value="%2$s" class="regular-text" /></label></p>',
            esc_attr(self::OPT_KEY),
            esc_attr($opt['recaptcha_site_key'])
        );
        printf(
            '<p><label>Secret Key<br /><input type="password" name="%1$s[recaptcha_secret_key]" value="%2$s" class="regular-text" /></label></p>',
            esc_attr(self::OPT_KEY),
            esc_attr($opt['recaptcha_secret_key'])
        );
        echo '</div>';

        echo '<hr />';
        echo '<p><strong>Rate Limiting</strong></p>';
        printf(
            '<label><input type="checkbox" name="%1$s[enable_rate_limit]" id="prmp_rate_limit_check" value="1" %2$s> %3$s</label>',
            esc_attr(self::OPT_KEY),
            checked(1, (int)$opt['enable_rate_limit'], false),
            esc_html__('Enable rate limiting', 'sh-review-members')
        );

        echo '<div id="prmp_rate_limit_opts" style="display:none; margin-top:10px; padding-left:20px;">';
        printf(
            '<p><label>Maximum number of attempts (per 30 minutes)<br /><input type="number" min="1" max="100" name="%1$s[max_login_attempts]" value="%2$s" class="small-text" /></label></p>',
            esc_attr(self::OPT_KEY),
            esc_attr($opt['max_login_attempts'])
        );
        echo '</div>';
    }

    public static function field_privacy() : void {
        $opt = self::get_options();

        echo '<p><strong>' . esc_html__('GDPR & Data Rights', 'sh-review-members') . '</strong></p>';
        echo '<p class="description">' . esc_html__('These settings allow users to request data export or deletion from their profile page. These requests trigger the standard WordPress privacy workflow (Tools > Export Personal Data / Erase Personal Data).', 'sh-review-members') . '</p>';

        printf(
            '<label style="display:block;margin-top:10px;"><input type="checkbox" name="%1$s[enable_data_export]" value="1" %2$s> %3$s</label>',
            esc_attr(self::OPT_KEY),
            checked(1, (int)($opt['enable_data_export'] ?? 0), false),
            esc_html__('Enable Data Export Request', 'sh-review-members')
        );

        printf(
            '<label style="display:block;margin-top:10px;"><input type="checkbox" name="%1$s[enable_data_deletion]" value="1" %2$s> %3$s</label>',
            esc_attr(self::OPT_KEY),
            checked(1, (int)($opt['enable_data_deletion'] ?? 0), false),
            esc_html__('Enable Account Deletion Request', 'sh-review-members')
        );
    }

    public static function field_social_login() : void {
        $opt = self::get_options();

        echo '<p><strong>Google</strong></p>';
        printf(
            '<label><input type="checkbox" name="%1$s[social_login_google]" id="prmp_sl_google_check" value="1" %2$s> %3$s</label>',
            esc_attr(self::OPT_KEY),
            checked(1, (int)$opt['social_login_google'], false),
            esc_html__('Enable Google login', 'sh-review-members')
        );

        echo '<div id="prmp_sl_google_opts" style="display:none; margin-top:10px; padding-left:20px;">';
        printf(
            '<p><label>Client ID<br /><input type="text" name="%1$s[google_client_id]" value="%2$s" class="regular-text" /></label></p>',
            esc_attr(self::OPT_KEY),
            esc_attr($opt['google_client_id'])
        );
        printf(
            '<p><label>Client Secret<br /><input type="password" name="%1$s[google_client_secret]" value="%2$s" class="regular-text" /></label></p>',
            esc_attr(self::OPT_KEY),
            esc_attr($opt['google_client_secret'])
        );
        echo '</div>';

        echo '<hr />';
        echo '<p><strong>WordPress.com</strong></p>';
        printf(
            '<label><input type="checkbox" name="%1$s[social_login_wordpress]" id="prmp_sl_wp_check" value="1" %2$s> %3$s</label>',
            esc_attr(self::OPT_KEY),
            checked(1, (int)$opt['social_login_wordpress'], false),
            esc_html__('Enable WordPress.com login', 'sh-review-members')
        );

        echo '<div id="prmp_sl_wp_opts" style="display:none; margin-top:10px; padding-left:20px;">';
        printf(
            '<p><label>Client ID<br /><input type="text" name="%1$s[wordpress_client_id]" value="%2$s" class="regular-text" /></label></p>',
            esc_attr(self::OPT_KEY),
            esc_attr($opt['wordpress_client_id'])
        );
        printf(
            '<p><label>Client Secret<br /><input type="password" name="%1$s[wordpress_client_secret]" value="%2$s" class="regular-text" /></label></p>',
            esc_attr(self::OPT_KEY),
            esc_attr($opt['wordpress_client_secret'])
        );
        echo '</div>';
    }

    public static function field_dashboard() : void {
        $opt = self::get_options();
        $public_post_types = get_post_types(['public' => true], 'objects');

        echo '<p><strong>' . esc_html__('Post types shown in “My Pages”:', 'sh-review-members') . '</strong></p>';
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
        echo '<label>' . esc_html__('Number of posts per page:', 'sh-review-members') . ' ';
        printf(
            '<input type="number" min="1" max="100" name="%1$s[dashboard_posts_per_page]" value="%2$d" style="width:90px;">',
            esc_attr(self::OPT_KEY),
            (int)$opt['dashboard_posts_per_page']
        );
        echo '</label></p>';

        echo '<hr />';
        echo '<p><strong>' . esc_html__('Pixel Review connection', 'sh-review-members') . '</strong></p>';

        printf(
            '<label style="display:block;margin-top:6px;"><input type="checkbox" name="%1$s[dashboard_only_pixel_reviews]" value="1" %2$s> %3$s</label>',
            esc_attr(self::OPT_KEY),
            checked(1, (int)$opt['dashboard_only_pixel_reviews'], false),
            esc_html__('Show only posts that have Pixel Review meta (e.g. rating)', 'sh-review-members')
        );

        printf(
            '<label style="display:block;margin-top:6px;"><input type="checkbox" name="%1$s[dashboard_show_review_meta]" value="1" %2$s> %3$s</label>',
            esc_attr(self::OPT_KEY),
            checked(1, (int)$opt['dashboard_show_review_meta'], false),
            esc_html__('Show Pixel Review fields in the list (rating, type, review date)', 'sh-review-members')
        );

        echo '<hr />';
        echo '<p><strong>' . esc_html__('Editor links', 'sh-review-members') . '</strong></p>';
        printf(
            '<label style="display:block;margin-top:6px;"><input type="checkbox" name="%1$s[allow_frontend_create]" value="1" %2$s> %3$s</label>',
            esc_attr(self::OPT_KEY),
            checked(1, (int)$opt['allow_frontend_create'], false),
            esc_html__('Show “Create new post” and “Create review” (opens the WordPress editor)', 'sh-review-members')
        );
    }
}
