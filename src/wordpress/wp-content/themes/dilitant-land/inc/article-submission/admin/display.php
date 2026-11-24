<?php
/**
 * Отображение данных заявки на статью в wp-admin.
 *
 * Здесь находятся только функции чтения
 * и вывода данных.
 *
 * Этот модуль ничего не изменяет
 * и не сохраняет.
 *
 * @package Dilitant_Land
 */


/**
 * Получает значение метаданных заявки.
 *
 * @param int    $post_id ID заявки.
 * @param string $name    Имя поля без служебного префикса.
 *
 * @return mixed
 */
function dilitant_land_article_submission_meta_value(
    $post_id,
    $name
) {
    return get_post_meta(
        $post_id,
        '_article_submission_' . $name,
        true
    );
}


/**
 * Выводит одно значение в административной карточке.
 *
 * @param string $label Название поля.
 * @param mixed  $value Значение.
 */
function dilitant_land_article_submission_admin_value(
    $label,
    $value
) {
    ?>
    <p>
        <strong><?php echo esc_html( $label ); ?></strong><br>

        <?php if ( '' !== (string) $value ) : ?>
            <?php
            echo nl2br(
                esc_html( (string) $value )
            );
            ?>
        <?php else : ?>
            <em>Не указано</em>
        <?php endif; ?>
    </p>
    <?php
}


/**
 * Преобразует значение 1/0
 * в понятное редактору Да/Нет.
 *
 * @param mixed $value Сохранённое значение.
 *
 * @return string
 */
function dilitant_land_article_submission_admin_boolean(
    $value
) {
    return '1' === (string) $value
        ? 'Да'
        : 'Нет';
}


/**
 * Выводит данные отправителя.
 *
 * @param WP_Post $post Текущая заявка.
 */
function dilitant_land_article_submission_submitter_box(
    $post
) {
    dilitant_land_article_submission_admin_value(
        'Имя',
        dilitant_land_article_submission_meta_value(
            $post->ID,
            'submitter_name'
        )
    );

    dilitant_land_article_submission_admin_value(
        'E-mail',
        dilitant_land_article_submission_meta_value(
            $post->ID,
            'submitter_email'
        )
    );
}


/**
 * Выводит сведения о статье.
 *
 * @param WP_Post $post Текущая заявка.
 */
function dilitant_land_article_submission_article_box(
    $post
) {
    dilitant_land_article_submission_admin_value(
        'Название статьи',
        dilitant_land_article_submission_meta_value(
            $post->ID,
            'article_title'
        )
    );

    dilitant_land_article_submission_admin_value(
        'Комментарий отправителя',
        dilitant_land_article_submission_meta_value(
            $post->ID,
            'submitter_comment'
        )
    );

    dilitant_land_article_submission_admin_value(
        'Подтверждение прав',
        dilitant_land_article_submission_admin_boolean(
            dilitant_land_article_submission_meta_value(
                $post->ID,
                'rights_confirmation'
            )
        )
    );
}


/**
 * Выводит технические сведения о файле рукописи.
 *
 * Сам файл здесь не открывается и не отдаётся.
 * Его защищённое скачивание будет отдельным модулем.
 *
 * @param WP_Post $post Текущая заявка.
 */
function dilitant_land_article_submission_file_box(
    $post
) {
    $original_filename =
        dilitant_land_article_submission_meta_value(
            $post->ID,
            'original_filename'
        );

    $detected_mime =
        dilitant_land_article_submission_meta_value(
            $post->ID,
            'detected_mime'
        );

    $extension =
        dilitant_land_article_submission_meta_value(
            $post->ID,
            'file_extension'
        );

    $file_size = absint(
        dilitant_land_article_submission_meta_value(
            $post->ID,
            'file_size'
        )
    );

    $scan_status =
        dilitant_land_article_submission_meta_value(
            $post->ID,
            'scan_status'
        );

    dilitant_land_article_submission_admin_value(
        'Исходное имя файла',
        $original_filename
    );

    dilitant_land_article_submission_admin_value(
        'Формат',
        $extension
            ? strtoupper( $extension )
            : ''
    );

    dilitant_land_article_submission_admin_value(
        'Определённый MIME-тип',
        $detected_mime
    );

    dilitant_land_article_submission_admin_value(
        'Размер файла',
        $file_size > 0
            ? size_format( $file_size )
            : ''
    );

    dilitant_land_article_submission_admin_value(
        'Антивирусная проверка',
        'clean' === $scan_status
            ? 'Проверено: угрозы не обнаружены'
            : (
                'infected' === $scan_status
                    ? 'Обнаружена угроза'
                    : (
                        'error' === $scan_status
                            ? 'Ошибка проверки'
                            : 'Не проверено'
                    )
            )
    );
}