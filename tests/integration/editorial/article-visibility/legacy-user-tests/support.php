<?php

declare(strict_types=1);

function hf_lu_require(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function hf_lu_query(string $sql): array
{
    global $wpdb;

    dilitant_visibility_verify_environment();
    dilitant_fixture_assert_lock();

    $rows = $wpdb->get_results($sql, ARRAY_A);
    $error = $wpdb->last_error;

    hf_lu_require(
        $error === '' && is_array($rows),
        'Legacy user test SQL failed.'
    );

    dilitant_fixture_assert_lock();

    return $rows;
}

function hf_lu_count(string $sql): int
{
    $rows = hf_lu_query($sql);

    hf_lu_require(
        count($rows) === 1
        && isset($rows[0]['n'])
        && ctype_digit((string) $rows[0]['n']),
        'Invalid Legacy User test count.'
    );

    return (int) $rows[0]['n'];
}

function hf_lu_user_row(int $id): array
{
    global $wpdb;

    $rows = hf_lu_query(
        $wpdb->prepare(
            "SELECT ID, user_login, user_email
             FROM {$wpdb->users}
             WHERE ID = %d",
            $id
        )
    );

    hf_lu_require(
        count($rows) === 1,
        'Expected user identity unavailable.'
    );

    return $rows[0];
}

function hf_lu_assert_user_absent(int $id): void
{
    global $wpdb;

    foreach ([
        [$wpdb->users, 'ID'],
        [$wpdb->usermeta, 'user_id'],
        [$wpdb->posts, 'post_author'],
        [$wpdb->links, 'link_owner'],
        [$wpdb->comments, 'user_id'],
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
            'Residual Legacy User database records.'
        );
    }
}

function hf_lu_expect_rejection(
    callable $operation,
    string $diagnostic
): void {
    try {
        $operation();
    } catch (Throwable $error) {
        hf_lu_require(
            str_contains(
                $error->getMessage(),
                $diagnostic
            ),
            'Unexpected rejection: '
            . $error->getMessage()
        );

        return;
    }

    throw new RuntimeException(
        'Expected operation rejection.'
    );
}

function hf_lu_with_users(callable $operation): void
{
    $users = new DilitantVisibilityUsers();

    $previous_user_id = get_current_user_id();

    try {
        $users->create();

        $ids = [
            $users->subscriber,
            $users->author,
        ];

        $administrator = hf_lu_user_row(
            $users->administrator
        );

        $operation($users);

    } finally {
        wp_set_current_user($previous_user_id);

        hf_lu_require(
            get_current_user_id() === $previous_user_id,
            'Legacy test user context restoration failed.'
        );

        $users->cleanup();
    }

    foreach ($ids as $id) {
        hf_lu_assert_user_absent($id);
    }

    hf_lu_require(
        hf_lu_user_row($users->administrator)
            === $administrator,
        'Protected administrator identity changed.'
    );
}

function hf_lu_run_case(
    string $id,
    string $name,
    callable $operation
): void {
    echo PHP_EOL;
    echo "=== {$id} {$name} ===\n";

    $operation();

    echo "PASS: {$id} {$name}\n";
}