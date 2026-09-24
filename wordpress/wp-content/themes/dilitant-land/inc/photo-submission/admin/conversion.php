<?php
/**
 * Административный запуск преобразования
 * заявки в запись фотоархива.
 *
 * Этот модуль отвечает только за:
 * - вывод кнопки преобразования;
 * - проверку административного запроса;
 * - запуск механизма moderation/conversion.php;
 * - перенаправление редактора к созданной записи.
 *
 * Само преобразование данных здесь не выполняется.
 *
 * @package Dilitant_Land
 */


/**
 * Вывод административного действия
 * для создания записи фотоархива.
 *
 * Кнопка появляется только у одобренной заявки,
 * для которой archive_photo ещё не создан.
 *
 * @param WP_Post $post Текущая заявка.
 */
function dilitant_land_photo_submission_conversion_control( $post ) {
    $review_status =
        dilitant_land_photo_submission_meta_value(
            $post->ID,
            'review_status'
        );

    $archive_photo_id = absint(
        dilitant_land_photo_submission_meta_value(
            $post->ID,
            'archive_photo_id'
        )
    );

    $conversion_status =
        dilitant_land_photo_submission_meta_value(
            $post->ID,
            'conversion_status'
        );

    /*
     * Если archive_photo уже существует,
     * показываем ссылку вместо кнопки.
     */
    if ( $archive_photo_id > 0 ) {
        $edit_link = get_edit_post_link(
            $archive_photo_id,
            ''
        );

        if ( $edit_link ) {
            ?>
            <hr>

            <p>
                <strong>Архивная фотография</strong><br>

                <a href="<?php echo esc_url( $edit_link ); ?>">
                    Запись №<?php
                    echo esc_html( $archive_photo_id );
                    ?>
                </a>
            </p>
            <?php
        }

        return;
    }

    /*
     * До одобрения заявки преобразование
     * редактору не предлагается.
     */
    if ( 'approved' !== $review_status ) {
        return;
    }

    $conversion_url = wp_nonce_url(
        add_query_arg(
            array(
                'action'        =>
                    'dilitant_photo_submission_convert',
                'submission_id' => $post->ID,
            ),
            admin_url( 'admin-post.php' )
        ),
        'dilitant_photo_submission_convert_'
            . $post->ID
    );
    ?>

    <hr>

    <p>
        <strong>Фотоархив</strong>
    </p>

    <?php if ( 'conversion_failed' === $conversion_status ) : ?>
        <p>
            Предыдущая попытка создания записи
            фотоархива завершилась ошибкой.
        </p>
    <?php endif; ?>

    <p>
        <a
            href="<?php echo esc_url( $conversion_url ); ?>"
            class="button button-secondary"
        >
            Создать запись фотоархива
        </a>
    </p>

    <?php
}


/**
 * Обработка явного административного запроса
 * на преобразование заявки.
 */
function dilitant_land_photo_submission_handle_conversion() {
    $submission_id = isset( $_GET['submission_id'] )
        ? absint( $_GET['submission_id'] )
        : 0;

    if ( $submission_id <= 0 ) {
        wp_die(
            'Некорректный ID заявки.',
            'Ошибка',
            array( 'response' => 400 )
        );
    }

    if (
        'photo_submission'
        !== get_post_type( $submission_id )
    ) {
        wp_die(
            'Заявка не найдена.',
            'Ошибка',
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
            'Недостаточно прав.',
            'Доступ запрещён',
            array( 'response' => 403 )
        );
    }

    check_admin_referer(
        'dilitant_photo_submission_convert_'
            . $submission_id
    );

    /*
     * Проверяем решение ещё раз на сервере.
     * Одного отсутствия/наличия кнопки недостаточно.
     */
    $review_status =
        dilitant_land_photo_submission_meta_value(
            $submission_id,
            'review_status'
        );

    if ( 'approved' !== $review_status ) {
        wp_die(
            'Преобразовать можно только одобренную заявку.',
            'Преобразование запрещено',
            array( 'response' => 409 )
        );
    }

    $archive_photo_id =
        dilitant_land_photo_submission_convert(
            $submission_id
        );

    if ( is_wp_error( $archive_photo_id ) ) {
        wp_die(
            esc_html(
                $archive_photo_id->get_error_message()
            ),
            'Не удалось создать запись фотоархива',
            array( 'response' => 500 )
        );
    }

    $edit_link = get_edit_post_link(
        $archive_photo_id,
        'raw'
    );

    if ( ! $edit_link ) {
        $edit_link = admin_url(
            'post.php?post='
            . absint( $archive_photo_id )
            . '&action=edit'
        );
    }

    wp_safe_redirect( $edit_link );
    exit;
}


add_action(
    'admin_post_dilitant_photo_submission_convert',
    'dilitant_land_photo_submission_handle_conversion'
);