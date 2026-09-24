<?php
/**
 * Получение и проверка приватной safe-копии фотографии.
 *
 * Этот модуль ничего не публикует и не изменяет.
 * Его задача — вернуть путь только к допустимому
 * приватному JPEG-файлу.
 *
 * @package Dilitant_Land
 */


/**
 * Максимально допустимая ширина safe-копии.
 */
if ( ! defined( 'DILITANT_LAND_PUBLICATION_SOURCE_MAX_WIDTH' ) ) {
    define(
        'DILITANT_LAND_PUBLICATION_SOURCE_MAX_WIDTH',
        10000
    );
}


/**
 * Максимально допустимая высота safe-копии.
 */
if ( ! defined( 'DILITANT_LAND_PUBLICATION_SOURCE_MAX_HEIGHT' ) ) {
    define(
        'DILITANT_LAND_PUBLICATION_SOURCE_MAX_HEIGHT',
        10000
    );
}


/**
 * Максимально допустимое количество пикселей.
 */
if ( ! defined( 'DILITANT_LAND_PUBLICATION_SOURCE_MAX_PIXELS' ) ) {
    define(
        'DILITANT_LAND_PUBLICATION_SOURCE_MAX_PIXELS',
        25000000
    );
}


/**
 * Возвращает путь к проверенной приватной safe-копии.
 *
 * Проверяются:
 *
 * - processing_status = safe;
 * - имя файла имеет вид {sha256}.jpg;
 * - файл находится в приватном photo-safe;
 * - файл существует;
 * - файл доступен для чтения;
 * - фактический тип изображения — JPEG;
 * - ширина и высота находятся в допустимых пределах;
 * - общее количество пикселей не превышает лимит.
 *
 * Функция ничего не копирует, не изменяет и не публикует.
 *
 * @param int $submission_id ID заявки.
 *
 * @return string|WP_Error Абсолютный путь к safe JPEG либо ошибка.
 */
function dilitant_land_photo_submission_get_publication_source(
    $submission_id
) {
    $submission_id = absint( $submission_id );

    if ( $submission_id <= 0 ) {
        return new WP_Error(
            'photo_submission_publication_source_invalid_id',
            'Некорректный ID заявки.'
        );
    }

    if ( 'photo_submission' !== get_post_type( $submission_id ) ) {
        return new WP_Error(
            'photo_submission_publication_source_wrong_type',
            'Указанная запись не является заявкой на фотографию.'
        );
    }

    /*
     * Публичное изображение разрешено создавать только
     * из файла, уже прошедшего безопасную обработку.
     */
    $processing_status = get_post_meta(
        $submission_id,
        '_photo_submission_processing_status',
        true
    );

    if ( 'safe' !== $processing_status ) {
        return new WP_Error(
            'photo_submission_publication_source_not_safe',
            'Файл заявки не имеет статуса safe.'
        );
    }

    /*
     * Имя приватной safe-копии хранится в
     * _photo_submission_safe_preview.
     *
     * Это каноническое поле существующего конвейера:
     * оно записывается при сохранении безопасной копии
     * и используется административным предпросмотром
     * и очисткой файлов.
     *
     * Допустимое имя:
     *
     * 64 шестнадцатеричных символа + .jpg
     */
    $safe_filename = get_post_meta(
        $submission_id,
        '_photo_submission_safe_preview',
        true
    );

    $safe_filename = trim(
        (string) $safe_filename
    );

    if (
        ! preg_match(
            '/\A[a-f0-9]{64}\.jpg\z/',
            $safe_filename
        )
    ) {
        return new WP_Error(
            'photo_submission_publication_source_invalid_filename',
            'Некорректное имя безопасной копии.'
        );
    }

    /*
     * Приватный каталог намеренно находится вне
     * document root WordPress.
     */
    $safe_dir =
        '/var/www/dilitant-private/photo-safe';

    $safe_path =
        $safe_dir
        . DIRECTORY_SEPARATOR
        . $safe_filename;

    /*
     * Дополнительная защита от выхода за пределы
     * ожидаемого каталога.
     *
     * realpath() вызывается только после формирования
     * пути из уже строго проверенного имени файла.
     */
    $real_safe_dir = realpath( $safe_dir );
    $real_safe_path = realpath( $safe_path );

    if (
        false === $real_safe_dir
        || false === $real_safe_path
    ) {
        return new WP_Error(
            'photo_submission_publication_source_missing',
            'Безопасная копия фотографии не найдена.'
        );
    }

    $expected_prefix =
        rtrim(
            $real_safe_dir,
            DIRECTORY_SEPARATOR
        )
        . DIRECTORY_SEPARATOR;

    if (
        0 !== strpos(
            $real_safe_path,
            $expected_prefix
        )
    ) {
        return new WP_Error(
            'photo_submission_publication_source_outside_directory',
            'Безопасная копия находится вне разрешённого каталога.'
        );
    }

    if ( ! is_file( $real_safe_path ) ) {
        return new WP_Error(
            'photo_submission_publication_source_not_file',
            'Безопасная копия не является обычным файлом.'
        );
    }

    if ( ! is_readable( $real_safe_path ) ) {
        return new WP_Error(
            'photo_submission_publication_source_not_readable',
            'Безопасная копия недоступна для чтения.'
        );
    }

    /*
     * Проверяем изображение по содержимому,
     * а не только по расширению имени.
     */
    $image_info = @getimagesize(
        $real_safe_path
    );

    if ( false === $image_info ) {
        return new WP_Error(
            'photo_submission_publication_source_invalid_image',
            'Безопасная копия не является допустимым изображением.'
        );
    }

    if (
        empty( $image_info['mime'] )
        || 'image/jpeg' !== $image_info['mime']
    ) {
        return new WP_Error(
            'photo_submission_publication_source_not_jpeg',
            'Безопасная копия не является JPEG-изображением.'
        );
    }

    $width = isset( $image_info[0] )
        ? (int) $image_info[0]
        : 0;

    $height = isset( $image_info[1] )
        ? (int) $image_info[1]
        : 0;

    if ( $width <= 0 || $height <= 0 ) {
        return new WP_Error(
            'photo_submission_publication_source_invalid_dimensions',
            'Не удалось определить размеры безопасной копии.'
        );
    }

    if (
        $width
        > DILITANT_LAND_PUBLICATION_SOURCE_MAX_WIDTH
    ) {
        return new WP_Error(
            'photo_submission_publication_source_too_wide',
            'Ширина безопасной копии превышает допустимый предел.'
        );
    }

    if (
        $height
        > DILITANT_LAND_PUBLICATION_SOURCE_MAX_HEIGHT
    ) {
        return new WP_Error(
            'photo_submission_publication_source_too_high',
            'Высота безопасной копии превышает допустимый предел.'
        );
    }

    /*
     * Деление используется вместо $width * $height,
     * чтобы проверка не зависела от возможного переполнения
     * при неожиданно больших значениях.
     */
    if (
        $width
        > intdiv(
            DILITANT_LAND_PUBLICATION_SOURCE_MAX_PIXELS,
            $height
        )
    ) {
        return new WP_Error(
            'photo_submission_publication_source_too_many_pixels',
            'Безопасная копия содержит слишком много пикселей.'
        );
    }

    return $real_safe_path;
}