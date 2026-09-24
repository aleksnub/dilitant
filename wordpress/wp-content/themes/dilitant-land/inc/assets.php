<?php
/**
 * Подключение CSS и JavaScript темы.
 *
 * @package Dilitant_Land
 */

function dilitant_land_enqueue_assets() {
    wp_enqueue_style(
        'dilitant-land-style',
        get_stylesheet_uri(),
        array(),
        filemtime( get_stylesheet_directory() . '/style.css' )
    );

    /*
     * Базовые стили сайта.
     */
    $base_css = get_template_directory()
        . '/assets/css/base.css';

    wp_enqueue_style(
        'dilitant-land-base',
        get_template_directory_uri()
            . '/assets/css/base.css',
        array( 'dilitant-land-style' ),
        file_exists( $base_css )
            ? filemtime( $base_css )
            : '1.0'
    );

    /*
     * Геро-блок главной страницы.
     */
    $home_hero_css = get_template_directory()
        . '/assets/css/home-hero.css';

    wp_enqueue_style(
        'dilitant-land-home-hero',
        get_template_directory_uri()
            . '/assets/css/home-hero.css',
        array( 'dilitant-land-base' ),
        file_exists( $home_hero_css )
            ? filemtime( $home_hero_css )
            : '1.0'
    );

    /*
     * Редакционный блок главной страницы.
     */
    $home_editorial_css = get_template_directory()
        . '/assets/css/home-editorial.css';

    wp_enqueue_style(
        'dilitant-land-home-editorial',
        get_template_directory_uri()
            . '/assets/css/home-editorial.css',
        array( 'dilitant-land-home-hero' ),
        file_exists( $home_editorial_css )
            ? filemtime( $home_editorial_css )
            : '1.0'
    );

    /*
     * Карусель обложек главной страницы.
     */
    $home_covers_carousel_css = get_template_directory()
        . '/assets/css/home-covers-carousel.css';

    wp_enqueue_style(
        'dilitant-land-home-covers-carousel',
        get_template_directory_uri()
            . '/assets/css/home-covers-carousel.css',
        array( 'dilitant-land-home-editorial' ),
        file_exists( $home_covers_carousel_css )
            ? filemtime( $home_covers_carousel_css )
            : '1.0'
    );

    /*
     * Блок подписки главной страницы.
     */
    $home_subscription_css = get_template_directory()
        . '/assets/css/home-subscription.css';

    wp_enqueue_style(
        'dilitant-land-home-subscription',
        get_template_directory_uri()
            . '/assets/css/home-subscription.css',
        array( 'dilitant-land-home-covers-carousel' ),
        file_exists( $home_subscription_css )
            ? filemtime( $home_subscription_css )
            : '1.0'
    );

    /*
     * Блок «Дилетант» в Армении главной страницы.
     */
    $home_armenia_css = get_template_directory()
        . '/assets/css/home-armenia.css';

    wp_enqueue_style(
        'dilitant-land-home-armenia',
        get_template_directory_uri()
            . '/assets/css/home-armenia.css',
        array( 'dilitant-land-home-subscription' ),
        file_exists( $home_armenia_css )
            ? filemtime( $home_armenia_css )
            : '1.0'
    );

    /*
     * Блок «Дилетантские чтения» главной страницы.
     */
    $home_readings_css = get_template_directory()
        . '/assets/css/home-readings.css';

    wp_enqueue_style(
        'dilitant-land-home-readings',
        get_template_directory_uri()
            . '/assets/css/home-readings.css',
        array( 'dilitant-land-home-armenia' ),
        file_exists( $home_readings_css )
            ? filemtime( $home_readings_css )
            : '1.0'
    );

    /*
     * Подвал главной страницы.
     */
    $home_footer_css = get_template_directory()
        . '/assets/css/home-footer.css';

    wp_enqueue_style(
        'dilitant-land-home-footer',
        get_template_directory_uri()
            . '/assets/css/home-footer.css',
        array( 'dilitant-land-home-readings' ),
        file_exists( $home_footer_css )
            ? filemtime( $home_footer_css )
            : '1.0'
    );

    /*
     * Главное навигационное меню сайта.
     */
    $navigation_css = get_template_directory()
        . '/assets/css/navigation.css';

    wp_enqueue_style(
        'dilitant-land-navigation',
        get_template_directory_uri()
            . '/assets/css/navigation.css',
        array( 'dilitant-land-home-footer' ),
        file_exists( $navigation_css )
            ? filemtime( $navigation_css )
            : '1.0'
    );

    /*
     * Стили юридических страниц.
     *
     * Подключаются только для страниц,
     * использующих шаблон page-legal.php.
     */
    if ( is_page_template( 'page-legal.php' ) ) {
        $legal_css = get_template_directory()
            . '/assets/css/legal.css';

        wp_enqueue_style(
            'dilitant-land-legal',
            get_template_directory_uri()
                . '/assets/css/legal.css',
            array( 'dilitant-land-navigation' ),
            file_exists( $legal_css )
                ? filemtime( $legal_css )
                : '1.0'
        );
    }

    /*
     * Стили публичной карточки архивной фотографии.
     *
     * Подключаются только для одиночной записи archive_photo,
     * поэтому остальные страницы сайта этот CSS не загружают.
     */
    if ( is_singular( 'archive_photo' ) ) {
        $archive_photo_css = get_template_directory()
            . '/assets/css/archive-photo.css';

        wp_enqueue_style(
            'dilitant-land-archive-photo',
            get_template_directory_uri()
                . '/assets/css/archive-photo.css',
            array( 'dilitant-land-base' ),
            file_exists( $archive_photo_css )
                ? filemtime( $archive_photo_css )
                : '1.0'
        );
    }

    /*
     * Ресурсы страницы «Дилетанты в Армении».
     */
    if ( is_page( 'diletant-armenia' ) ) {

        /*
         * Общая функциональная раскладка страницы:
         * карусели, карточки и колонки форм.
         */
        $diletant_armenia_css = get_template_directory()
            . '/assets/css/diletant-armenia.css';

        wp_enqueue_style(
            'dilitant-land-diletant-armenia',
            get_template_directory_uri()
                . '/assets/css/diletant-armenia.css',
            array( 'dilitant-land-base' ),
            file_exists( $diletant_armenia_css )
                ? filemtime( $diletant_armenia_css )
                : '1.0'
        );

        /*
         * Стили формы отправки фотографии.
         */
        $photo_submission_css = get_template_directory()
            . '/assets/css/photo-submission.css';

        wp_enqueue_style(
            'dilitant-land-photo-submission',
            get_template_directory_uri()
                . '/assets/css/photo-submission.css',
            array(
                'dilitant-land-base',
                'dilitant-land-diletant-armenia',
            ),
            file_exists( $photo_submission_css )
                ? filemtime( $photo_submission_css )
                : '1.0'
        );

        /*
         * JavaScript двух каруселей:
         * статьи и фотоархив.
         */
        $diletant_armenia_js = get_template_directory()
            . '/assets/js/diletant-armenia.js';

        wp_enqueue_script(
            'dilitant-land-diletant-armenia',
            get_template_directory_uri()
                . '/assets/js/diletant-armenia.js',
            array(),
            file_exists( $diletant_armenia_js )
                ? filemtime( $diletant_armenia_js )
                : '1.0',
            true
        );
    }

    wp_enqueue_script(
        'dilitant-land-navigation',
        get_template_directory_uri() . '/assets/js/navigation.js',
        array(),
        file_exists( get_template_directory() . '/assets/js/navigation.js' )
            ? filemtime( get_template_directory() . '/assets/js/navigation.js' )
            : '1.0',
        true
    );
}

add_action( 'wp_enqueue_scripts', 'dilitant_land_enqueue_assets' );