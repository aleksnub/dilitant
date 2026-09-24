<?php
/**
 * Отображение данных заявки на фотографию в wp-admin.
 *
 * Здесь находятся только функции чтения и вывода данных.
 * Этот модуль ничего не изменяет и не сохраняет.
 *
 * @package Dilitant_Land
 */


/**
 * Получение значения meta заявки.
 *
 * @param int    $post_id ID заявки.
 * @param string $name    Имя поля без служебного префикса.
 *
 * @return mixed
 */
function dilitant_land_photo_submission_meta_value( $post_id, $name ) {
    return get_post_meta(
        $post_id,
        '_photo_submission_' . $name,
        true
    );
}


/**
 * Вывод одного значения в административной карточке.
 *
 * @param string $label Название поля.
 * @param mixed  $value Значение.
 */
function dilitant_land_photo_submission_admin_value( $label, $value ) {
    ?>
    <p>
        <strong><?php echo esc_html( $label ); ?></strong><br>

        <?php if ( '' !== (string) $value ) : ?>
            <?php echo nl2br( esc_html( (string) $value ) ); ?>
        <?php else : ?>
            <em>Не указано</em>
        <?php endif; ?>
    </p>
    <?php
}


/**
 * Преобразование флага 1/0 в понятное
 * редактору значение Да/Нет.
 *
 * @param mixed $value Сохранённое значение.
 *
 * @return string
 */
function dilitant_land_photo_submission_admin_boolean( $value ) {
    return '1' === (string) $value
        ? 'Да'
        : 'Нет';
}


/**
 * Данные отправителя.
 *
 * @param WP_Post $post Текущая заявка.
 */
function dilitant_land_photo_submission_submitter_box( $post ) {
    dilitant_land_photo_submission_admin_value(
        'Имя',
        dilitant_land_photo_submission_meta_value(
            $post->ID,
            'submitter_name'
        )
    );

    dilitant_land_photo_submission_admin_value(
        'E-mail',
        dilitant_land_photo_submission_meta_value(
            $post->ID,
            'submitter_email'
        )
    );
}


/**
 * Сведения о присланной фотографии.
 *
 * @param WP_Post $post Текущая заявка.
 */
function dilitant_land_photo_submission_photo_box( $post ) {
    $fields = array(
        'author_name' => 'Фотограф / автор',
        'approx_date' => 'Примерная дата / период',
        'place'       => 'Место',
        'source'      => 'Источник',
        'description' => 'Описание',
    );

    foreach ( $fields as $name => $label ) {
        dilitant_land_photo_submission_admin_value(
            $label,
            dilitant_land_photo_submission_meta_value(
                $post->ID,
                $name
            )
        );
    }
}


/**
 * Правовое основание публикации.
 *
 * @param WP_Post $post Текущая заявка.
 */
function dilitant_land_photo_submission_rights_box( $post ) {
    dilitant_land_photo_submission_admin_value(
        'Основание публикации',
        dilitant_land_photo_submission_meta_value(
            $post->ID,
            'publication_basis'
        )
    );

    dilitant_land_photo_submission_admin_value(
        'Подтверждение прав',
        dilitant_land_photo_submission_admin_boolean(
            dilitant_land_photo_submission_meta_value(
                $post->ID,
                'rights_confirmation'
            )
        )
    );

    dilitant_land_photo_submission_admin_value(
        'Указывать отправителя',
        dilitant_land_photo_submission_admin_boolean(
            dilitant_land_photo_submission_meta_value(
                $post->ID,
                'credit_submitter'
            )
        )
    );
}


/**
 * Технические сведения о присланном файле
 * и безопасный просмотр фотографии.
 *
 * Оригинал из карантина намеренно не выводится
 * как изображение или ссылка.
 *
 * Для просмотра используется только безопасная
 * JPEG-копия из приватного каталога photo-safe.
 *
 * @param WP_Post $post Текущая заявка.
 */
function dilitant_land_photo_submission_file_box( $post ) {
    $original_filename =
        dilitant_land_photo_submission_meta_value(
            $post->ID,
            'original_filename'
        );

    $detected_mime =
        dilitant_land_photo_submission_meta_value(
            $post->ID,
            'detected_mime'
        );

    $file_size = absint(
        dilitant_land_photo_submission_meta_value(
            $post->ID,
            'file_size'
        )
    );

    $width = absint(
        dilitant_land_photo_submission_meta_value(
            $post->ID,
            'width'
        )
    );

    $height = absint(
        dilitant_land_photo_submission_meta_value(
            $post->ID,
            'height'
        )
    );

    $pixel_count = absint(
        dilitant_land_photo_submission_meta_value(
            $post->ID,
            'pixel_count'
        )
    );

    $processing_status =
        dilitant_land_photo_submission_meta_value(
            $post->ID,
            'processing_status'
        );

    $processing_error =
        dilitant_land_photo_submission_meta_value(
            $post->ID,
            'processing_error'
        );

    $safe_preview =
        dilitant_land_photo_submission_meta_value(
            $post->ID,
            'safe_preview'
        );

    /*
     * Показываем редактору только безопасную
     * перекодированную JPEG-копию.
     *
     * Оригинал из карантина здесь не используется.
     */
    if ( $safe_preview ) {
        $preview_url =
            dilitant_land_photo_submission_preview_url(
                $post->ID
            );
        ?>
        <p>
            <strong>Безопасный просмотр</strong>
        </p>

        <p>
            <img
                src="<?php echo esc_url( $preview_url ); ?>"
                alt="Безопасная копия присланной фотографии"
                style="
                    display: block;
                    max-width: 100%;
                    width: auto;
                    height: auto;
                    max-height: 700px;
                "
            >
        </p>
        <?php
    } else {
        dilitant_land_photo_submission_admin_value(
            'Безопасный просмотр',
            ''
        );
    }

    dilitant_land_photo_submission_admin_value(
        'Исходное имя файла',
        $original_filename
    );

    dilitant_land_photo_submission_admin_value(
        'Определённый MIME-тип',
        $detected_mime
    );

    dilitant_land_photo_submission_admin_value(
        'Размер файла',
        $file_size > 0
            ? size_format( $file_size )
            : ''
    );

    $dimensions = '';

    if ( $width > 0 && $height > 0 ) {
        $dimensions = $width . ' × ' . $height . ' px';
    }

    dilitant_land_photo_submission_admin_value(
        'Размер изображения',
        $dimensions
    );

    dilitant_land_photo_submission_admin_value(
        'Количество пикселей',
        $pixel_count > 0
            ? number_format_i18n( $pixel_count )
            : ''
    );

    dilitant_land_photo_submission_admin_value(
        'Статус обработки',
        $processing_status
    );

    dilitant_land_photo_submission_admin_value(
        'Ошибка обработки',
        $processing_error
    );

    dilitant_land_photo_submission_admin_value(
        'Безопасная копия',
        $safe_preview
            ? 'Подготовлена системой'
            : ''
    );
}