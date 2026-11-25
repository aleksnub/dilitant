<?php

declare(strict_types=1);

if (
    PHP_SAPI !== 'cli'
    || !defined('DILITANT_TEST_DB_NAME')
    || !defined('DB_NAME')
    || DB_NAME !== DILITANT_TEST_DB_NAME
) {
    exit(1);
}

trait DilitantArticleFixtureVerification
{
    private function verify_post(
        int $id,
        int $category_id,
        string $status,
        string $post_type,
        string $password = '',
        string $author_slot = 'administrator'
    ): void {
        dilitant_test_assert(
            in_array($id, $this->post_ids, true),
            'Verified post does not belong to fixtures.'
        );

        $post = get_post($id);

        dilitant_test_assert(
            $post instanceof WP_Post,
            "Created post {$id} cannot be resolved."
        );

        $this->verify_post_ownership($post);

        dilitant_test_assert_same(
            $post_type,
            $post->post_type,
            "Unexpected post type for fixture {$id}."
        );

        dilitant_test_assert_same(
            $status,
            $post->post_status,
            "Unexpected post status for fixture {$id}."
        );

        dilitant_test_assert_same(
            $password,
            $post->post_password,
            "Unexpected password state for fixture {$id}."
        );

        dilitant_test_assert_same(
            $this->resolve_post_author($author_slot),
            (int) $post->post_author,
            "Unexpected author for fixture {$id}."
        );

        $this->verify_post_categories($id, $category_id);

        if ($status === 'future') {
            $this->verify_future_post($post);
        }
    }

    private function verify_post_categories(
        int $post_id,
        int $category_id
    ): void {
        $actual_categories = wp_get_object_terms(
            $post_id,
            'category',
            ['fields' => 'ids']
        );

        if (
            is_wp_error($actual_categories)
            || !is_array($actual_categories)
        ) {
            throw new RuntimeException(
                'Unable to verify fixture categories.'
            );
        }

        dilitant_test_assert_same(
            [$category_id],
            dilitant_test_normalize_ids($actual_categories),
            "Incorrect categories for fixture {$post_id}."
        );
    }

    private function verify_future_post(WP_Post $post): void
    {
        dilitant_test_assert_same(
            'future',
            $post->post_status,
            'Future fixture has incorrect status.'
        );

        $timestamp = strtotime(
            $post->post_date_gmt . ' UTC'
        );

        dilitant_test_assert(
            $timestamp !== false && $timestamp > time(),
            'Future fixture date is not in the future.'
        );

        dilitant_test_assert_same(
            get_date_from_gmt(
                $post->post_date_gmt,
                'Y-m-d H:i:s'
            ),
            $post->post_date,
            'Future fixture dates are inconsistent.'
        );
    }

    public function verify_base_fixtures(): void
    {
        $this->verify_environment();

        dilitant_test_assert(
            $this->base_created,
            'Base fixtures are not available.'
        );

        $this->verify_post(
            $this->root_post,
            $this->root_category,
            'publish',
            'post'
        );

        $this->verify_post(
            $this->child_post,
            $this->child_category,
            'publish',
            'post'
        );

        $this->verify_post(
            $this->grandchild_post,
            $this->grandchild_category,
            'publish',
            'post'
        );

        $this->verify_post(
            $this->outside_post,
            $this->outside_category,
            'publish',
            'post'
        );

        $this->verify_post(
            $this->draft_post,
            $this->child_category,
            'draft',
            'post',
            '',
            'author_a'
        );
    }

    public function verify_query_fixtures(): void
    {
        $this->verify_environment();

        dilitant_test_assert(
            $this->base_created && $this->query_created,
            'Complete query fixtures are not available.'
        );

        $this->verify_base_fixtures();

        $this->verify_post(
            $this->page_post,
            $this->root_category,
            'publish',
            'page'
        );

        $this->verify_post(
            $this->private_post,
            $this->root_category,
            'private',
            'post',
            '',
            'author_b'
        );

        $this->verify_post(
            $this->future_post,
            $this->root_category,
            'future',
            'post'
        );

        $this->verify_post(
            $this->password_post,
            $this->root_category,
            'publish',
            'post',
            self::TEST_POST_PASSWORD
        );

        $this->verify_post(
            $this->archive_post,
            $this->root_category,
            'publish',
            self::ARCHIVE_POST_TYPE
        );

        dilitant_test_assert_same(
            10,
            count($this->post_ids),
            'Unexpected number of query fixture posts.'
        );

        dilitant_test_assert_same(
            4,
            count($this->category_ids),
            'Unexpected number of fixture categories.'
        );

        if ($this->user_ids !== []) {
            $this->verify_users();
            $this->verify_user_post_permissions();
        }
    }

    private function verify_user_post_permissions(): void
    {
        $cases = [
            ['administrator', $this->draft_post, true],
            ['subscriber', $this->draft_post, false],
            ['author_a', $this->draft_post, true],
            ['author_b', $this->draft_post, false],

            ['administrator', $this->private_post, true],
            ['subscriber', $this->private_post, false],
            ['author_a', $this->private_post, false],
            ['author_b', $this->private_post, true],
        ];

        foreach ($cases as [$slot, $post_id, $expected]) {
            $record = $this->owned_record(
                'user',
                $this->{$slot}
            );

            DilitantFixtureUserPermissions::verify_post_access(
                $record,
                $this->run_id,
                $post_id,
                $expected
            );
        }

        foreach ([$this->draft_post, $this->private_post] as $id) {
            $anonymous = new WP_User(0);

            dilitant_test_assert_same(
                false,
                user_can($anonymous, 'edit_post', $id),
                'Anonymous post permission accepted.'
            );
        }

        DilitantFixtureUserResolution::guard();
    }
}