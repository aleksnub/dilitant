<?php

declare(strict_types=1);

trait DilitantVisibilityUserCleanup
{
    private function verify_deletable(
        int $id,
        string $login
    ): void {
        global $wpdb;

        dilitant_visibility_verify_environment();

        $protected_id = $this->protected_admin_id();

        dilitant_test_assert(
            $id > 0
            && $id !== $this->administrator
            && $id !== $protected_id
            && $id !== get_current_user_id(),
            'Refusing to delete protected or current user.'
        );

        dilitant_test_assert(
            $this->resolve_owned_user($id, $login),
            'Visibility user identity unavailable.'
        );

        $owned_posts = $this->count_rows(
            $wpdb->prepare(
                "SELECT COUNT(*) AS n
                 FROM {$wpdb->posts}
                 WHERE post_author = %d",
                $id
            )
        );

        dilitant_test_assert_same(
            0,
            $owned_posts,
            'Fixture user owns database records.'
        );

        $owned_links = $this->count_rows(
            $wpdb->prepare(
                "SELECT COUNT(*) AS n
                 FROM {$wpdb->links}
                 WHERE link_owner = %d",
                $id
            )
        );

        dilitant_test_assert_same(
            0,
            $owned_links,
            'Fixture user owns links.'
        );

        $owned_comments = $this->count_rows(
            $wpdb->prepare(
                "SELECT COUNT(*) AS n
                 FROM {$wpdb->comments}
                 WHERE user_id = %d",
                $id
            )
        );

        dilitant_test_assert_same(
            0,
            $owned_comments,
            'Fixture user owns comments.'
        );
    }

    private function verify_removed(
        int $id,
        string $login
    ): void {
        global $wpdb;

        dilitant_test_assert_same(
            [],
            $this->identity_candidates($id, $login),
            'Fixture user survived cleanup.'
        );

        $remaining_meta = $this->count_rows(
            $wpdb->prepare(
                "SELECT COUNT(*) AS n
                 FROM {$wpdb->usermeta}
                 WHERE user_id = %d",
                $id
            )
        );

        dilitant_test_assert_same(
            0,
            $remaining_meta,
            'Fixture user metadata survived cleanup.'
        );

        $remaining_posts = $this->count_rows(
            $wpdb->prepare(
                "SELECT COUNT(*) AS n
                 FROM {$wpdb->posts}
                 WHERE post_author = %d",
                $id
            )
        );

        dilitant_test_assert_same(
            0,
            $remaining_posts,
            'Fixture user post dependencies survived cleanup.'
        );

        $remaining_links = $this->count_rows(
            $wpdb->prepare(
                "SELECT COUNT(*) AS n
                 FROM {$wpdb->links}
                 WHERE link_owner = %d",
                $id
            )
        );

        dilitant_test_assert_same(
            0,
            $remaining_links,
            'Fixture user link dependencies survived cleanup.'
        );

        $remaining_comments = $this->count_rows(
            $wpdb->prepare(
                "SELECT COUNT(*) AS n
                 FROM {$wpdb->comments}
                 WHERE user_id = %d",
                $id
            )
        );

        dilitant_test_assert_same(
            0,
            $remaining_comments,
            'Fixture user comment dependencies survived cleanup.'
        );
    }

    private function remove_user(
        int $id,
        string $login
    ): void {
        dilitant_visibility_verify_environment();

        dilitant_test_assert(
            $id > 0
            && $id !== $this->administrator
            && $id !== $this->protected_admin_id()
            && $id !== get_current_user_id(),
            'Refusing to delete protected or current user.'
        );

        if ($this->resolve_owned_user($id, $login)) {
            clean_user_cache($id);

            $this->verify_deletable($id, $login);

            dilitant_visibility_verify_environment();

            dilitant_test_assert_same(
                true,
                wp_delete_user($id, null),
                'Unable to delete fixture user.'
            );
        }

        $this->verify_removed($id, $login);
    }
}