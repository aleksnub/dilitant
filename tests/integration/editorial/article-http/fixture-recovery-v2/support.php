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

require_once dirname(__DIR__)
    . '/user-recovery-tests/support.php';

require_once dirname(__DIR__)
    . '/recovery-tests/support.php';

function hfr2_probe(string $run_id): array
{
    hfur_guard();
    hfur_run_id($run_id);

    $users = hfur_probe($run_id);
    $objects = hf09_probe($run_id);

    $result = [
        'run_id' => $run_id,
        'user_ids' => $users['user_ids'],
        'post_ids' => $objects['post_ids'],
        'term_ids' => $objects['term_ids'],
        'taxonomy_ids' => $objects['taxonomy_ids'],
        'partial_term_ids' => $objects['partial_term_ids'],
    ];

    foreach ($result as $field => &$values) {
        if ($field === 'run_id') {
            continue;
        }

        foreach ($values as $id) {
            hfur_require(
                is_int($id) && $id > 0,
                "Invalid fixture identifier: {$field}."
            );
        }

        $values = array_values(array_unique($values));
        sort($values, SORT_NUMERIC);
    }

    unset($values);

    hfur_guard();

    return $result;
}

function hfr2_assert_clean_start(): void
{
    hfur_assert_clean_start();

    $manifest = hfur_manifest();
    $probe = hfr2_probe($manifest['run_id']);

    foreach ($probe as $field => $values) {
        if ($field !== 'run_id') {
            hfur_require(
                $values === [],
                "Residual fixtures: {$field}."
            );
        }
    }

    require_once dirname(__DIR__)
        . '/user-recovery-tests/audit.php';

    hfur_audit();

    hfur_guard();
}

function hfr2_pending(
    string $run_id,
    string $kind,
    array $states = ['CREATING', 'CLEANUP_FAILED']
): array {
    hfur_require(
        in_array($kind, ['category', 'post'], true),
        'Invalid pending fixture kind.'
    );

    $manifest = hfur_require_manifest($run_id, $states);

    $objects = $manifest['objects'];

    $pending = array_values(array_filter(
        $objects,
        static fn(array $record): bool =>
            $record['id'] === 0
    ));

    hfur_require(
        count($pending) === 1
        && $pending[0] === $objects[array_key_last($objects)]
        && $pending[0]['kind'] === $kind
        && $pending[0]['taxonomy_id'] === 0,
        'Expected exactly one pending fixture intent.'
    );

    $expected_count = $kind === 'category' ? 5 : 9;

    hfur_require(
        count($objects) === $expected_count
        && $manifest['snapshot'] === null,
        'Unexpected pending fixture registry.'
    );

    return $pending[0];
}

function hfr2_verify_users(array $manifest): void
{
    $records = array_values(array_filter(
        $manifest['objects'],
        static fn(array $record): bool =>
            $record['kind'] === 'user'
    ));

    hfur_require(
        count($records) === 4,
        'Incomplete fixture user registry.'
    );

    $ids = [];

    foreach ($records as $record) {
        hfur_require(
            $record['id'] > 0,
            'Unconfirmed fixture user.'
        );

        $id = hfur_require_owned_user(
            $record,
            $manifest['run_id']
        );

        hfur_require(
            $id === $record['id'],
            'Fixture user identity mismatch.'
        );

        $user = get_userdata($id);

        hfur_require(
            $user instanceof WP_User
            && $user->roles === [$record['identity']['role']],
            'Fixture user role mismatch.'
        );

        $ids[] = $id;
    }

    sort($ids, SORT_NUMERIC);

    hfur_require(
        hfur_probe($manifest['run_id'])['user_ids'] === $ids,
        'Unexpected fixture user identities.'
    );

    hfur_guard();
}

function hfr2_checkpoint(
    string $stage,
    array $extra = []
): never {
    hfur_guard();

    $stages = [
        'fixture-empty',
        'fixture-intent',
        'fixture-category',
        'fixture-post',
        'fixture-confirmed',
        'fixture-ambiguous',
        'fixture-container',
        'fixture-sql-intent',
        'fixture-partial-category',
        'fixture-cleanup-crash',
    ];

    hfur_require(
        in_array($stage, $stages, true),
        'Unsupported fixture recovery checkpoint.'
    );

    $manifest = hfur_manifest();

    $expected_state = $stage === 'fixture-cleanup-crash'
        ? 'CLEANING'
        : 'CREATING';

    hfur_require(
        $manifest['state'] === $expected_state,
        'Unexpected fixture checkpoint state.'
    );

    if ($stage === 'fixture-empty') {
        hfur_require(
            $manifest['objects'] === []
            && $manifest['snapshot'] === null,
            'Expected empty Manifest V2.'
        );
    } else {
        hfr2_verify_users($manifest);
    }

    $pid = getmypid();

    hfur_require(
        is_int($pid) && $pid > 1,
        'Invalid recovery worker PID.'
    );

    hfur_json([
        'event' => 'CRASH_READY',
        'stage' => $stage,
        'pid' => $pid,
        'manifest' => $manifest,
        'extra' => (object) $extra,
    ]);

    $deadline = microtime(true) + 60;

    while (microtime(true) < $deadline) {
        sleep(1);
    }

    throw new RuntimeException(
        'Crash controller did not terminate fixture worker.'
    );
}