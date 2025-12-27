<?php
// includes/traits/trait-prmp-pages.php
if (!defined('ABSPATH')) { exit; }

trait PRMP_Pages {

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
                'title' => __('Redigera/Skapa inlägg', 'sh-review-members'),
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
