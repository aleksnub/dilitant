<?php
/**
 * Загрузчик подсистемы заявок на фотографии.
 *
 * Подключает тип записи, модель метаданных,
 * безопасную обработку файлов,
 * публичную форму
 * и административный интерфейс.
 *
 * @package Dilitant_Land
 */

require_once __DIR__ . '/photo-submission/post-type.php';
require_once __DIR__ . '/photo-submission/meta.php';
require_once __DIR__ . '/photo-submission/processing.php';
require_once __DIR__ . '/photo-submission/moderation.php';
require_once __DIR__ . '/photo-submission/public.php';

if ( is_admin() ) {
    require_once __DIR__ . '/photo-submission/admin.php';
}