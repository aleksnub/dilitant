<?php

declare(strict_types=1);

function dilitant_visibility_run(): int
{
    $fixtures = null;
    $users = null;

    $tests = [];
    $results = [];

    $initialization_status = 'NOT EXECUTED';
    $execution_error = false;

    $previous_user_id = get_current_user_id();

    echo PHP_EOL;
    echo "=== EDC-01 ARTICLE VISIBILITY ===\n";
    echo PHP_EOL;

    try {
        dilitant_visibility_initialize(
            $fixtures,
            $users
        );

        $initialization_status = 'PASS';

        $tests = dilitant_visibility_register_tests(
            $fixtures,
            $users
        );

        echo "PASS: visibility test registration\n";

        echo "Registered scenarios: "
            . count($tests)
            . PHP_EOL;

        echo PHP_EOL;

        $results = dilitant_visibility_execute_tests(
            $tests
        );
    } catch (Throwable $error) {
        $execution_error = true;

        if ($initialization_status !== 'PASS') {
            $initialization_status = 'FAIL';
        }

        fwrite(
            STDERR,
            "FAIL: visibility initialization or execution\n"
            . $error->getMessage()
            . PHP_EOL
        );
    } finally {
        $cleanup = dilitant_visibility_finalize_environment(
            $fixtures,
            $users,
            $previous_user_id
        );
    }

    return dilitant_visibility_finalize_report(
        $tests,
        $results,
        $initialization_status,
        $cleanup,
        $execution_error
    );
}