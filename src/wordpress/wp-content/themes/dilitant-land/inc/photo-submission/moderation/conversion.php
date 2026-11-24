<?php
/**
 * Преобразование заявки в запись фотоархива.
 *
 * @package Dilitant_Land
 */


/**
 * Преобразует одобренную и безопасную заявку
 * в черновик archive_photo.
 *
 * Пустые описательные поля допустимы и не препятствуют
 * созданию записи.
 *
 * @param int $submission_id ID заявки.
 *
 * @return int|WP_Error ID archive_photo или объект ошибки.
 */
function dilitant_land_photo_submission_convert( $submission_id ) {
    global $wpdb;

    $submission_id = absint( $submission_id );

    if ( $submission_id <= 0 ) {
        return new WP_Error(
            'photo_submission_invalid_id',
            'Некорректный ID заявки.'
        );
    }

    /*
     * Сначала проверяем, не существует ли уже archive_photo
     * с обратной ссылкой на эту заявку.
     *
     * Если существует, новую запись не создаём:
     * recovery продолжит ранее начатое преобразование.
     */
    $existing_archive_photo_id =
        dilitant_land_photo_submission_find_archive_photo(
            $submission_id
        );

    if ( $existing_archive_photo_id > 0 ) {
        return dilitant_land_photo_submission_recover_conversion(
            $submission_id
        );
    }

    /*
     * Получаем исключительное право начать преобразование.
     */
    $acquired =
        dilitant_land_photo_submission_acquire_conversion(
            $submission_id
        );

    if ( is_wp_error( $acquired ) ) {
        return $acquired;
    }

    /*
     * После захвата ещё раз проверяем обратную связь.
     */
    $existing_archive_photo_id =
        dilitant_land_photo_submission_find_archive_photo(
            $submission_id
        );

    if ( $existing_archive_photo_id > 0 ) {
        return dilitant_land_photo_submission_recover_conversion(
            $submission_id
        );
    }

    /*
     * Получаем только данные заявки,
     * которые разрешено переносить в публичную запись.
     */
    $description = get_post_meta(
        $submission_id,
        '_photo_submission_description',
        true
    );

    $approx_date = get_post_meta(
        $submission_id,
        '_photo_submission_approx_date',
        true
    );

    $place = get_post_meta(
        $submission_id,
        '_photo_submission_place',
        true
    );

    $photographer = get_post_meta(
        $submission_id,
        '_photo_submission_author_name',
        true
    );

    $source = get_post_meta(
        $submission_id,
        '_photo_submission_source',
        true
    );

    /*
     * В публичное поле «Права» переносим содержательное
     * основание публикации.
     */
    $rights = get_post_meta(
        $submission_id,
        '_photo_submission_publication_basis',
        true
    );

    $submitter_name = get_post_meta(
        $submission_id,
        '_photo_submission_submitter_name',
        true
    );

    $credit_submitter = get_post_meta(
        $submission_id,
        '_photo_submission_credit_submitter',
        true
    );

    /*
     * Имя отправителя становится публичным только
     * при его согласии на указание имени.
     */
    $provided_by = '';

    if (
        in_array(
            trim( (string) $credit_submitter ),
            array( 'yes', '1', 'true', 'Да', 'да' ),
            true
        )
    ) {
        $provided_by = $submitter_name;
    }

    /*
     * Формируем служебный заголовок.
     */
    $title = 'Архивная фотография';

    if ( $approx_date ) {
        $title .= ' — ' . $approx_date;
    } elseif ( $place ) {
        $title .= ' — ' . $place;
    }

    /*
     * Создаём archive_photo и его метаданные
     * в одной SQL-транзакции.
     */
    $wpdb->query( 'START TRANSACTION' );

    $archive_photo_id = wp_insert_post(
        array(
            'post_type'    => 'archive_photo',
            'post_status'  => 'draft',
            'post_title'   => $title,
            'post_content' => $description,
        ),
        true
    );

    if ( is_wp_error( $archive_photo_id ) ) {
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

        return $archive_photo_id;
    }

    /*
     * Обратная служебная связь:
     *
     * archive_photo → photo_submission.
     *
     * Благодаря ей recovery сможет найти запись,
     * даже если процесс оборвётся позднее.
     */
    update_post_meta(
        $archive_photo_id,
        '_archive_photo_submission_id',
        $submission_id
    );

    update_post_meta(
        $archive_photo_id,
        '_archive_photo_approx_date',
        $approx_date
    );

    update_post_meta(
        $archive_photo_id,
        '_archive_photo_place',
        $place
    );

    update_post_meta(
        $archive_photo_id,
        '_archive_photo_photographer',
        $photographer
    );

    update_post_meta(
        $archive_photo_id,
        '_archive_photo_provided_by',
        $provided_by
    );

    update_post_meta(
        $archive_photo_id,
        '_archive_photo_source',
        $source
    );

    update_post_meta(
        $archive_photo_id,
        '_archive_photo_rights',
        $rights
    );

    if ( false === $wpdb->query( 'COMMIT' ) ) {
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

        return new WP_Error(
            'photo_submission_archive_commit_failed',
            'Не удалось завершить создание записи фотоархива.'
        );
    }

    clean_post_cache( $archive_photo_id );

    /*
     * archive_photo теперь существует как самостоятельная
     * целостная запись.
     *
     * SQL-транзакция уже закрыта: обработка Imagick
     * и файловой системы не должна удерживать блокировку БД.
     *
     * Создаём новый JPEG с водяным знаком,
     * сохраняем его в uploads и создаём attachment
     * (медиафайл WordPress).
     */
    $attachment_id =
        dilitant_land_photo_submission_create_publication_image(
            $submission_id,
            $archive_photo_id
        );

    if ( is_wp_error( $attachment_id ) ) {
        /*
         * archive_photo НЕ удаляем.
         *
         * Его обратная связь с заявкой позволяет recovery
         * продолжить преобразование при следующей попытке.
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
     * Публичное изображение создано.
     *
     * Теперь фиксируем прямую связь:
     *
     * photo_submission → archive_photo
     *
     * и окончательное состояние converted.
     */
    update_post_meta(
        $submission_id,
        '_photo_submission_archive_photo_id',
        $archive_photo_id
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

    clean_post_cache( $submission_id );
    clean_post_cache( $archive_photo_id );

    return $archive_photo_id;
}