<?php

declare(strict_types=1);

function hf_lu_lifecycle_case(
    string $failure,
    array $expected_calls,
    array $expected_result
): void {
    $calls = [];

    $restore = static function () use (
        &$calls,
        $failure
    ): void {
        $calls[] = 'restore';

        if ($failure === 'restore') {
            throw new RuntimeException(
                'Injected user restoration failure.'
            );
        }
    };

    $fixtures = static function () use (
        &$calls,
        $failure
    ): void {
        $calls[] = 'fixtures';

        if ($failure === 'fixtures') {
            throw new RuntimeException(
                'Injected fixture cleanup failure.'
            );
        }
    };

    $users = static function () use (
        &$calls,
        $failure
    ): void {
        $calls[] = 'users';

        if ($failure === 'users') {
            throw new RuntimeException(
                'Injected user cleanup failure.'
            );
        }
    };

    $result = dilitant_visibility_cleanup_sequence(
        $restore,
        $failure === 'missing-fixtures'
            ? null
            : $fixtures,
        $users
    );

    hf_lu_require(
        $calls === $expected_calls,
        'Invalid cleanup operation sequence.'
    );

    hf_lu_require(
        $result === $expected_result,
        'Unexpected cleanup result.'
    );
}

function hf_lu_lifecycle_success(): void
{
    hf_lu_lifecycle_case(
        '',
        ['restore', 'fixtures', 'users'],
        [
            'user_restoration_status' => 'PASS',
            'fixture_cleanup_status' => 'PASS',
            'user_cleanup_status' => 'PASS',
            'execution_error' => false,
        ]
    );
}

function hf_lu_lifecycle_fixture_failure(): void
{
    hf_lu_lifecycle_case(
        'fixtures',
        ['restore', 'fixtures'],
        [
            'user_restoration_status' => 'PASS',
            'fixture_cleanup_status' => 'FAIL',
            'user_cleanup_status' => 'FAIL',
            'execution_error' => true,
        ]
    );
}

function hf_lu_lifecycle_restore_failure(): void
{
    hf_lu_lifecycle_case(
        'restore',
        ['restore', 'fixtures'],
        [
            'user_restoration_status' => 'FAIL',
            'fixture_cleanup_status' => 'PASS',
            'user_cleanup_status' => 'FAIL',
            'execution_error' => true,
        ]
    );
}

function hf_lu_lifecycle_missing_fixtures(): void
{
    hf_lu_lifecycle_case(
        'missing-fixtures',
        ['restore'],
        [
            'user_restoration_status' => 'PASS',
            'fixture_cleanup_status' => 'NOT EXECUTED',
            'user_cleanup_status' => 'FAIL',
            'execution_error' => true,
        ]
    );
}

function hf_lu_lifecycle_user_failure(): void
{
    hf_lu_lifecycle_case(
        'users',
        ['restore', 'fixtures', 'users'],
        [
            'user_restoration_status' => 'PASS',
            'fixture_cleanup_status' => 'PASS',
            'user_cleanup_status' => 'FAIL',
            'execution_error' => true,
        ]
    );
}