<?php
/**
 * Поиск данных для восстановления преобразования заявки.
 *
 * @package Dilitant_Land
 */


/**
 * Ищет archive_photo, ранее созданный из указанной заявки.
 *
 * Поиск выполняется по обратной служебной связи:
 *
 * _archive_photo_submission_id = ID заявки.
 *
 * @param int $submission_id ID заявки.
 *
 * @return int ID archive_photo или 0.
 */
function dilitant_land_photo_submission_find_archive_photo( $submission_id ) {
    $submission_id = absint( $submission_id );

    if ( $submission_id <= 0 ) {
        return 0;
    }

    $archive_ids = get_posts(
        array(
            'post_type'      => 'archive_photo',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_key'       => '_archive_photo_submission_id',
            'meta_value'     => $submission_id,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        )
    );

    if ( empty( $archive_ids ) ) {
        return 0;
    }

    return absint( $archive_ids[0] );
}


/**
 * Ищет уже созданный публичный attachment
 * (медиафайл WordPress) для заявки.
 *
 * Сначала проверяется прямая служебная связь
 * со стороны заявки.
 *
 * Если её нет, дополнительно проверяется
 * основное изображение archive_photo.
 *
 * @param int $submission_id    ID заявки.
 * @param int $archive_photo_id ID записи фотоархива.
 *
 * @return int ID attachment или 0.
 */
function dilitant_land_photo_submission_find_public_attachment(
    $submission_id,
    $archive_photo_id
) {
    $submission_id    = absint( $submission_id );
    $archive_photo_id = absint( $archive_photo_id );

    if ( $submission_id <= 0 || $archive_photo_id <= 0 ) {
        return 0;
    }

    $attachment_id = absint(
        get_post_meta(
            $submission_id,
            '_photo_submission_public_attachment_id',
            true
        )
    );

    if (
        $attachment_id > 0
        && 'attachment' === get_post_type( $attachment_id )
    ) {
        return $attachment_id;
    }

    /*
     * Если прямая служебная связь отсутствует,
     * проверяем основное изображение archive_photo.
     */
    $thumbnail_id = absint(
        get_post_thumbnail_id( $archive_photo_id )
    );

    if (
        $thumbnail_id > 0
        && 'attachment' === get_post_type( $thumbnail_id )
    ) {
        return $thumbnail_id;
    }

    return 0;
}