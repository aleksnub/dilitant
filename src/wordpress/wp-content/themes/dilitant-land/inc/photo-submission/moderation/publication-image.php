<?php
/**
 * Координатор создания публичного изображения
 * архивной фотографии.
 *
 * @package Dilitant_Land
 */

require_once __DIR__ . '/publication-image/source.php';
require_once __DIR__ . '/publication-image/watermark.php';
require_once __DIR__ . '/publication-image/attachment.php';


/**
 * Создаёт публичное изображение для записи фотоархива.
 *
 * Последовательность:
 *
 * 1. Получить и проверить приватную safe-копию.
 * 2. Создать из неё новый JPEG с водяным знаком.
 * 3. Передать новый JPEG модулю attachment.php.
 *
 * attachment.php:
 *
 * - сохраняет новый JPEG через WordPress uploads;
 * - создаёт attachment;
 * - создаёт метаданные Media Library;
 * - назначает attachment изображением archive_photo;
 * - сохраняет связь с исходной заявкой.
 *
 * Приватная safe-копия никогда не публикуется напрямую.
 *
 * @param int $submission_id    ID заявки.
 * @param int $archive_photo_id ID записи фотоархива.
 *
 * @return int|WP_Error ID созданного attachment либо ошибка.
 */
function dilitant_land_photo_submission_create_publication_image(
    $submission_id,
    $archive_photo_id
) {
    $submission_id    = absint( $submission_id );
    $archive_photo_id = absint( $archive_photo_id );

    /*
     * Проверяем записи, между которыми создаётся связь.
     */
    if ( $submission_id <= 0 || $archive_photo_id <= 0 ) {
        return new WP_Error(
            'photo_submission_publication_image_invalid_id',
            'Некорректный ID заявки или записи фотоархива.'
        );
    }

    if ( 'photo_submission' !== get_post_type( $submission_id ) ) {
        return new WP_Error(
            'photo_submission_publication_image_wrong_submission',
            'Указанная запись не является заявкой на фотографию.'
        );
    }

    if ( 'archive_photo' !== get_post_type( $archive_photo_id ) ) {
        return new WP_Error(
            'photo_submission_publication_image_wrong_archive',
            'Указанная запись не является архивной фотографией.'
        );
    }

    /*
     * Шаг 1.
     *
     * Получаем путь только к уже подготовленной
     * и проверенной приватной safe-копии.
     */
    $safe_source =
        dilitant_land_photo_submission_get_publication_source(
            $submission_id
        );

    if ( is_wp_error( $safe_source ) ) {
        return $safe_source;
    }

    /*
     * Шаг 2.
     *
     * Из приватной safe-копии создаётся новый JPEG
     * с водяным знаком.
     *
     * Возвращается бинарное содержимое нового JPEG
     * в памяти, а не путь к приватному исходнику.
     */
    $public_jpeg =
        dilitant_land_photo_submission_create_watermarked_jpeg(
            $safe_source
        );

    if ( is_wp_error( $public_jpeg ) ) {
        return $public_jpeg;
    }

    /*
     * Шаг 3.
     *
     * Передаём только новый JPEG модулю WordPress.
     *
     * attachment.php не получает путь к приватному
     * safe-файлу.
     */
    $attachment_id =
        dilitant_land_photo_submission_create_public_attachment(
            $submission_id,
            $archive_photo_id,
            $public_jpeg
        );

    /*
     * Бинарное содержимое изображения координатору
     * больше не требуется.
     */
    unset( $public_jpeg );

    if ( is_wp_error( $attachment_id ) ) {
        return $attachment_id;
    }

    return (int) $attachment_id;
}