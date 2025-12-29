<?php
// includes/traits/trait-prmp-shortcodes.php
if (!defined('ABSPATH')) { exit; }

trait PRMP_Shortcodes {

    /* =========================================================
     * Shortcodes
     * ======================================================= */

    public static function sc_login($atts = []) : string {
        $opt = self::get_options();
        if (empty($opt['enabled'])) return '';

        if (is_user_logged_in()) {
            $url = self::redirect_after_login();
            return '<div class="sh-card pr-card">' . self::render_flash() . '<p>' . esc_html__('You are already logged in.', 'sh-review-members') . ' <a class="pr-link" href="' . esc_url($url) . '">' . esc_html__('Go to My Pages', 'sh-review-members') . '</a></p></div>';
        }

        $register_url = self::page_url('register');

        ob_start();
        echo '<div class="sh-card pr-card pr-card--auth">';
        echo self::render_flash();
        echo '<h2>' . esc_html__('Log in', 'sh-review-members') . '</h2>';
        echo '<form method="post" class="pr-form">';
        echo '<input type="hidden" name="pr_form" value="login">';
        wp_nonce_field('pr_login');
        echo '<p><label>' . esc_html__('Username or Email', 'sh-review-members') . '<br>';
        echo '<input type="text" name="log" autocomplete="username"></label></p>';
        echo '<p><label>' . esc_html__('Password', 'sh-review-members') . '<br>';
        echo '<input type="password" name="pwd" autocomplete="current-password"></label></p>';
        echo '<p><label><input type="checkbox" name="rememberme" value="1"> ' . esc_html__('Remember me', 'sh-review-members') . '</label></p>';

        echo self::render_bot_protection();

        echo '<p><button type="submit" class="pr-button pr-button--wordpress">' . esc_html__('Log in', 'sh-review-members') . '</button></p>';
        echo '</form>';

        if (!empty($opt['allow_register']) && $register_url) {
            echo '<div class="pr-separator"><span class="pr-muted">' . esc_html__('or', 'sh-review-members') . '</span></div>';
            echo '<p class="pr-muted">' . esc_html__('No account yet?', 'sh-review-members') . ' <a class="pr-link" href="' . esc_url($register_url) . '">' . esc_html__('Register here', 'sh-review-members') . '</a></p>';
        }

        // Social login buttons (optional)
        echo self::render_social_login_buttons();

        echo '</div>';
        return ob_get_clean();
    }

    public static function sc_register($atts = []) : string {
        $opt = self::get_options();
        if (empty($opt['enabled'])) return '';
        if (empty($opt['allow_register'])) return '';

        if (is_user_logged_in()) {
            $url = self::redirect_after_login();
            return '<div class="sh-card pr-card">' . self::render_flash() . '<p>' . esc_html__('You are already logged in.', 'sh-review-members') . ' <a class="pr-link" href="' . esc_url($url) . '">' . esc_html__('Go to My Pages', 'sh-review-members') . '</a></p></div>';
        }

        $login_url = self::page_url('login');

        ob_start();
        echo '<div class="sh-card pr-card pr-card--auth">';
        echo self::render_flash();
        echo '<h2>' . esc_html__('Create an account', 'sh-review-members') . '</h2>';
        echo '<form method="post" class="pr-form">';
        echo '<input type="hidden" name="pr_form" value="register">';
        wp_nonce_field('pr_register');

        echo '<div class="pr-grid">';
        echo '<p><label>' . esc_html__('Username', 'sh-review-members') . '<br>';
        echo '<input type="text" name="user_login" autocomplete="username"></label></p>';

        echo '<p><label>' . esc_html__('Email', 'sh-review-members') . '<br>';
        echo '<input type="email" name="user_email" autocomplete="email"></label></p>';
        echo '</div>';

        echo '<div class="pr-grid">';
        echo '<p><label>' . esc_html__('Password', 'sh-review-members') . '<br>';
        echo '<input type="password" name="pass1" autocomplete="new-password"></label></p>';

        echo '<p><label>' . esc_html__('Repeat password', 'sh-review-members') . '<br>';
        echo '<input type="password" name="pass2" autocomplete="new-password"></label></p>';
        echo '</div>';

        echo self::render_bot_protection();

        echo '<p><button type="submit" class="pr-button">' . esc_html__('Register', 'sh-review-members') . '</button></p>';
        echo '</form>';

        if ($login_url) {
            echo '<div class="pr-separator"><span class="pr-muted">' . esc_html__('or', 'sh-review-members') . '</span></div>';
            echo '<p class="pr-muted">' . esc_html__('Already have an account?', 'sh-review-members') . ' <a class="pr-link" href="' . esc_url($login_url) . '">' . esc_html__('Log in here', 'sh-review-members') . '</a></p>';
        }

        // Social login buttons (optional)
        echo self::render_social_login_buttons();

        echo '</div>';
        return ob_get_clean();
    }

    public static function sc_profile($atts = []) : string {
        $opt = self::get_options();
        if (empty($opt['enabled'])) return '';

        if (!is_user_logged_in()) {
            $login_url = self::page_url('login') ?: wp_login_url(self::current_url());
            return '<div class="sh-card pr-card">' . self::render_flash() . '<p>' . esc_html__('You must be logged in to view your profile.', 'sh-review-members') . ' <a class="pr-link" href="' . esc_url($login_url) . '">' . esc_html__('Log in', 'sh-review-members') . '</a></p></div>';
        }

        $user = wp_get_current_user();
        if (!$user || !$user->ID) return '';

        $author = [];
        if (method_exists(__CLASS__, 'prmp_get_author_profile_values')) {
            $author = self::prmp_get_author_profile_values($user->ID);
        }

        $custom_avatar_url = '';
        if (method_exists(__CLASS__, 'prmp_get_custom_avatar_url')) {
            $custom_avatar_url = self::prmp_get_custom_avatar_url($user->ID);
        }

        $dashboard_url = self::page_url('dashboard');
        $logout_url = self::logout_url();

        $avatar = get_avatar_url($user->ID, ['size' => 160]);
        $display_name = $user->display_name ?: $user->user_login;

        ob_start();
        echo '<div class="sh-card pr-card pr-card--profile">';
        echo self::render_flash();

        echo '<div class="pr-dashboard__header">';
        echo '<div>';
        echo '<h2>' . esc_html__('My Profile', 'sh-review-members') . '</h2>';
        echo '<p class="pr-muted">' . esc_html($display_name) . '</p>';
        echo '</div>';
        echo '<div class="pr-dashboard__actions">';
        if ($dashboard_url) echo '<a class="pr-button pr-button--secondary" href="' . esc_url($dashboard_url) . '">' . esc_html__('Dashboard', 'sh-review-members') . '</a>';
        if ($logout_url) echo '<a class="pr-button pr-button--secondary" href="' . esc_url($logout_url) . '">' . esc_html__('Log out', 'sh-review-members') . '</a>';
        echo '</div>';
        echo '</div>';

        echo '<div class="pr-profile__avatar">';
        echo '<img class="pr-profile__avatar-img" src="' . esc_url($avatar) . '" alt="">';
        echo '<div class="pr-profile__avatar-text">';
        echo '<div><strong>' . esc_html($display_name) . '</strong></div>';
        echo '<div class="pr-muted">' . esc_html($user->user_email) . '</div>';
        echo '</div>';
        echo '</div>';

        echo '<hr class="pr-hr">';

        echo '<form method="post" class="pr-form">';
        echo '<input type="hidden" name="pr_form" value="profile_update">';
        wp_nonce_field('pr_profile');

        echo '<div class="pr-grid">';
        echo '<p><label>' . esc_html__('Display name', 'sh-review-members') . '<br>';
        echo '<input type="text" name="display_name" value="' . esc_attr($user->display_name) . '"></label></p>';

        echo '<p><label>' . esc_html__('Email', 'sh-review-members') . '<br>';
        echo '<input type="email" value="' . esc_attr($user->user_email) . '" disabled></label></p>';
        echo '</div>';

        echo '<div class="pr-grid">';
        echo '<p><label>' . esc_html__('First name', 'sh-review-members') . '<br>';
        echo '<input type="text" name="first_name" value="' . esc_attr(get_user_meta($user->ID, 'first_name', true)) . '"></label></p>';

        echo '<p><label>' . esc_html__('Last name', 'sh-review-members') . '<br>';
        echo '<input type="text" name="last_name" value="' . esc_attr(get_user_meta($user->ID, 'last_name', true)) . '"></label></p>';
        echo '</div>';


        echo '<h3>' . esc_html__('Author profile (Pixel Review)', 'sh-review-members') . '</h3>';
        echo '<p class="pr-muted">' . esc_html__('This information is used on your author page and in Pixel Review author boxes.', 'sh-review-members') . '</p>';

        echo '<div class="pr-grid">';
        echo '<p><label>' . esc_html__('Author title / role', 'sh-review-members') . '<br>';
        echo '<input type="text" name="pr_author_title" value="' . esc_attr($author['title'] ?? '') . '"></label></p>';

        echo '<p><label>' . esc_html__('Location', 'sh-review-members') . '<br>';
        echo '<input type="text" name="pr_author_location" value="' . esc_attr($author['location'] ?? '') . '"></label></p>';
        echo '</div>';

        echo '<p><label>' . esc_html__('Short tagline', 'sh-review-members') . '<br>';
        echo '<input type="text" name="pr_author_tagline" value="' . esc_attr($author['tagline'] ?? '') . '"></label></p>';

        echo '<p><label>' . esc_html__('Favorite games (optional)', 'sh-review-members') . '<br>';
        echo '<input type="text" name="pr_author_favorite_games" value="' . esc_attr($author['favorite_games'] ?? '') . '"></label></p>';

        echo '<p><label>' . esc_html__('Extended biography', 'sh-review-members') . '<br>';
        echo '<textarea name="pr_author_long_bio">' . esc_textarea($author['long_bio'] ?? '') . '</textarea></label></p>';

        echo '<div class="pr-grid">';
        echo '<p><label>' . esc_html__('Website / portfolio', 'sh-review-members') . '<br>';
        echo '<input type="url" name="pr_author_website" value="' . esc_attr($author['website'] ?? '') . '" placeholder="https://"></label></p>';

        echo '<p><label>' . esc_html__('X / Twitter URL', 'sh-review-members') . '<br>';
        echo '<input type="url" name="pr_author_x" value="' . esc_attr($author['x'] ?? '') . '" placeholder="https://x.com/... "></label></p>';
        echo '</div>';

        echo '<div class="pr-grid">';
        echo '<p><label>' . esc_html__('Twitch URL', 'sh-review-members') . '<br>';
        echo '<input type="url" name="pr_author_twitch" value="' . esc_attr($author['twitch'] ?? '') . '" placeholder="https://twitch.tv/... "></label></p>';

        echo '<p><label>' . esc_html__('YouTube channel URL', 'sh-review-members') . '<br>';
        echo '<input type="url" name="pr_author_youtube" value="' . esc_attr($author['youtube'] ?? '') . '" placeholder="https://youtube.com/... "></label></p>';
        echo '</div>';

        echo '<p><label>' . esc_html__('Discord server/profile URL', 'sh-review-members') . '<br>';
        echo '<input type="url" name="pr_author_discord" value="' . esc_attr($author['discord'] ?? '') . '" placeholder="https://discord.gg/... "></label></p>';

        echo '<div class="pr-grid">';
        echo '<p><label>' . esc_html__('Author header background image URL', 'sh-review-members') . '<br>';
        echo '<input type="url" name="pr_author_bg_url" value="' . esc_attr($author['bg_url'] ?? '') . '" placeholder="https://"></label></p>';

        echo '<p><label>' . esc_html__('Profile picture URL', 'sh-review-members') . '<br>';
        echo '<input type="url" name="pr_author_avatar_url" value="' . esc_attr($custom_avatar_url) . '" placeholder="https://"></label></p>';
        echo '</div>';

        echo '<div class="pr-grid">';
        echo '<p><label>' . esc_html__('New password', 'sh-review-members') . '<br>';
        echo '<input type="password" name="pass1" autocomplete="new-password"></label><small>' . esc_html__('Leave blank to keep current password.', 'sh-review-members') . '</small></p>';

        echo '<p><label>' . esc_html__('Repeat new password', 'sh-review-members') . '<br>';
        echo '<input type="password" name="pass2" autocomplete="new-password"></label></p>';
        echo '</div>';

        echo '<p><button type="submit" class="pr-button">' . esc_html__('Save profile', 'sh-review-members') . '</button></p>';
        echo '</form>';

        // Privacy & GDPR (optional)
        echo self::render_privacy_section($user);

        echo '</div>';
        return ob_get_clean();
    }

    public static function sc_dashboard($atts = []) : string {
        $opt = self::get_options();
        if (empty($opt['enabled'])) return '';

        if (!is_user_logged_in()) {
            $login_url = self::page_url('login') ?: wp_login_url(self::current_url());
            return '<div class="sh-card pr-card">' . self::render_flash() . '<p>' . esc_html__('You must be logged in.', 'sh-review-members') . ' <a class="pr-link" href="' . esc_url($login_url) . '">' . esc_html__('Log in', 'sh-review-members') . '</a></p></div>';
        }

        $user = wp_get_current_user();
        if (!$user || !$user->ID) return '';

        $profile_url = self::page_url('profile');
        $logout_url = self::logout_url();

        ob_start();
        echo '<div class="sh-card pr-card pr-card--dashboard">';
        echo self::render_flash();

        echo '<div class="pr-dashboard__header">';
        echo '<div>';
        echo '<h2>' . esc_html__('My Dashboard', 'sh-review-members') . '</h2>';
        echo '<p class="pr-muted">' . esc_html(sprintf(__('Hello %s', 'sh-review-members'), $user->display_name ?: $user->user_login)) . '</p>';
        echo '</div>';
        echo '<div class="pr-dashboard__actions">';
        if ($profile_url) echo '<a class="pr-button pr-button--secondary" href="' . esc_url($profile_url) . '">' . esc_html__('Edit Profile', 'sh-review-members') . '</a>';
        if ($logout_url) echo '<a class="pr-button pr-button--secondary" href="' . esc_url($logout_url) . '">' . esc_html__('Log out', 'sh-review-members') . '</a>';
        echo '</div>';
        echo '</div>';

        echo '<hr class="pr-hr">';

        // Create Actions
        if (self::can_show_create_actions()) {
            $types = self::dashboard_post_types();
            if (!empty($types)) {
                echo '<div class="pr-dashboard__actions" style="margin-bottom: 16px;">';

                // Specific "Create Review" button if Pixel Review is active
                if (defined('SH_REVIEW_VERSION') && current_user_can('edit_posts')) {
                    $nonce = wp_create_nonce('sh_quick_review_create');
                    $url = admin_url('admin.php?page=sh-quick-review-post&action=create&sh_nonce=' . $nonce);
                    echo '<a class="pr-button pr-button--secondary" href="' . esc_url($url) . '"> + ' . esc_html__('Create Review', 'sh-review-members') . '</a>';
                }

                foreach ($types as $pt) {
                    $obj = get_post_type_object($pt);
                    if (!$obj || !$obj->cap->create_posts || !current_user_can($obj->cap->create_posts)) continue;

                    $label = sprintf(__('New %s', 'sh-review-members'), $obj->labels->singular_name);
                    $url = admin_url('post-new.php?post_type=' . $pt);

                    // Specific handling for Pixel Review shortcode usage if needed, but standard admin URL is safest fallback.
                    echo '<a class="pr-button pr-button--secondary" href="' . esc_url($url) . '"> + ' . esc_html($label) . '</a>';
                }
                echo '</div>';
            }
        }

        // List user posts
        $paged = max(1, get_query_var('paged'), get_query_var('page'));
        $post_types = self::dashboard_post_types();

        $args = [
            'post_type' => $post_types,
            'post_status' => ['publish', 'draft', 'pending', 'future'],
            'author' => $user->ID,
            'posts_per_page' => 20,
            'paged' => $paged,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $q = new WP_Query($args);

        if ($q->have_posts()) {
            echo '<div class="pr-table-wrap">';
            echo '<table class="pr-table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Title', 'sh-review-members') . '</th>';
            echo '<th>' . esc_html__('Status', 'sh-review-members') . '</th>';
            echo '<th>' . esc_html__('Date', 'sh-review-members') . '</th>';
            echo '<th style="text-align:right">' . esc_html__('Action', 'sh-review-members') . '</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            while ($q->have_posts()) {
                $q->the_post();
                $pid = get_the_ID();
                $status_obj = get_post_status_object(get_post_status());
                $status_label = $status_obj ? $status_obj->label : get_post_status();

                $edit_url = get_edit_post_link($pid);
                // If user cannot edit, maybe view?
                $view_url = get_permalink($pid);

                echo '<tr>';
                echo '<td><strong>' . esc_html(get_the_title()) . '</strong></td>';
                echo '<td><span class="pr-badge">' . esc_html($status_label) . '</span></td>';
                echo '<td>' . get_the_date() . '</td>';
                echo '<td style="text-align:right;">';
                if ($edit_url) {
                    echo '<a class="pr-link" href="' . esc_url($edit_url) . '">' . esc_html__('Edit', 'sh-review-members') . '</a>';
                } elseif ($view_url && get_post_status() === 'publish') {
                    echo '<a class="pr-link" href="' . esc_url($view_url) . '">' . esc_html__('View', 'sh-review-members') . '</a>';
                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div>';

            // Pagination
            $big = 999999999;
            $links = paginate_links([
                'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
                'format' => '?paged=%#%',
                'current' => $paged,
                'total' => $q->max_num_pages,
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'type' => 'array',
            ]);

            if ($links) {
                echo '<div class="pr-pagination" style="margin-top:12px;">';
                foreach ($links as $link) {
                    // Simple styling wrapper if needed, or just output
                    echo $link;
                }
                echo '</div>';
            }

            wp_reset_postdata();

        } else {
            echo '<p class="pr-muted">' . esc_html__('You have not submitted any content yet.', 'sh-review-members') . '</p>';
        }

        echo '</div>';
        return ob_get_clean();
    }

    /* =========================================================
     * Rendering helpers (privacy/social)
     * ======================================================= */

    protected static function render_bot_protection() : string {
        $opt = self::get_options();
        $out = '';

        // Honeypot: Hidden via inline style (safer against CSS loading failures)
        $out .= '<div style="display:none; visibility:hidden; opacity:0; position:absolute; left:-9999px;">';
        $out .= '<label>' . esc_html__('Do not fill this field', 'sh-review-members') . ' <input type="text" name="pr_hp" value="" autocomplete="off" tabindex="-1"></label>';
        $out .= '</div>';

        // Timestamp
        $out .= '<input type="hidden" name="pr_ts" value="' . time() . '">';

        // CAPTCHA
        $provider = $opt['captcha_provider'] ?? '';

        if ($provider === 'turnstile' && !empty($opt['turnstile_site_key'])) {
            $out .= '<div class="cf-turnstile" data-sitekey="' . esc_attr($opt['turnstile_site_key']) . '"></div>';
        }

        if ($provider === 'recaptcha_v3' && !empty($opt['recaptcha_site_key'])) {
            $key = esc_attr($opt['recaptcha_site_key']);
            $out .= '<input type="hidden" name="g-recaptcha-response" class="g-recaptcha-response" value="">';
            $out .= '<script>
            (function() {
                var init = function() {
                    if (typeof grecaptcha !== "undefined") {
                        grecaptcha.ready(function() {
                            grecaptcha.execute("' . $key . '", {action: "submit"}).then(function(token) {
                                var fields = document.querySelectorAll(".g-recaptcha-response");
                                for (var i = 0; i < fields.length; i++) { fields[i].value = token; }
                            });
                        });
                    }
                };
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", init);
                } else {
                    init();
                }
            })();
            </script>';
        }

        return $out;
    }

    protected static function render_social_login_buttons() : string {
        $opt = self::get_options();
        if (empty($opt['enable_social_login'])) return '';

        ob_start();
        echo '<div class="pr-social-login">';
        echo '<div class="pr-separator"><span class="pr-muted">' . esc_html__('Social login', 'sh-review-members') . '</span></div>';

        if (!empty($opt['enable_wp_social_login'])) {
            $url = wp_login_url(self::redirect_after_login());
            echo '<a class="pr-button pr-button--wordpress" href="' . esc_url($url) . '">' . esc_html__('Continue with WordPress', 'sh-review-members') . '</a>';
        }

        if (!empty($opt['enable_google_login'])) {
            $url = self::google_login_url();
            if ($url) echo '<a class="pr-button pr-button--google" href="' . esc_url($url) . '">' . esc_html__('Continue with Google', 'sh-review-members') . '</a>';
        }

        echo '</div>';
        return ob_get_clean();
    }

    protected static function render_privacy_section(WP_User $user) : string {
        $opt = self::get_options();

        $export_on = !empty($opt['enable_data_export']);
        $delete_on = !empty($opt['enable_data_deletion']);
        if (!$export_on && !$delete_on) return '';

        ob_start();
        echo '<hr class="pr-hr">';
        echo '<h3>' . esc_html__('Privacy & GDPR', 'sh-review-members') . '</h3>';
        echo '<p class="pr-muted">' . esc_html__('You can request a copy of your personal data or request account deletion.', 'sh-review-members') . '</p>';

        echo '<form method="post" class="pr-form">';
        echo '<input type="hidden" name="pr_form" value="privacy_request">';
        wp_nonce_field('pr_privacy');

        echo '<div class="pr-dashboard__actions">';
        if ($export_on) {
            echo '<button type="submit" name="privacy_action" value="export_personal_data" class="pr-button pr-button--secondary">' . esc_html__('Request my data (export)', 'sh-review-members') . '</button>';
        }
        if ($delete_on) {
            echo '<button type="submit" name="privacy_action" value="remove_personal_data" class="pr-button pr-button--danger" onclick="return confirm(\'' . esc_js(__('Are you sure? This starts the deletion request process.', 'sh-review-members')) . '\');">' . esc_html__('Request deletion', 'sh-review-members') . '</button>';
        }
        echo '</div>';

        echo '</form>';

        return ob_get_clean();
    }

    /* =========================================================
     * Misc helpers
     * ======================================================= */

    protected static function logout_url() : string {
        $profile = self::page_url('profile') ?: home_url('/');
        return wp_nonce_url(add_query_arg(['pr_action' => 'logout'], $profile), 'pr_logout');
    }

    protected static function google_login_url() : string {
        // Placeholder: your project already contains the working Google flow.
        // Return empty string if disabled/unavailable.
        if (method_exists(__CLASS__, 'get_google_login_url')) {
            return (string) self::get_google_login_url();
        }
        return '';
    }
}
