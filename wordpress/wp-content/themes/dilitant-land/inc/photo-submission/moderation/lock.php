<?php
/**
 * Атомарный захват заявки для преобразования.
 *
 * @package Dilitant_Land
 */


/**
 * Через сколько секунд незавершённое преобразование
 * считается зависшим.
 *
 * 10 минут.
 */
if (
    ! defined(
        'DILITANT_LAND_PHOTO_SUBMISSION_CONVERSION_TIMEOUT'
    )
) {
    define(
        'DILITANT_LAND_PHOTO_SUBMISSION_CONVERSION_TIMEOUT',
        10 * MINUTE_IN_SECONDS
    );
}


/**
 * Проверяет, считается ли текущее преобразование зависшим.
 *
 * Время начала хранится в UTC в формате MySQL:
 *
 * YYYY-MM-DD HH:MM:SS
 *
 * Если время отсутствует или имеет неправильный формат,
 * состояние converting считаем зависшим.
 *
 * @param string $started_at Время начала в UTC.
 *
 * @return bool
 */
function dilitant_land_photo_submission_conversion_is_stale(
    $started_at
) {
    $started_at = trim( (string) $started_at );

    if ( '' === $started_at ) {
        return true;
    }

    /*
     * Значение хранится в UTC, поэтому разбираем его
     * явно как UTC и сравниваем с обычным Unix timestamp.
     *
     * Это не зависит от часового пояса WordPress
     * и системного часового пояса PHP.
     */
    $started_datetime = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i:s',
        $started_at,
        new DateTimeZone( 'UTC' )
    );

    if ( false === $started_datetime ) {
        return true;
    }

    /*
     * createFromFormat() может принять некоторые
     * некорректные даты с автоматической нормализацией.
     * Поэтому дополнительно требуем точное совпадение
     * восстановленной строки с исходной.
     */
    if (
        $started_datetime->format( 'Y-m-d H:i:s' )
        !== $started_at
    ) {
        return true;
    }

    $started_timestamp = $started_datetime->getTimestamp();
    $current_timestamp = time();

    /*
     * Время начала из будущего тоже считаем некорректным
     * состоянием и разрешаем восстановление.
     */
    if ( $started_timestamp > $current_timestamp ) {
        return true;
    }

    return (
        $current_timestamp - $started_timestamp
        >= DILITANT_LAND_PHOTO_SUBMISSION_CONVERSION_TIMEOUT
    );
}


/**
 * Захватывает заявку для преобразования.
 *
 * Строка заявки в таблице posts блокируется через
 * SELECT ... FOR UPDATE внутри короткой транзакции.
 *
 * После получения блокировки повторно проверяются
 * критические условия преобразования.
 *
 * Свежий статус converting означает, что другой процесс
 * ещё считается активным.
 *
 * Зависший converting разрешается захватить повторно,
 * если связанный archive_photo ещё не существует.
 *
 * При успешном захвате устанавливаются:
 *
 * conversion_status     = converting
 * conversion_started_at = текущее время UTC
 *
 * @param int $submission_id ID заявки.
 *
 * @return true|WP_Error
 */
function dilitant_land_photo_submission_acquire_conversion( $submission_id ) {
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
            'photo_submission_transaction_failed',
            'Не удалось начать транзакцию преобразования.'
        );
    }

    /*
     * Блокируем строку самой заявки.
     *
     * Для InnoDB SELECT ... FOR UPDATE удерживает блокировку
     * до COMMIT или ROLLBACK.
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

    /*
     * После получения блокировки сбрасываем кеш записи
     * и её метаданных.
     */
    clean_post_cache( $submission_id );

    $review_status = get_post_meta(
        $submission_id,
        '_photo_submission_review_status',
        true
    );

    if ( 'approved' !== $review_status ) {
        $wpdb->query( 'ROLLBACK' );

        return new WP_Error(
            'photo_submission_not_approved',
            'Заявка не одобрена редактором.'
        );
    }

    $processing_status = get_post_meta(
        $submission_id,
        '_photo_submission_processing_status',
        true
    );

    if ( 'safe' !== $processing_status ) {
        $wpdb->query( 'ROLLBACK' );

        return new WP_Error(
            'photo_submission_not_safe',
            'Файл заявки не имеет статуса safe.'
        );
    }

    /*
     * Прямая связь имеет приоритет:
     * если archive_photo уже записан в заявке,
     * повторное преобразование запрещено.
     */
    $archive_photo_id = absint(
        get_post_meta(
            $submission_id,
            '_photo_submission_archive_photo_id',
            true
        )
    );

    if ( $archive_photo_id > 0 ) {
        $wpdb->query( 'ROLLBACK' );

        return new WP_Error(
            'photo_submission_already_converted',
            'Для заявки уже существует связанная запись фотоархива.'
        );
    }

    /*
     * Проверяем также обратную связь.
     */
    $existing_archive_photo_id =
        dilitant_land_photo_submission_find_archive_photo(
            $submission_id
        );

    if ( $existing_archive_photo_id > 0 ) {
        $wpdb->query( 'ROLLBACK' );

        return new WP_Error(
            'photo_submission_recovery_required',
            'Для заявки уже найдена запись фотоархива; требуется восстановление связи.'
        );
    }

    $conversion_status = get_post_meta(
        $submission_id,
        '_photo_submission_conversion_status',
        true
    );

    if ( 'converted' === $conversion_status ) {
        $wpdb->query( 'ROLLBACK' );

        return new WP_Error(
            'photo_submission_already_converted',
            'Заявка уже была преобразована в запись фотоархива.'
        );
    }

    if ( 'converting' === $conversion_status ) {
        $conversion_started_at = get_post_meta(
            $submission_id,
            '_photo_submission_conversion_started_at',
            true
        );

        /*
         * Свежий захват принадлежит другому процессу.
         */
        if (
            ! dilitant_land_photo_submission_conversion_is_stale(
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
         * Если converting завис, а archive_photo не найден,
         * разрешаем повторно захватить ту же заявку.
         */
    }

    /*
     * Пустой статус, conversion_failed или зависший converting
     * могут быть захвачены заново.
     */
    update_post_meta(
        $submission_id,
        '_photo_submission_conversion_status',
        'converting'
    );

    /*
     * Храним время захвата в UTC.
     */
    update_post_meta(
        $submission_id,
        '_photo_submission_conversion_started_at',
        current_time( 'mysql', true )
    );

    if ( false === $wpdb->query( 'COMMIT' ) ) {
        $wpdb->query( 'ROLLBACK' );

        return new WP_Error(
            'photo_submission_commit_failed',
            'Не удалось завершить захват заявки.'
        );
    }

    clean_post_cache( $submission_id );

    return true;
}