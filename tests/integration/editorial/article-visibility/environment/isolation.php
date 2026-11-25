<?php

declare(strict_types=1);

function dilitant_visibility_verify_environment(): void
{
    global $wpdb;

    dilitant_test_assert_same(
        '1',
        getenv('DILITANT_TEST_MODE'),
        'Test mode is not enabled.'
    );

    dilitant_test_assert(
        $wpdb instanceof wpdb,
        'WordPress database interface is unavailable.'
    );

    dilitant_test_assert_same(
        DILITANT_TEST_DB_NAME,
        DB_NAME,
        'Unexpected database name.'
    );

    $database = $wpdb->get_var('SELECT DATABASE()');

    dilitant_test_assert_same(
        '',
        $wpdb->last_error,
        'Database verification failed.'
    );

    dilitant_test_assert_same(
        DILITANT_TEST_DB_NAME,
        $database,
        'Unexpected active database.'
    );

    dilitant_test_assert_same(
        'wp_test_',
        $wpdb->prefix,
        'Unexpected database prefix.'
    );

    dilitant_test_assert_same(
        'wp_test_',
        $wpdb->base_prefix,
        'Unexpected database base prefix.'
    );

    $account = $wpdb->get_var('SELECT CURRENT_USER()');

    dilitant_test_assert_same(
        '',
        $wpdb->last_error,
        'Database account verification failed.'
    );

    dilitant_test_assert_same(
        'dilitant_test@%',
        $account,
        'Unexpected database account.'
    );

    dilitant_test_assert(
        !is_multisite(),
        'Multisite environment is unsupported.'
    );
}