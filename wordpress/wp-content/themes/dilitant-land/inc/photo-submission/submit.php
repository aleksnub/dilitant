<?php
/**
 * Координатор публичной отправки фотографии.
 *
 * Последовательность:
 * 1. определить отправку формы;
 * 2. проверить защитный nonce;
 * 3. прочитать и проверить данные формы;
 * 4. получить HTTP-upload;
 * 5. безопасно обработать изображение;
 * 6. сохранить photo_submission;
 * 7. перенаправить посетителя на обычный GET.
 *
 * @package Dilitant_Land
 */

require_once __DIR__ . '/submit/request.php';
require_once __DIR__ . '/submit/storage.php';


/**
 * Преобразует внутреннюю ошибку обработки изображения
 * в безопасный публичный код ошибки.
 *
 * Посетителю не передаются внутренние сообщения,
 * пути к файлам и технические подробности.
 *
 * @param WP_Error $error Внутренняя ошибка.
 *
 * @return string Публичный код ошибки.
 */
function dilitant_land_photo_submission_public_processing_error(
    $error
) {
    $invalid_file_codes = array(
        'photo_submission_file_missing',
        'photo_submission_file_empty',
        'photo_submission_file_too_large',
        'photo_submission_mime_not_allowed',
        'photo_submission_image_invalid',
        'photo_submission_mime_mismatch',
        'photo_submission_width_too_large',
        'photo_submission_height_too_large',
        'photo_submission_pixel_count_too_large',
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
 * Обрабатывает публичную отправку фотографии.
 */
function dilitant_land_photo_submission_handle_public_submit() {
    /*
     * Не наш POST-запрос — ничего не делаем.
     */
    if ( ! dilitant_land_photo_submission_is_public_request() ) {
        return;
    }

    /*
     * Проверяем защитный маркер формы.
     */
    if ( ! dilitant_land_photo_submission_verify_nonce() ) {
        dilitant_land_photo_submission_redirect_error(
            'invalid_nonce'
        );
    }

    /*
     * Читаем и проверяем текстовые данные формы.
     */
    $data =
        dilitant_land_photo_submission_read_request_data();

    if ( is_wp_error( $data ) ) {
        dilitant_land_photo_submission_redirect_error(
            $data->get_error_code()
        );
    }

    /*
     * Получаем настоящий HTTP-upload.
     */
    $photo =
        dilitant_land_photo_submission_get_uploaded_photo();

    if ( is_wp_error( $photo ) ) {
        dilitant_land_photo_submission_redirect_error(
            $photo->get_error_code()
        );
    }

    /*
     * Файл проходит существующую безопасную цепочку:
     *
     * HTTP-upload
     * → приватный карантин
     * → проверка типа и размеров
     * → приватная безопасная JPEG-копия.
     */
    $processed =
        dilitant_land_photo_submission_process_upload(
            $photo['tmp_name']
        );

    if ( is_wp_error( $processed ) ) {
        dilitant_land_photo_submission_redirect_error(
            dilitant_land_photo_submission_public_processing_error(
                $processed
            )
        );
    }

    /*
     * Создаём внутреннюю заявку
     * и сохраняем её метаданные.
     */
    $submission_id =
        dilitant_land_photo_submission_store(
            $data,
            $photo,
            $processed
        );

    if ( is_wp_error( $submission_id ) ) {
        dilitant_land_photo_submission_redirect_error(
            'save_failed'
        );
    }

    /*
     * Заявка полностью сохранена.
     *
     * Переходим с POST на обычный GET,
     * поэтому обновление страницы
     * не отправит форму повторно.
     */
    dilitant_land_photo_submission_redirect_success();
}

add_action(
    'template_redirect',
    'dilitant_land_photo_submission_handle_public_submit'
);