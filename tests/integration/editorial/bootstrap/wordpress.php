<?php

declare(strict_types=1);

if (
    !defined('ABSPATH')
    || !defined('DB_NAME')
    || DB_NAME !== DILITANT_TEST_DB_NAME
) {
    dilitant_test_abort('Invalid WordPress configuration.');
}

require ABSPATH . 'wp-settings.php';

global $wpdb;

if (!($wpdb instanceof wpdb)) {
    dilitant_test_abort('WordPress database connection is unavailable.');
}

if (
    !function_exists('did_action')
    || did_action('wp_loaded') < 1
) {
    dilitant_test_abort('WordPress initialization is incomplete.');
}