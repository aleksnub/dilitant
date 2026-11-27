<?php

declare(strict_types=1);

function hfur_require(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function hfur_guard(): void
{
    dilitant_http_require_bootstrap();
    dilitant_fixture_assert_lock();
}

function hfur_query(string $sql): array
{
    global $wpdb;

    hfur_guard();

    $rows = $wpdb->get_results($sql, ARRAY_A);
    $error = $wpdb->last_error;

    hfur_guard();

    hfur_require(
        $error === '' && is_array($rows),
        'Independent user SQL query failed.'
    );

    return $rows;
}

function hfur_count(string $sql): int
{
    $rows = hfur_query($sql);

    hfur_require(
        count($rows) === 1
        && isset($rows[0]['n'])
        && ctype_digit((string) $rows[0]['n']),
        'Invalid independent SQL count.'
    );

    return (int) $rows[0]['n'];
}

function hfur_json(array $data): void
{
    fwrite(
        STDOUT,
        json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . PHP_EOL
    );

    fflush(STDOUT);
}

function hfur_run_id(string $run_id): string
{
    hfur_require(
        preg_match('/\A[0-9a-f]{12}\z/', $run_id) === 1,
        'Invalid User Recovery run ID.'
    );

    return $run_id;
}

function hfur_id(string $value): int
{
    hfur_require(
        ctype_digit($value)
        && (int) $value > 0
        && (string) (int) $value === $value,
        'Invalid User Recovery object ID.'
    );

    return (int) $value;
}

function hfur_require_keys(
    array $data,
    array $expected
): void {
    $actual = array_keys($data);

    sort($actual);
    sort($expected);

    hfur_require(
        $actual === $expected,
        'Unexpected record structure.'
    );
}

function hfur_checkpoint(
    string $stage,
    array $extra = []
): never {
    hfur_guard();

    $stages = [
        'user-intent',
        'user-unconfirmed',
        'user-partial',
        'user-ambiguous',
        'user-substituted',
        'user-sql-intent',
        'user-container',
        'user-cleanup-partial',
        'user-cleanup-crash',
    ];

    hfur_require(
        in_array($stage, $stages, true),
        'Unknown User Recovery checkpoint.'
    );

    $manifest = hfur_manifest();

    hfur_require(
        in_array(
            $manifest['state'],
            ['CREATING', 'CLEANING'],
            true
        ),
        'Unexpected crash checkpoint manifest state.'
    );

    $pid = getmypid();

    hfur_require(
        is_int($pid) && $pid > 1,
        'Invalid crash worker process ID.'
    );

    hfur_json([
        'event' => 'CRASH_READY',
        'stage' => $stage,
        'pid' => $pid,
        'manifest' => $manifest,
        'extra' => (object) $extra,
    ]);

    $deadline = microtime(true) + 60;

    while (microtime(true) < $deadline) {
        sleep(1);
    }

    throw new RuntimeException(
        'Crash controller did not terminate User Recovery worker.'
    );
}