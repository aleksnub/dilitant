<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/fixtures/lock.php';

require_once __DIR__ . '/ownership-tests/support.php';
require_once __DIR__ . '/ownership-tests/posts.php';
require_once __DIR__ . '/ownership-tests/categories.php';
require_once __DIR__ . '/ownership-tests/registry.php';

function run_ownership_test(callable $scenario): void
{
    $fixtures = new DilitantArticleFixtures();
    $journal = new DilitantOwnershipTestJournal();

    $fixtures->attach_journal($journal);

    try {
        $fixtures->create();
        $fixtures->create_query_fixtures();
        $scenario($fixtures, $journal);

    } finally {
        $fixtures->cleanup();
    }
}

$scenarios = [
    'HF-M08.1 Post title' => 'ownership_test_title',
    'HF-M08.2 Content marker' => 'ownership_test_content_marker',
    'HF-M08.3 Category identity' => 'ownership_test_category_identity',
    'HF-M08.4 Foreign dependency' => 'ownership_test_foreign_dependency',
    'HF-M08.5 Registry substitution' => 'ownership_test_registry_substitution',
];

$passed = 0;

try {
    dilitant_fixture_acquire_lock();

    try {
        $manifest = get_option(
            'dilitant_http_fixture_manifest_v1',
            null
        );

        if (
            $manifest !== null
            && (
                !is_array($manifest)
                || ($manifest['state'] ?? null) !== 'CLEANED'
            )
        ) {
            throw new RuntimeException('An HTTP fixture run is active.');
        }

        if (get_category_by_slug('diletant-armenia') !== false) {
            throw new RuntimeException('A clean test database is required.');
        }

        echo "=== HF-M08 FIXTURE OWNERSHIP ===\n";

        foreach ($scenarios as $name => $scenario) {
            run_ownership_test($scenario);

            ++$passed;

            echo "PASS: {$name}\n";
        }

        dilitant_test_assert_same(
            $manifest,
            get_option('dilitant_http_fixture_manifest_v1', null),
            'Persistent manifest was modified.'
        );

        echo "PASS: Persistent manifest unchanged\n";

    } finally {
        dilitant_fixture_release_lock();
    }

} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);

    echo "Passed: {$passed}/" . count($scenarios) . PHP_EOL;

    exit(1);
}

echo "=== RESULT ===\n";
echo "Total: " . count($scenarios) . PHP_EOL;
echo "Passed: {$passed}\n";
echo "Failed: 0\n";
echo "PASS: HF-M08\n";