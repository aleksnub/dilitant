<?php

declare(strict_types=1);

function hfur_repair_manifest(string $run_id): array
{
    hfur_guard();

    $data = hfur_require_manifest(
        $run_id,
        ['CLEANUP_FAILED']
    );

    hfur_require(
        count($data['objects']) === 1
        && $data['snapshot'] === null,
        'Unexpected repair manifest structure.'
    );

    $record = $data['objects'][0];

    hfur_validate_user_record(
        $record,
        $run_id
    );

    hfur_require(
        $record['identity']['slot'] === 'administrator',
        'Unexpected repair user slot.'
    );

    return $data;
}

function hfur_repair_require_ids(
    int $original_id,
    int $other_id
): void {
    hfur_require(
        $original_id > 0
        && $other_id > 0
        && $original_id !== $other_id,
        'Invalid controlled repair identifiers.'
    );

    hfur_require(
        $original_id !== hfur_protected_id(),
        'Original fixture user is protected.'
    );
}

function hfur_repair_user_digest(int $id): string
{
    global $wpdb;

    hfur_guard();

    hfur_require(
        $id > 0,
        'Invalid user digest identifier.'
    );

    $users = hfur_query(
        $wpdb->prepare(
            "SELECT *
             FROM {$wpdb->users}
             WHERE ID = %d",
            $id
        )
    );

    hfur_require(
        count($users) === 1
        && (int) $users[0]['ID'] === $id,
        'User digest identity unavailable.'
    );

    $metadata = hfur_query(
        $wpdb->prepare(
            "SELECT *
             FROM {$wpdb->usermeta}
             WHERE user_id = %d
             ORDER BY umeta_id",
            $id
        )
    );

    return hash(
        'sha256',
        serialize([
            'user' => $users[0],
            'metadata' => $metadata,
        ])
    );
}

function hfur_repair_require_no_dependencies(
    int $id,
    bool $allow_metadata = false
): void {
    $dependencies = hfur_user_dependencies($id);

    foreach ($dependencies as $name => $count) {
        if ($allow_metadata && $name === 'usermeta') {
            continue;
        }

        hfur_require(
            $count === 0,
            "Repair user has unexpected {$name}."
        );
    }
}

function hfur_repair_candidate_ids(
    array $record,
    string $run_id
): array {
    $rows = hfur_identity_candidates(
        $record,
        $run_id
    );

    $ids = array_map(
        static fn(array $row): int =>
            hfur_id((string) $row['ID']),
        $rows
    );

    sort($ids);

    hfur_require(
        count($ids) === count(array_unique($ids)),
        'Duplicate SQL candidate identifiers.'
    );

    return $ids;
}

function hfur_repair_commit(
    array $previous,
    array $next
): void {
    global $wpdb;

    hfur_guard();

    hfur_require(
        hfur_manifest() === $previous,
        'Manifest changed before controlled repair.'
    );

    hfur_require(
        $previous['schema_version'] === 2
        && $next['schema_version'] === 2
        && $previous['run_id'] === $next['run_id']
        && $previous['state'] === 'CLEANUP_FAILED'
        && $next['state'] === 'CLEANUP_FAILED',
        'Invalid controlled repair transition.'
    );

    hfur_validate_registry(
        $next['objects'],
        $next['run_id'],
        $next['state']
    );

    $option = DilitantHttpFixtureManifest::OPTION;

    $updated = $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->options}
             SET option_value = %s
             WHERE option_name = %s
               AND BINARY option_value = BINARY %s",
            serialize($next),
            $option,
            serialize($previous)
        )
    );

    $error = $wpdb->last_error;

    hfur_guard();

    hfur_require(
        $updated === 1 && $error === '',
        'Controlled manifest repair failed.'
    );

    wp_cache_delete($option, 'options');
    wp_cache_delete('alloptions', 'options');
    wp_cache_delete('notoptions', 'options');

    hfur_require(
        hfur_manifest() === $next,
        'Repaired manifest was not persisted.'
    );

    hfur_guard();
}