<?php
/**
 * Сохранение публичной заявки на статью.
 *
 * Файл отвечает только за создание article_submission
 * и сохранение связанных с заявкой метаданных.
 *
 * @package Dilitant_Land
 */


/**
 * Удаляет сохранённую рукопись при откате операции.
 *
 * Удаление разрешено только из приватного каталога
 * рукописей и только по имени файла.
 *
 * @param string $stored_filename Внутреннее имя файла.
 *
 * @return void
 */
function dilitant_land_article_submission_delete_stored_file(
    $stored_filename
) {
    $stored_filename = wp_basename( $stored_filename );

    if ( '' === $stored_filename ) {
        return;
    }

    $path =
        trailingslashit(
            dilitant_land_article_submission_storage_dir()
        )
        . $stored_filename;

    if ( is_file( $path ) ) {
        unlink( $path );
    }
}


/**
 * Создаёт внутреннюю заявку article_submission
 * и сохраняет её данные.
 *
 * @param array $data      Проверенные данные формы.
 * @param array $processed Результат обработки файла.
 *
 * @return int|WP_Error ID созданной заявки или ошибка.
 */
function dilitant_land_article_submission_store(
    $data,
    $processed
) {
    /*
     * Название записи нужно только редактору
     * для удобства в административном списке.
     */
    $post_title = $data['article_title'];

    if ( '' === $post_title ) {
        $post_title = 'Рукопись от ' . $data['submitter_name'];
    }

    $submission_id = wp_insert_post(
        array(
            'post_type'   => 'article_submission',
            'post_status' => 'received',
            'post_title'  => $post_title,
        ),
        true
    );

    if ( is_wp_error( $submission_id ) || ! $submission_id ) {
        dilitant_land_article_submission_delete_stored_file(
            $processed['stored_filename']
        );

        if ( is_wp_error( $submission_id ) ) {
            return $submission_id;
        }

        return new WP_Error(
            'article_submission_create_failed',
            'Не удалось создать заявку.'
        );
    }

    /*
     * Данные отправителя.
     */
    update_post_meta(
        $submission_id,
        '_article_submission_submitter_name',
        $data['submitter_name']
    );

    update_post_meta(
        $submission_id,
        '_article_submission_submitter_email',
        $data['submitter_email']
    );

    /*
     * Сведения о статье.
     */
    update_post_meta(
        $submission_id,
        '_article_submission_article_title',
        $data['article_title']
    );

    update_post_meta(
        $submission_id,
        '_article_submission_submitter_comment',
        $data['submitter_comment']
    );

    update_post_meta(
        $submission_id,
        '_article_submission_rights_confirmation',
        $data['rights_confirmation']
    );

    /*
     * Сведения о приватно сохранённом файле.
     *
     * Полный путь в базе не храним.
     */
    update_post_meta(
        $submission_id,
        '_article_submission_original_filename',
        $processed['original_filename']
    );

    update_post_meta(
        $submission_id,
        '_article_submission_stored_filename',
        $processed['stored_filename']
    );

    update_post_meta(
        $submission_id,
        '_article_submission_detected_mime',
        $processed['detected_mime']
    );

    update_post_meta(
        $submission_id,
        '_article_submission_file_extension',
        $processed['extension']
    );

    update_post_meta(
        $submission_id,
        '_article_submission_file_size',
        (int) $processed['file_size']
    );

    /*
     * Антивирус пока не подключён.
     *
     * Поэтому честно записываем not_scanned —
     * «не проверено антивирусом».
     */
    update_post_meta(
        $submission_id,
        '_article_submission_scan_status',
        $processed['scan_status']
    );

    /*
     * Новая заявка ожидает решения редактора.
     */
    update_post_meta(
        $submission_id,
        '_article_submission_review_status',
        'pending'
    );

    update_post_meta(
        $submission_id,
        '_article_submission_submitted_at',
        current_time( 'mysql' )
    );

    return (int) $submission_id;
}