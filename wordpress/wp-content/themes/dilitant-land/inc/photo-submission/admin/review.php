<?php
/**
 * Административная модерация заявок на фотографии.
 *
 * Этот модуль отвечает только за:
 * - интерфейс решения редактора;
 * - сохранение редакционных полей.
 *
 * Запуск преобразования заявки в archive_photo
 * находится в admin/conversion.php.
 *
 * @package Dilitant_Land
 */


/**
 * Панель редакционной модерации.
 *
 * @param WP_Post $post Текущая заявка.
 */
function dilitant_land_photo_submission_review_box( $post ) {
    wp_nonce_field(
        'dilitant_land_save_photo_submission_review',
        'dilitant_land_photo_submission_review_nonce'
    );

    $review_status =
        dilitant_land_photo_submission_meta_value(
            $post->ID,
            'review_status'
        );

    if ( ! $review_status ) {
        $review_status = 'pending';
    }

    $reviewer_note =
        dilitant_land_photo_submission_meta_value(
            $post->ID,
            'reviewer_note'
        );

    $submitted_at =
        dilitant_land_photo_submission_meta_value(
            $post->ID,
            'submitted_at'
        );

    $reviewed_at =
        dilitant_land_photo_submission_meta_value(
            $post->ID,
            'reviewed_at'
        );

    $reviewed_by = absint(
        dilitant_land_photo_submission_meta_value(
            $post->ID,
            'reviewed_by'
        )
    );
    ?>

    <p>
        <label for="photo_submission_review_status">
            <strong>Статус</strong>
        </label>
    </p>

    <select
        id="photo_submission_review_status"
        name="photo_submission_review_status"
        class="widefat"
    >
        <option
            value="pending"
            <?php selected( $review_status, 'pending' ); ?>
        >
            Ожидает рассмотрения
        </option>

        <option
            value="approved"
            <?php selected( $review_status, 'approved' ); ?>
        >
            Одобрена
        </option>

        <option
            value="rejected"
            <?php selected( $review_status, 'rejected' ); ?>
        >
            Отклонена
        </option>
    </select>

    <p>
        <label for="photo_submission_reviewer_note">
            <strong>Заметка редактора</strong>
        </label>
    </p>

    <textarea
        id="photo_submission_reviewer_note"
        name="photo_submission_reviewer_note"
        class="widefat"
        rows="6"
    ><?php echo esc_textarea( $reviewer_note ); ?></textarea>

    <p>
        <?php
        submit_button(
            'Сохранить решение',
            'primary',
            'save',
            false
        );
        ?>
    </p>

    <hr>

    <?php
    dilitant_land_photo_submission_admin_value(
        'Получена',
        $submitted_at
    );

    dilitant_land_photo_submission_admin_value(
        'Рассмотрена',
        $reviewed_at
    );

    if ( $reviewed_by > 0 ) {
        $reviewer = get_userdata( $reviewed_by );

        dilitant_land_photo_submission_admin_value(
            'Рассмотрел',
            $reviewer
                ? $reviewer->display_name
                : 'Пользователь #' . $reviewed_by
        );
    }

    /*
     * Кнопка преобразования или ссылка на уже
     * созданную запись выводятся отдельным модулем.
     */
    dilitant_land_photo_submission_conversion_control(
        $post
    );
}


/**
 * Сохранение редакционного решения.
 *
 * Этот обработчик имеет право изменять только:
 *
 * - review_status;
 * - reviewer_note;
 * - reviewed_at;
 * - reviewed_by.
 *
 * Исходные данные заявки и результаты проверки файла
 * здесь не изменяются.
 *
 * @param int $post_id ID заявки.
 */
function dilitant_land_save_photo_submission_review( $post_id ) {
    if (
        ! isset(
            $_POST['dilitant_land_photo_submission_review_nonce']
        )
    ) {
        return;
    }

    $nonce = sanitize_text_field(
        wp_unslash(
            $_POST['dilitant_land_photo_submission_review_nonce']
        )
    );

    if (
        ! wp_verify_nonce(
            $nonce,
            'dilitant_land_save_photo_submission_review'
        )
    ) {
        return;
    }

    if (
        defined( 'DOING_AUTOSAVE' )
        && DOING_AUTOSAVE
    ) {
        return;
    }

    if (
        get_post_type( $post_id )
        !== 'photo_submission'
    ) {
        return;
    }

    if (
        ! current_user_can(
            'edit_post',
            $post_id
        )
    ) {
        return;
    }

    $old_status =
        dilitant_land_photo_submission_meta_value(
            $post_id,
            'review_status'
        );

    if ( ! $old_status ) {
        $old_status = 'pending';
    }

    $new_status = $old_status;

    $allowed_statuses = array(
        'pending',
        'approved',
        'rejected',
    );

    if (
        isset(
            $_POST['photo_submission_review_status']
        )
    ) {
        $candidate_status = sanitize_key(
            wp_unslash(
                $_POST['photo_submission_review_status']
            )
        );

        if (
            in_array(
                $candidate_status,
                $allowed_statuses,
                true
            )
        ) {
            $new_status = $candidate_status;

            update_post_meta(
                $post_id,
                '_photo_submission_review_status',
                $new_status
            );
        }
    }

    if (
        isset(
            $_POST['photo_submission_reviewer_note']
        )
    ) {
        update_post_meta(
            $post_id,
            '_photo_submission_reviewer_note',
            sanitize_textarea_field(
                wp_unslash(
                    $_POST['photo_submission_reviewer_note']
                )
            )
        );
    }

    /*
     * Время и редактора фиксируем только при
     * фактическом изменении решения.
     */
    if (
        $new_status !== $old_status
        && 'pending' !== $new_status
    ) {
        update_post_meta(
            $post_id,
            '_photo_submission_reviewed_at',
            current_time( 'mysql' )
        );

        update_post_meta(
            $post_id,
            '_photo_submission_reviewed_by',
            get_current_user_id()
        );
    }

    /*
     * Возврат в pending снова делает заявку
     * нерассмотренной.
     */
    if (
        $new_status !== $old_status
        && 'pending' === $new_status
    ) {
        delete_post_meta(
            $post_id,
            '_photo_submission_reviewed_at'
        );

        delete_post_meta(
            $post_id,
            '_photo_submission_reviewed_by'
        );
    }
}


add_action(
    'save_post_photo_submission',
    'dilitant_land_save_photo_submission_review'
);