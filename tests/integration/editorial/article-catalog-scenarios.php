<?php
/**
 * Загрузчик нормативных испытаний
 * публичного каталога Article Model.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial, редакция 1.1
 *     EDC-01 — Article Model
 *     AM-D02 — Защита инвариантов WP_Query
 *     AM-D05 — Пагинация каталога
 *
 * Подключает сценарии AM-CQ-01–10.
 *
 * Регистрация, исполнение и очистка
 * выполняются test-article-catalog.php.
 *
 * Компонент не запускает испытания.
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


/*
 * AM-CQ-01 — обязательная политика.
 */
require_once __DIR__
    . '/article-catalog-scenarios/policy.php';


/*
 * AM-CQ-02–03 — публичная видимость.
 */
require_once __DIR__
    . '/article-catalog-scenarios/visibility.php';


/*
 * AM-CQ-04 — нормативное дерево категорий.
 */
require_once __DIR__
    . '/article-catalog-scenarios/categories.php';


/*
 * AM-CQ-05–06 — изоляция запросов.
 */
require_once __DIR__
    . '/article-catalog-scenarios/isolation.php';


/*
 * AM-CQ-07 — отдельная статья.
 */
require_once __DIR__
    . '/article-catalog-scenarios/singular.php';


/*
 * AM-CQ-08 — фотоархив.
 */
require_once __DIR__
    . '/article-catalog-scenarios/archive-photo.php';


/*
 * AM-CQ-09 — пагинация.
 */
require_once __DIR__
    . '/article-catalog-scenarios/pagination.php';


/*
 * AM-CQ-10 — закрытый отказ.
 */
require_once __DIR__
    . '/article-catalog-scenarios/closed-failure.php';