<?php

declare(strict_types=1);

function &dilitant_fixture_lock_connection(): ?string
{
    static $connection = null;

    return $connection;
}

function dilitant_fixture_lock_owner(): array
{
    global $wpdb;

    $row = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT CONNECTION_ID() AS connection_id,
                    IS_USED_LOCK(%s) AS owner_id',
            'dilitant_land_test:editorial:fixtures'
        ),
        ARRAY_A
    );

    if (
        $wpdb->last_error !== ''
        || !is_array($row)
        || !isset($row['connection_id'])
        || !array_key_exists('owner_id', $row)
        || !ctype_digit((string) $row['connection_id'])
        || (int) $row['connection_id'] <= 0
    ) {
        throw new RuntimeException('Fixture lock lookup failed.');
    }

    if (
        $row['owner_id'] !== null
        && (
            !ctype_digit((string) $row['owner_id'])
            || (int) $row['owner_id'] <= 0
        )
    ) {
        throw new RuntimeException('Invalid fixture lock owner.');
    }

    return [
        'connection' => (string) $row['connection_id'],
        'owner' => $row['owner_id'] === null
            ? null
            : (string) $row['owner_id'],
    ];
}

function dilitant_fixture_assert_lock(): void
{
    $connection = &dilitant_fixture_lock_connection();
    $state = dilitant_fixture_lock_owner();

    if (
        $connection === null
        || $state['connection'] !== $connection
        || $state['owner'] !== $connection
    ) {
        throw new RuntimeException('Fixture lock ownership lost.');
    }
}

function dilitant_fixture_acquire_lock(): void
{
    global $wpdb;

    $connection = &dilitant_fixture_lock_connection();

    if ($connection !== null) {
        dilitant_fixture_assert_lock();

        return;
    }

    $before = dilitant_fixture_lock_owner();

    if ($before['owner'] !== null) {
        throw new RuntimeException(
            'Another fixture operation is active.'
        );
    }

    $result = $wpdb->get_var(
        $wpdb->prepare(
            'SELECT GET_LOCK(%s, 0)',
            'dilitant_land_test:editorial:fixtures'
        )
    );

    if ($wpdb->last_error !== '' || (string) $result !== '1') {
        throw new RuntimeException('Unable to acquire fixture lock.');
    }

    $connection = $before['connection'];

    dilitant_fixture_assert_lock();
}

function dilitant_fixture_release_lock(): void
{
    global $wpdb;

    dilitant_fixture_assert_lock();

    $result = $wpdb->get_var(
        $wpdb->prepare(
            'SELECT RELEASE_LOCK(%s)',
            'dilitant_land_test:editorial:fixtures'
        )
    );

    if ($wpdb->last_error !== '' || (string) $result !== '1') {
        throw new RuntimeException('Fixture lock release failed.');
    }

    $state = dilitant_fixture_lock_owner();
    $connection = &dilitant_fixture_lock_connection();

    if (
        $state['connection'] !== $connection
        || $state['owner'] === $connection
    ) {
        throw new RuntimeException(
            'Fixture lock release verification failed.'
        );
    }

    $connection = null;
}