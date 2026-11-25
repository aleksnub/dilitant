<?php
/**
 * Точка входа испытательной
 * инфраструктуры маршрутизации AM-D03.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D03 — Публичная доступность
 *
 * Ответственность:
 *     - подключение компонентов;
 *     - обеспечение порядка зависимостей;
 *     - сохранение программных интерфейсов.
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
 * Настоящий WP_Query и контролируемый
 * контекст отдельной публикации.
 */
require_once __DIR__
    . '/routing/query-context.php';


/*
 * Сохранение и восстановление
 * обработчиков перенаправления.
 */
require_once __DIR__
    . '/routing/redirect-hooks.php';


/*
 * Предусловия отказа
 * и проверка очистки запроса.
 */
require_once __DIR__
    . '/routing/rejection.php';


/*
 * Исполнение производственного
 * обработчика доступности.
 */
require_once __DIR__
    . '/routing/handler.php';


/*
 * Штатный Preview WordPress,
 * параметры запроса и перехват wp_die().
 */
require_once __DIR__
    . '/routing/native-preview.php';