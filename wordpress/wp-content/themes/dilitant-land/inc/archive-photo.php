<?php
/**
 * Загрузчик функциональности фотоархива.
 *
 * @package Dilitant_Land
 */

require_once __DIR__ . '/archive-photo/post-type.php';
require_once __DIR__ . '/archive-photo/meta.php';

if ( is_admin() ) {
    require_once __DIR__ . '/archive-photo/admin.php';
}