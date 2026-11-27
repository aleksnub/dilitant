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

function hfur_cleanup_target(): array
{
    hfur_guard();

    $data = hfur_manifest();

    hfur_require(
        $data['state'] === 'READY',
        'Cleanup crash requires a ready manifest.'
    );

    $run_id = $data['run_id'];

    $record = hfur_registered_user(
        $run_id,
        'author_b'
    );

    $id = hfur_require_owned_user(
        $record,
        $run_id
    );

    hfur_require(
        $id !== hfur_protected_id()
        && $id !== get_current_user_id(),
        'Protected or active cleanup target.'
    );

    hfur_require(
        hfur_user_dependencies($id)['usermeta'] > 1,
        'Insufficient metadata for partial cleanup.'
    );

    return [
        'run_id' => $run_id,
        'record' => $record,
        'id' => $id,
    ];
}

function hfur_cleanup_verify_manifest(
    array $target
): array {
    hfur_guard();

    $data = hfur_require_manifest(
        $target['run_id'],
        ['CLEANING']
    );

    $record = hfur_registered_user(
        $target['run_id'],
        'author_b'
    );

    hfur_require(
        $record === $target['record']
        && $record['id'] === $target['id'],
        'Cleanup target registry changed.'
    );

    return $data;
}

function hfur_cleanup_verify_dependencies(
    int $id
): array {
    $dependencies = hfur_user_dependencies($id);

    foreach ([
        'posts',
        'links',
        'comments',
        'sessions',
        'application_passwords',
    ] as $name) {
        hfur_require(
            $dependencies[$name] === 0,
            "Unexpected cleanup dependency: {$name}."
        );
    }

    return $dependencies;
}

function hfur_cleanup_metadata_ids(int $id): array
{
    global $wpdb;

    hfur_require(
        $id > 0,
        'Invalid cleanup metadata user ID.'
    );

    $rows = hfur_query(
        $wpdb->prepare(
            "SELECT umeta_id
             FROM {$wpdb->usermeta}
             WHERE user_id = %d
             ORDER BY umeta_id",
            $id
        )
    );

    return array_map(
        static fn(array $row): int =>
            hfur_id((string) $row['umeta_id']),
        $rows
    );
}

function hfur_arm_partial_cleanup(
    array $target
): void {
    $initial_metadata = hfur_cleanup_metadata_ids(
        $target['id']
    );

    hfur_require(
        count($initial_metadata) > 1,
        'Insufficient initial user metadata.'
    );

    add_action(
        'deleted_user_meta',
        static function (
            $meta_ids,
            $object_id,
            $meta_key,
            $meta_value
        ) use ($target, $initial_metadata): void {
            if ((int) $object_id !== $target['id']) {
                return;
            }

            hfur_guard();

            hfur_cleanup_verify_manifest($target);

            $id = hfur_require_owned_user(
                $target['record'],
                $target['run_id']
            );

            hfur_require(
                $id === $target['id'],
                'Partially deleted user identity mismatch.'
            );

            $remaining = hfur_cleanup_metadata_ids($id);

            $removed = array_values(
                array_diff(
                    $initial_metadata,
                    $remaining
                )
            );

            hfur_require(
                array_diff(
                    $remaining,
                    $initial_metadata
                ) === [],
                'Unexpected new user metadata.'
            );

            hfur_require(
                $removed !== []
                && $remaining !== [],
                'Expected partially deleted user metadata.'
            );

            $deleted_ids = array_map(
                static fn($value): int => (int) $value,
                (array) $meta_ids
            );

            hfur_require(
                $deleted_ids !== []
                && array_diff(
                    $deleted_ids,
                    $removed
                ) === [],
                'Deleted metadata hook mismatch.'
            );

            $dependencies =
                hfur_cleanup_verify_dependencies($id);

            hfur_require(
                $dependencies['usermeta']
                    === count($remaining),
                'Partial cleanup metadata count mismatch.'
            );

            hfur_checkpoint(
                'user-cleanup-partial',
                [
                    'id' => $id,
                    'identity' =>
                        $target['record']['identity'],
                    'initial_metadata_ids' =>
                        $initial_metadata,
                    'removed_metadata_ids' =>
                        $removed,
                    'remaining_metadata_ids' =>
                        $remaining,
                    'dependencies' =>
                        $dependencies,
                ]
            );
        },
        PHP_INT_MAX,
        4
    );
}

function hfur_arm_completed_cleanup(
    array $target
): void {
    add_action(
        'deleted_user',
        static function (
            $id,
            $reassign,
            $user
        ) use ($target): void {
            if ((int) $id !== $target['id']) {
                return;
            }

            hfur_guard();

            hfur_require(
                $reassign === null,
                'Unexpected user content reassignment.'
            );

            hfur_cleanup_verify_manifest($target);

            hfur_assert_user_absent(
                $target['record'],
                $target['run_id']
            );

            $dependencies = hfur_user_dependencies(
                $target['id']
            );

            foreach ($dependencies as $name => $count) {
                hfur_require(
                    $count === 0,
                    "Residual deleted user dependency: {$name}."
                );
            }

            hfur_checkpoint(
                'user-cleanup-crash',
                [
                    'id' => $target['id'],
                    'identity' =>
                        $target['record']['identity'],
                    'dependencies' =>
                        $dependencies,
                ]
            );
        },
        PHP_INT_MAX,
        3
    );
}

function hfur_arm_cleanup_crash(
    string $stage = 'user-cleanup-crash'
): void {
    hfur_guard();

    hfur_require(
        in_array(
            $stage,
            [
                'user-cleanup-partial',
                'user-cleanup-crash',
            ],
            true
        ),
        'Unsupported user cleanup crash stage.'
    );

    $target = hfur_cleanup_target();

    match ($stage) {
        'user-cleanup-partial' =>
            hfur_arm_partial_cleanup($target),

        'user-cleanup-crash' =>
            hfur_arm_completed_cleanup($target),
    };

    hfur_guard();
}