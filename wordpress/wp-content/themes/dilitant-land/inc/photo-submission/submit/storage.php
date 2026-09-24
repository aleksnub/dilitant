<?php
/**
 * Сохранение публичной заявки на фотографию.
 *
 * Файл отвечает только за создание photo_submission
 * и сохранение связанных с заявкой метаданных.
 *
 * Он не проверяет HTTP-запрос,
 * не обрабатывает изображение
 * и не выполняет перенаправления.
 *
 * @package Dilitant_Land
 */


/**
 * Создаёт внутреннюю заявку photo_submission
 * и сохраняет её данные.
 *
 * @param array $data      Проверенные данные формы.
 * @param array $photo     Данные исходного HTTP-upload.
 * @param array $processed Результат безопасной обработки файла.
 *
 * @return int|WP_Error ID созданной заявки или ошибка.
 */
function dilitant_land_photo_submission_store(
    $data,
    $photo,
    $processed
) {
    $submission_id = wp_insert_post(
        array(
            'post_type'   => 'photo_submission',
            'post_status' => 'publish',
        ),
        true
    );

    if ( is_wp_error( $submission_id ) ) {
        return $submission_id;
    }

    if ( ! $submission_id ) {
        return new WP_Error(
            'photo_submission_create_failed',
            'Не удалось создать заявку.'
        );
    }

    /*
     * Данные отправителя.
     */
    update_post_meta(
        $submission_id,
        '_photo_submission_submitter_name',
        $data['submitter_name']
    );

    update_post_meta(
        $submission_id,
        '_photo_submission_submitter_email',
        $data['submitter_email']
    );

    /*
     * Сведения о фотографии.
     *
     * Эти поля могут быть пустыми:
     * отсутствие исторических сведений
     * не препятствует приёму фотографии.
     */
    update_post_meta(
        $submission_id,
        '_photo_submission_author_name',
        $data['author_name']
    );

    update_post_meta(
        $submission_id,
        '_photo_submission_approx_date',
        $data['approx_date']
    );

    update_post_meta(
        $submission_id,
        '_photo_submission_place',
        $data['place']
    );

    update_post_meta(
        $submission_id,
        '_photo_submission_source',
        $data['source']
    );

    update_post_meta(
        $submission_id,
        '_photo_submission_description',
        $data['description']
    );

    /*
     * Основание для публикации
     * и отдельный факт подтверждения прав.
     */
    update_post_meta(
        $submission_id,
        '_photo_submission_publication_basis',
        $data['publication_basis']
    );

    update_post_meta(
        $submission_id,
        '_photo_submission_rights_confirmation',
        $data['rights_confirmation']
    );

    update_post_meta(
        $submission_id,
        '_photo_submission_credit_submitter',
        $data['credit_submitter']
    );

    /*
     * Исходное имя файла сохраняется
     * исключительно как справочная информация.
     *
     * Оно не используется как имя файла
     * в приватном хранилище.
     */
    update_post_meta(
        $submission_id,
        '_photo_submission_original_filename',
        sanitize_file_name(
            wp_unslash( $photo['name'] )
        )
    );

    /*
     * Сведения об оригинале,
     * находящемся в приватном карантине.
     */
    update_post_meta(
        $submission_id,
        '_photo_submission_quarantine_filename',
        $processed['quarantine_filename']
    );

    update_post_meta(
        $submission_id,
        '_photo_submission_detected_mime',
        $processed['detected_mime']
    );

    update_post_meta(
        $submission_id,
        '_photo_submission_file_size',
        (int) $processed['file_size']
    );

    update_post_meta(
        $submission_id,
        '_photo_submission_width',
        (int) $processed['width']
    );

    update_post_meta(
        $submission_id,
        '_photo_submission_height',
        (int) $processed['height']
    );

    update_post_meta(
        $submission_id,
        '_photo_submission_pixel_count',
        (int) $processed['pixel_count']
    );

    /*
     * Имя безопасной перекодированной JPEG-копии.
     *
     * Здесь хранится только внутреннее имя файла,
     * а не полный путь и не публичный URL.
     */
    update_post_meta(
        $submission_id,
        '_photo_submission_safe_preview',
        $processed['safe_filename']
    );

    update_post_meta(
        $submission_id,
        '_photo_submission_processing_status',
        'safe'
    );

    update_post_meta(
        $submission_id,
        '_photo_submission_processing_error',
        ''
    );

    /*
     * Новая заявка ожидает решения редактора.
     */
    update_post_meta(
        $submission_id,
        '_photo_submission_review_status',
        'pending'
    );

    update_post_meta(
        $submission_id,
        '_photo_submission_conversion_status',
        ''
    );

    update_post_meta(
        $submission_id,
        '_photo_submission_submitted_at',
        current_time( 'mysql' )
    );

    return (int) $submission_id;
}