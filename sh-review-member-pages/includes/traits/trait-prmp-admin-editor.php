<?php
// includes/traits/trait-prmp-admin-editor.php
if (!defined('ABSPATH')) { exit; }

trait PRMP_Admin_Editor {

    protected static function wp_admin_edit_post_url(int $post_id) : string {
        if (!$post_id) return '';
        return admin_url('post.php?action=edit&post=' . (int)$post_id);
    }

    protected static function wp_admin_new_post_url(string $post_type = 'post') : string {
        $post_type = sanitize_key($post_type ?: 'post');
        return admin_url('post-new.php?post_type=' . $post_type);
    }

    /**
     * Pixel Review: "Create Review" URL (admin page that creates draft + redirects to editor).
     *
     * Pixel Review core uses:
     *  - page: sh-quick-review-post
     *  - action: create
     *  - nonce action: sh_quick_review_create
     *  - nonce param: sh_nonce
     */
    protected static function pixel_review_create_review_url() : string {
        if (!defined('SH_REVIEW_VERSION')) return '';
        $base = admin_url('admin.php?page=sh-quick-review-post&action=create');
        return wp_nonce_url($base, 'sh_quick_review_create', 'sh_nonce');
    }
}
