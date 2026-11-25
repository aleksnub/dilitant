<?php

declare(strict_types=1);

function dilitant_visibility_finalize_report(
    array $tests,
    array $results,
    string $initialization_status,
    array $cleanup,
    bool $execution_error
): int {
    $statistics = dilitant_visibility_statistics(
        $tests,
        $results
    );

    $fixture_cleanup_status =
        $cleanup['fixture_cleanup_status'];

    $user_cleanup_status =
        $cleanup['user_cleanup_status'];

    $execution_error =
        $execution_error
        || $cleanup['execution_error']
        || $cleanup['user_restoration_status'] !== 'PASS';

    dilitant_visibility_report(
        $statistics,
        $initialization_status,
        $fixture_cleanup_status,
        $user_cleanup_status
    );

    $accepted =
        !$execution_error
        && $initialization_status === 'PASS'
        && $fixture_cleanup_status === 'PASS'
        && $user_cleanup_status === 'PASS'
        && dilitant_visibility_is_accepted(
            $statistics,
            $initialization_status,
            $fixture_cleanup_status,
            $user_cleanup_status,
            $execution_error
        );

    echo PHP_EOL;

    if (!$accepted) {
        echo "FAIL: EDC-01 AM-D03 VISIBILITY\n";

        return 1;
    }

    echo "PASS: EDC-01 AM-D03 VISIBILITY\n";

    return 0;
}