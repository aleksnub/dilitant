<?php
/**
 * Работа с публичным HTTP-запросом отправки статьи.
 *
 * Файл отвечает за:
 * - определение отправки формы;
 * - проверку nonce;
 * - чтение и очистку полей;
 * - проверку обязательных данных;
 * - получение загруженного файла;
 * - перенаправление после обработки заявки.
 *
 * Он не проверяет содержимое DOC/DOCX
 * и не создаёт article_submission.
 *
 * @package Dilitant_Land
 */


/**
 * Проверяет, является ли текущий запрос
 * отправкой публичной формы статьи.
 *
 * @return bool
 */
function dilitant_land_article_submission_is_public_request() {
    return (
        isset( $_SERVER['REQUEST_METHOD'] )
        && 'POST' === $_SERVER['REQUEST_METHOD']
        && isset(
            $_POST['dilitant_land_article_submission_submit']
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
function dilitant_land_article_submission_post_text( $key ) {
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
function dilitant_land_article_submission_post_textarea( $key ) {
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
 * Nonce — защитный маркер WordPress,
 * связывающий запрос с нашей формой.
 *
 * @return bool
 */
function dilitant_land_article_submission_verify_nonce() {
    if (
        ! isset(
            $_POST['dilitant_land_article_submission_nonce']
        )
    ) {
        return false;
    }

    $nonce = sanitize_text_field(
        wp_unslash(
            $_POST['dilitant_land_article_submission_nonce']
        )
    );

    return (bool) wp_verify_nonce(
        $nonce,
        'dilitant_land_article_submission'
    );
}


/**
 * Читает и проверяет текстовые данные формы.
 *
 * Название статьи и комментарий необязательны.
 *
 * @return array|WP_Error
 */
function dilitant_land_article_submission_read_request_data() {
    $submitter_name =
        dilitant_land_article_submission_post_text(
            'article_submitter_name'
        );

    $submitter_email =
        dilitant_land_article_submission_post_text(
            'article_submitter_email'
        );

    $article_title =
        dilitant_land_article_submission_post_text(
            'article_title'
        );

    $submitter_comment =
        dilitant_land_article_submission_post_textarea(
            'article_submitter_comment'
        );

    $rights_confirmation =
        isset( $_POST['article_rights_confirmation'] )
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

    if ( '1' !== $rights_confirmation ) {
        return new WP_Error(
            'rights_not_confirmed',
            'Не подтверждено право передачи рукописи.'
        );
    }

    return array(
        'submitter_name'      => $submitter_name,
        'submitter_email'     => sanitize_email(
            $submitter_email
        ),
        'article_title'       => $article_title,
        'submitter_comment'   => $submitter_comment,
        'rights_confirmation' => $rights_confirmation,
    );
}


/**
 * Получает рукопись из HTTP-upload.
 *
 * Содержимое файла здесь ещё не проверяется.
 *
 * @return array|WP_Error
 */
function dilitant_land_article_submission_get_uploaded_file() {
    if (
        ! isset( $_FILES['article_file'] )
        || ! is_array( $_FILES['article_file'] )
    ) {
        return new WP_Error(
            'file_missing',
            'Файл статьи не получен.'
        );
    }

    $file = $_FILES['article_file'];

    if (
        ! isset(
            $file['error'],
            $file['tmp_name'],
            $file['name'],
            $file['size']
        )
    ) {
        return new WP_Error(
            'upload_failed',
            'Получены неполные данные загрузки.'
        );
    }

    if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
        return new WP_Error(
            'upload_failed',
            'PHP сообщил об ошибке загрузки файла.'
        );
    }

    if ( '' === $file['tmp_name'] ) {
        return new WP_Error(
            'upload_failed',
            'Временный файл загрузки отсутствует.'
        );
    }

    return $file;
}


/**
 * Возвращает адрес страницы формы.
 *
 * @return string
 */
function dilitant_land_article_submission_form_url() {
    return home_url( '/diletant-armenia/' );
}


/**
 * Перенаправляет посетителя после успешной отправки.
 */
function dilitant_land_article_submission_redirect_success() {
    $url = add_query_arg(
        'article_submission',
        'success',
        dilitant_land_article_submission_form_url()
    );

    $url .= '#article-submission';

    wp_safe_redirect( $url );
    exit;
}


/**
 * Перенаправляет посетителя после ошибки.
 *
 * В URL передаётся только разрешённый код ошибки,
 * а не техническое сообщение.
 *
 * @param string $error_code Код ошибки.
 */
function dilitant_land_article_submission_redirect_error(
    $error_code
) {
    $allowed_codes = array(
        'invalid_request',
        'invalid_nonce',
        'submitter_name_missing',
        'submitter_email_missing',
        'invalid_email',
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
        'article_submission_error',
        $error_code,
        dilitant_land_article_submission_form_url()
    );

    $url .= '#article-submission';

    wp_safe_redirect( $url );
    exit;
}