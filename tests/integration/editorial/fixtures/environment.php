<?php
/**
 * Проверка изоляции тестовой среды
 * Article Model.
 *
 * Ответственность:
 *     - проверка режима выполнения;
 *     - проверка конфигурации базы данных;
 *     - проверка фактического подключения;
 *     - проверка префикса таблиц.
 *
 * Компонент не изменяет состояние базы данных.
 */

declare(strict_types=1);

if (
    PHP_SAPI !== 'cli'
    || !defined('DILITANT_TEST_DB_NAME')
    || !defined('DB_NAME')
    || DB_NAME !== DILITANT_TEST_DB_NAME
) {
    exit(1);
}


trait DilitantArticleFixtureEnvironment
{
    /**
     * Проверяет изоляцию непосредственно
     * перед операциями с тестовыми данными.
     *
     * При невозможности подтвердить
     * конфигурацию выполнение прекращается
     * посредством исключения.
     */
    private function verify_environment(): void
    {
        global $wpdb;


        /*
         * Проверяем явное разрешение
         * тестового режима.
         */
        dilitant_test_assert_same(
            '1',
            getenv('DILITANT_TEST_MODE'),
            'Test mode is not enabled.'
        );


        /*
         * Проверяем наличие действительного
         * объекта подключения WordPress.
         */
        dilitant_test_assert(
            $wpdb instanceof wpdb,
            'WordPress database connection is unavailable.'
        );


        /*
         * Проверяем настроенную базу данных.
         */
        dilitant_test_assert_same(
            DILITANT_TEST_DB_NAME,
            DB_NAME,
            'Unexpected configured database.'
        );


        /*
         * Проверяем настроенного пользователя.
         */
        dilitant_test_assert_same(
            'dilitant_test',
            DB_USER,
            'Unexpected configured database user.'
        );


        /*
         * Проверяем фактически выбранную
         * базу данных.
         */
        dilitant_test_assert_same(
            DILITANT_TEST_DB_NAME,
            $wpdb->get_var(
                'SELECT DATABASE()'
            ),
            'Unexpected active database.'
        );


        /*
         * Проверяем учётную запись MariaDB,
         * использованную для авторизации.
         */
        dilitant_test_assert_same(
            'dilitant_test@%',
            $wpdb->get_var(
                'SELECT CURRENT_USER()'
            ),
            'Unexpected authenticated database account.'
        );


        /*
         * Проверяем текущий префикс таблиц.
         */
        dilitant_test_assert_same(
            DILITANT_TEST_TABLE_PREFIX,
            $wpdb->prefix,
            'Unexpected table prefix.'
        );


        /*
         * Проверяем базовый префикс.
         */
        dilitant_test_assert_same(
            DILITANT_TEST_TABLE_PREFIX,
            $wpdb->base_prefix,
            'Unexpected base table prefix.'
        );
    }
}