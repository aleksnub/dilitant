<?php

declare(strict_types=1);

function hf_lu_load_visibility_runner(): void
{
    $base = dirname(__DIR__);
    $editorial = dirname($base);

    require_once $editorial . '/fixtures.php';
    require_once $editorial . '/article-catalog-environment.php';

    require_once $base . '/environment.php';
    require_once $base . '/assertions.php';
    require_once $base . '/routing.php';
    require_once $base . '/public-visibility.php';
    require_once $base . '/password-protection.php';
    require_once $base . '/preview-authorization.php';
    require_once $base . '/runner.php';

    dilitant_visibility_verify_interfaces();

    hf_lu_require(
        defined('DILITANT_VISIBILITY_EXPECTED_TOTAL')
        && DILITANT_VISIBILITY_EXPECTED_TOTAL === 20,
        'Invalid normative visibility scenario count.'
    );
}

function hf_lu_runner_database_snapshot(): array
{
    global $wpdb;

    $tables = [
        'posts' => [
            $wpdb->posts,
            'ID, post_author, post_type, post_parent',
        ],
        'postmeta' => [
            $wpdb->postmeta,
            'meta_id, post_id, meta_key',
        ],
        'terms' => [
            $wpdb->terms,
            'term_id, name, slug',
        ],
        'termmeta' => [
            $wpdb->termmeta,
            'meta_id, term_id, meta_key',
        ],
        'taxonomy' => [
            $wpdb->term_taxonomy,
            'term_taxonomy_id, term_id, taxonomy, parent',
        ],
        'relationships' => [
            $wpdb->term_relationships,
            'object_id, term_taxonomy_id',
        ],
        'comments' => [
            $wpdb->comments,
            'comment_ID, comment_post_ID, user_id',
        ],
        'commentmeta' => [
            $wpdb->commentmeta,
            'meta_id, comment_id, meta_key',
        ],
        'users' => [
            $wpdb->users,
            'ID, user_login, user_email, user_nicename',
        ],
        'usermeta' => [
            $wpdb->usermeta,
            'umeta_id, user_id, meta_key',
        ],
        'links' => [
            $wpdb->links,
            'link_id, link_owner, link_name',
        ],
    ];

    $snapshot = [];

    foreach ($tables as $name => [$table, $columns]) {
        $rows = hf_lu_query(
            "SELECT {$columns}
             FROM {$table}
             ORDER BY {$columns}"
        );

        $snapshot[$name] = [
            'count' => count($rows),
            'digest' => hash(
                'sha256',
                serialize($rows)
            ),
        ];
    }

    return $snapshot;
}

function hf_lu_runner_cleanup_context(): ?array
{
    foreach (debug_backtrace(0) as $frame) {
        if (
            ($frame['function'] ?? null)
                !== 'dilitant_visibility_finalize_environment'
        ) {
            continue;
        }

        $arguments = $frame['args'] ?? [];

        $fixtures = $arguments[0] ?? null;
        $users = $arguments[1] ?? null;
        $previous_user_id = $arguments[2] ?? null;

        hf_lu_require(
            $fixtures instanceof DilitantArticleFixtures
            && $users instanceof DilitantVisibilityUsers
            && is_int($previous_user_id),
            'Invalid runner cleanup context.'
        );

        return [
            'fixtures' => $fixtures,
            'users' => $users,
            'previous_user_id' => $previous_user_id,
        ];
    }

    return null;
}

function hf_lu_runner_verify_post_exists(int $id): void
{
    global $wpdb;

    hf_lu_require(
        hf_lu_count(
            $wpdb->prepare(
                "SELECT COUNT(*) AS n
                 FROM {$wpdb->posts}
                 WHERE ID = %d",
                $id
            )
        ) === 1,
        'Blocked fixture post was removed.'
    );
}

function hf_lu_runner_verify_posts_absent(array $ids): void
{
    global $wpdb;

    foreach ($ids as $id) {
        hf_lu_require(
            is_int($id) && $id > 0,
            'Invalid runner fixture post identifier.'
        );

        foreach ([
            [$wpdb->posts, 'ID'],
            [$wpdb->posts, 'post_parent'],
            [$wpdb->postmeta, 'post_id'],
            [$wpdb->term_relationships, 'object_id'],
            [$wpdb->comments, 'comment_post_ID'],
        ] as [$table, $column]) {
            hf_lu_require(
                hf_lu_count(
                    $wpdb->prepare(
                        "SELECT COUNT(*) AS n
                         FROM {$table}
                         WHERE {$column} = %d",
                        $id
                    )
                ) === 0,
                'Residual runner fixture post records.'
            );
        }

        hf_lu_require(
            wp_next_scheduled(
                'publish_future_post',
                [$id]
            ) === false,
            'Residual fixture scheduled publication.'
        );
    }
}

function hf_lu_runner_failure(): void
{
    dilitant_http_require_bootstrap();
    dilitant_visibility_verify_environment();
    dilitant_fixture_assert_lock();

    hf_lu_load_visibility_runner();

    $baseline = hf_lu_runner_database_snapshot();

    $context = null;
    $injected = false;

    $blocked_post_id = 0;
    $post_ids = [];
    $user_ids = [];
    $user_rows = [];

    $user_deletion_calls = [];

    $delete_user_hook = static function (
        int $id
    ) use (&$user_deletion_calls): void {
        $user_deletion_calls[] = $id;
    };

    $failure_hook = static function (
        $delete,
        WP_Post $post,
        bool $force_delete
    ) use (
        &$context,
        &$injected,
        &$blocked_post_id,
        &$post_ids,
        &$user_ids,
        &$user_rows
    ) {
        if ($injected || !$force_delete) {
            return $delete;
        }

        $candidate = hf_lu_runner_cleanup_context();

        if ($candidate === null) {
            return $delete;
        }

        $fixtures = $candidate['fixtures'];
        $users = $candidate['users'];

        if ((int) $post->ID !== $fixtures->root_post) {
            return $delete;
        }

        dilitant_fixture_assert_lock();

        $context = $candidate;

        $blocked_post_id = (int) $post->ID;
        $post_ids = $fixtures->all_post_ids();

        $user_ids = [
            $users->subscriber,
            $users->author,
        ];

        hf_lu_require(
            count($post_ids) === 10
            && count(array_unique($post_ids)) === 10
            && in_array(
                $blocked_post_id,
                $post_ids,
                true
            ),
            'Unexpected runner fixture registry.'
        );

        hf_lu_require(
            $user_ids[0] > 0
            && $user_ids[1] > 0
            && $user_ids[0] !== $user_ids[1]
            && !in_array(
                $users->administrator,
                $user_ids,
                true
            ),
            'Unexpected runner user registry.'
        );

        foreach ($user_ids as $id) {
            $user_rows[$id] = hf_lu_user_row($id);
        }

        $injected = true;

        echo "HF-LU-14: Fixture deletion failure injected\n";

        return false;
    };

    $exit_code = null;
    $output = '';

    add_action(
        'delete_user',
        $delete_user_hook,
        10,
        1
    );

    add_filter(
        'pre_delete_post',
        $failure_hook,
        PHP_INT_MAX,
        3
    );

    try {
        ob_start();

        try {
            $exit_code = dilitant_visibility_run();
        } finally {
            $output = (string) ob_get_clean();

            echo $output;

            remove_filter(
                'pre_delete_post',
                $failure_hook,
                PHP_INT_MAX
            );

            remove_action(
                'delete_user',
                $delete_user_hook,
                10
            );
        }

        hf_lu_require(
            $injected && $context !== null,
            'Fixture cleanup failure was not injected.'
        );

        hf_lu_require(
            $exit_code === 1,
            'AM-D03 accepted failed fixture cleanup.'
        );

        hf_lu_require(
            str_contains(
                $output,
                'Fixture initialization: PASS'
            ),
            'AM-D03 fixture initialization did not succeed.'
        );

        hf_lu_require(
            str_contains($output, 'Passed: 20')
            && str_contains($output, 'Failed: 0'),
            'AM-D03 scenarios did not complete successfully.'
        );

        hf_lu_require(
            str_contains(
                $output,
                'Fixture cleanup: FAIL'
            ),
            'Fixture cleanup failure was not reported.'
        );

        hf_lu_require(
            str_contains(
                $output,
                'User cleanup: FAIL'
            ),
            'Blocked user cleanup was not reported.'
        );

        hf_lu_require(
            str_contains(
                $output,
                'FAIL: EDC-01 AM-D03 VISIBILITY'
            ),
            'Negative AM-D03 result was not reported.'
        );

        hf_lu_require(
            !str_contains(
                $output,
                'PASS: visibility user cleanup'
            ),
            'User cleanup was unexpectedly completed.'
        );

        hf_lu_require(
            $user_deletion_calls === [],
            'WordPress user deletion was invoked.'
        );

        hf_lu_runner_verify_post_exists(
            $blocked_post_id
        );

        foreach ($user_ids as $id) {
            hf_lu_require(
                hf_lu_user_row($id) === $user_rows[$id],
                'Legacy user identity was changed or removed.'
            );
        }

        $users = $context['users'];

        hf_lu_require(
            [
                $users->subscriber,
                $users->author,
            ] === $user_ids,
            'Legacy user registry was modified.'
        );

        echo "PASS: AM-D03 returned exit code 1\n";
        echo "PASS: Fixture cleanup failure reported\n";
        echo "PASS: User deletion was not invoked\n";
        echo "PASS: Both Legacy Users preserved\n";

    } finally {
        remove_filter(
            'pre_delete_post',
            $failure_hook,
            PHP_INT_MAX
        );

        remove_action(
            'delete_user',
            $delete_user_hook,
            10
        );

        if ($context !== null) {
            $recovery = dilitant_visibility_finalize_environment(
                $context['fixtures'],
                $context['users'],
                $context['previous_user_id']
            );

            hf_lu_require(
                $recovery === [
                    'user_restoration_status' => 'PASS',
                    'fixture_cleanup_status' => 'PASS',
                    'user_cleanup_status' => 'PASS',
                    'execution_error' => false,
                ],
                'Controlled runner recovery failed.'
            );

            echo "PASS: Controlled runner recovery\n";
        }
    }

    hf_lu_require(
        $context !== null,
        'Runner recovery context unavailable.'
    );

    foreach ($user_ids as $id) {
        hf_lu_assert_user_absent($id);
    }

    hf_lu_runner_verify_posts_absent($post_ids);

    hf_lu_require(
        hf_lu_runner_database_snapshot() === $baseline,
        'Runner failure changed database identities.'
    );

    dilitant_fixture_assert_lock();

    echo "PASS: No residual Legacy Users\n";
    echo "PASS: No residual fixture posts\n";
    echo "PASS: Independent runner database audit\n";
}