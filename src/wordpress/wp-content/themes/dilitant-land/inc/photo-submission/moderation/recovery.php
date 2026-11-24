<?php
/**
 * Восстановление преобразования заявки после сбоя.
 *
 * @package Dilitant_Land
 */


/**
 * Ищет archive_photo, ранее созданный из указанной заявки.
 *
 * Поиск выполняется по обратной служебной связи:
 *
 * _archive_photo_submission_id = ID заявки.
 *
 * @param int $submission_id ID заявки.
 *
 * @return int ID archive_photo или 0.
 */
function dilitant_land_photo_submission_find_archive_photo( $submission_id ) {
    $submission_id = absint( $submission_id );

    if ( $submission_id <= 0 ) {
        return 0;
    }

    $archive_ids = get_posts(
        array(
            'post_type'      => 'archive_photo',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_key'       => '_archive_photo_submission_id',
            'meta_value'     => $submission_id,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        )
    );

    if ( empty( $archive_ids ) ) {
        return 0;
    }

    return absint( $archive_ids[0] );
}


/**
 * Ищет уже созданный публичный attachment
 * (медиафайл WordPress) для заявки.
 *
 * Сначала проверяется прямая служебная связь
 * со стороны заявки.
 *
 * Если её нет, дополнительно проверяется
 * основное изображение archive_photo.
 *
 * @param int $submission_id    ID заявки.
 * @param int $archive_photo_id ID записи фотоархива.
 *
 * @return int ID attachment или 0.
 */
function dilitant_land_photo_submission_find_public_attachment(
    $submission_id,
    $archive_photo_id
) {
    $submission_id    = absint( $submission_id );
    $archive_photo_id = absint( $archive_photo_id );

    if ( $submission_id <= 0 || $archive_photo_id <= 0 ) {
        return 0;
    }

    $attachment_id = absint(
        get_post_meta(
            $submission_id,
            '_photo_submission_public_attachment_id',
            true
        )
    );

    if (
        $attachment_id > 0
        && 'attachment' === get_post_type( $attachment_id )
    ) {
        return $attachment_id;
    }

    /*
     * Если прямая служебная связь отсутствует,
     * проверяем основное изображение archive_photo.
     */
    $thumbnail_id = absint(
        get_post_thumbnail_id( $archive_photo_id )
    );

    if (
        $thumbnail_id > 0
        && 'attachment' === get_post_type( $thumbnail_id )
    ) {
        return $thumbnail_id;
    }

    return 0;
}


/**
 * Восстанавливает незавершённое преобразование заявки.
 *
 * Последовательность:
 *
 * 1. Блокируем строку заявки.
 * 2. Находим уже созданный archive_photo.
 * 3. Проверяем, не выполняет ли восстановление
 *    другой процесс.
 * 4. Если публичного изображения ещё нет,
 *    закрепляем операцию за текущим процессом
 *    свежим статусом converting.
 * 5. Отпускаем блокировку БД.
 * 6. Создаём публичное изображение.
 * 7. Снова блокируем заявку.
 * 8. Завершаем связи и устанавливаем converted.
 *
 * Тяжёлая работа Imagick и файловой системы
 * выполняется без открытой SQL-транзакции.
 *
 * @param int $submission_id ID заявки.
 *
 * @return int|WP_Error ID найденного archive_photo
 *                      или объект ошибки.
 */
function dilitant_land_photo_submission_recover_conversion( $submission_id ) {
    global $wpdb;

    $submission_id = absint( $submission_id );

    if ( $submission_id <= 0 ) {
        return new WP_Error(
            'photo_submission_invalid_id',
            'Некорректный ID заявки.'
        );
    }

    /*
     * Блокируем строку заявки.
     *
     * acquire_conversion() использует ту же строку,
     * поэтому проверка и изменение состояния заявки
     * выполняются последовательно.
     */
    if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
        return new WP_Error(
            'photo_submission_recovery_transaction_failed',
            'Не удалось начать транзакцию восстановления.'
        );
    }

    $locked_post = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT ID, post_type
            FROM {$wpdb->posts}
            WHERE ID = %d
            FOR UPDATE",
            $submission_id
        )
    );

    if ( ! $locked_post ) {
        $wpdb->query( 'ROLLBACK' );

        return new WP_Error(
            'photo_submission_not_found',
            'Заявка не найдена.'
        );
    }

    if ( 'photo_submission' !== $locked_post->post_type ) {
        $wpdb->query( 'ROLLBACK' );

        return new WP_Error(
            'photo_submission_wrong_type',
            'Указанная запись не является заявкой на фотографию.'
        );
    }

    clean_post_cache( $submission_id );

    /*
     * После получения блокировки заново ищем archive_photo.
     *
     * Состояние могло измениться, пока текущий процесс
     * ожидал блокировку.
     */
    $archive_photo_id =
        dilitant_land_photo_submission_find_archive_photo(
            $submission_id
        );

    if ( $archive_photo_id <= 0 ) {
        $wpdb->query( 'ROLLBACK' );

        return new WP_Error(
            'photo_submission_recovery_not_found',
            'Связанная запись фотоархива для восстановления не найдена.'
        );
    }

    /*
     * Проверяем наличие уже созданного публичного
     * attachment — медиафайла WordPress.
     */
    $attachment_id =
        dilitant_land_photo_submission_find_public_attachment(
            $submission_id,
            $archive_photo_id
        );

    /*
     * Если изображение уже существует, тяжёлая часть
     * восстановления не нужна.
     *
     * Сразу восстанавливаем прямую связь и завершаем
     * преобразование.
     */
    if ( $attachment_id > 0 ) {
        update_post_meta(
            $submission_id,
            '_photo_submission_archive_photo_id',
            $archive_photo_id
        );

        /*
         * Если attachment был найден только через
         * featured image archive_photo, восстанавливаем
         * также служебную связь со стороны заявки.
         */
        update_post_meta(
            $submission_id,
            '_photo_submission_public_attachment_id',
            $attachment_id
        );

        update_post_meta(
            $submission_id,
            '_photo_submission_conversion_status',
            'converted'
        );

        delete_post_meta(
            $submission_id,
            '_photo_submission_conversion_started_at'
        );

        if ( false === $wpdb->query( 'COMMIT' ) ) {
            $wpdb->query( 'ROLLBACK' );

            return new WP_Error(
                'photo_submission_recovery_commit_failed',
                'Не удалось завершить восстановление заявки.'
            );
        }

        clean_post_cache( $submission_id );
        clean_post_cache( $archive_photo_id );

        return $archive_photo_id;
    }

    /*
     * Публичного изображения нет.
     *
     * Теперь проверяем, не принадлежит ли уже операция
     * другому активному процессу.
     */
    $conversion_status = get_post_meta(
        $submission_id,
        '_photo_submission_conversion_status',
        true
    );

    $conversion_started_at = get_post_meta(
        $submission_id,
        '_photo_submission_conversion_started_at',
        true
    );

    if (
        'converting' === $conversion_status
        && ! dilitant_land_photo_submission_conversion_is_stale(
            $conversion_started_at
        )
    ) {
        $wpdb->query( 'ROLLBACK' );

        return new WP_Error(
            'photo_submission_conversion_in_progress',
            'Преобразование этой заявки уже выполняется.'
        );
    }

    /*
     * Пустой статус, conversion_failed или зависший
     * converting разрешают восстановление.
     *
     * Закрепляем операцию за текущим процессом.
     */
    update_post_meta(
        $submission_id,
        '_photo_submission_conversion_status',
        'converting'
    );

    update_post_meta(
        $submission_id,
        '_photo_submission_conversion_started_at',
        current_time( 'mysql', true )
    );

    /*
     * Теперь другой процесс после получения блокировки
     * увидит свежий converting и не начнёт создавать
     * второе изображение.
     *
     * SQL-транзакцию завершаем до Imagick и работы
     * с файловой системой.
     */
    if ( false === $wpdb->query( 'COMMIT' ) ) {
        $wpdb->query( 'ROLLBACK' );

        return new WP_Error(
            'photo_submission_recovery_claim_failed',
            'Не удалось закрепить восстановление заявки.'
        );
    }

    clean_post_cache( $submission_id );

    /*
     * Создаём новый публичный JPEG с водяным знаком,
     * сохраняем его в uploads и создаём attachment.
     *
     * На этом этапе SQL-транзакция не удерживается.
     */
    $attachment_id =
        dilitant_land_photo_submission_create_publication_image(
            $submission_id,
            $archive_photo_id
        );

    if ( is_wp_error( $attachment_id ) ) {
        /*
         * Операция не завершилась.
         *
         * archive_photo сохраняем: следующая попытка
         * сможет восстановить преобразование через него.
         */
        update_post_meta(
            $submission_id,
            '_photo_submission_conversion_status',
            'conversion_failed'
        );

        delete_post_meta(
            $submission_id,
            '_photo_submission_conversion_started_at'
        );

        clean_post_cache( $submission_id );
        clean_post_cache( $archive_photo_id );

        return $attachment_id;
    }

    /*
     * Изображение создано.
     *
     * Снова блокируем строку заявки перед окончательным
     * изменением её состояния.
     */
    if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
        return new WP_Error(
            'photo_submission_recovery_finalize_transaction_failed',
            'Не удалось начать завершение восстановления.'
        );
    }

    $locked_post = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT ID, post_type
            FROM {$wpdb->posts}
            WHERE ID = %d
            FOR UPDATE",
            $submission_id
        )
    );

    if ( ! $locked_post ) {
        $wpdb->query( 'ROLLBACK' );

        return new WP_Error(
            'photo_submission_not_found',
            'Заявка не найдена.'
        );
    }

    if ( 'photo_submission' !== $locked_post->post_type ) {
        $wpdb->query( 'ROLLBACK' );

        return new WP_Error(
            'photo_submission_wrong_type',
            'Указанная запись не является заявкой на фотографию.'
        );
    }

    clean_post_cache( $submission_id );

    /*
     * После повторного получения блокировки ещё раз
     * проверяем, что созданный attachment существует.
     */
    $attachment_id =
        dilitant_land_photo_submission_find_public_attachment(
            $submission_id,
            $archive_photo_id
        );

    if ( $attachment_id <= 0 ) {
        $wpdb->query( 'ROLLBACK' );

        update_post_meta(
            $submission_id,
            '_photo_submission_conversion_status',
            'conversion_failed'
        );

        delete_post_meta(
            $submission_id,
            '_photo_submission_conversion_started_at'
        );

        clean_post_cache( $submission_id );
        clean_post_cache( $archive_photo_id );

        return new WP_Error(
            'photo_submission_recovery_attachment_missing',
            'Созданное публичное изображение не найдено.'
        );
    }

    /*
     * Восстанавливаем прямую связь:
     *
     * photo_submission → archive_photo.
     */
    update_post_meta(
        $submission_id,
        '_photo_submission_archive_photo_id',
        $archive_photo_id
    );

    update_post_meta(
        $submission_id,
        '_photo_submission_public_attachment_id',
        $attachment_id
    );

    update_post_meta(
        $submission_id,
        '_photo_submission_conversion_status',
        'converted'
    );

    delete_post_meta(
        $submission_id,
        '_photo_submission_conversion_started_at'
    );

    if ( false === $wpdb->query( 'COMMIT' ) ) {
        $wpdb->query( 'ROLLBACK' );

        return new WP_Error(
            'photo_submission_recovery_finalize_commit_failed',
            'Не удалось завершить восстановление заявки.'
        );
    }

    clean_post_cache( $submission_id );
    clean_post_cache( $archive_photo_id );

    return $archive_photo_id;
}