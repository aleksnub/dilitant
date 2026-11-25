<?php

declare(strict_types=1);

function dilitant_cli_verify_database(array $config): void
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $connection = null;

    try {
        $connection = new mysqli(
            'dilitant_land_db',
            $config['user'],
            $config['password'],
            $config['name'],
            3306
        );

        $result = $connection->query(
            'SELECT DATABASE() AS db, CURRENT_USER() AS account'
        );

        $identity = $result->fetch_assoc();
        $result->free();

        if (($identity['db'] ?? null) !== DILITANT_TEST_DB_NAME) {
            throw new RuntimeException('Unexpected active database.');
        }

        if (($identity['account'] ?? null) !== 'dilitant_test@%') {
            throw new RuntimeException('Unexpected database account.');
        }

        dilitant_cli_verify_production_isolation($connection);
        dilitant_cli_verify_database_options($connection);

    } catch (mysqli_sql_exception $error) {
        dilitant_test_abort(
            'Database verification failed. Error code: '
            . $error->getCode()
        );

    } catch (RuntimeException $error) {
        dilitant_test_abort($error->getMessage());

    } finally {
        if ($connection instanceof mysqli) {
            $connection->close();
        }

        mysqli_report(MYSQLI_REPORT_OFF);
    }
}

function dilitant_cli_verify_production_isolation(mysqli $connection): void
{
    try {
        $connection->select_db('dilitant_land_wp');

    } catch (mysqli_sql_exception $error) {
        if ((int) $error->getCode() !== 1044) {
            throw $error;
        }

        $connection->select_db(DILITANT_TEST_DB_NAME);
        return;
    }

    throw new RuntimeException(
        'Test user has access to production database.'
    );
}

function dilitant_cli_verify_database_options(mysqli $connection): void
{
    $result = $connection->query(
        "SELECT option_name, option_value
         FROM wp_test_options
         WHERE option_name IN (
             'active_plugins',
             'template',
             'stylesheet'
         )"
    );

    $options = [];

    while ($row = $result->fetch_assoc()) {
        $options[$row['option_name']] = $row['option_value'];
    }

    $result->free();

    if (($options['active_plugins'] ?? null) !== 'a:0:{}') {
        throw new RuntimeException('Active plugins are not allowed.');
    }

    if (
        ($options['template'] ?? null) !== 'dilitant-land'
        || ($options['stylesheet'] ?? null) !== 'dilitant-land'
    ) {
        throw new RuntimeException('Unexpected test WordPress theme.');
    }
}