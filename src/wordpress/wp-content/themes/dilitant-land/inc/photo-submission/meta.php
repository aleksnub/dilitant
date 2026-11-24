<?php
/**
 * Метаданные заявки на архивную фотографию.
 *
 * Здесь описывается модель данных photo_submission.
 * Публичная форма, обработка файла и административный
 * интерфейс используют эту модель, но не определяют её.
 *
 * @package Dilitant_Land
 */


/**
 * Регистрация метаданных photo_submission.
 */
function dilitant_land_register_photo_submission_meta() {

    /*
     * Данные отправителя.
     */
    $submitter_text_fields = array(
        '_photo_submission_submitter_name',
        '_photo_submission_submitter_email',
    );

    /*
     * Сведения о фотографии.
     */
    $photo_text_fields = array(
        '_photo_submission_author_name',
        '_photo_submission_approx_date',
        '_photo_submission_place',
        '_photo_submission_source',
    );

    /*
     * Правовое основание и указание имени отправителя.
     */
    $rights_text_fields = array(
        '_photo_submission_publication_basis',
        '_photo_submission_credit_submitter',
    );

    /*
     * Технические строковые сведения о файле.
     *
     * quarantine_filename — внутреннее имя оригинала
     * в закрытом карантинном хранилище.
     *
     * safe_preview — ссылка/идентификатор только
     * безопасной производной копии, не оригинала.
     */
    $file_text_fields = array(
        '_photo_submission_original_filename',
        '_photo_submission_quarantine_filename',
        '_photo_submission_detected_mime',
        '_photo_submission_processing_status',
        '_photo_submission_processing_error',
        '_photo_submission_safe_preview',
    );

    /*
     * Состояние редакционной проверки
     * и внутреннее состояние преобразования в фотоархив.
     *
     * conversion_status используется системой для
     * атомарного захвата заявки, фиксации завершения
     * преобразования и восстановления после сбоя.
     */
    $review_text_fields = array(
        '_photo_submission_review_status',
        '_photo_submission_conversion_status',
    );

    $text_fields = array_merge(
        $submitter_text_fields,
        $photo_text_fields,
        $rights_text_fields,
        $file_text_fields,
        $review_text_fields
    );

    foreach ( $text_fields as $meta_key ) {
        register_post_meta(
            'photo_submission',
            $meta_key,
            array(
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => false,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback'     => function() {
                    return current_user_can( 'edit_posts' );
                },
            )
        );
    }


    /*
     * Многострочные пользовательские и редакционные данные.
     */
    $textarea_fields = array(
        '_photo_submission_description',
        '_photo_submission_rights_confirmation',
        '_photo_submission_reviewer_note',
    );

    foreach ( $textarea_fields as $meta_key ) {
        register_post_meta(
            'photo_submission',
            $meta_key,
            array(
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => false,
                'sanitize_callback' => 'sanitize_textarea_field',
                'auth_callback'     => function() {
                    return current_user_can( 'edit_posts' );
                },
            )
        );
    }


    /*
     * Числовые технические характеристики файла
     * и внутренние связи заявки.
     */
    $integer_fields = array(
        '_photo_submission_file_size',
        '_photo_submission_width',
        '_photo_submission_height',
        '_photo_submission_pixel_count',
        '_photo_submission_reviewed_by',
        '_photo_submission_archive_photo_id',
    );

    foreach ( $integer_fields as $meta_key ) {
        register_post_meta(
            'photo_submission',
            $meta_key,
            array(
                'type'              => 'integer',
                'single'            => true,
                'show_in_rest'      => false,
                'sanitize_callback' => 'absint',
                'auth_callback'     => function() {
                    return current_user_can( 'edit_posts' );
                },
            )
        );
    }


    /*
     * Временные отметки храним в стандартизированном
     * строковом формате. Значения устанавливает система,
     * а не посетитель.
     *
     * conversion_started_at — время, когда система
     * захватила заявку для преобразования. Используется
     * для обнаружения зависшего преобразования после сбоя.
     */
    $datetime_fields = array(
        '_photo_submission_submitted_at',
        '_photo_submission_reviewed_at',
        '_photo_submission_conversion_started_at',
    );

    foreach ( $datetime_fields as $meta_key ) {
        register_post_meta(
            'photo_submission',
            $meta_key,
            array(
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => false,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback'     => function() {
                    return current_user_can( 'edit_posts' );
                },
            )
        );
    }
}

add_action(
    'init',
    'dilitant_land_register_photo_submission_meta'
);