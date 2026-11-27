<?php

declare(strict_types=1);

function hfur_crash_creation(string $stage): never
{
    hfur_guard();

    $allowed = [
        'user-intent',
        'user-unconfirmed',
        'user-partial',
        'user-ambiguous',
        'user-substituted',
        'user-sql-intent',
        'user-container',
    ];

    hfur_require(
        in_array($stage, $allowed, true),
        'Unsupported User Recovery creation stage.'
    );

    hfur_assert_clean_start();

    $run_id = bin2hex(random_bytes(6));

    $manifest = DilitantHttpFixtureManifest::begin(
        $run_id,
        2
    );

    $journal = new DilitantUserRecoveryCrashJournal(
        $manifest,
        $run_id,
        $stage
    );

    $hook = null;

    try {
        if ($stage === 'user-partial') {
            $hook = hfur_arm_partial_creation(
                $run_id
            );
        }

        DilitantFixtureUserCreation::create(
            $run_id,
            'administrator',
            $journal
        );

    } finally {
        if ($hook !== null) {
            remove_filter(
                'insert_user_meta',
                $hook,
                PHP_INT_MAX
            );
        }
    }

    throw new RuntimeException(
        "Expected crash checkpoint was not reached: {$stage}"
    );
}