<?php
/**
 * Проверка файлов фотографий до декодирования.
 *
 * Файл к моменту вызова этих функций уже должен находиться
 * в приватном карантине.
 *
 * Проверка не создаёт растровое изображение через GD или Imagick.
 *
 * @package Dilitant_Land
 */


/**
 * Максимальный допустимый размер файла: 10 МБ.
 */
define(
    'DILITANT_LAND_PHOTO_MAX_FILE_SIZE',
    10 * 1024 * 1024
);


/**
 * Максимальная ширина изображения в пикселях.
 */
define(
    'DILITANT_LAND_PHOTO_MAX_WIDTH',
    10000
);


/**
 * Максимальная высота изображения в пикселях.
 */
define(
    'DILITANT_LAND_PHOTO_MAX_HEIGHT',
    10000
);


/**
 * Максимальное количество пикселей изображения.
 */
define(
    'DILITANT_LAND_PHOTO_MAX_PIXELS',
    25000000
);


/**
 * Возвращает разрешённые MIME-типы изображений.
 *
 * MIME-тип — техническое обозначение реального формата файла,
 * например image/jpeg.
 *
 * @return string[]
 */
function dilitant_land_photo_submission_allowed_mime_types() {
    return array(
        'image/jpeg',
        'image/png',
        'image/webp',
    );
}


/**
 * Проверяет файл фотографии до полного декодирования.
 *
 * Проверяются:
 * - существование обычного файла;
 * - размер файла;
 * - MIME-тип по содержимому;
 * - возможность прочитать размеры изображения;
 * - совпадение MIME-типа с форматом изображения;
 * - ширина;
 * - высота;
 * - общее количество пикселей.
 *
 * @param string $file_path Абсолютный путь к файлу в карантине.
 *
 * @return array|WP_Error Данные проверенного изображения
 *                        или объект ошибки.
 */
function dilitant_land_photo_submission_validate_file(
    $file_path
) {
    $file_path = (string) $file_path;

    if (
        '' === $file_path
        || ! is_file( $file_path )
    ) {
        return new WP_Error(
            'photo_submission_file_missing',
            'Файл фотографии не найден.'
        );
    }

    $file_size = filesize( $file_path );

    if ( false === $file_size ) {
        return new WP_Error(
            'photo_submission_file_size_failed',
            'Не удалось определить размер файла.'
        );
    }

    if (
        0 >= $file_size
        || DILITANT_LAND_PHOTO_MAX_FILE_SIZE < $file_size
    ) {
        return new WP_Error(
            'photo_submission_file_size_invalid',
            'Размер файла фотографии недопустим.'
        );
    }

    /*
     * Определяем MIME-тип по содержимому файла,
     * а не по имени или данным, присланным браузером.
     */
    $finfo = new finfo( FILEINFO_MIME_TYPE );
    $detected_mime = $finfo->file( $file_path );

    if (
        ! is_string( $detected_mime )
        || ! in_array(
            $detected_mime,
            dilitant_land_photo_submission_allowed_mime_types(),
            true
        )
    ) {
        return new WP_Error(
            'photo_submission_mime_not_allowed',
            'Формат файла фотографии не разрешён.'
        );
    }

    /*
     * Получаем размеры изображения без создания
     * полного растрового изображения в памяти.
     */
    $image_info = @getimagesize( $file_path );

    if (
        false === $image_info
        || empty( $image_info[0] )
        || empty( $image_info[1] )
        || empty( $image_info['mime'] )
    ) {
        return new WP_Error(
            'photo_submission_image_invalid',
            'Не удалось определить параметры изображения.'
        );
    }

    $width  = (int) $image_info[0];
    $height = (int) $image_info[1];
    $image_mime = (string) $image_info['mime'];

    /*
     * fileinfo и анализ структуры изображения должны
     * согласиться относительно формата файла.
     */
    if ( $detected_mime !== $image_mime ) {
        return new WP_Error(
            'photo_submission_mime_mismatch',
            'Формат файла определён неоднозначно.'
        );
    }

    if (
        DILITANT_LAND_PHOTO_MAX_WIDTH < $width
        || DILITANT_LAND_PHOTO_MAX_HEIGHT < $height
    ) {
        return new WP_Error(
            'photo_submission_dimensions_too_large',
            'Размеры изображения превышают допустимый предел.'
        );
    }

    /*
     * Умножаем только после ограничения каждой стороны.
     * При наших пределах переполнение integer здесь невозможно
     * на используемой 64-битной PHP-среде.
     */
    $pixel_count = $width * $height;

    if (
        0 >= $pixel_count
        || DILITANT_LAND_PHOTO_MAX_PIXELS < $pixel_count
    ) {
        return new WP_Error(
            'photo_submission_pixel_count_too_large',
            'Изображение содержит слишком много пикселей.'
        );
    }

    return array(
        'file_size'     => (int) $file_size,
        'detected_mime' => $detected_mime,
        'width'         => $width,
        'height'        => $height,
        'pixel_count'   => $pixel_count,
    );
}