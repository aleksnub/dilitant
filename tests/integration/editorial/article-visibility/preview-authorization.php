<?php
/**
 * Интеграционные испытания авторизации
 * предварительного просмотра статей.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D03 — Публичная доступность
 *
 * Сценарии:
 *     AM-T11.1–AM-T11.8.
 *
 * Ответственность:
 *     - подключение испытаний полномочий;
 *     - подключение испытаний контекста;
 *     - подключение испытаний Preview nonce.
 *
 * Исполняемая логика сценариев
 * находится в отдельных компонентах.
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
 * AM-T11.1–AM-T11.4.
 *
 * Аутентификация и объектные полномочия.
 */
require_once __DIR__
    . '/preview-authorization/permissions.php';


/*
 * AM-T11.5–AM-T11.7.
 *
 * Контекст, статус и идентичность записи.
 */
require_once __DIR__
    . '/preview-authorization/context.php';


/*
 * AM-T11.8.
 *
 * Штатная проверка Preview nonce
 * и его привязка к запрошенной записи.
 */
require_once __DIR__
    . '/preview-authorization/nonce.php';