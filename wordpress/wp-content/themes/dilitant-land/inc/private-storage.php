<?php
/**
 * Пути приватного хранилища Diletant Land.
 *
 * По умолчанию используется путь локального Docker-окружения.
 * На production корневой путь можно переопределить константой
 * DILITANT_LAND_PRIVATE_ROOT в wp-config.php.
 *
 * @package Dilitant_Land
 */

function dilitant_land_private_storage_root() {
    $root = defined( 'DILITANT_LAND_PRIVATE_ROOT' )
        ? DILITANT_LAND_PRIVATE_ROOT
        : '/var/www/dilitant-private';

    return rtrim( $root, '/\\' );
}

function dilitant_land_private_storage_path( $directory = '' ) {
    $root = dilitant_land_private_storage_root();

    if ( '' === $directory ) {
        return $root;
    }

    return $root . '/' . ltrim( $directory, '/\\' );
}