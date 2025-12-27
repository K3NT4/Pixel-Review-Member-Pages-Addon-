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
        $author_meta = self::prmp_get_author_profile_values((int)$user->ID);

        ob_start();
        echo '<div class="pr-card pr-card--profile">';
        echo self::render_flash();
        echo '<h2>' . esc_html__('Min profil', 'sh-review-members') . '</h2>';

        // Avatar / Gravatar
        echo '<div class="pr-profile__avatar">';
        echo '<div class="pr-profile__avatar-img">' . get_avatar($user->ID, 96) . '</div>';
        echo '<div class="pr-profile__avatar-text">';
        echo '<strong>' . esc_html__('Profilbild', 'sh-review-members') . '</strong><br />';
        echo '<span class="pr-muted">' . esc_html__('Profilbilden hämtas från Gravatar (baserat på din e-post).', 'sh-review-members') . '</span><br />';
        echo '<a class="pr-link" href="' . esc_url('https://gravatar.com') . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Ändra profilbild på Gravatar', 'sh-review-members') . '</a>';
        echo '</div>';
        echo '</div>';

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
        echo '<textarea name="pr_author_long_bio" rows="6">' . esc_textarea($author_meta['long_bio'] ?? '') . '</textarea></label></p>';

        // Pixel Review author fields
        echo '<details class="pr-details">';
        echo '<summary>' . esc_html__('Pixel Review – författarprofil', 'sh-review-members') . '</summary>';
        echo '<div class="pr-details__content">';
        echo '<p class="pr-muted">' . esc_html__('Dessa fält används av Pixel Review på författarsidor och kan även visas i teman/moduler som läser Pixel Review-meta.', 'sh-review-members') . '</p>';

        echo '<div class="pr-grid">';
        echo '<p><label>' . esc_html__('Titel', 'sh-review-members') . '<br />';
        echo '<input type="text" name="pr_author_title" value="' . esc_attr($author_meta['title'] ?? '') . '" placeholder="Redaktör"></label></p>';

        echo '<p><label>' . esc_html__('Plats', 'sh-review-members') . '<br />';
        echo '<input type="text" name="pr_author_location" value="' . esc_attr($author_meta['location'] ?? '') . '" placeholder="Stockholm"></label></p>';

        echo '<p><label>' . esc_html__('Tagline', 'sh-review-members') . '<br />';
        echo '<input type="text" name="pr_author_tagline" value="' . esc_attr($author_meta['tagline'] ?? '') . '" placeholder="Skriver om spel och hårdvara"></label></p>';

        echo '<p><label>' . esc_html__('Favoritspel', 'sh-review-members') . '<br />';
        echo '<input type="text" name="pr_author_favorite_games" value="' . esc_attr($author_meta['favorite_games'] ?? '') . '" placeholder="Elden Ring, ..."></label></p>';
        echo '</div>';

        echo '<div class="pr-grid">';
        echo '<p><label>' . esc_html__('Webbplats', 'sh-review-members') . '<br />';
        echo '<input type="url" name="pr_author_website" value="' . esc_attr($author_meta['website'] ?? '') . '" placeholder="https://..."></label></p>';

        echo '<p><label>' . esc_html__('X/Twitter', 'sh-review-members') . '<br />';
        echo '<input type="url" name="pr_author_x" value="' . esc_attr($author_meta['x'] ?? '') . '" placeholder="https://x.com/... "></label></p>';

        echo '<p><label>' . esc_html__('Twitch', 'sh-review-members') . '<br />';
        echo '<input type="url" name="pr_author_twitch" value="' . esc_attr($author_meta['twitch'] ?? '') . '" placeholder="https://twitch.tv/... "></label></p>';

        echo '<p><label>' . esc_html__('YouTube', 'sh-review-members') . '<br />';
        echo '<input type="url" name="pr_author_youtube" value="' . esc_attr($author_meta['youtube'] ?? '') . '" placeholder="https://youtube.com/... "></label></p>';
        echo '</div>';

        echo '<div class="pr-grid">';
        echo '<p><label>' . esc_html__('Discord', 'sh-review-members') . '<br />';
        echo '<input type="url" name="pr_author_discord" value="' . esc_attr($author_meta['discord'] ?? '') . '" placeholder="https://discord.gg/... "></label></p>';

        echo '<p><label>' . esc_html__('Bakgrundsbild (URL)', 'sh-review-members') . '<br />';
        echo '<input type="url" name="pr_author_bg_url" value="' . esc_attr($author_meta['bg_url'] ?? '') . '" placeholder="https://..."></label></p>';
        echo '</div>';

        echo '</div>';
        echo '</details>';

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
        $post_types = self::dashboard_post_types();
        $per_page = (int)($opt['dashboard_posts_per_page'] ?? 20);
        $paged = max(1, absint(get_query_var('paged') ?: (get_query_var('page') ?: 1)));

        $meta_query = [];
        if (!empty($opt['dashboard_only_pixel_reviews'])) {
            $score_key = self::meta_key('score');
            if ($score_key) {
                $meta_query[] = [
                    'key' => $score_key,
                    'compare' => 'EXISTS',
                ];
            }
        }

        // Always list only the logged-in user's own content.
        $args = [
            'post_type'      => $post_types,
            'author'         => $user->ID,
            'post_status'    => ['publish', 'pending', 'draft', 'future', 'private'],
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'no_found_rows'  => false,
        ];
        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }

        $q = new WP_Query($args);

        $profile_url = self::page_url('profile');
        $logout_url = self::page_url('logout');

        $show_review_meta = !empty($opt['dashboard_show_review_meta']);
        $score_key = $show_review_meta ? self::meta_key('score') : '';
        $mode_key  = $show_review_meta ? self::meta_key('mode') : '';
        $date_key  = $show_review_meta ? self::meta_key('pubdate') : '';

        ob_start();
        echo '<div class="pr-card pr-card--dashboard">';
        echo self::render_flash();

        echo '<div class="pr-dashboard__header">';
        echo '<div>';
        echo '<h2>' . esc_html__('Mina sidor', 'sh-review-members') . '</h2>';
        echo '<p class="pr-muted">' . sprintf(esc_html__('Inloggad som %s', 'sh-review-members'), '<strong>' . esc_html($user->display_name) . '</strong>') . '</p>';
        echo '</div>';
        echo '<div class="pr-dashboard__actions">';

        // Open real WordPress editor for create actions.
        if (self::can_show_create_actions()) {
            $default_type = $post_types[0] ?? 'post';
            $new_admin_url = self::wp_admin_new_post_url($default_type);

            echo '<a class="pr-button" href="' . esc_url($new_admin_url) . '">' . esc_html__('Skapa nytt inlägg', 'sh-review-members') . '</a>';

            // Pixel Review: create review (draft + redirect to editor)
            $review_create = self::pixel_review_create_review_url();
            if ($review_create) {
                echo '<a class="pr-button" href="' . esc_url($review_create) . '">' . esc_html__('Skapa recension', 'sh-review-members') . '</a>';
            }
        }

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

            if ($show_review_meta && $score_key) {
                echo '<th>' . esc_html__('Betyg', 'sh-review-members') . '</th>';
            }
            if ($show_review_meta && $mode_key) {
                echo '<th>' . esc_html__('Typ', 'sh-review-members') . '</th>';
            }
            if ($show_review_meta && $date_key) {
                echo '<th>' . esc_html__('Recensionsdatum', 'sh-review-members') . '</th>';
            }

            echo '<th>' . esc_html__('Status', 'sh-review-members') . '</th>';
            echo '<th>' . esc_html__('Datum', 'sh-review-members') . '</th>';
            echo '<th>' . esc_html__('Åtgärd', 'sh-review-members') . '</th>';
            echo '</tr></thead><tbody>';

            while ($q->have_posts()) {
                $q->the_post();
                $pid = get_the_ID();
                $status = get_post_status($pid);
                $date = get_the_date('Y-m-d');

                $score = $score_key ? get_post_meta($pid, $score_key, true) : '';
                $mode  = $mode_key ? (string)get_post_meta($pid, $mode_key, true) : '';
                $rdate = $date_key ? (string)get_post_meta($pid, $date_key, true) : '';

                echo '<tr>';
                echo '<td><a href="' . esc_url(get_permalink($pid)) . '">' . esc_html(get_the_title()) . '</a></td>';

                if ($show_review_meta && $score_key) {
                    $score_out = ($score !== '') ? number_format((float)$score, 1, '.', '') : '—';
                    echo '<td>' . esc_html($score_out) . '</td>';
                }

                if ($show_review_meta && $mode_key) {
                    $mode_out = $mode === 'product' ? __('Produkt', 'sh-review-members') : ($mode === 'game' ? __('Spel', 'sh-review-members') : '—');
                    echo '<td>' . esc_html($mode_out) . '</td>';
                }

                if ($show_review_meta && $date_key) {
                    echo '<td>' . esc_html($rdate ?: '—') . '</td>';
                }

                echo '<td>' . esc_html($status) . '</td>';
                echo '<td>' . esc_html($date) . '</td>';
                echo '<td>';
                if (current_user_can('edit_post', $pid)) {
                    $edit_admin = self::wp_admin_edit_post_url((int)$pid);
                    $title = get_the_title($pid);
                    $edit_aria_label = sprintf(__('Redigera "%s"', 'sh-review-members'), $title);
                    echo '<a class="pr-link" href="' . esc_url($edit_admin) . '" aria-label="' . esc_attr($edit_aria_label) . '">' . esc_html__('Redigera', 'sh-review-members') . '</a>';
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
            if (!empty($opt['dashboard_only_pixel_reviews'])) {
                echo '<p class="pr-muted">' . esc_html__('Notis: Filter är aktivt (endast inlägg med Pixel Review-meta). Stäng av i inställningarna om du vill se alla.', 'sh-review-members') . '</p>';
            }
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

        // If a post_id is provided, send to real WP editor.
        $post_id = absint($_GET['post_id'] ?? 0);
        if ($post_id && current_user_can('edit_post', $post_id)) {
            wp_safe_redirect(self::wp_admin_edit_post_url($post_id));
            exit;
        }

        // If action=new, send to real WP "new post"
        $action = sanitize_key((string)($_GET['action'] ?? ''));
        if ($action === 'new' && self::can_show_create_actions()) {
            $default_type = self::dashboard_post_types()[0] ?? 'post';
            wp_safe_redirect(self::wp_admin_new_post_url($default_type));
            exit;
        }

        // If action=create-review, send to Pixel Review create flow (draft + editor)
        if ($action === 'create-review' && self::can_show_create_actions()) {
            $url = self::pixel_review_create_review_url();
            if ($url) {
                wp_safe_redirect($url);
                exit;
            }
        }

        // Otherwise: show a helper card.
        $dash = self::page_url('dashboard') ?: home_url('/');
        $new_admin = self::can_show_create_actions()
            ? self::wp_admin_new_post_url(self::dashboard_post_types()[0] ?? 'post')
            : '';

        $review_create = self::can_show_create_actions()
            ? self::pixel_review_create_review_url()
            : '';

        ob_start();
        echo '<div class="pr-card">';
        echo self::render_flash();
        echo '<h2>' . esc_html__('Redigering', 'sh-review-members') . '</h2>';
        echo '<p class="pr-muted">' . esc_html__('Redigering och skapande sker i WordPress editorn.', 'sh-review-members') . '</p>';

        echo '<p><a class="pr-button pr-button--secondary" href="' . esc_url($dash) . '">' . esc_html__('Tillbaka till Mina sidor', 'sh-review-members') . '</a></p>';

        if ($new_admin) {
            echo '<p><a class="pr-button" href="' . esc_url($new_admin) . '">' . esc_html__('Skapa nytt inlägg', 'sh-review-members') . '</a></p>';
        }
        if ($review_create) {
            echo '<p><a class="pr-button" href="' . esc_url($review_create) . '">' . esc_html__('Skapa recension', 'sh-review-members') . '</a></p>';
        }

        echo '</div>';
        return (string)ob_get_clean();
    }
}
