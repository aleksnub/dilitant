<?php
/**
 * Проверка допуска заявки к преобразованию в фотоархив.
 *
 * @package Dilitant_Land
 */


/**
 * Проверяет базовые условия преобразования заявки.
 *
 * Для преобразования одновременно должны выполняться условия:
 *
 * - запись существует;
 * - запись имеет тип photo_submission;
 * - редактор одобрил заявку;
 * - файл прошёл техническую обработку и имеет статус safe;
 * - archive_photo для этой заявки ещё не связан.
 *
 * Эта функция выполняет обычную проверку состояния.
 * Она не является защитой от одновременных процессов.
 *
 * @param int $submission_id ID заявки.
 *
 * @return bool
 */
function dilitant_land_photo_submission_can_convert( $submission_id ) {
    $submission_id = absint( $submission_id );

    if ( $submission_id <= 0 ) {
        return false;
    }

    $submission = get_post( $submission_id );

    if (
        ! $submission
        || 'photo_submission' !== $submission->post_type
    ) {
        return false;
    }

    $review_status = get_post_meta(
        $submission_id,
        '_photo_submission_review_status',
        true
    );

    if ( 'approved' !== $review_status ) {
        return false;
    }

    $processing_status = get_post_meta(
        $submission_id,
        '_photo_submission_processing_status',
        true
    );

    if ( 'safe' !== $processing_status ) {
        return false;
    }

    $archive_photo_id = absint(
        get_post_meta(
            $submission_id,
            '_photo_submission_archive_photo_id',
            true
        )
    );

    if ( $archive_photo_id > 0 ) {
        return false;
    }

    return true;
}