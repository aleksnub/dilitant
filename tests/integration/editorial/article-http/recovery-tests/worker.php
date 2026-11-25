<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/fixtures/manifest.php';

require_once __DIR__ . '/support.php';
require_once __DIR__ . '/ambiguity.php';
require_once __DIR__ . '/creation.php';
require_once __DIR__ . '/cleanup.php';

$action = $argv[1] ?? '';
$args = array_slice($argv, 2);

$creation_stages = [
    'empty',
    'intent',
    'category',
    'post',
    'confirmed',
    'ambiguous',
    'container',
    'sql-intent',
    'partial-category',
];

try {
    dilitant_http_require_bootstrap();
    dilitant_fixture_acquire_lock();

    $delegate_cleanup = false;
    $result = [];

    try {
        if (in_array($action, $creation_stages, true)) {
            hf09_require($args === [], 'Unexpected creation arguments.');

            hf09_crash_creation($action);
        }

        switch ($action) {
            case 'cleanup-crash':
                hf09_require($args === [], 'Unexpected cleanup arguments.');

                hf09_arm_cleanup_crash();

                $delegate_cleanup = true;
                break;

            case 'sql-failure':
                hf09_require(
                    count($args) === 1,
                    'Expected run ID.'
                );

                hf09_arm_sql_failure(hf09_run_id($args[0]));

                $delegate_cleanup = true;
                break;

            case 'audit':
                $ids = array_map('hf09_id', $args);

                $result = hf09_audit($ids);
                break;

            case 'probe':
                hf09_require(
                    count($args) === 1,
                    'Expected run ID.'
                );

                $result = hf09_probe(hf09_run_id($args[0]));
                break;

            case 'repair-ambiguity':
                hf09_require(
                    count($args) === 3,
                    'Expected run ID and two post IDs.'
                );

                hf09_repair_ambiguity(
                    hf09_run_id($args[0]),
                    hf09_id($args[1]),
                    hf09_id($args[2])
                );

                $result = ['repaired' => true];
                break;

            case 'repair-partial-category':
                hf09_require(
                    count($args) === 2,
                    'Expected run ID and term ID.'
                );

                hf09_repair_partial_category(
                    hf09_run_id($args[0]),
                    hf09_id($args[1])
                );

                $result = ['repaired' => true];
                break;

            default:
                throw new RuntimeException(
                    "Unknown recovery test action: {$action}"
                );
        }

    } finally {
        if (!$delegate_cleanup) {
            dilitant_fixture_release_lock();
        }
    }

    if ($delegate_cleanup) {
        $argv[1] = 'cleanup';

        require dirname(__DIR__) . '/fixtures.php';

        exit(0);
    }

    hf09_json($result);

} catch (Throwable $error) {
    fwrite(
        STDERR,
        'FAIL: ' . $error->getMessage() . PHP_EOL
    );

    exit(1);
}