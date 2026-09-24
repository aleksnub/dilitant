<?php
/**
 * Безопасное сохранение загружаемых рукописей.
 *
 * Общая последовательность:
 * 1. проверить временный файл;
 * 2. создать случайное внутреннее имя;
 * 3. переместить файл в приватное хранилище.
 *
 * Файлы не помещаются в WordPress Media Library
 * и не получают публичного HTTP-адреса.
 *
 * @package Dilitant_Land
 */


/**
 * Возвращает путь к приватному хранилищу рукописей.
 *
 * @return string
 */
function dilitant_land_article_submission_storage_dir() {
    return dilitant_land_private_storage_path( 'article-submissions' );
}


/**
 * Проверяет и сохраняет загруженную рукопись.
 *
 * @param array $file Элемент массива $_FILES.
 *
 * @return array|WP_Error
 */
function dilitant_land_article_submission_process_upload( $file ) {

    /*
     * Этап 1.
     * Проверяем файл до его постоянного сохранения.
     */
    $validation =
        dilitant_land_validate_article_submission_file( $file );

    if ( is_wp_error( $validation ) ) {
        return $validation;
    }

    $storage_dir =
        dilitant_land_article_submission_storage_dir();

    if (
        ! is_dir( $storage_dir )
        || ! is_writable( $storage_dir )
    ) {
        return new WP_Error(
            'article_submission_storage_not_writable',
            'Приватное хранилище рукописей недоступно для записи.'
        );
    }

    /*
     * Этап 2.
     * Создаём случайное внутреннее имя.
     *
     * Исходное имя посетителя никогда не используется
     * как имя файла в приватном хранилище.
     */
    try {
        $stored_filename =
            bin2hex( random_bytes( 32 ) )
            . '.'
            . $validation['extension'];
    } catch ( Exception $exception ) {
        return new WP_Error(
            'article_submission_filename_failed',
            'Не удалось создать внутреннее имя файла.'
        );
    }

    $destination =
        trailingslashit( $storage_dir )
        . $stored_filename;

    /*
     * Этап 3.
     * Для настоящего HTTP-upload используем
     * move_uploaded_file().
     *
     * Эта функция не позволяет подменить загрузку
     * произвольным локальным файлом сервера.
     */
    if (
        ! move_uploaded_file(
            $file['tmp_name'],
            $destination
        )
    ) {
        return new WP_Error(
            'article_submission_move_failed',
            'Не удалось сохранить загруженную рукопись.'
        );
    }

    /*
     * Наружу возвращаем только внутреннее имя
     * и уже проверенные технические сведения.
     *
     * not_scanned означает буквально:
     * антивирусная проверка не выполнялась.
     */
    return array(
        'original_filename' => $validation['original_filename'],
        'stored_filename'   => $stored_filename,
        'extension'         => $validation['extension'],
        'detected_mime'     => $validation['detected_mime'],
        'file_size'         => $validation['file_size'],
        'scan_status'       => 'not_scanned',
    );
}