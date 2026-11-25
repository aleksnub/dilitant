<?php
/**
 * Общие нормативные константы
 * интеграционных испытаний Editorial.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D01 — Article Identity
 *     AM-D02 — Query Policy
 *     AM-D03 — Public Visibility
 *     HTTP-D02 — Fixtures & Authentication
 *     HF-D01 — Shared Test Bootstrap
 *
 * Ответственность:
 *     - определение идентичности тестовой базы;
 *     - определение префикса тестовых таблиц;
 *     - предоставление общих параметров
 *       CLI и HTTP Bootstrap.
 *
 * Компонент:
 *     - не загружает WordPress;
 *     - не подключается к базе данных;
 *     - не изменяет файловую систему;
 *     - не содержит секретов;
 *     - не создаёт испытательные объекты.
 *
 * Определение данных констант само по себе
 * не является доказательством изоляции.
 */

declare(strict_types=1);


/*
 * --------------------------------------------------
 * 1. Ограничение режима исполнения
 * --------------------------------------------------
 *
 * Общие испытательные компоненты
 * предназначены исключительно для PHP CLI.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);

    exit(1);
}


/*
 * --------------------------------------------------
 * 2. Проверка предварительных определений
 * --------------------------------------------------
 *
 * Не допускаем использование ранее определённых
 * несовместимых значений тестовой конфигурации.
 */

if (
    defined('DILITANT_TEST_DB_NAME')
    && DILITANT_TEST_DB_NAME !== 'dilitant_land_test'
) {
    fwrite(
        STDERR,
        "ABORT: Conflicting test database constant.\n"
    );

    exit(1);
}


if (
    defined('DILITANT_TEST_TABLE_PREFIX')
    && DILITANT_TEST_TABLE_PREFIX !== 'wp_test_'
) {
    fwrite(
        STDERR,
        "ABORT: Conflicting test table prefix.\n"
    );

    exit(1);
}


/*
 * --------------------------------------------------
 * 3. Идентичность тестовой базы данных
 * --------------------------------------------------
 */

if (!defined('DILITANT_TEST_DB_NAME')) {
    define(
        'DILITANT_TEST_DB_NAME',
        'dilitant_land_test'
    );
}


/*
 * --------------------------------------------------
 * 4. Префикс таблиц тестового WordPress
 * --------------------------------------------------
 */

if (!defined('DILITANT_TEST_TABLE_PREFIX')) {
    define(
        'DILITANT_TEST_TABLE_PREFIX',
        'wp_test_'
    );
}