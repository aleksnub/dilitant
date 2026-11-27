<?php

declare(strict_types=1);

function hfur_create_ambiguous_user(
    array $record,
    string $run_id,
    int $original_id
): int {
    global $wpdb;

    hfur_guard();

    hfur_require(
        $record['id'] === 0,
        'Ambiguity injection requires a pending user.'
    );

    hfur_require(
        hfur_require_owned_user(
            $record,
            $run_id
        ) === $original_id,
        'Original user identity mismatch.'
    );

    $identity = $record['identity'];

    $login = 'hfur-duplicate-' . $run_id;
    $nicename = $login;

    $existing = hfur_query(
        $wpdb->prepare(
            "SELECT ID
             FROM {$wpdb->users}
             WHERE user_login = %s
                OR user_nicename = %s",
            $login,
            $nicename
        )
    );

    hfur_require(
        $existing === [],
        'Ambiguity fixture already exists.'
    );

    $password = bin2hex(random_bytes(32));

    try {
        $inserted = $wpdb->insert(
            $wpdb->users,
            [
                'user_login' => $login,
                'user_pass' => wp_hash_password($password),
                'user_nicename' => $nicename,
                'user_email' => $identity['email'],
                'user_url' => '',
                'user_registered' => gmdate('Y-m-d H:i:s'),
                'user_activation_key' => '',
                'user_status' => 0,
                'display_name' => $login,
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
            ]
        );

        $error = $wpdb->last_error;
        $duplicate_id = (int) $wpdb->insert_id;

    } finally {
        unset($password);
    }

    hfur_guard();

    hfur_require(
        $inserted === 1
        && $error === ''
        && $duplicate_id > 0
        && $duplicate_id !== $original_id
        && $duplicate_id !== hfur_protected_id(),
        'Ambiguity fixture insertion failed.'
    );

    $duplicate = hfur_user_row($duplicate_id);

    hfur_require(
        $duplicate['user_login'] === $login
        && $duplicate['user_nicename'] === $nicename
        && $duplicate['user_email'] === $identity['email'],
        'Ambiguity fixture identity mismatch.'
    );

    foreach (
        hfur_user_dependencies($duplicate_id)
        as $name => $count
    ) {
        hfur_require(
            $count === 0,
            "Unexpected ambiguity fixture dependency: {$name}."
        );
    }

    $candidates = hfur_identity_candidates(
        $record,
        $run_id
    );

    $ids = array_map(
        static fn(array $row): int => (int) $row['ID'],
        $candidates
    );

    $expected = [
        $original_id,
        $duplicate_id,
    ];

    sort($ids);
    sort($expected);

    hfur_require(
        $ids === $expected,
        'Expected exactly two ambiguous user candidates.'
    );

    hfur_guard();

    return $duplicate_id;
}

function hfur_substitute_user_id(
    string $run_id,
    int $original_id
): int {
    global $wpdb;

    hfur_guard();

    $data = hfur_require_manifest(
        $run_id,
        ['CREATING']
    );

    hfur_require(
        count($data['objects']) === 1
        && $data['snapshot'] === null,
        'Unexpected substitution manifest structure.'
    );

    $record = $data['objects'][0];

    hfur_require(
        $record['kind'] === 'user'
        && $record['id'] === $original_id
        && $record['identity']['slot'] === 'administrator',
        'Unexpected substitution target.'
    );

    hfur_require(
        hfur_require_owned_user(
            $record,
            $run_id
        ) === $original_id,
        'Original confirmed user identity mismatch.'
    );

    $protected = hfur_protected_user();
    $substituted_id = (int) $protected['ID'];

    hfur_require(
        $substituted_id !== $original_id,
        'Original user is protected.'
    );

    $next = $data;
    $next['objects'][0]['id'] = $substituted_id;

    $option = DilitantHttpFixtureManifest::OPTION;

    $updated = $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->options}
             SET option_value = %s
             WHERE option_name = %s
               AND BINARY option_value = BINARY %s",
            serialize($next),
            $option,
            serialize($data)
        )
    );

    $error = $wpdb->last_error;

    hfur_guard();

    hfur_require(
        $updated === 1 && $error === '',
        'Controlled manifest substitution failed.'
    );

    wp_cache_delete($option, 'options');
    wp_cache_delete('alloptions', 'options');
    wp_cache_delete('notoptions', 'options');

    hfur_require(
        hfur_manifest() === $next,
        'Substituted manifest was not persisted.'
    );

    hfur_require(
        hfur_protected_user() === $protected,
        'Protected administrator identity changed.'
    );

    hfur_require(
        hfur_verify_user_identity(
            hfur_user_row($original_id),
            $record,
            $run_id
        ) === $original_id,
        'Original fixture user was modified.'
    );

    hfur_guard();

    return $substituted_id;
}