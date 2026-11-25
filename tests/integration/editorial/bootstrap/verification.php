<?php

declare(strict_types=1);

function dilitant_cli_verify_wordpress(): void
{
    global $wpdb;

    if (!($wpdb instanceof wpdb)) {
        dilitant_test_abort('WordPress database connection is unavailable.');
    }

    if (
        DB_NAME !== DILITANT_TEST_DB_NAME
        || DB_USER !== 'dilitant_test'
    ) {
        dilitant_test_abort('Unexpected database configuration.');
    }

    if ($wpdb->get_var('SELECT DATABASE()') !== DILITANT_TEST_DB_NAME) {
        dilitant_test_abort('Unexpected active database.');
    }

    if ($wpdb->get_var('SELECT CURRENT_USER()') !== 'dilitant_test@%') {
        dilitant_test_abort('Unexpected authenticated database account.');
    }

    if (
        $wpdb->prefix !== DILITANT_TEST_TABLE_PREFIX
        || $wpdb->base_prefix !== DILITANT_TEST_TABLE_PREFIX
    ) {
        dilitant_test_abort('Unexpected WordPress table prefix.');
    }

    if (!is_blog_installed() || is_multisite()) {
        dilitant_test_abort('Unexpected WordPress installation.');
    }

    if (get_option('active_plugins') !== []) {
        dilitant_test_abort('Unexpected active WordPress plugins.');
    }

    if (
        get_template() !== 'dilitant-land'
        || get_stylesheet() !== 'dilitant-land'
    ) {
        dilitant_test_abort('Unexpected active WordPress theme.');
    }

    if (
        home_url() !== 'http://dilitant-test.invalid'
        || site_url() !== 'http://dilitant-test.invalid'
    ) {
        dilitant_test_abort('Unexpected CLI WordPress URL.');
    }
}

function dilitant_cli_verify_interfaces(): void
{
    $functions = [
        'dilitant_land_get_blog_category_id',
        'dilitant_land_get_blog_category_ids',
        'dilitant_land_is_blog_article',
        'dilitant_land_get_blog_articles',
        'dilitant_test_assert',
        'dilitant_test_assert_same',
        'dilitant_test_normalize_ids',
        'dilitant_test_query_ids',
    ];

    foreach ($functions as $function) {
        if (!function_exists($function)) {
            dilitant_test_abort("Required function missing: {$function}");
        }
    }
}

function dilitant_cli_report_ready(): void
{
    global $wpdb;

    echo "PASS: database isolation\n";
    echo "PASS: isolated WordPress test environment\n";
    echo "Database: " . DB_NAME . PHP_EOL;
    echo "Table prefix: " . $wpdb->prefix . PHP_EOL;
    echo "Theme: " . get_stylesheet() . PHP_EOL;
}