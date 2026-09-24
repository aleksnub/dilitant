<?php
/**
 * Работа с публичным HTTP-запросом отправки фотографии.
 *
 * Файл отвечает за:
 * - определение отправки формы;
 * - проверку nonce;
 * - чтение и очистку полей;
 * - проверку обязательных данных;
 * - получение загруженного файла;
 * - перенаправление после обработки заявки.
 *
 * Он не обрабатывает изображение
 * и не создаёт записи photo_submission.
 *
 * @package Dilitant_Land
 */


/**
 * Проверяет, является ли текущий запрос
 * отправкой публичной формы фотографии.
 *
 * @return bool
 */
function dilitant_land_photo_submission_is_public_request() {
    return (
        isset( $_SERVER['REQUEST_METHOD'] )
        && 'POST' === $_SERVER['REQUEST_METHOD']
        && isset(
            $_POST['dilitant_land_photo_submission_submit']
        )
    );
}


/**
 * Возвращает строковое поле из POST.
 *
 * @param string $key Имя поля.
 *
 * @return string
 */
function dilitant_land_photo_submission_post_text( $key ) {
    if ( ! isset( $_POST[ $key ] ) ) {
        return '';
    }

    return sanitize_text_field(
        wp_unslash( $_POST[ $key ] )
    );
}


/**
 * Возвращает многострочное поле из POST.
 *
 * @param string $key Имя поля.
 *
 * @return string
 */
function dilitant_land_photo_submission_post_textarea( $key ) {
    if ( ! isset( $_POST[ $key ] ) ) {
        return '';
    }

    return sanitize_textarea_field(
        wp_unslash( $_POST[ $key ] )
    );
}


/**
 * Проверяет защитный nonce формы.
 *
 * Nonce — одноразовый защитный маркер WordPress,
 * подтверждающий, что запрос пришёл
 * из нашей формы.
 *
 * @return bool
 */
function dilitant_land_photo_submission_verify_nonce() {
    if (
        ! isset(
            $_POST['dilitant_land_photo_submission_nonce']
        )
    ) {
        return false;
    }

    $nonce = sanitize_text_field(
        wp_unslash(
            $_POST['dilitant_land_photo_submission_nonce']
        )
    );

    return (bool) wp_verify_nonce(
        $nonce,
        'dilitant_land_photo_submission'
    );
}


/**
 * Читает и проверяет данные публичной формы.
 *
 * Необязательные исторические сведения
 * могут оставаться пустыми.
 *
 * @return array|WP_Error
 */
function dilitant_land_photo_submission_read_request_data() {
    $submitter_name =
        dilitant_land_photo_submission_post_text(
            'submitter_name'
        );

    $submitter_email =
        dilitant_land_photo_submission_post_text(
            'submitter_email'
        );

    $author_name =
        dilitant_land_photo_submission_post_text(
            'author_name'
        );

    $approx_date =
        dilitant_land_photo_submission_post_text(
            'approx_date'
        );

    $place =
        dilitant_land_photo_submission_post_text(
            'place'
        );

    $source =
        dilitant_land_photo_submission_post_text(
            'source'
        );

    $description =
        dilitant_land_photo_submission_post_textarea(
            'description'
        );

    $publication_basis =
        dilitant_land_photo_submission_post_textarea(
            'publication_basis'
        );

    $rights_confirmation =
        isset( $_POST['rights_confirmation'] )
            ? '1'
            : '';

    $credit_submitter =
        isset( $_POST['credit_submitter'] )
            ? '1'
            : '';

    if ( '' === $submitter_name ) {
        return new WP_Error(
            'submitter_name_missing',
            'Не указано имя отправителя.'
        );
    }

    if ( '' === $submitter_email ) {
        return new WP_Error(
            'submitter_email_missing',
            'Не указан адрес электронной почты.'
        );
    }

    if ( ! is_email( $submitter_email ) ) {
        return new WP_Error(
            'invalid_email',
            'Некорректный адрес электронной почты.'
        );
    }

    if ( '' === $publication_basis ) {
        return new WP_Error(
            'publication_basis_missing',
            'Не указано основание для публикации.'
        );
    }

    if ( '1' !== $rights_confirmation ) {
        return new WP_Error(
            'rights_not_confirmed',
            'Не подтверждено право передачи фотографии.'
        );
    }

    return array(
        'submitter_name'      => $submitter_name,
        'submitter_email'     => sanitize_email(
            $submitter_email
        ),
        'author_name'         => $author_name,
        'approx_date'         => $approx_date,
        'place'               => $place,
        'source'              => $source,
        'description'         => $description,
        'publication_basis'   => $publication_basis,
        'rights_confirmation' => $rights_confirmation,
        'credit_submitter'    => $credit_submitter,
    );
}


/**
 * Получает фотографию из HTTP-upload.
 *
 * @return array|WP_Error
 */
function dilitant_land_photo_submission_get_uploaded_photo() {
    if (
        ! isset( $_FILES['photo'] )
        || ! is_array( $_FILES['photo'] )
    ) {
        return new WP_Error(
            'file_missing',
            'Файл фотографии не получен.'
        );
    }

    $photo = $_FILES['photo'];

    if (
        ! isset(
            $photo['error'],
            $photo['tmp_name'],
            $photo['name']
        )
    ) {
        return new WP_Error(
            'upload_failed',
            'Получены неполные данные загрузки.'
        );
    }

    if ( UPLOAD_ERR_OK !== (int) $photo['error'] ) {
        return new WP_Error(
            'upload_failed',
            'PHP сообщил об ошибке загрузки файла.'
        );
    }

    if ( '' === $photo['tmp_name'] ) {
        return new WP_Error(
            'upload_failed',
            'Временный файл загрузки отсутствует.'
        );
    }

    return $photo;
}


/**
 * Возвращает базовый адрес публичной формы.
 *
 * @return string
 */
function dilitant_land_photo_submission_form_url() {
    return home_url( '/diletant-armenia/' );
}


/**
 * Перенаправляет посетителя после успешной отправки.
 */
function dilitant_land_photo_submission_redirect_success() {
    $url = add_query_arg(
        'photo_submission',
        'success',
        dilitant_land_photo_submission_form_url()
    );

    $url .= '#photo-submission';

    wp_safe_redirect( $url );
    exit;
}


/**
 * Перенаправляет посетителя после ошибки.
 *
 * Через URL передаётся только внутренний код ошибки.
 * Техническое сообщение WP_Error посетителю
 * напрямую не показывается.
 *
 * @param string $error_code Код ошибки.
 */
function dilitant_land_photo_submission_redirect_error(
    $error_code
) {
    $allowed_codes = array(
        'invalid_request',
        'invalid_nonce',
        'submitter_name_missing',
        'submitter_email_missing',
        'invalid_email',
        'publication_basis_missing',
        'rights_not_confirmed',
        'file_missing',
        'upload_failed',
        'invalid_file',
        'processing_failed',
        'save_failed',
    );

    if ( ! in_array( $error_code, $allowed_codes, true ) ) {
        $error_code = 'processing_failed';
    }

    $url = add_query_arg(
        'photo_submission_error',
        $error_code,
        dilitant_land_photo_submission_form_url()
    );

    $url .= '#photo-submission';

    wp_safe_redirect( $url );
    exit;
}