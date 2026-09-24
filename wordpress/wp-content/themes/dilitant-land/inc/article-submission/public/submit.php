<?php
/**
 * Координатор публичной отправки статьи.
 *
 * Последовательность:
 * 1. определить отправку формы;
 * 2. проверить nonce;
 * 3. проверить данные формы;
 * 4. получить HTTP-upload;
 * 5. проверить и сохранить рукопись;
 * 6. создать article_submission;
 * 7. перенаправить посетителя на обычный GET.
 *
 * @package Dilitant_Land
 */

require_once __DIR__ . '/submit/request.php';
require_once __DIR__ . '/submit/storage.php';


/**
 * Преобразует внутреннюю ошибку проверки файла
 * в безопасный публичный код ошибки.
 *
 * @param WP_Error $error Внутренняя ошибка.
 *
 * @return string
 */
function dilitant_land_article_submission_public_processing_error(
    $error
) {
    $invalid_file_codes = array(
        'empty_file',
        'file_too_large',
        'unsupported_extension',
        'invalid_docx_mime',
        'invalid_docx_zip',
        'invalid_docx_structure',
        'invalid_doc_mime',
        'invalid_doc_signature',
    );

    if (
        is_wp_error( $error )
        && in_array(
            $error->get_error_code(),
            $invalid_file_codes,
            true
        )
    ) {
        return 'invalid_file';
    }

    return 'processing_failed';
}


/**
 * Обрабатывает публичную отправку статьи.
 */
function dilitant_land_article_submission_handle_public_submit() {

    /*
     * Не запрос нашей формы — ничего не делаем.
     */
    if ( ! dilitant_land_article_submission_is_public_request() ) {
        return;
    }

    /*
     * Проверяем защитный маркер формы.
     */
    if ( ! dilitant_land_article_submission_verify_nonce() ) {
        dilitant_land_article_submission_redirect_error(
            'invalid_nonce'
        );
    }

    /*
     * Читаем и проверяем текстовые данные.
     */
    $data =
        dilitant_land_article_submission_read_request_data();

    if ( is_wp_error( $data ) ) {
        dilitant_land_article_submission_redirect_error(
            $data->get_error_code()
        );
    }

    /*
     * Получаем настоящий HTTP-upload.
     */
    $file =
        dilitant_land_article_submission_get_uploaded_file();

    if ( is_wp_error( $file ) ) {
        dilitant_land_article_submission_redirect_error(
            $file->get_error_code()
        );
    }

    /*
     * Проверяем DOC/DOCX и только после проверки
     * сохраняем файл в приватном хранилище.
     */
    $processed =
        dilitant_land_article_submission_process_upload(
            $file
        );

    if ( is_wp_error( $processed ) ) {
        dilitant_land_article_submission_redirect_error(
            dilitant_land_article_submission_public_processing_error(
                $processed
            )
        );
    }

    /*
     * Создаём внутреннюю заявку
     * и сохраняем её метаданные.
     */
    $submission_id =
        dilitant_land_article_submission_store(
            $data,
            $processed
        );

    if ( is_wp_error( $submission_id ) ) {
        dilitant_land_article_submission_redirect_error(
            'save_failed'
        );
    }

    /*
     * POST завершён.
     *
     * Переходим на GET, чтобы обновление страницы
     * не отправляло рукопись повторно.
     */
    dilitant_land_article_submission_redirect_success();
}

add_action(
    'template_redirect',
    'dilitant_land_article_submission_handle_public_submit'
);