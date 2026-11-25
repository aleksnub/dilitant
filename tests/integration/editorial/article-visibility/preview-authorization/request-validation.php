<?php
/**
 * Точка входа расширенных испытаний
 * параметров предварительного просмотра.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D03 — Публичная доступность
 *     AM-T11.8 — Preview nonce
 *
 * Внутренние сценарии:
 *     PV-01–PV-18.
 *
 * Ответственность:
 *     - подключение компонентов;
 *     - определение нормативного количества
 *       внутренних испытаний;
 *     - сохранение программного интерфейса.
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
 * Нормативное количество дополнительных
 * испытаний параметров Preview.
 */
const DILITANT_PREVIEW_VALIDATION_TOTAL = 18;


/*
 * Исполнение отдельного сценария.
 */
require_once __DIR__
    . '/request-validation/execution.php';


/*
 * Подготовка тестовых записей,
 * пользовательских контекстов и nonce.
 */
require_once __DIR__
    . '/request-validation/fixtures.php';


/*
 * Нормативный перечень
 * внутренних сценариев.
 */
require_once __DIR__
    . '/request-validation/cases.php';


/*
 * Исполнение полного набора
 * и итоговая отчётность.
 */
require_once __DIR__
    . '/request-validation/runner.php';