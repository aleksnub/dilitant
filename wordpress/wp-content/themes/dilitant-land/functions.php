<?php
/**
 * Основной загрузчик функций темы Diletant Land.
 *
 * @package Dilitant_Land
 */

add_theme_support( 'title-tag' );
add_theme_support( 'post-thumbnails' );

require_once get_template_directory() . '/inc/assets.php';
require_once get_template_directory() . '/inc/archive-photo.php';
require_once get_template_directory() . '/inc/photo-submission.php';
require_once get_template_directory() . '/inc/article-submission.php';
require_once get_template_directory() . '/inc/diletant-blog.php';