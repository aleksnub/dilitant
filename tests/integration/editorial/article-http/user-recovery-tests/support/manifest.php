<?php

declare(strict_types=1);

function hfur_manifest(): array
{
    global $wpdb;

    $rows = hfur_query(
        $wpdb->prepare(
            "SELECT option_value
             FROM {$wpdb->options}
             WHERE option_name = %s",
            DilitantHttpFixtureManifest::OPTION
        )
    );

    hfur_require(
        count($rows) === 1
        && is_string($rows[0]['option_value']),
        'Persistent User Recovery manifest unavailable.'
    );

    $raw = $rows[0]['option_value'];

    $data = unserialize(
        $raw,
        ['allowed_classes' => false]
    );

    hfur_require(
        is_array($data)
        && serialize($data) === $raw,
        'Invalid persistent manifest encoding.'
    );

    hfur_require_keys($data, [
        'schema_version',
        'run_id',
        'database_name',
        'table_prefix',
        'container_origin',
        'created_at',
        'state',
        'objects',
        'snapshot',
    ]);

    hfur_require(
        $data['schema_version'] === 2
        && $data['database_name'] === DILITANT_TEST_DB_NAME
        && $data['table_prefix'] === DILITANT_TEST_TABLE_PREFIX,
        'Invalid Manifest V2 identity.'
    );

    hfur_require(
        is_string($data['run_id'])
        && is_string($data['container_origin'])
        && is_string($data['created_at'])
        && is_string($data['state']),
        'Invalid Manifest V2 metadata.'
    );

    hfur_run_id($data['run_id']);

    hfur_require(
        in_array(
            $data['state'],
            [
                'CREATING',
                'READY',
                'TESTING',
                'CLEANING',
                'CLEANUP_FAILED',
                'CLEANED',
            ],
            true
        ),
        'Invalid User Recovery manifest state.'
    );

    hfur_require(
        is_array($data['objects'])
        && array_is_list($data['objects'])
        && count($data['objects']) <= 18
        && (
            $data['snapshot'] === null
            || is_array($data['snapshot'])
        ),
        'Invalid Manifest V2 registry.'
    );

    hfur_validate_registry(
        $data['objects'],
        $data['run_id'],
        $data['state']
    );

    hfur_guard();

    return $data;
}

function hfur_validate_registry(
    array $objects,
    string $run_id,
    string $state
): void {
    $counts = [
        'user' => 0,
        'category' => 0,
        'post' => 0,
    ];

    $limits = [
        'user' => 4,
        'category' => 4,
        'post' => 10,
    ];

    $order = [
        'user' => 0,
        'category' => 1,
        'post' => 2,
    ];

    $slots = [
        'administrator',
        'subscriber',
        'author_a',
        'author_b',
    ];

    $ids = [];
    $identities = [];
    $pending = 0;
    $previous_order = 0;

    foreach ($objects as $index => $object) {
        hfur_require(
            is_array($object),
            'Invalid manifest object.'
        );

        hfur_require_keys($object, [
            'kind',
            'id',
            'taxonomy_id',
            'identity',
        ]);

        $kind = $object['kind'];

        hfur_require(
            is_string($kind)
            && isset($counts[$kind])
            && is_int($object['id'])
            && $object['id'] >= 0
            && is_int($object['taxonomy_id'])
            && $object['taxonomy_id'] >= 0
            && is_array($object['identity']),
            'Invalid manifest object fields.'
        );

        hfur_require(
            $order[$kind] >= $previous_order,
            'Unexpected fixture registration order.'
        );

        $previous_order = $order[$kind];

        ++$counts[$kind];

        hfur_require(
            $counts[$kind] <= $limits[$kind],
            'Fixture registry limit exceeded.'
        );

        if ($kind !== 'user') {
            hfur_require(
                $counts['user'] === 4,
                'Category or post registered before users.'
            );
        }

        if ($kind === 'post') {
            hfur_require(
                $counts['category'] === 4,
                'Post registered before categories.'
            );
        }

        if ($object['id'] === 0) {
            ++$pending;

            hfur_require(
                $pending === 1
                && $index === array_key_last($objects)
                && $object['taxonomy_id'] === 0,
                'Invalid pending intent position.'
            );
        } else {
            $key = $kind . ':' . $object['id'];

            hfur_require(
                !isset($ids[$key]),
                'Duplicate manifest object ID.'
            );

            $ids[$key] = true;
        }

        if ($kind === 'user') {
            hfur_validate_user_record(
                $object,
                $run_id
            );

            $identity = $object['identity'];

            hfur_require(
                $identity['slot']
                    === $slots[$counts['user'] - 1],
                'Unexpected fixture user slot order.'
            );

            foreach (['login', 'email', 'nicename'] as $field) {
                $key = $field . ':' . $identity[$field];

                hfur_require(
                    !isset($identities[$key]),
                    'Duplicate fixture user identity.'
                );

                $identities[$key] = true;
            }
        } elseif ($kind === 'post') {
            hfur_require(
                $object['taxonomy_id'] === 0,
                'Unexpected post taxonomy ID.'
            );
        } elseif ($object['id'] > 0) {
            hfur_require(
                $object['taxonomy_id'] > 0,
                'Missing category taxonomy ID.'
            );
        }
    }

    if (in_array(
        $state,
        ['READY', 'TESTING', 'CLEANED'],
        true
    )) {
        hfur_require(
            $pending === 0,
            'Pending intent in stable manifest state.'
        );
    }

    if (in_array($state, ['READY', 'TESTING'], true)) {
        hfur_require(
            $counts === $limits,
            'Incomplete ready fixture registry.'
        );
    }
}

function hfur_require_manifest(
    string $run_id,
    array $states
): array {
    $data = hfur_manifest();

    hfur_require(
        $data['run_id'] === hfur_run_id($run_id)
        && in_array($data['state'], $states, true),
        'Unexpected User Recovery manifest.'
    );

    return $data;
}

function hfur_assert_clean_start(): void
{
    global $wpdb;

    $data = hfur_manifest();

    hfur_require(
        $data['state'] === 'CLEANED',
        'Previous fixture run is unfinished.'
    );

    $probe = hfur_probe($data['run_id']);

    hfur_require(
        $probe['user_ids'] === [],
        'Previous fixture users still exist.'
    );

    foreach ($data['objects'] as $record) {
        if ($record['kind'] === 'user') {
            hfur_assert_user_absent(
                $record,
                $data['run_id']
            );
        }
    }

    hfur_protected_user();

    hfur_require(
        hfur_count(
            "SELECT COUNT(*) AS n
             FROM {$wpdb->usermeta} m
             LEFT JOIN {$wpdb->users} u
                 ON u.ID = m.user_id
             WHERE u.ID IS NULL"
        ) === 0,
        'Initial database contains orphan user metadata.'
    );

    hfur_guard();
}

function hfur_pending_record(string $run_id): array
{
    $data = hfur_require_manifest(
        $run_id,
        ['CREATING', 'CLEANING', 'CLEANUP_FAILED']
    );

    $pending = array_values(array_filter(
        $data['objects'],
        static fn(array $object): bool =>
            $object['id'] === 0
    ));

    hfur_require(
        count($pending) === 1
        && $pending[0]['kind'] === 'user',
        'Expected exactly one pending user intent.'
    );

    hfur_validate_user_record(
        $pending[0],
        $run_id
    );

    return $pending[0];
}

function hfur_registered_user(
    string $run_id,
    string $slot
): array {
    $data = hfur_require_manifest(
        $run_id,
        [
            'CREATING',
            'READY',
            'TESTING',
            'CLEANING',
            'CLEANUP_FAILED',
            'CLEANED',
        ]
    );

    $records = array_values(array_filter(
        $data['objects'],
        static fn(array $record): bool =>
            $record['kind'] === 'user'
            && $record['identity']['slot'] === $slot
    ));

    hfur_require(
        count($records) === 1
        && $records[0]['id'] > 0,
        'Confirmed fixture user unavailable.'
    );

    return $records[0];
}