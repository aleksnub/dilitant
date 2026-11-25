<?php
/**
 * Точка входа исполнителя интеграционных
 * испытаний публичной доступности статей.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D03 — Публичная доступность
 *
 * Ответственность:
 *     - подключение компонентов исполнителя;
 *     - определение нормативного количества
 *       обязательных сценариев;
 *     - сохранение программного интерфейса.
 *
 * Исполняемая логика вынесена
 * в отдельные компоненты.
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
 * Нормативное количество испытаний:
 *
 *     AM-T09 — 9;
 *     AM-T10 — 3;
 *     AM-T11 — 8.
 */
const DILITANT_VISIBILITY_EXPECTED_TOTAL = 20;


/*
 * Нормативный перечень,
 * проверка интерфейсов и регистрация.
 */
require_once __DIR__
    . '/runner/registration.php';


/*
 * Исполнение сценариев
 * и восстановление окружения.
 */
require_once __DIR__
    . '/runner/execution.php';


/*
 * Статистика и итоговая отчётность.
 */
require_once __DIR__
    . '/runner/reporting.php';


/*
 * Полный жизненный цикл испытаний.
 */
require_once __DIR__
    . '/runner/lifecycle.php';