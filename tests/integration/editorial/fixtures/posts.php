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

trait DilitantArticleFixturePosts
{
    private function resolve_post_author(string $slot): int
    {
        if (!in_array(
            $slot,
            ['administrator', 'author_a', 'author_b'],
            true
        )) {
            throw new RuntimeException(
                'Invalid fixture post author slot.'
            );
        }

        if ($this->user_ids === []) {
            if ($this->users_created || $this->author_id <= 0) {
                throw new RuntimeException(
                    'Fixture author unavailable.'
                );
            }

            return $this->author_id;
        }

        if (!$this->users_created) {
            throw new RuntimeException(
                'Fixture users are incomplete.'
            );
        }

        $id = $this->{$slot};
        $record = $this->owned_record('user', $id);

        if (
            $record['identity']['slot'] !== $slot
            || !in_array($id, $this->user_ids, true)
        ) {
            throw new RuntimeException(
                'Fixture post author slot mismatch.'
            );
        }

        DilitantFixtureUserPermissions::verify(
            $record,
            $this->run_id
        );

        return $id;
    }

    private function create_post(
        string $title,
        int $category_id,
        string $status = 'publish',
        string $post_type = 'post',
        string $password = '',
        string $author_slot = 'administrator'
    ): int {
        $this->verify_environment();
        dilitant_fixture_assert_lock();

        dilitant_test_assert(
            post_type_exists($post_type),
            "Unknown fixture post type: {$post_type}"
        );

        dilitant_test_assert(
            in_array(
                $status,
                ['publish', 'draft', 'private', 'future'],
                true
            ),
            "Unsupported fixture status: {$status}"
        );

        dilitant_test_assert(
            in_array($category_id, $this->category_ids, true),
            'Category does not belong to fixtures.'
        );

        $author_id = $this->resolve_post_author($author_slot);

        $full_title = $title . ' ' . $this->run_id;

        $marker = 'AM-CONTENT-' . $this->run_id . '-'
            . bin2hex(random_bytes(8));

        $data = [
            'post_type' => $post_type,
            'post_title' => $full_title,
            'post_content' =>
                "Article Model integration test fixture. {$marker}",
            'post_status' => $status,
            'post_author' => $author_id,
            'post_password' => $password,
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        ];

        if ($post_type === 'post') {
            $data['post_category'] = [$category_id];
        }

        if ($status === 'future') {
            $data = array_merge(
                $data,
                $this->build_future_post_dates()
            );
        }

        $identity = [
            'title' => $full_title,
            'type' => $post_type,
            'author_id' => $author_id,
            'content_marker' => $marker,
        ];

        $this->register_intent('post', $identity);

        dilitant_fixture_assert_lock();

        $result = wp_insert_post($data, true);

        dilitant_fixture_assert_lock();

        if (
            is_wp_error($result)
            || !is_int($result)
            || $result <= 0
        ) {
            throw new RuntimeException('Post creation failed.');
        }

        $this->register_object(
            'post',
            $result,
            $identity
        );

        $this->assign_post_category(
            $result,
            $category_id
        );

        $this->verify_post(
            $result,
            $category_id,
            $status,
            $post_type,
            $password,
            $author_slot
        );

        dilitant_fixture_assert_lock();

        return $result;
    }

    private function build_future_post_dates(): array
    {
        $gmt = gmdate(
            'Y-m-d H:i:s',
            time() + 7 * DAY_IN_SECONDS
        );

        return [
            'post_date_gmt' => $gmt,
            'post_date' => get_date_from_gmt(
                $gmt,
                'Y-m-d H:i:s'
            ),
        ];
    }

    private function assign_post_category(
        int $post_id,
        int $category_id
    ): void {
        $this->verify_environment();
        dilitant_fixture_assert_lock();

        dilitant_test_assert(
            in_array($post_id, $this->post_ids, true),
            'Post does not belong to fixtures.'
        );

        dilitant_test_assert(
            in_array($category_id, $this->category_ids, true),
            'Category does not belong to fixtures.'
        );

        $assigned = wp_set_object_terms(
            $post_id,
            [$category_id],
            'category',
            false
        );

        dilitant_fixture_assert_lock();

        if (is_wp_error($assigned)) {
            throw new RuntimeException(
                'Category assignment failed.'
            );
        }

        dilitant_test_assert(
            is_array($assigned) && count($assigned) === 1,
            'Unexpected category assignment result.'
        );
    }
}