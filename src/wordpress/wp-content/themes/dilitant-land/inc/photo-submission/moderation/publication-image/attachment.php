<?php
/**
 * Создание публичного attachment WordPress
 * для архивной фотографии.
 *
 * Модуль получает уже созданное бинарное содержимое
 * нового JPEG. Он не имеет доступа к приватному
 * safe-файлу и не занимается обработкой изображения.
 *
 * Его ответственность:
 *
 * - сохранить JPEG через WordPress uploads;
 * - создать attachment;
 * - создать метаданные Media Library;
 * - назначить attachment изображением archive_photo;
 * - сохранить связь с исходной заявкой.
 *
 * @package Dilitant_Land
 */


/**
 * Создаёт публичный attachment и связывает его
 * с записью фотоархива и исходной заявкой.
 *
 * @param int    $submission_id    ID исходной заявки.
 * @param int    $archive_photo_id ID записи фотоархива.
 * @param string $jpeg_blob        Бинарное содержимое нового JPEG.
 *
 * @return int|WP_Error ID attachment либо ошибка.
 */
function dilitant_land_photo_submission_create_public_attachment(
    $submission_id,
    $archive_photo_id,
    $jpeg_blob
) {
    $submission_id    = absint( $submission_id );
    $archive_photo_id = absint( $archive_photo_id );

    if ( $submission_id <= 0 || $archive_photo_id <= 0 ) {
        return new WP_Error(
            'photo_submission_attachment_invalid_id',
            'Некорректный ID заявки или записи фотоархива.'
        );
    }

    if ( 'photo_submission' !== get_post_type( $submission_id ) ) {
        return new WP_Error(
            'photo_submission_attachment_wrong_submission',
            'Указанная запись не является заявкой на фотографию.'
        );
    }

    if ( 'archive_photo' !== get_post_type( $archive_photo_id ) ) {
        return new WP_Error(
            'photo_submission_attachment_wrong_archive',
            'Указанная запись не является архивной фотографией.'
        );
    }

    if ( ! is_string( $jpeg_blob ) || '' === $jpeg_blob ) {
        return new WP_Error(
            'photo_submission_attachment_empty_image',
            'Не передано содержимое публичного JPEG.'
        );
    }

    /*
     * Защита от повторного создания публичного изображения.
     *
     * Если связь уже сохранена и attachment действительно
     * существует, повторно создавать файл нельзя.
     */
    $existing_attachment_id = absint(
        get_post_meta(
            $submission_id,
            '_photo_submission_public_attachment_id',
            true
        )
    );

    if ( $existing_attachment_id > 0 ) {
        $existing_attachment = get_post(
            $existing_attachment_id
        );

        if (
            $existing_attachment
            && 'attachment' === $existing_attachment->post_type
        ) {
            return new WP_Error(
                'photo_submission_attachment_already_exists',
                'Для заявки уже существует публичное изображение.'
            );
        }
    }

    /*
     * Сохраняем только новый JPEG, созданный watermark.php.
     *
     * Приватный safe-файл сюда вообще не передаётся.
     */
    $upload = wp_upload_bits(
        'archive-photo-' . $archive_photo_id . '.jpg',
        null,
        $jpeg_blob
    );

    if ( ! empty( $upload['error'] ) ) {
        return new WP_Error(
            'photo_submission_attachment_upload_failed',
            'Не удалось сохранить публичное изображение: '
            . $upload['error']
        );
    }

    if (
        empty( $upload['file'] )
        || ! is_file( $upload['file'] )
    ) {
        return new WP_Error(
            'photo_submission_attachment_upload_missing',
            'WordPress не создал файл публичного изображения.'
        );
    }

    $public_file = $upload['file'];

    /*
     * Функции создания метаданных изображения находятся
     * в административной библиотеке WordPress и могут
     * быть ещё не загружены.
     */
    require_once ABSPATH . 'wp-admin/includes/image.php';

    /*
     * Создаём attachment.
     *
     * archive_photo становится родительской записью
     * созданного изображения.
     */
    $attachment_id = wp_insert_attachment(
        array(
            'post_mime_type' => 'image/jpeg',
            'post_title'     => get_the_title(
                $archive_photo_id
            ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ),
        $public_file,
        $archive_photo_id,
        true
    );

    if ( is_wp_error( $attachment_id ) ) {
        if ( is_file( $public_file ) ) {
            @unlink( $public_file );
        }

        return $attachment_id;
    }

    $attachment_id = absint(
        $attachment_id
    );

    /*
     * Создаём стандартные метаданные Media Library:
     * размеры изображения, миниатюры и прочие данные,
     * которыми WordPress управляет для attachment.
     *
     * Важно: wp_generate_attachment_metadata() может
     * самостоятельно сохранять промежуточные метаданные
     * во время создания размеров изображения.
     */
    $attachment_metadata =
        wp_generate_attachment_metadata(
            $attachment_id,
            $public_file
        );

    if (
        ! is_array( $attachment_metadata )
        || empty( $attachment_metadata )
    ) {
        /*
         * wp_delete_attachment(..., true) удалит как запись
         * attachment, так и связанный публичный файл.
         */
        wp_delete_attachment(
            $attachment_id,
            true
        );

        return new WP_Error(
            'photo_submission_attachment_metadata_failed',
            'Не удалось создать метаданные публичного изображения.'
        );
    }

    /*
     * Сохраняем итоговые метаданные.
     *
     * wp_update_attachment_metadata() возвращает false
     * не только при ошибке, но и когда такое же значение
     * уже находится в _wp_attachment_metadata.
     *
     * Поэтому false само по себе ошибкой не считаем:
     * перечитываем фактически сохранённые данные и сравниваем.
     */
    $metadata_result =
        wp_update_attachment_metadata(
            $attachment_id,
            $attachment_metadata
        );

    if ( false === $metadata_result ) {
        $stored_metadata =
            wp_get_attachment_metadata(
                $attachment_id
            );

        if ( $stored_metadata !== $attachment_metadata ) {
            wp_delete_attachment(
                $attachment_id,
                true
            );

            return new WP_Error(
                'photo_submission_attachment_metadata_save_failed',
                'Не удалось сохранить метаданные публичного изображения.'
            );
        }
    }

    /*
     * Назначаем attachment основным изображением
     * записи archive_photo.
     */
    $thumbnail_result = set_post_thumbnail(
        $archive_photo_id,
        $attachment_id
    );

    if ( false === $thumbnail_result ) {
        wp_delete_attachment(
            $attachment_id,
            true
        );

        return new WP_Error(
            'photo_submission_attachment_thumbnail_failed',
            'Не удалось назначить изображение записи фотоархива.'
        );
    }

    /*
     * Сохраняем связь со стороны исходной заявки.
     */
    $relation_result = update_post_meta(
        $submission_id,
        '_photo_submission_public_attachment_id',
        $attachment_id
    );

    if ( false === $relation_result ) {
        /*
         * Не оставляем частично завершённое состояние:
         * сначала убираем featured image, затем удаляем
         * созданный attachment и его публичный файл.
         */
        delete_post_thumbnail(
            $archive_photo_id
        );

        wp_delete_attachment(
            $attachment_id,
            true
        );

        return new WP_Error(
            'photo_submission_attachment_relation_failed',
            'Не удалось сохранить связь публичного изображения с заявкой.'
        );
    }

    return $attachment_id;
}