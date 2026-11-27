<?php

declare(strict_types=1);

function hfur_validate_identity(
    array $identity,
    string $run_id
): void {
    hfur_run_id($run_id);

    hfur_require_keys($identity, [
        'slot',
        'login',
        'email',
        'nicename',
        'role',
    ]);

    foreach ($identity as $value) {
        hfur_require(
            is_string($value),
            'Invalid user identity field type.'
        );
    }

    $slots = [
        'administrator' => ['administrator', 'administrator'],
        'subscriber' => ['subscriber', 'subscriber'],
        'author_a' => ['author', 'author-a'],
        'author_b' => ['author', 'author-b'],
    ];

    hfur_require(
        isset($slots[$identity['slot']]),
        'Unknown fixture user slot.'
    );

    [$role, $segment] = $slots[$identity['slot']];

    $login = "am-http-{$segment}-{$run_id}";

    hfur_require(
        $identity['login'] === $login
        && $identity['email'] === $login . '@example.invalid'
        && $identity['role'] === $role,
        'Invalid canonical user identity.'
    );

    hfur_require(
        preg_match(
            '/\Aam-user-' . $run_id . '-[0-9a-f]{16}\z/',
            $identity['nicename']
        ) === 1,
        'Invalid canonical user nicename.'
    );
}

function hfur_validate_user_record(
    array $record,
    string $run_id
): void {
    hfur_require_keys($record, [
        'kind',
        'id',
        'taxonomy_id',
        'identity',
    ]);

    hfur_require(
        $record['kind'] === 'user'
        && is_int($record['id'])
        && $record['id'] >= 0
        && $record['taxonomy_id'] === 0
        && is_array($record['identity']),
        'Invalid fixture user record.'
    );

    hfur_validate_identity(
        $record['identity'],
        $run_id
    );
}

function hfur_user_rows(int $id): array
{
    global $wpdb;

    hfur_require(
        $id > 0,
        'Invalid fixture user ID.'
    );

    return hfur_query(
        $wpdb->prepare(
            "SELECT ID, user_login, user_email, user_nicename
             FROM {$wpdb->users}
             WHERE ID = %d",
            $id
        )
    );
}

function hfur_user_row(int $id): array
{
    $rows = hfur_user_rows($id);

    hfur_require(
        count($rows) === 1
        && (int) $rows[0]['ID'] === $id,
        'Fixture user row unavailable.'
    );

    return $rows[0];
}

function hfur_protected_user(): array
{
    global $wpdb;

    $rows = hfur_query(
        $wpdb->prepare(
            "SELECT ID, user_login, user_email, user_nicename
             FROM {$wpdb->users}
             WHERE user_login = %s",
            'testadmin'
        )
    );

    hfur_require(
        count($rows) === 1
        && $rows[0]['user_login'] === 'testadmin'
        && (int) $rows[0]['ID'] > 0,
        'Protected test administrator unavailable.'
    );

    return $rows[0];
}

function hfur_protected_id(): int
{
    return (int) hfur_protected_user()['ID'];
}

function hfur_identity_candidates(
    array $record,
    string $run_id
): array {
    global $wpdb;

    hfur_validate_user_record(
        $record,
        $run_id
    );

    $identity = $record['identity'];
    $id = $record['id'];

    return hfur_query(
        $wpdb->prepare(
            "SELECT ID, user_login, user_email, user_nicename
             FROM {$wpdb->users}
             WHERE user_login = %s
                OR user_email = %s
                OR user_nicename = %s
                OR (%d > 0 AND ID = %d)
             ORDER BY ID",
            $identity['login'],
            $identity['email'],
            $identity['nicename'],
            $id,
            $id
        )
    );
}

function hfur_verify_user_identity(
    array $row,
    array $record,
    string $run_id
): int {
    hfur_validate_user_record(
        $record,
        $run_id
    );

    $identity = $record['identity'];
    $id = (int) ($row['ID'] ?? 0);

    hfur_require(
        $id > 0
        && $id !== hfur_protected_id()
        && (
            $record['id'] === 0
            || $record['id'] === $id
        )
        && ($row['user_login'] ?? null)
            === $identity['login']
        && ($row['user_email'] ?? null)
            === $identity['email']
        && ($row['user_nicename'] ?? null)
            === $identity['nicename'],
        'Independent fixture user identity mismatch.'
    );

    return $id;
}

function hfur_require_owned_user(
    array $record,
    string $run_id
): int {
    $rows = hfur_identity_candidates(
        $record,
        $run_id
    );

    hfur_require(
        count($rows) === 1,
        'Independent user ownership is ambiguous.'
    );

    return hfur_verify_user_identity(
        $rows[0],
        $record,
        $run_id
    );
}

function hfur_user_dependencies(int $id): array
{
    global $wpdb;

    hfur_require(
        $id > 0,
        'Invalid dependency user ID.'
    );

    $queries = [
        'posts' =>
            "SELECT COUNT(*) AS n
             FROM {$wpdb->posts}
             WHERE post_author = %d",

        'links' =>
            "SELECT COUNT(*) AS n
             FROM {$wpdb->links}
             WHERE link_owner = %d",

        'comments' =>
            "SELECT COUNT(*) AS n
             FROM {$wpdb->comments}
             WHERE user_id = %d",

        'usermeta' =>
            "SELECT COUNT(*) AS n
             FROM {$wpdb->usermeta}
             WHERE user_id = %d",

        'sessions' =>
            "SELECT COUNT(*) AS n
             FROM {$wpdb->usermeta}
             WHERE user_id = %d
               AND meta_key = 'session_tokens'",

        'application_passwords' =>
            "SELECT COUNT(*) AS n
             FROM {$wpdb->usermeta}
             WHERE user_id = %d
               AND meta_key = '_application_passwords'",
    ];

    $result = [];

    foreach ($queries as $name => $sql) {
        $result[$name] = hfur_count(
            $wpdb->prepare($sql, $id)
        );
    }

    return $result;
}

function hfur_assert_user_absent(
    array $record,
    string $run_id
): void {
    hfur_validate_user_record(
        $record,
        $run_id
    );

    hfur_require(
        hfur_identity_candidates(
            $record,
            $run_id
        ) === [],
        'Residual fixture user identity.'
    );

    $id = $record['id'];

    if ($id === 0) {
        return;
    }

    hfur_require(
        $id !== hfur_protected_id(),
        'Protected user cannot be a removed fixture.'
    );

    foreach (hfur_user_dependencies($id) as $name => $count) {
        hfur_require(
            $count === 0,
            "Residual user dependency: {$name}."
        );
    }
}

function hfur_probe(string $run_id): array
{
    global $wpdb;

    hfur_run_id($run_id);

    $rows = hfur_query(
        $wpdb->prepare(
            "SELECT ID, user_login, user_email, user_nicename
             FROM {$wpdb->users}
             WHERE user_login LIKE %s
                OR user_email LIKE %s
                OR user_nicename LIKE %s
             ORDER BY ID",
            'am-http-%-' . $run_id,
            'am-http-%-' . $run_id . '@example.invalid',
            'am-user-' . $run_id . '-%'
        )
    );

    $protected_id = hfur_protected_id();

    $result = [
        'run_id' => $run_id,
        'user_ids' => [],
        'users' => [],
    ];

    foreach ($rows as $row) {
        $id = (int) $row['ID'];

        hfur_require(
            $id > 0 && $id !== $protected_id,
            'Protected or invalid user in fixture probe.'
        );

        $result['user_ids'][] = $id;

        $result['users'][] = [
            'id' => $id,
            'login' => $row['user_login'],
            'email' => $row['user_email'],
            'nicename' => $row['user_nicename'],
            'dependencies' => hfur_user_dependencies($id),
        ];
    }

    hfur_guard();

    return $result;
}