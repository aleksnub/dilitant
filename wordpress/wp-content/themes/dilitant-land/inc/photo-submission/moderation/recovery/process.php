<?php
/**
 * Выполнение восстановления преобразования заявки.
 *
 * @package Dilitant_Land
 */


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
 *    закрепляем операцию свежим статусом converting.
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

    if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
        return new WP_Error(
            'photo_submission_recovery_transaction_failed',
            'Не удалось начать транзакцию восстановления.'
        );
    }

    /*
     * Блокируем строку заявки.
     */
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
     * Изображение уже существует.
     * Остаётся восстановить связи и состояние заявки.
     */
    if ( $attachment_id > 0 ) {
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
                'photo_submission_recovery_commit_failed',
                'Не удалось завершить восстановление заявки.'
            );
        }

        clean_post_cache( $submission_id );
        clean_post_cache( $archive_photo_id );

        return $archive_photo_id;
    }

    /*
     * Изображения нет.
     * Проверяем, не выполняет ли операцию другой процесс.
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
     * Закрепляем восстановление за текущим процессом.
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
     * Освобождаем SQL-блокировку до обработки изображения.
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
     * Создаём публичный JPEG с водяным знаком
     * и регистрируем его как медиафайл WordPress.
     */
    $attachment_id =
        dilitant_land_photo_submission_create_publication_image(
            $submission_id,
            $archive_photo_id
        );

    if ( is_wp_error( $attachment_id ) ) {
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
     * Снова блокируем заявку перед финализацией.
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
     * После повторного получения блокировки проверяем,
     * что созданный attachment действительно существует.
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
     * Восстанавливаем окончательные связи.
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