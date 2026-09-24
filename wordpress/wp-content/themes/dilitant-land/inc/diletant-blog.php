<?php
/**
 * Загрузчик функциональности блога
 * «Дилетанты в Армении».
 *
 * Блог использует обычные записи WordPress,
 * относящиеся к категории diletant-armenia,
 * и интегрируется с существующим фотоархивом.
 *
 * Этот файл только подключает отдельные модули.
 * Бизнес-логика непосредственно здесь не размещается.
 *
 * @package Dilitant_Land
 */

require_once __DIR__ . '/diletant-blog/routing.php';
require_once __DIR__ . '/diletant-blog/article.php';
require_once __DIR__ . '/diletant-blog/archive-photos.php';
require_once __DIR__ . '/diletant-blog/featured-photo.php';
require_once __DIR__ . '/diletant-blog/shortcode.php';

if ( is_admin() ) {
    require_once __DIR__ . '/diletant-blog/admin.php';
}