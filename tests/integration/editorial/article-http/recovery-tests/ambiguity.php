<?php

declare(strict_types=1);

function hf09_create_duplicate_post(
    int $original_id,
    array $identity
): int {
    $original = hf09_post_row($original_id);

    hf09_verify_post_identity($original, $identity);

    dilitant_fixture_assert_lock();

    $id = wp_insert_post([
        'post_title' => $identity['title'],
        'post_content' => $original['post_content'],
        'post_type' => $identity['type'],
        'post_author' => $identity['author_id'],
        'post_status' => 'draft',
        'comment_status' => 'closed',
        'ping_status' => 'closed',
    ], true);

    hf09_require(
        !is_wp_error($id)
        && is_int($id)
        && $id > 0
        && $id !== $original_id,
        'Duplicate test post creation failed.'
    );

    dilitant_fixture_assert_lock();

    hf09_verify_post_identity(
        hf09_post_row($id),
        $identity
    );

    return $id;
}

function hf09_repair_ambiguity(
    string $run_id,
    int $original_id,
    int $duplicate_id
): void {
    global $wpdb;

    hf09_require(
        hf09_manifest()['state'] === 'CLEANUP_FAILED',
        'Ambiguity repair requires CLEANUP_FAILED.'
    );

    hf09_require(
        $original_id !== $duplicate_id,
        'Invalid duplicate identifiers.'
    );

    $identity = hf09_pending($run_id, 'post');

    $original = hf09_post_row($original_id);
    $duplicate = hf09_post_row($duplicate_id);

    hf09_verify_post_identity($original, $identity);
    hf09_verify_post_identity($duplicate, $identity);

    hf09_require(
        $duplicate['post_status'] === 'draft'
        && $duplicate['post_content'] === $original['post_content']
        && (int) $duplicate['post_parent'] === 0,
        'Duplicate object identity changed.'
    );

    $children = hf09_count(
        $wpdb->prepare(
            "SELECT COUNT(*) AS n
             FROM {$wpdb->posts}
             WHERE post_parent = %d",
            $duplicate_id
        )
    );

    $comments = hf09_count(
        $wpdb->prepare(
            "SELECT COUNT(*) AS n
             FROM {$wpdb->comments}
             WHERE comment_post_ID = %d",
            $duplicate_id
        )
    );

    hf09_require(
        $children === 0 && $comments === 0,
        'Duplicate test object has unexpected dependencies.'
    );

    clean_post_cache($duplicate_id);

    dilitant_fixture_assert_lock();

    $deleted = wp_delete_post($duplicate_id, true);

    hf09_require(
        $deleted instanceof WP_Post,
        'Duplicate test object deletion failed.'
    );

    dilitant_fixture_assert_lock();

    hf09_require(
        hf09_count(
            $wpdb->prepare(
                "SELECT COUNT(*) AS n
                 FROM {$wpdb->posts}
                 WHERE ID = %d",
                $duplicate_id
            )
        ) === 0,
        'Duplicate test object still exists.'
    );

    hf09_verify_post_identity(
        hf09_post_row($original_id),
        $identity
    );
}

function hf09_create_partial_category(array $identity): int
{
    global $wpdb;

    dilitant_fixture_assert_lock();

    $affected = $wpdb->insert(
        $wpdb->terms,
        [
            'name' => $identity['name'],
            'slug' => $identity['slug'],
            'term_group' => 0,
        ],
        ['%s', '%s', '%d']
    );

    $id = (int) $wpdb->insert_id;

    hf09_require(
        $wpdb->last_error === ''
        && $affected === 1
        && $id > 0,
        'Partial category creation failed.'
    );

    dilitant_fixture_assert_lock();

    return $id;
}

function hf09_repair_partial_category(
    string $run_id,
    int $id
): void {
    global $wpdb;

    hf09_require(
        hf09_manifest()['state'] === 'CLEANUP_FAILED',
        'Partial category repair requires CLEANUP_FAILED.'
    );

    $identity = hf09_pending($run_id, 'category');

    $rows = hf09_query(
        $wpdb->prepare(
            "SELECT term_id, name, slug, term_group
             FROM {$wpdb->terms}
             WHERE term_id = %d",
            $id
        )
    );

    hf09_require(
        count($rows) === 1
        && $rows[0]['name'] === $identity['name']
        && $rows[0]['slug'] === $identity['slug']
        && (int) $rows[0]['term_group'] === 0,
        'Partial category identity mismatch.'
    );

    $taxonomy = hf09_count(
        $wpdb->prepare(
            "SELECT COUNT(*) AS n
             FROM {$wpdb->term_taxonomy}
             WHERE term_id = %d",
            $id
        )
    );

    $metadata = hf09_count(
        $wpdb->prepare(
            "SELECT COUNT(*) AS n
             FROM {$wpdb->termmeta}
             WHERE term_id = %d",
            $id
        )
    );

    hf09_require(
        $taxonomy === 0 && $metadata === 0,
        'Partial category has unexpected dependencies.'
    );

    dilitant_fixture_assert_lock();

    $affected = $wpdb->delete(
        $wpdb->terms,
        [
            'term_id' => $id,
            'name' => $identity['name'],
            'slug' => $identity['slug'],
        ],
        ['%d', '%s', '%s']
    );

    hf09_require(
        $wpdb->last_error === ''
        && $affected === 1,
        'Partial category repair failed.'
    );

    dilitant_fixture_assert_lock();

    hf09_require(
        hf09_count(
            $wpdb->prepare(
                "SELECT COUNT(*) AS n
                 FROM {$wpdb->terms}
                 WHERE term_id = %d",
                $id
            )
        ) === 0,
        'Partial category still exists.'
    );
}

function hf09_arm_sql_failure(string $run_id): void
{
    global $wpdb;

    hf09_pending($run_id, 'post');

    $wpdb->suppress_errors(true);

    add_filter(
        'query',
        static function (string $sql): string {
            static $injected = false;

            if (
                !$injected
                && str_contains($sql, 'LOCATE(BINARY')
                && str_contains($sql, 'post_content')
            ) {
                $injected = true;

                fwrite(
                    STDERR,
                    "HF09 SQL failure injected\n"
                );

                return 'SELECT FROM';
            }

            return $sql;
        }
    );
}