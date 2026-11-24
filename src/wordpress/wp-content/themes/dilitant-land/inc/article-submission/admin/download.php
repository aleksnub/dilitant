<?php
/**
 * Защищённое скачивание рукописи редактором.
 *
 * Файл рукописи хранится вне публичного каталога
 * WordPress и не имеет собственного HTTP-адреса.
 *
 * WordPress отдаёт файл только после проверки:
 * - авторизации пользователя;
 * - права редактировать конкретную заявку;
 * - nonce;
 * - связи файла с этой заявкой;
 * - допустимого внутреннего имени файла.
 *
 * @package Dilitant_Land
 */


/**
 * Создаёт защищённый URL для скачивания
 * рукописи конкретной заявки.
 *
 * @param int $submission_id ID заявки.
 *
 * @return string
 */
function dilitant_land_article_submission_download_url(
    $submission_id
) {
    $submission_id = (int) $submission_id;

    $url = add_query_arg(
        array(
            'action'        =>
                'dilitant_article_submission_download',
            'submission_id' => $submission_id,
        ),
        admin_url( 'admin-post.php' )
    );

    return wp_nonce_url(
        $url,
        'dilitant_article_submission_download_'
            . $submission_id,
        '_wpnonce'
    );
}


/**
 * Выводит кнопку скачивания рукописи.
 *
 * @param WP_Post $post Текущая заявка.
 */
function dilitant_land_article_submission_download_control(
    $post
) {
    $stored_filename =
        dilitant_land_article_submission_meta_value(
            $post->ID,
            'stored_filename'
        );

    if ( ! $stored_filename ) {
        dilitant_land_article_submission_admin_value(
            'Рукопись',
            'Файл отсутствует'
        );

        return;
    }

    $download_url =
        dilitant_land_article_submission_download_url(
            $post->ID
        );
    ?>

    <p>
        <a
            href="<?php echo esc_url( $download_url ); ?>"
            class="button button-secondary"
        >
            Скачать рукопись
        </a>
    </p>

    <?php
}


/**
 * Отдаёт приватную рукопись редактору.
 */
function dilitant_land_article_submission_serve_download() {

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

    if ( $submission_id <= 0 ) {
        wp_die(
            'Заявка не указана.',
            'Некорректный запрос',
            array( 'response' => 400 )
        );
    }

    if (
        'article_submission'
        !== get_post_type( $submission_id )
    ) {
        wp_die(
            'Заявка не найдена.',
            'Заявка не найдена',
            array( 'response' => 404 )
        );
    }

    if (
        ! current_user_can(
            'edit_post',
            $submission_id
        )
    ) {
        wp_die(
            'Недостаточно прав для скачивания рукописи.',
            'Доступ запрещён',
            array( 'response' => 403 )
        );
    }

    /*
     * Nonce — защитный маркер WordPress.
     * Здесь он привязан к конкретной заявке.
     */
    check_admin_referer(
        'dilitant_article_submission_download_'
            . $submission_id
    );

    $stored_filename =
        dilitant_land_article_submission_meta_value(
            $submission_id,
            'stored_filename'
        );

    if (
        ! is_string( $stored_filename )
        || '' === $stored_filename
    ) {
        wp_die(
            'Файл рукописи отсутствует.',
            'Файл недоступен',
            array( 'response' => 404 )
        );
    }

    /*
     * Файлы создаются системой:
     *
     * 64 шестнадцатеричных символа
     * + расширение .doc или .docx.
     *
     * Произвольный путь из базы здесь
     * использовать запрещено.
     */
    if (
        ! preg_match(
            '/\A[a-f0-9]{64}\.(doc|docx)\z/',
            $stored_filename
        )
    ) {
        wp_die(
            'Некорректное внутреннее имя файла.',
            'Файл недоступен',
            array( 'response' => 404 )
        );
    }

    $path =
        trailingslashit(
            dilitant_land_article_submission_storage_dir()
        )
        . $stored_filename;

    if (
        ! is_file( $path )
        || ! is_readable( $path )
    ) {
        wp_die(
            'Файл рукописи не найден.',
            'Файл недоступен',
            array( 'response' => 404 )
        );
    }

    /*
     * Имя, которое увидит редактор при скачивании.
     */
    $original_filename =
        dilitant_land_article_submission_meta_value(
            $submission_id,
            'original_filename'
        );

    $extension =
        strtolower(
            (string) pathinfo(
                $stored_filename,
                PATHINFO_EXTENSION
            )
        );

    $download_filename =
        sanitize_file_name(
            (string) $original_filename
        );

    /*
     * Если исходное имя почему-либо отсутствует,
     * создаём безопасное понятное имя.
     */
    if ( '' === $download_filename ) {
        $download_filename =
            'article-'
            . $submission_id
            . '.'
            . $extension;
    }

    /*
     * Не позволяем метаданным подменить расширение
     * фактически сохранённого файла.
     */
    if (
        strtolower(
            (string) pathinfo(
                $download_filename,
                PATHINFO_EXTENSION
            )
        )
        !== $extension
    ) {
        $download_filename =
            'article-'
            . $submission_id
            . '.'
            . $extension;
    }

    $content_type =
        'docx' === $extension
            ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            : 'application/msword';

    nocache_headers();

    header(
        'Content-Type: ' . $content_type
    );

    header(
        'Content-Length: '
        . (string) filesize( $path )
    );

    header(
        'Content-Disposition: attachment; filename="'
        . $download_filename
        . '"'
    );

    header( 'X-Content-Type-Options: nosniff' );

    readfile( $path );
    exit;
}


add_action(
    'admin_post_dilitant_article_submission_download',
    'dilitant_land_article_submission_serve_download'
);