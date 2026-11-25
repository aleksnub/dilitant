<?php

declare(strict_types=1);

function dilitant_visibility_restore_runner_user(
    int $previous_user_id
): void {
    dilitant_visibility_verify_environment();

    wp_set_current_user($previous_user_id);

    dilitant_test_assert_same(
        $previous_user_id,
        get_current_user_id(),
        'Unable to restore runner user.'
    );
}

function dilitant_visibility_cleanup_sequence(
    callable $restore_user,
    ?callable $cleanup_fixtures,
    ?callable $cleanup_users
): array {
    $result = [
        'user_restoration_status' => 'NOT EXECUTED',
        'fixture_cleanup_status' => 'NOT EXECUTED',
        'user_cleanup_status' => 'NOT EXECUTED',
        'execution_error' => false,
    ];

    try {
        $restore_user();

        $result['user_restoration_status'] = 'PASS';
    } catch (Throwable $error) {
        $result['user_restoration_status'] = 'FAIL';
        $result['execution_error'] = true;

        fwrite(
            STDERR,
            "FAIL: runner user restoration\n"
            . $error->getMessage()
            . PHP_EOL
        );
    }

    if ($cleanup_fixtures !== null) {
        try {
            $cleanup_fixtures();

            $result['fixture_cleanup_status'] = 'PASS';
        } catch (Throwable $error) {
            $result['fixture_cleanup_status'] = 'FAIL';
            $result['execution_error'] = true;

            fwrite(
                STDERR,
                "FAIL: visibility fixture cleanup\n"
                . $error->getMessage()
                . PHP_EOL
            );
        }
    }

    if ($cleanup_users === null) {
        return $result;
    }

    if (
        $result['fixture_cleanup_status'] !== 'PASS'
        || $result['user_restoration_status'] !== 'PASS'
    ) {
        $result['user_cleanup_status'] = 'FAIL';
        $result['execution_error'] = true;

        fwrite(
            STDERR,
            "FAIL: visibility user cleanup blocked\n"
            . "Fixture cleanup and user restoration must succeed.\n"
        );

        return $result;
    }

    try {
        $cleanup_users();

        $result['user_cleanup_status'] = 'PASS';
    } catch (Throwable $error) {
        $result['user_cleanup_status'] = 'FAIL';
        $result['execution_error'] = true;

        fwrite(
            STDERR,
            "FAIL: visibility user cleanup\n"
            . $error->getMessage()
            . PHP_EOL
        );
    }

    return $result;
}

function dilitant_visibility_finalize_environment(
    ?DilitantArticleFixtures $fixtures,
    ?DilitantVisibilityUsers $users,
    int $previous_user_id
): array {
    return dilitant_visibility_cleanup_sequence(
        static function () use ($previous_user_id): void {
            dilitant_visibility_restore_runner_user(
                $previous_user_id
            );
        },
        $fixtures === null
            ? null
            : static function () use ($fixtures): void {
                $fixtures->cleanup();
            },
        $users === null
            ? null
            : static function () use ($users): void {
                $users->cleanup();
            }
    );
}