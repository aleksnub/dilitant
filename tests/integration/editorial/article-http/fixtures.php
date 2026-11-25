<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/fixtures/lock.php';
require_once __DIR__ . '/fixtures/manifest.php';

function record_http_fixture_failure(
    DilitantHttpFixtureManifest $manifest,
    string $expected_state,
    string $failure_state,
    Throwable $primary
): void {
    try {
        dilitant_fixture_assert_lock();

        if ($manifest->state() !== $expected_state) {
            return;
        }

        $current = DilitantHttpFixtureManifest::load();

        if ($current->data() !== $manifest->data()) {
            throw new RuntimeException(
                'Persistent manifest changed during operation.'
            );
        }

        $manifest->transition($failure_state);

    } catch (Throwable $secondary) {
        throw new RuntimeException(
            $primary->getMessage()
                . PHP_EOL
                . 'Failure state persistence: '
                . $secondary->getMessage(),
            0,
            $primary
        );
    }
}

function create_http_fixtures(
    int $schema_version = 1
): array {
    dilitant_fixture_assert_lock();

    $fixtures = new DilitantArticleFixtures();

    $manifest = DilitantHttpFixtureManifest::begin(
        $fixtures->run_id(),
        $schema_version
    );

    try {
        $fixtures->attach_journal($manifest);

        dilitant_fixture_assert_lock();

        if ($schema_version === 2) {
            $fixtures->create_users();

            dilitant_fixture_assert_lock();
        }

        $fixtures->create();

        dilitant_fixture_assert_lock();

        $fixtures->create_query_fixtures();
        $fixtures->verify_query_fixtures();

        dilitant_fixture_assert_lock();

        $manifest->ready($fixtures->export_state());

        return $manifest->data();

    } catch (Throwable $error) {
        record_http_fixture_failure(
            $manifest,
            'CREATING',
            'FAILED',
            $error
        );

        throw $error;
    }
}

function verify_http_fixtures(): array
{
    dilitant_fixture_assert_lock();

    $manifest = DilitantHttpFixtureManifest::load();

    DilitantArticleFixtures::restore_ready(
        $manifest->data()
    );

    dilitant_fixture_assert_lock();

    return $manifest->data();
}

function cleanup_http_fixtures(): array
{
    dilitant_fixture_assert_lock();

    $manifest = DilitantHttpFixtureManifest::load();

    if ($manifest->state() === 'CLEANED') {
        return $manifest->data();
    }

    if ($manifest->state() !== 'CLEANING') {
        $manifest->transition('CLEANING');
    }

    try {
        $manifest->recover_pending();

        dilitant_fixture_assert_lock();

        $data = $manifest->data();

        $fixtures = DilitantArticleFixtures::restore_for_cleanup(
            $data['run_id'],
            $data['objects']
        );

        $fixtures->cleanup();

        dilitant_fixture_assert_lock();

        $manifest->transition('CLEANED');

        return $manifest->data();

    } catch (Throwable $error) {
        record_http_fixture_failure(
            $manifest,
            'CLEANING',
            'CLEANUP_FAILED',
            $error
        );

        throw $error;
    }
}

function execute_http_fixture_command(
    string $command
): array {
    dilitant_fixture_assert_lock();

    return match ($command) {
        'create' => create_http_fixtures(1),

        'create-v2' => create_http_fixtures(2),

        'status' => DilitantHttpFixtureManifest::load()->data(),

        'verify' => verify_http_fixtures(),

        'cleanup' => cleanup_http_fixtures(),

        default => throw new RuntimeException(
            'Unsupported fixture command.'
        ),
    };
}

function run_http_fixture_command(string $command): array
{
    dilitant_fixture_acquire_lock();

    $result = null;
    $failure = null;
    $buffer_level = ob_get_level();

    ob_start();

    try {
        $result = execute_http_fixture_command($command);

        dilitant_fixture_assert_lock();

    } catch (Throwable $error) {
        $failure = $error;

    } finally {
        while (ob_get_level() > $buffer_level) {
            ob_end_clean();
        }

        try {
            dilitant_fixture_release_lock();

        } catch (Throwable $error) {
            if ($failure === null) {
                $failure = $error;
            } else {
                $failure = new RuntimeException(
                    $failure->getMessage()
                        . PHP_EOL
                        . 'Lock release: '
                        . $error->getMessage(),
                    0,
                    $failure
                );
            }
        }
    }

    if ($failure !== null) {
        throw $failure;
    }

    if (!is_array($result)) {
        throw new RuntimeException(
            'Fixture operation returned no result.'
        );
    }

    return $result;
}

$command = $argv[1] ?? '';

if (!in_array(
    $command,
    ['create', 'create-v2', 'status', 'verify', 'cleanup'],
    true
)) {
    fwrite(
        STDERR,
        "Usage: fixtures.php create|create-v2|status|verify|cleanup\n"
    );

    exit(2);
}

try {
    $result = run_http_fixture_command($command);

    echo json_encode(
        $result,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR
    ), PHP_EOL;

} catch (Throwable $error) {
    fwrite(
        STDERR,
        'FAIL: ' . $error->getMessage() . PHP_EOL
    );

    exit(1);
}