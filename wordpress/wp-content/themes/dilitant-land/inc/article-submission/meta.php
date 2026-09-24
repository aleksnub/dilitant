<?php
/**
 * Метаданные заявки на статью.
 *
 * Здесь описывается модель данных article_submission.
 * Публичная форма, обработка файла и административный
 * интерфейс используют эту модель, но не определяют её.
 *
 * @package Dilitant_Land
 */


/**
 * Регистрация метаданных article_submission.
 */
function dilitant_land_register_article_submission_meta() {

    /*
     * Данные отправителя и сведения о рукописи.
     */
    $submission_text_fields = array(
        '_article_submission_submitter_name',
        '_article_submission_submitter_email',
        '_article_submission_article_title',
    );

    /*
     * Правовое подтверждение.
     */
    $rights_text_fields = array(
        '_article_submission_rights_confirmation',
    );

    /*
     * Технические сведения о принятом файле.
     *
     * original_filename — исходное имя файла,
     * присланного посетителем.
     *
     * stored_filename — случайное внутреннее имя файла
     * в закрытом хранилище вне публичного web-каталога.
     *
     * scan_status — состояние антивирусной проверки.
     * Значение не должно утверждать, что файл проверен,
     * если антивирусная проверка фактически не выполнялась.
     */
    $file_text_fields = array(
        '_article_submission_original_filename',
        '_article_submission_stored_filename',
        '_article_submission_detected_mime',
        '_article_submission_file_extension',
        '_article_submission_scan_status',
    );

    /*
     * Внутреннее состояние редакционной обработки заявки.
     */
    $review_text_fields = array(
        '_article_submission_review_status',
    );

    $text_fields = array_merge(
        $submission_text_fields,
        $rights_text_fields,
        $file_text_fields,
        $review_text_fields
    );

    foreach ( $text_fields as $meta_key ) {
        register_post_meta(
            'article_submission',
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
     * Многострочные пользовательские
     * и редакционные данные.
     */
    $textarea_fields = array(
        '_article_submission_submitter_comment',
        '_article_submission_reviewer_note',
    );

    foreach ( $textarea_fields as $meta_key ) {
        register_post_meta(
            'article_submission',
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
     * Числовые технические сведения
     * и данные редакционной проверки.
     */
    $integer_fields = array(
        '_article_submission_file_size',
        '_article_submission_reviewed_by',
    );

    foreach ( $integer_fields as $meta_key ) {
        register_post_meta(
            'article_submission',
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
     * Временные отметки устанавливает система,
     * а не посетитель.
     */
    $datetime_fields = array(
        '_article_submission_submitted_at',
        '_article_submission_reviewed_at',
    );

    foreach ( $datetime_fields as $meta_key ) {
        register_post_meta(
            'article_submission',
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
    'dilitant_land_register_article_submission_meta'
);