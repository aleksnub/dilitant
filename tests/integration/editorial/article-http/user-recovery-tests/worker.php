<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/fixtures/manifest.php';
require_once __DIR__ . '/support.php';

$action = $argv[1] ?? '';
$args = array_slice($argv, 2);

$user_creation_stages = [
    'user-intent',
    'user-unconfirmed',
    'user-partial',
    'user-ambiguous',
    'user-substituted',
    'user-sql-intent',
    'user-container',
];

$fixture_creation_stages = [
    'fixture-empty',
    'fixture-intent',
    'fixture-category',
    'fixture-post',
    'fixture-confirmed',
    'fixture-ambiguous',
    'fixture-container',
    'fixture-sql-intent',
    'fixture-partial-category',
];

$fixture_modules = dirname(__DIR__) . '/fixture-recovery-v2';

try {
    dilitant_http_require_bootstrap();
    dilitant_fixture_acquire_lock();

    $delegate_cleanup = false;
    $result = [];

    try {
        hfur_manifest();

        if (in_array($action, $user_creation_stages, true)) {
            hfur_require(
                $args === [],
                'Unexpected user creation arguments.'
            );

            require_once __DIR__ . '/creation.php';

            hfur_assert_clean_start();
            hfur_crash_creation($action);
        }

        if (in_array($action, $fixture_creation_stages, true)) {
            hfur_require(
                $args === [],
                'Unexpected fixture creation arguments.'
            );

            require_once $fixture_modules . '/support.php';
            require_once $fixture_modules . '/creation.php';

            hfur_assert_clean_start();
            hfr2_crash_creation($action);
        }

        switch ($action) {
            case 'manifest':
                hfur_require(
                    $args === [],
                    'Unexpected manifest arguments.'
                );

                $result = hfur_manifest();
                break;

            case 'probe':
                hfur_require(
                    count($args) === 1,
                    'Expected run ID.'
                );

                $result = hfur_probe(
                    hfur_run_id($args[0])
                );
                break;

            case 'protected-user':
                hfur_require(
                    $args === [],
                    'Unexpected protected user audit arguments.'
                );

                require_once __DIR__ . '/audit.php';

                $result = hfur_audit_protected_user();
                break;

            case 'audit':
                require_once __DIR__ . '/audit.php';

                $result = hfur_audit(
                    array_map('hfur_id', $args)
                );
                break;

            case 'user-cleanup-partial':
            case 'user-cleanup-crash':
                hfur_require(
                    $args === [],
                    'Unexpected cleanup arguments.'
                );

                require_once __DIR__ . '/cleanup.php';

                hfur_arm_cleanup_crash($action);

                $delegate_cleanup = true;
                break;

            case 'user-sql-failure':
                hfur_require(
                    count($args) === 1,
                    'Expected run ID.'
                );

                require_once __DIR__ . '/sql-failure.php';

                hfur_arm_sql_failure(
                    hfur_run_id($args[0])
                );

                $delegate_cleanup = true;
                break;

            case 'repair-user-ambiguity':
                hfur_require(
                    count($args) === 3,
                    'Expected run ID and two user IDs.'
                );

                require_once __DIR__ . '/ambiguity.php';

                hfur_repair_ambiguity(
                    hfur_run_id($args[0]),
                    hfur_id($args[1]),
                    hfur_id($args[2])
                );

                $result = ['repaired' => true];
                break;

            case 'repair-user-substitution':
                hfur_require(
                    count($args) === 3,
                    'Expected run ID and two user IDs.'
                );

                require_once __DIR__ . '/ambiguity.php';

                hfur_repair_substitution(
                    hfur_run_id($args[0]),
                    hfur_id($args[1]),
                    hfur_id($args[2])
                );

                $result = ['repaired' => true];
                break;

            case 'fixture-probe':
                hfur_require(
                    count($args) === 1,
                    'Expected fixture run ID.'
                );

                require_once $fixture_modules . '/support.php';

                $result = hfr2_probe(
                    hfur_run_id($args[0])
                );
                break;

            case 'fixture-cleanup-crash':
                hfur_require(
                    $args === [],
                    'Unexpected fixture cleanup arguments.'
                );

                require_once $fixture_modules . '/support.php';
                require_once $fixture_modules . '/cleanup.php';

                hfr2_arm_cleanup_crash();

                $delegate_cleanup = true;
                break;

            case 'fixture-sql-failure':
                hfur_require(
                    count($args) === 1,
                    'Expected fixture run ID.'
                );

                require_once $fixture_modules . '/support.php';
                require_once $fixture_modules . '/sql-failure.php';

                hfr2_arm_sql_failure(
                    hfur_run_id($args[0])
                );

                $delegate_cleanup = true;
                break;

            case 'fixture-repair-ambiguity':
                hfur_require(
                    count($args) === 3,
                    'Expected run ID and two post IDs.'
                );

                require_once $fixture_modules . '/support.php';
                require_once $fixture_modules . '/ambiguity.php';

                hfr2_repair_ambiguity(
                    hfur_run_id($args[0]),
                    hfur_id($args[1]),
                    hfur_id($args[2])
                );

                $result = ['repaired' => true];
                break;

            case 'fixture-repair-partial-category':
                hfur_require(
                    count($args) === 2,
                    'Expected run ID and term ID.'
                );

                require_once $fixture_modules . '/support.php';
                require_once $fixture_modules . '/ambiguity.php';

                hfr2_repair_partial_category(
                    hfur_run_id($args[0]),
                    hfur_id($args[1])
                );

                $result = ['repaired' => true];
                break;

            default:
                throw new RuntimeException(
                    "Unknown Recovery action: {$action}"
                );
        }

        dilitant_fixture_assert_lock();

    } finally {
        if (!$delegate_cleanup) {
            dilitant_fixture_release_lock();
        }
    }

    if ($delegate_cleanup) {
        $argv = [
            dirname(__DIR__) . '/fixtures.php',
            'cleanup',
        ];

        $argc = count($argv);

        require dirname(__DIR__) . '/fixtures.php';

        exit(0);
    }

    hfur_json($result);

    exit(0);

} catch (Throwable $error) {
    fwrite(
        STDERR,
        'FAIL: ' . $error->getMessage() . PHP_EOL
    );

    exit(1);
}