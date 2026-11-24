<?php
/**
 * Административная очистка старых файлов заявок.
 *
 * Позволяет вручную удалить старые осиротевшие файлы
 * из приватного карантина и каталога безопасных копий.
 *
 * Файл разрешено удалить, только если:
 * 1. ему больше установленного срока хранения;
 * 2. он не связан ни с одной существующей заявкой.
 *
 * @package Dilitant_Land
 */


/**
 * Возраст файла, после которого он считается старым.
 */
const DILITANT_LAND_PHOTO_CLEANUP_DAYS = 30;


/**
 * Добавляет страницу очистки в раздел заявок на фотографии.
 */
function dilitant_land_photo_submission_add_cleanup_page() {
    add_submenu_page(
        'edit.php?post_type=photo_submission',
        'Очистка старых файлов',
        'Очистка файлов',
        'manage_options',
        'photo-submission-cleanup',
        'dilitant_land_photo_submission_render_cleanup_page'
    );
}

add_action(
    'admin_menu',
    'dilitant_land_photo_submission_add_cleanup_page'
);


/**
 * Получает внутренние имена файлов,
 * связанных с существующими заявками.
 *
 * @param string $meta_key Ключ метаданных заявки.
 *
 * @return array
 */
function dilitant_land_photo_submission_get_used_filenames(
    $meta_key
) {
    global $wpdb;

    $rows = $wpdb->get_col(
        $wpdb->prepare(
            "
            SELECT DISTINCT pm.meta_value
            FROM {$wpdb->postmeta} AS pm
            INNER JOIN {$wpdb->posts} AS p
                ON p.ID = pm.post_id
            WHERE p.post_type = %s
              AND pm.meta_key = %s
              AND pm.meta_value <> ''
            ",
            'photo_submission',
            $meta_key
        )
    );

    if ( ! is_array( $rows ) ) {
        return array();
    }

    $filenames = array();

    foreach ( $rows as $filename ) {
        $filename = basename( (string) $filename );

        if ( '' !== $filename ) {
            $filenames[ $filename ] = true;
        }
    }

    return $filenames;
}


/**
 * Анализирует один приватный каталог.
 *
 * Старый файл считается разрешённым к удалению,
 * только если он не связан с существующей заявкой.
 *
 * @param string $directory      Абсолютный путь к каталогу.
 * @param array  $used_filenames Имена защищённых файлов.
 *
 * @return array
 */
function dilitant_land_photo_submission_scan_cleanup_directory(
    $directory,
    $used_filenames
) {
    $result = array(
        'total'     => 0,
        'old'       => 0,
        'protected' => 0,
        'deletable' => 0,
    );

    if ( ! is_dir( $directory ) ) {
        return $result;
    }

    $items = scandir( $directory );

    if ( false === $items ) {
        return $result;
    }

    $cutoff =
        time()
        - (
            DILITANT_LAND_PHOTO_CLEANUP_DAYS
            * DAY_IN_SECONDS
        );

    foreach ( $items as $item ) {
        if ( '.' === $item || '..' === $item ) {
            continue;
        }

        $path = trailingslashit( $directory ) . $item;

        if ( ! is_file( $path ) ) {
            continue;
        }

        $result['total']++;

        $modified_time = filemtime( $path );

        if (
            false === $modified_time
            || $modified_time >= $cutoff
        ) {
            continue;
        }

        $result['old']++;

        if ( isset( $used_filenames[ $item ] ) ) {
            $result['protected']++;
            continue;
        }

        $result['deletable']++;
    }

    return $result;
}


/**
 * Удаляет старые осиротевшие файлы из одного каталога.
 *
 * Перед каждым удалением повторно проверяются:
 * - существование обычного файла;
 * - возраст;
 * - отсутствие связи с заявкой.
 *
 * @param string $directory      Абсолютный путь к каталогу.
 * @param array  $used_filenames Имена защищённых файлов.
 *
 * @return array
 */
function dilitant_land_photo_submission_cleanup_directory(
    $directory,
    $used_filenames
) {
    $result = array(
        'deleted' => 0,
        'errors'  => 0,
    );

    if (
        ! is_dir( $directory )
        || ! is_readable( $directory )
    ) {
        $result['errors']++;
        return $result;
    }

    $items = scandir( $directory );

    if ( false === $items ) {
        $result['errors']++;
        return $result;
    }

    $cutoff =
        time()
        - (
            DILITANT_LAND_PHOTO_CLEANUP_DAYS
            * DAY_IN_SECONDS
        );

    foreach ( $items as $item ) {
        if ( '.' === $item || '..' === $item ) {
            continue;
        }

        /*
         * Используем только имя файла из самого каталога.
         * Внешний путь или имя от пользователя сюда
         * не передаются.
         */
        if ( basename( $item ) !== $item ) {
            continue;
        }

        $path = trailingslashit( $directory ) . $item;

        if ( ! is_file( $path ) ) {
            continue;
        }

        $modified_time = filemtime( $path );

        if (
            false === $modified_time
            || $modified_time >= $cutoff
        ) {
            continue;
        }

        if ( isset( $used_filenames[ $item ] ) ) {
            continue;
        }

        if ( unlink( $path ) ) {
            $result['deleted']++;
        } else {
            $result['errors']++;
        }
    }

    return $result;
}


/**
 * Обрабатывает ручной запуск очистки.
 *
 * @return array|null Результат очистки или null,
 *                    если очистка не запускалась.
 */
function dilitant_land_photo_submission_handle_cleanup() {
    if (
        'POST' !== $_SERVER['REQUEST_METHOD']
        || ! isset( $_POST['dilitant_land_photo_cleanup_submit'] )
    ) {
        return null;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die(
            'Недостаточно прав для выполнения этой операции.'
        );
    }

    check_admin_referer(
        'dilitant_land_photo_cleanup'
    );

    /*
     * Список защищённых файлов получаем заново
     * непосредственно перед удалением.
     */
    $used_quarantine =
        dilitant_land_photo_submission_get_used_filenames(
            '_photo_submission_quarantine_filename'
        );

    $used_safe =
        dilitant_land_photo_submission_get_used_filenames(
            '_photo_submission_safe_preview'
        );

    $quarantine =
        dilitant_land_photo_submission_cleanup_directory(
            dilitant_land_photo_submission_quarantine_dir(),
            $used_quarantine
        );

    $safe =
        dilitant_land_photo_submission_cleanup_directory(
            dilitant_land_private_storage_path( 'photo-safe' ),
            $used_safe
        );

    return array(
        'deleted' =>
            $quarantine['deleted']
            + $safe['deleted'],

        'errors' =>
            $quarantine['errors']
            + $safe['errors'],
    );
}


/**
 * Выводит административную страницу очистки.
 */
function dilitant_land_photo_submission_render_cleanup_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die(
            'Недостаточно прав для выполнения этой операции.'
        );
    }

    /*
     * Если пользователь нажал кнопку, сначала выполняем
     * очистку, а затем заново строим статистику.
     */
    $cleanup_result =
        dilitant_land_photo_submission_handle_cleanup();

    $quarantine_dir =
        dilitant_land_photo_submission_quarantine_dir();

    $safe_dir =
        dilitant_land_private_storage_path( 'photo-safe' );

    $used_quarantine =
        dilitant_land_photo_submission_get_used_filenames(
            '_photo_submission_quarantine_filename'
        );

    $used_safe =
        dilitant_land_photo_submission_get_used_filenames(
            '_photo_submission_safe_preview'
        );

    $quarantine =
        dilitant_land_photo_submission_scan_cleanup_directory(
            $quarantine_dir,
            $used_quarantine
        );

    $safe =
        dilitant_land_photo_submission_scan_cleanup_directory(
            $safe_dir,
            $used_safe
        );

    $deletable_total =
        $quarantine['deletable']
        + $safe['deletable'];

    ?>
    <div class="wrap">
        <h1>Очистка старых файлов</h1>

        <?php if ( null !== $cleanup_result ) : ?>

            <?php if ( 0 === $cleanup_result['errors'] ) : ?>

                <div class="notice notice-success is-dismissible">
                    <p>
                        Очистка завершена.
                        Удалено файлов:
                        <strong>
                            <?php
                            echo esc_html(
                                $cleanup_result['deleted']
                            );
                            ?>
                        </strong>.
                    </p>
                </div>

            <?php else : ?>

                <div class="notice notice-warning">
                    <p>
                        Очистка завершена.
                        Удалено файлов:
                        <strong>
                            <?php
                            echo esc_html(
                                $cleanup_result['deleted']
                            );
                            ?>
                        </strong>.
                        Ошибок удаления:
                        <strong>
                            <?php
                            echo esc_html(
                                $cleanup_result['errors']
                            );
                            ?>
                        </strong>.
                    </p>
                </div>

            <?php endif; ?>

        <?php endif; ?>

        <p>
            Старым считается файл, которому больше
            <?php
            echo esc_html(
                DILITANT_LAND_PHOTO_CLEANUP_DAYS
            );
            ?>
            дней.
        </p>

        <p>
            Файлы, связанные с существующими заявками,
            защищены от этой очистки.
        </p>

        <h2>Карантинные оригиналы</h2>

        <table
            class="widefat striped"
            style="max-width: 700px;"
        >
            <tbody>
                <tr>
                    <td>Всего файлов</td>
                    <td>
                        <?php
                        echo esc_html(
                            $quarantine['total']
                        );
                        ?>
                    </td>
                </tr>

                <tr>
                    <td>Старше 30 дней</td>
                    <td>
                        <?php
                        echo esc_html(
                            $quarantine['old']
                        );
                        ?>
                    </td>
                </tr>

                <tr>
                    <td>
                        Защищены существующими заявками
                    </td>
                    <td>
                        <?php
                        echo esc_html(
                            $quarantine['protected']
                        );
                        ?>
                    </td>
                </tr>

                <tr>
                    <td>Можно удалить</td>
                    <td>
                        <strong>
                            <?php
                            echo esc_html(
                                $quarantine['deletable']
                            );
                            ?>
                        </strong>
                    </td>
                </tr>
            </tbody>
        </table>

        <h2>Безопасные JPEG-копии</h2>

        <table
            class="widefat striped"
            style="max-width: 700px;"
        >
            <tbody>
                <tr>
                    <td>Всего файлов</td>
                    <td>
                        <?php
                        echo esc_html(
                            $safe['total']
                        );
                        ?>
                    </td>
                </tr>

                <tr>
                    <td>Старше 30 дней</td>
                    <td>
                        <?php
                        echo esc_html(
                            $safe['old']
                        );
                        ?>
                    </td>
                </tr>

                <tr>
                    <td>
                        Защищены существующими заявками
                    </td>
                    <td>
                        <?php
                        echo esc_html(
                            $safe['protected']
                        );
                        ?>
                    </td>
                </tr>

                <tr>
                    <td>Можно удалить</td>
                    <td>
                        <strong>
                            <?php
                            echo esc_html(
                                $safe['deletable']
                            );
                            ?>
                        </strong>
                    </td>
                </tr>
            </tbody>
        </table>

        <h2>Результат</h2>

        <p>
            Сейчас разрешено удалить:
            <strong>
                <?php
                echo esc_html(
                    $deletable_total
                );
                ?>
            </strong>
            файлов.
        </p>

        <form method="post">
            <?php
            wp_nonce_field(
                'dilitant_land_photo_cleanup'
            );
            ?>

            <p>
                <button
                    type="submit"
                    name="dilitant_land_photo_cleanup_submit"
                    value="1"
                    class="button button-primary"
                    <?php disabled( 0 === $deletable_total ); ?>
                >
                    Очистить файлы старше 30 дней
                </button>
            </p>

            <p class="description">
                Будут удалены только старые файлы,
                которые не связаны ни с одной
                существующей заявкой.
            </p>
        </form>
    </div>
    <?php
}