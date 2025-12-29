<?php
// includes/traits/trait-prmp-author-profile.php
if (!defined('ABSPATH')) { exit; }

/**
 * Pixel Review author profile compatibility.
 *
 * Pixel Review core stores its author profile fields as user meta. This addon
 * offers a front-end profile form that can keep the same data in sync.
 */
trait PRMP_Author_Profile {

    /**
     * Pixel Review user meta keys used by SH_Review_Author_Profile.
     */
    protected static function prmp_author_meta_keys() : array {
        return [
            // Core fields
            'sh_author_title',
            'sh_author_location',
            'sh_author_tagline',
            'sh_author_favorite_games',
            'sh_author_long_bio',

            // Links
            'sh_author_website',
            'sh_author_x',
            'sh_author_twitch',
            'sh_author_youtube',
            'sh_author_discord',

            // Background image
            'sh_review_author_bg',
        ];
    }

    /**
     * Read Pixel Review long bio with fallback to WordPress biographical info.
     */
    protected static function prmp_get_long_bio(int $user_id) : string {
        $long = get_user_meta($user_id, 'sh_author_long_bio', true);
        if (is_string($long) && $long !== '') {
            return $long;
        }

        // WordPress stores "Biographical Info" in user meta key "description".
        $wp_bio = get_user_meta($user_id, 'description', true);
        return is_string($wp_bio) ? $wp_bio : '';
    }

    /**
     * Convenience: return all Pixel Review author profile values for a user.
     */
    protected static function prmp_get_author_profile_values(int $user_id) : array {
        $out = [];

        $out['title']          = (string)get_user_meta($user_id, 'sh_author_title', true);
        $out['location']       = (string)get_user_meta($user_id, 'sh_author_location', true);
        $out['tagline']        = (string)get_user_meta($user_id, 'sh_author_tagline', true);
        $out['favorite_games'] = (string)get_user_meta($user_id, 'sh_author_favorite_games', true);

        $out['website'] = (string)get_user_meta($user_id, 'sh_author_website', true);
        $out['x']       = (string)get_user_meta($user_id, 'sh_author_x', true);
        $out['twitch']  = (string)get_user_meta($user_id, 'sh_author_twitch', true);
        $out['youtube'] = (string)get_user_meta($user_id, 'sh_author_youtube', true);
        $out['discord'] = (string)get_user_meta($user_id, 'sh_author_discord', true);

        $out['bg_url']  = (string)get_user_meta($user_id, 'sh_review_author_bg', true);

        $out['long_bio'] = self::prmp_get_long_bio($user_id);

        return $out;
    }

    /**
     * Save author profile data from the front-end profile form.
     *
     * Notes:
     *  - Long bio is stored in Pixel Review meta (sh_author_long_bio) and a plain
     *    text version is also saved into WordPress "description" for compatibility.
     *  - All other fields are stored as user meta keys used by Pixel Review.
     */
    protected static function prmp_save_author_profile_from_post(int $user_id) : void {
        // Long bio (Option A): one source of truth in Pixel Review meta (HTML),
        // synced to WordPress "description" as plain text.
        $bio_field_present = array_key_exists('pr_author_long_bio', $_POST) || array_key_exists('pr_description', $_POST);
        if ($bio_field_present) {
            $bio_raw = '';
            if (array_key_exists('pr_author_long_bio', $_POST)) {
                $bio_raw = (string) wp_unslash($_POST['pr_author_long_bio']);
            } elseif (array_key_exists('pr_description', $_POST)) {
                // Backward compatibility with older field name.
                $bio_raw = (string) wp_unslash($_POST['pr_description']);
            }

            $bio_raw = trim($bio_raw);

            if ($bio_raw === '') {
                delete_user_meta($user_id, 'sh_author_long_bio');

                // Also clear WP description.
                wp_update_user([
                    'ID' => $user_id,
                    'description' => '',
                ]);
            } else {
                $bio_html  = wp_kses_post($bio_raw);
                $bio_plain = sanitize_textarea_field(wp_strip_all_tags($bio_html));

                update_user_meta($user_id, 'sh_author_long_bio', $bio_html);

                // Keep WordPress biographical info in sync.
                wp_update_user([
                    'ID' => $user_id,
                    'description' => $bio_plain,
                ]);
            }
        }

        // Text fields
        $text_map = [
            'pr_author_title'          => 'sh_author_title',
            'pr_author_location'       => 'sh_author_location',
            'pr_author_tagline'        => 'sh_author_tagline',
            'pr_author_favorite_games' => 'sh_author_favorite_games',
        ];

        foreach ($text_map as $input_key => $meta_key) {
            if (!array_key_exists($input_key, $_POST)) {
                continue;
            }

            $raw = trim((string)wp_unslash($_POST[$input_key]));
            if ($raw === '') {
                delete_user_meta($user_id, $meta_key);
            } else {
                update_user_meta($user_id, $meta_key, sanitize_text_field($raw));
            }
        }

        // URL fields
        $url_map = [
            'pr_author_website' => 'sh_author_website',
            'pr_author_x'       => 'sh_author_x',
            'pr_author_twitch'  => 'sh_author_twitch',
            'pr_author_youtube' => 'sh_author_youtube',
            'pr_author_discord' => 'sh_author_discord',
            'pr_author_bg_url'  => 'sh_review_author_bg',
        ];

        foreach ($url_map as $input_key => $meta_key) {
            if (!array_key_exists($input_key, $_POST)) {
                continue;
            }

            $raw = trim((string)wp_unslash($_POST[$input_key]));
            if ($raw === '') {
                delete_user_meta($user_id, $meta_key);
                continue;
            }

            $sanitized = esc_url_raw($raw);
            if ($sanitized === '') {
                delete_user_meta($user_id, $meta_key);
            } else {
                update_user_meta($user_id, $meta_key, $sanitized);
            }
        }
    }

    /**
     * Public wrapper invoked from the profile form handler.
     *
     * Keeps Pixel Review author profile meta in sync with the addon's front-end
     * profile form, including (optionally) a local avatar URL.
     */
    public static function sync_author_profile_from_profile_form(int $user_id) : void {
        if ($user_id <= 0) return;

        self::prmp_save_author_profile_from_post($user_id);

        // Optional: local avatar support (provided by PRMP_Avatar).
        if (method_exists(__CLASS__, 'prmp_save_custom_avatar_from_post')) {
            self::prmp_save_custom_avatar_from_post($user_id);
        }
    }
}
