<?php

declare(strict_types=1);

require_once dirname(__DIR__)
    . '/article-http/bootstrap.php';

require_once __DIR__ . '/environment.php';

require_once dirname(__DIR__)
    . '/fixtures/lock.php';

require_once __DIR__ . '/runner/lifecycle.php';

require_once __DIR__ . '/legacy-user-tests/support.php';
require_once __DIR__ . '/legacy-user-tests/lifecycle.php';
require_once __DIR__ . '/legacy-user-tests/identity.php';
require_once __DIR__ . '/legacy-user-tests/dependencies.php';
require_once __DIR__ . '/legacy-user-tests/runner-failure.php';

$cases = [
    'HF-LU-01' => [
        'Cleanup sequence',
        'hf_lu_lifecycle_success',
    ],
    'HF-LU-02' => [
        'Fixture cleanup failure',
        'hf_lu_lifecycle_fixture_failure',
    ],
    'HF-LU-03' => [
        'User restoration failure',
        'hf_lu_lifecycle_restore_failure',
    ],
    'HF-LU-04' => [
        'Missing fixture cleanup',
        'hf_lu_lifecycle_missing_fixtures',
    ],
    'HF-LU-05' => [
        'User cleanup failure',
        'hf_lu_lifecycle_user_failure',
    ],
    'HF-LU-06' => [
        'Protected administrator',
        'hf_lu_protected_administrator',
    ],
    'HF-LU-07' => [
        'Changed identity',
        'hf_lu_changed_identity',
    ],
    'HF-LU-08' => [
        'Current user protection',
        'hf_lu_current_user',
    ],
    'HF-LU-09' => [
        'Dependency SQL failure',
        'hf_lu_sql_failure',
    ],
    'HF-LU-10' => [
        'Foreign post dependency',
        'hf_lu_foreign_post',
    ],
    'HF-LU-11' => [
        'Foreign link dependency',
        'hf_lu_foreign_link',
    ],
    'HF-LU-12' => [
        'Foreign comment dependency',
        'hf_lu_foreign_comment',
    ],
    'HF-LU-13' => [
        'Idempotent cleanup',
        'hf_lu_idempotent_cleanup',
    ],
    'HF-LU-14' => [
        'Runner fixture cleanup failure',
        'hf_lu_runner_failure',
    ],
];

$passed = 0;
$failed = 0;
$executed = 0;
$locked = false;

echo PHP_EOL;
echo "=== HF-LU LEGACY USER SAFETY ===\n";

try {
    dilitant_http_require_bootstrap();

    dilitant_visibility_verify_environment();

    dilitant_fixture_acquire_lock();

    $locked = true;

    foreach ($cases as $id => [$name, $operation]) {
        ++$executed;

        try {
            hf_lu_run_case(
                $id,
                $name,
                $operation
            );

            ++$passed;
        } catch (Throwable $error) {
            ++$failed;

            fwrite(
                STDERR,
                "FAIL: {$id} {$name}\n"
                . $error->getMessage()
                . PHP_EOL
            );

            break;
        }
    }
} catch (Throwable $error) {
    ++$failed;

    fwrite(
        STDERR,
        "FAIL: Legacy User test environment\n"
        . $error->getMessage()
        . PHP_EOL
    );
} finally {
    if ($locked) {
        try {
            dilitant_fixture_release_lock();
        } catch (Throwable $error) {
            ++$failed;

            fwrite(
                STDERR,
                "FAIL: Legacy User test lock release\n"
                . $error->getMessage()
                . PHP_EOL
            );
        }
    }
}

$total = count($cases);

echo PHP_EOL;
echo "=== RESULT ===\n";
echo "Total: {$total}\n";
echo "Executed: {$executed}\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
echo "Not executed: " . ($total - $executed) . PHP_EOL;

if (
    $executed !== $total
    || $passed !== $total
    || $failed !== 0
) {
    echo "FAIL: HF-LU\n";

    exit(1);
}

echo "PASS: HF-LU\n";

exit(0);