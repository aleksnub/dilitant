<?php

declare(strict_types=1);

function hfur_verify_duplicate_user(
    array $record,
    string $run_id,
    int $duplicate_id
): array {
    hfur_guard();

    hfur_require(
        $duplicate_id > 0
        && $duplicate_id !== hfur_protected_id()
        && $duplicate_id !== get_current_user_id(),
        'Protected or active duplicate user.'
    );

    $row = hfur_user_row($duplicate_id);

    $login = 'hfur-duplicate-' . $run_id;

    hfur_require(
        $row['user_login'] === $login
        && $row['user_nicename'] === $login
        && $row['user_email']
            === $record['identity']['email'],
        'Duplicate repair identity mismatch.'
    );

    hfur_repair_require_no_dependencies(
        $duplicate_id
    );

    return $row;
}

function hfur_delete_duplicate_user(
    array $row
): void {
    global $wpdb;

    hfur_guard();

    $id = hfur_id((string) $row['ID']);

    hfur_require(
        $id !== hfur_protected_id()
        && $id !== get_current_user_id(),
        'Protected or active duplicate deletion rejected.'
    );

    hfur_require(
        hfur_user_row($id) === $row,
        'Duplicate user changed before deletion.'
    );

    hfur_repair_require_no_dependencies($id);

    $deleted = $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->users}
             WHERE ID = %d
               AND BINARY user_login = BINARY %s
               AND BINARY user_email = BINARY %s
               AND BINARY user_nicename = BINARY %s",
            $id,
            $row['user_login'],
            $row['user_email'],
            $row['user_nicename']
        )
    );

    $error = $wpdb->last_error;

    hfur_guard();

    hfur_require(
        $deleted === 1 && $error === '',
        'Controlled duplicate deletion failed.'
    );

    wp_cache_delete($id, 'users');

    wp_cache_delete(
        $row['user_login'],
        'userlogins'
    );

    wp_cache_delete(
        $row['user_email'],
        'useremail'
    );

    wp_cache_delete(
        $row['user_nicename'],
        'userslugs'
    );

    wp_cache_delete($id, 'user_meta');

    hfur_require(
        hfur_user_rows($id) === [],
        'Duplicate user survived controlled deletion.'
    );

    hfur_repair_require_no_dependencies($id);

    hfur_guard();
}

function hfur_repair_ambiguity(
    string $run_id,
    int $id,
    int $duplicate_id
): void {
    hfur_guard();

    hfur_repair_require_ids(
        $id,
        $duplicate_id
    );

    $manifest = hfur_repair_manifest($run_id);
    $record = $manifest['objects'][0];

    hfur_require(
        $record['id'] === 0,
        'Ambiguity repair requires a pending intent.'
    );

    $expected_ids = [
        $id,
        $duplicate_id,
    ];

    sort($expected_ids);

    hfur_require(
        hfur_repair_candidate_ids(
            $record,
            $run_id
        ) === $expected_ids,
        'Unexpected ambiguity candidate set.'
    );

    $original = hfur_user_row($id);

    hfur_require(
        hfur_verify_user_identity(
            $original,
            $record,
            $run_id
        ) === $id,
        'Original ambiguity user mismatch.'
    );

    hfur_repair_require_no_dependencies(
        $id,
        true
    );

    $duplicate = hfur_verify_duplicate_user(
        $record,
        $run_id,
        $duplicate_id
    );

    $protected_id = hfur_protected_id();

    $protected_digest = hfur_repair_user_digest(
        $protected_id
    );

    $original_digest = hfur_repair_user_digest($id);

    hfur_require(
        hfur_manifest() === $manifest,
        'Manifest changed before ambiguity repair.'
    );

    hfur_delete_duplicate_user($duplicate);

    hfur_require(
        hfur_manifest() === $manifest,
        'Ambiguity repair modified manifest.'
    );

    hfur_require(
        hfur_repair_candidate_ids(
            $record,
            $run_id
        ) === [$id],
        'Ambiguity was not eliminated.'
    );

    hfur_require(
        hfur_require_owned_user(
            $record,
            $run_id
        ) === $id,
        'Original user could not be resolved after repair.'
    );

    hfur_require(
        hfur_repair_user_digest($id)
            === $original_digest,
        'Original user changed during ambiguity repair.'
    );

    hfur_require(
        hfur_repair_user_digest($protected_id)
            === $protected_digest,
        'Protected user changed during ambiguity repair.'
    );

    hfur_guard();
}