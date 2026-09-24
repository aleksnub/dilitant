<?php
/**
 * Безопасный административный просмотр
 * фотографии из заявки.
 *
 * Безопасная JPEG-копия хранится вне публичного
 * каталога WordPress и не имеет собственного URL.
 *
 * WordPress отдаёт изображение только после проверки:
 * - авторизации пользователя;
 * - права редактировать конкретную заявку;
 * - nonce;
 * - связи файла с этой заявкой.
 *
 * @package Dilitant_Land
 */


/**
 * Возвращает путь к каталогу безопасных JPEG-копий.
 *
 * @return string
 */
function dilitant_land_photo_submission_safe_dir() {
    return dilitant_land_private_storage_path( 'photo-safe' );
}


/**
 * Создаёт защищённый URL для просмотра
 * безопасной фотографии конкретной заявки.
 *
 * @param int $submission_id ID заявки.
 *
 * @return string
 */
function dilitant_land_photo_submission_preview_url(
    $submission_id
) {
    $submission_id = (int) $submission_id;

    $url = add_query_arg(
        array(
            'action'        => 'dilitant_photo_submission_preview',
            'submission_id' => $submission_id,
        ),
        admin_url( 'admin-post.php' )
    );

    return wp_nonce_url(
        $url,
        'dilitant_photo_submission_preview_'
            . $submission_id,
        '_wpnonce'
    );
}


/**
 * Отдаёт безопасную JPEG-копию редактору.
 */
function dilitant_land_photo_submission_serve_preview() {
    if ( ! is_user_logged_in() ) {
        wp_die(
            'Доступ запрещён.',
            'Доступ запрещён',
            array( 'response' => 403 )
        );
    }

    $submission_id = isset( $_GET['submission_id'] )
        ? absint( $_GET['submission_id'] )
        : 0;

    if ( ! $submission_id ) {
        wp_die(
            'Заявка не указана.',
            'Некорректный запрос',
            array( 'response' => 400 )
        );
    }

    if (
        'photo_submission'
        !== get_post_type( $submission_id )
    ) {
        wp_die(
            'Заявка не найдена.',
            'Заявка не найдена',
            array( 'response' => 404 )
        );
    }

    /*
     * Проверяем право пользователя работать
     * именно с этой записью.
     */
    if (
        ! current_user_can(
            'edit_post',
            $submission_id
        )
    ) {
        wp_die(
            'Недостаточно прав для просмотра фотографии.',
            'Доступ запрещён',
            array( 'response' => 403 )
        );
    }

    /*
     * URL содержит nonce, привязанный
     * к конкретной заявке.
     */
    check_admin_referer(
        'dilitant_photo_submission_preview_'
            . $submission_id
    );

    /*
     * Получаем только внутреннее имя безопасной копии,
     * записанное в метаданных заявки.
     */
    $filename = get_post_meta(
        $submission_id,
        '_photo_submission_safe_preview',
        true
    );

    if (
        ! is_string( $filename )
        || '' === $filename
    ) {
        wp_die(
            'Безопасная копия фотографии отсутствует.',
            'Фотография недоступна',
            array( 'response' => 404 )
        );
    }

    /*
     * Безопасные копии создаются системой
     * с именем из 64 шестнадцатеричных символов
     * и расширением .jpg.
     *
     * Не принимаем произвольные имена и пути
     * из метаданных.
     */
    if (
        ! preg_match(
            '/\A[a-f0-9]{64}\.jpg\z/',
            $filename
        )
    ) {
        wp_die(
            'Некорректное имя безопасной копии.',
            'Фотография недоступна',
            array( 'response' => 404 )
        );
    }

    $safe_dir =
        dilitant_land_photo_submission_safe_dir();

    $path =
        trailingslashit( $safe_dir )
        . $filename;

    if (
        ! is_file( $path )
        || ! is_readable( $path )
    ) {
        wp_die(
            'Файл безопасной копии не найден.',
            'Фотография недоступна',
            array( 'response' => 404 )
        );
    }

    /*
     * Дополнительно убеждаемся, что сохранённый
     * производный файл действительно JPEG.
     */
    $image_info = getimagesize( $path );

    if (
        false === $image_info
        || ! isset( $image_info['mime'] )
        || 'image/jpeg' !== $image_info['mime']
    ) {
        wp_die(
            'Безопасная копия имеет неверный формат.',
            'Фотография недоступна',
            array( 'response' => 500 )
        );
    }

    /*
     * Запрещаем кэширование приватной фотографии
     * общими HTTP-кэшами.
     */
    nocache_headers();

    header( 'Content-Type: image/jpeg' );
    header(
        'Content-Length: '
        . (string) filesize( $path )
    );
    header(
        'Content-Disposition: inline; filename="preview.jpg"'
    );
    header( 'X-Content-Type-Options: nosniff' );

    readfile( $path );
    exit;
}

add_action(
    'admin_post_dilitant_photo_submission_preview',
    'dilitant_land_photo_submission_serve_preview'
);