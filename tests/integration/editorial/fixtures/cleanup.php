<?php

declare(strict_types=1);

trait DilitantArticleFixtureCleanup
{
    public function cleanup(): void
    {
        $this->verify_environment();

        require_once __DIR__ . '/lock.php';

        dilitant_fixture_acquire_lock();

        $errors = [];

        foreach (array_reverse($this->post_ids) as $id) {
            dilitant_fixture_assert_lock();

            try {
                $this->cleanup_post($id);
            } catch (Throwable $error) {
                $errors[] = "Post {$id}: " . $error->getMessage();
            }
        }

        if ($errors !== []) {
            throw new RuntimeException(implode(PHP_EOL, $errors));
        }

        foreach (array_reverse($this->category_ids) as $id) {
            dilitant_fixture_assert_lock();

            try {
                $this->cleanup_category($id);
            } catch (Throwable $error) {
                $errors[] = "Category {$id}: " . $error->getMessage();
            }
        }

        if ($errors !== []) {
            throw new RuntimeException(implode(PHP_EOL, $errors));
        }

        $this->verify_cleanup();

        if ($this->user_ids !== []) {
            $this->cleanup_users();
        }

        dilitant_fixture_assert_lock();

        $this->reset_fixture_state();

        echo "PASS: fixture cleanup\n";
    }

    private function cleanup_query(string $sql): array
    {
        global $wpdb;

        $this->verify_environment();
        dilitant_fixture_assert_lock();

        $rows = $wpdb->get_results($sql, ARRAY_A);

        if ($wpdb->last_error !== '' || !is_array($rows)) {
            throw new RuntimeException('Cleanup SQL query failed.');
        }

        dilitant_fixture_assert_lock();

        return $rows;
    }

    private function cleanup_count(string $sql): int
    {
        global $wpdb;

        $this->verify_environment();
        dilitant_fixture_assert_lock();

        $count = $wpdb->get_var($sql);

        if (
            $wpdb->last_error !== ''
            || $count === null
            || !ctype_digit((string) $count)
        ) {
            throw new RuntimeException('Cleanup SQL count failed.');
        }

        dilitant_fixture_assert_lock();

        return (int) $count;
    }

    private function resolve_cleanup_post(int $id): ?WP_Post
    {
        global $wpdb;

        $this->owned_record('post', $id);

        $rows = $this->cleanup_query(
            $wpdb->prepare(
                "SELECT *
                 FROM {$wpdb->posts}
                 WHERE ID = %d",
                $id
            )
        );

        if ($rows === []) {
            return null;
        }

        if (
            count($rows) !== 1
            || (int) $rows[0]['ID'] !== $id
        ) {
            throw new RuntimeException('Ambiguous post identity.');
        }

        $post = new WP_Post((object) $rows[0]);

        $this->verify_post_ownership($post);

        return $post;
    }

    private function cleanup_post(int $id): void
    {
        $post = $this->resolve_cleanup_post($id);

        if ($post === null) {
            $this->assert_post_absent($id);

            return;
        }

        $this->verify_post_children($id);

        $this->cleanup_post_schedule($id);
        $this->cleanup_post_categories($id);

        $post = $this->resolve_cleanup_post($id);

        if (!$post instanceof WP_Post) {
            throw new RuntimeException(
                'Post disappeared during cleanup.'
            );
        }

        clean_post_cache($id);

        dilitant_fixture_assert_lock();

        $deleted = wp_delete_post($id, true);

        if (!$deleted instanceof WP_Post) {
            throw new RuntimeException('Post deletion failed.');
        }

        dilitant_fixture_assert_lock();

        $this->assert_post_absent($id);
    }

    private function verify_post_children(int $id): void
    {
        global $wpdb;

        $children = $this->cleanup_query(
            $wpdb->prepare(
                "SELECT ID, post_type, post_parent
                 FROM {$wpdb->posts}
                 WHERE post_parent = %d",
                $id
            )
        );

        foreach ($children as $child) {
            if (
                $child['post_type'] !== 'revision'
                || (int) $child['post_parent'] !== $id
                || (int) $child['ID'] <= 0
            ) {
                throw new RuntimeException(
                    'Unexpected dependent post.'
                );
            }
        }
    }

    private function cleanup_post_schedule(int $id): void
    {
        dilitant_fixture_assert_lock();

        $result = wp_clear_scheduled_hook(
            'publish_future_post',
            [$id],
            true
        );

        if (is_wp_error($result) || $result === false) {
            throw new RuntimeException(
                'Scheduled event cleanup failed.'
            );
        }

        dilitant_fixture_assert_lock();

        dilitant_test_assert_same(
            false,
            wp_next_scheduled('publish_future_post', [$id]),
            'Scheduled publication still exists.'
        );
    }

    private function cleanup_post_categories(int $id): void
    {
        global $wpdb;

        dilitant_fixture_assert_lock();

        wp_delete_object_term_relationships($id, 'category');

        dilitant_fixture_assert_lock();

        $remaining = $this->cleanup_count(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$wpdb->term_relationships} r
                 INNER JOIN {$wpdb->term_taxonomy} tt
                     ON tt.term_taxonomy_id = r.term_taxonomy_id
                 WHERE r.object_id = %d
                   AND tt.taxonomy = 'category'",
                $id
            )
        );

        if ($remaining !== 0) {
            throw new RuntimeException(
                'Post category relationships still exist.'
            );
        }
    }

    private function assert_post_absent(int $id): void
    {
        global $wpdb;

        $this->owned_record('post', $id);

        $queries = [
            'post' => $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$wpdb->posts}
                 WHERE ID = %d OR post_parent = %d",
                $id,
                $id
            ),

            'metadata' => $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$wpdb->postmeta}
                 WHERE post_id = %d",
                $id
            ),

            'relationships' => $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$wpdb->term_relationships}
                 WHERE object_id = %d",
                $id
            ),

            'comments' => $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$wpdb->comments}
                 WHERE comment_post_ID = %d",
                $id
            ),
        ];

        foreach ($queries as $kind => $sql) {
            if ($this->cleanup_count($sql) !== 0) {
                throw new RuntimeException(
                    "Residual post {$kind}: {$id}"
                );
            }
        }

        dilitant_fixture_assert_lock();

        dilitant_test_assert_same(
            false,
            wp_next_scheduled('publish_future_post', [$id]),
            "Fixture post {$id} has a scheduled event."
        );
    }

    private function resolve_cleanup_category(int $id): ?WP_Term
    {
        global $wpdb;

        $record = $this->owned_record('category', $id);
        $taxonomy_id = $record['taxonomy_id'];

        $rows = $this->cleanup_query(
            $wpdb->prepare(
                "SELECT t.term_id, t.name, t.slug, t.term_group,
                        tt.term_taxonomy_id, tt.taxonomy,
                        tt.description, tt.parent, tt.count
                 FROM {$wpdb->terms} t
                 LEFT JOIN {$wpdb->term_taxonomy} tt
                     ON tt.term_id = t.term_id
                 WHERE t.term_id = %d
                    OR tt.term_taxonomy_id = %d",
                $id,
                $taxonomy_id
            )
        );

        if ($rows === []) {
            $remaining = $this->cleanup_count(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                     FROM {$wpdb->term_taxonomy}
                     WHERE term_id = %d
                        OR term_taxonomy_id = %d",
                    $id,
                    $taxonomy_id
                )
            );

            if ($remaining !== 0) {
                throw new RuntimeException(
                    'Incomplete category taxonomy.'
                );
            }

            return null;
        }

        if (
            count($rows) !== 1
            || (int) $rows[0]['term_id'] !== $id
            || (int) $rows[0]['term_taxonomy_id'] !== $taxonomy_id
            || $rows[0]['taxonomy'] !== 'category'
        ) {
            throw new RuntimeException(
                'Incomplete or ambiguous category identity.'
            );
        }

        $term = new WP_Term((object) $rows[0]);

        $this->verify_category_ownership($term);

        return $term;
    }

    private function cleanup_category(int $id): void
    {
        global $wpdb;

        $term = $this->resolve_cleanup_category($id);

        if ($term === null) {
            $this->assert_category_absent($id);

            return;
        }

        $objects = $this->cleanup_count(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$wpdb->term_relationships}
                 WHERE term_taxonomy_id = %d",
                (int) $term->term_taxonomy_id
            )
        );

        if ($objects !== 0) {
            throw new RuntimeException(
                'Category still has dependent objects.'
            );
        }

        $children = $this->cleanup_count(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$wpdb->term_taxonomy}
                 WHERE taxonomy = 'category'
                   AND parent = %d",
                $id
            )
        );

        if ($children !== 0) {
            throw new RuntimeException(
                'Category still has children.'
            );
        }

        clean_term_cache($id, 'category');

        dilitant_fixture_assert_lock();

        dilitant_test_assert_same(
            true,
            wp_delete_term($id, 'category'),
            'Category deletion failed.'
        );

        dilitant_fixture_assert_lock();

        $this->assert_category_absent($id);
    }

    private function assert_category_absent(int $id): void
    {
        global $wpdb;

        $record = $this->owned_record('category', $id);
        $taxonomy_id = $record['taxonomy_id'];

        $queries = [
            'term' => $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$wpdb->terms}
                 WHERE term_id = %d",
                $id
            ),

            'taxonomy' => $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$wpdb->term_taxonomy}
                 WHERE term_id = %d
                    OR term_taxonomy_id = %d
                    OR (taxonomy = 'category' AND parent = %d)",
                $id,
                $taxonomy_id,
                $id
            ),

            'metadata' => $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$wpdb->termmeta}
                 WHERE term_id = %d",
                $id
            ),

            'relationships' => $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$wpdb->term_relationships}
                 WHERE term_taxonomy_id = %d",
                $taxonomy_id
            ),
        ];

        foreach ($queries as $kind => $sql) {
            if ($this->cleanup_count($sql) !== 0) {
                throw new RuntimeException(
                    "Residual category {$kind}: {$id}"
                );
            }
        }
    }

    private function verify_cleanup(): void
    {
        global $wpdb;

        $this->verify_environment();
        dilitant_fixture_assert_lock();

        foreach ($this->post_ids as $id) {
            $this->assert_post_absent($id);
        }

        foreach ($this->category_ids as $id) {
            $this->assert_category_absent($id);
        }

        if ($this->root_category !== 0) {
            $remaining = $this->cleanup_count(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                     FROM {$wpdb->terms}
                     WHERE BINARY slug = BINARY %s",
                    self::ROOT_SLUG
                )
            );

            if ($remaining !== 0) {
                throw new RuntimeException(
                    'Fixture root category still exists.'
                );
            }
        }
    }

    private function reset_fixture_state(): void
    {
        $this->reset_users();

        $this->post_ids = [];
        $this->category_ids = [];
        $this->owned_objects = [];

        $this->base_created = false;
        $this->query_created = false;

        foreach (self::fixture_fields() as $field) {
            $this->{$field} = 0;
        }
    }
}