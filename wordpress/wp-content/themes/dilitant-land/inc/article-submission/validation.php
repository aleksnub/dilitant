<?php
/**
 * Проверка файлов рукописей.
 *
 * Поддерживаются:
 * - .doc
 * - .docx
 *
 * Файл проверяется до помещения
 * в постоянное приватное хранилище.
 *
 * @package Dilitant_Land
 */

const DILITANT_LAND_ARTICLE_SUBMISSION_MAX_FILE_SIZE = 10 * 1024 * 1024;


/**
 * Проверяет загруженный файл рукописи.
 *
 * При успехе возвращает массив технических сведений.
 * При ошибке возвращает WP_Error.
 *
 * @param array $file Элемент массива $_FILES.
 *
 * @return array|WP_Error
 */
function dilitant_land_validate_article_submission_file( $file ) {

    if (
        ! is_array( $file )
        || ! isset(
            $file['name'],
            $file['tmp_name'],
            $file['size'],
            $file['error']
        )
    ) {
        return new WP_Error(
            'invalid_upload',
            'Не удалось получить файл рукописи.'
        );
    }

    if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
        return new WP_Error(
            'upload_error',
            'Во время загрузки файла произошла ошибка.'
        );
    }

    $file_size = (int) $file['size'];

    if ( $file_size <= 0 ) {
        return new WP_Error(
            'empty_file',
            'Загруженный файл пуст.'
        );
    }

    if ( $file_size > DILITANT_LAND_ARTICLE_SUBMISSION_MAX_FILE_SIZE ) {
        return new WP_Error(
            'file_too_large',
            'Размер файла превышает 10 МБ.'
        );
    }

    $tmp_name = (string) $file['tmp_name'];

    if ( ! is_file( $tmp_name ) || ! is_readable( $tmp_name ) ) {
        return new WP_Error(
            'unreadable_file',
            'Не удалось прочитать загруженный файл.'
        );
    }

    $original_filename = sanitize_file_name(
        wp_basename( (string) $file['name'] )
    );

    $extension = strtolower(
        (string) pathinfo(
            $original_filename,
            PATHINFO_EXTENSION
        )
    );

    if ( ! in_array( $extension, array( 'doc', 'docx' ), true ) ) {
        return new WP_Error(
            'unsupported_extension',
            'Разрешены только файлы .doc и .docx.'
        );
    }

    $finfo = new finfo( FILEINFO_MIME_TYPE );
    $detected_mime = (string) $finfo->file( $tmp_name );

    if ( 'docx' === $extension ) {

        $allowed_docx_mimes = array(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
        );

        if ( ! in_array( $detected_mime, $allowed_docx_mimes, true ) ) {
            return new WP_Error(
                'invalid_docx_mime',
                'Содержимое файла не соответствует формату DOCX.'
            );
        }

        $zip = new ZipArchive();

        $open_result = $zip->open(
            $tmp_name,
            ZipArchive::RDONLY
        );

        if ( true !== $open_result ) {
            return new WP_Error(
                'invalid_docx_zip',
                'Файл DOCX имеет повреждённую структуру.'
            );
        }

        $has_content_types = false !== $zip->locateName(
            '[Content_Types].xml',
            ZipArchive::FL_NOCASE
        );

        $has_document = false !== $zip->locateName(
            'word/document.xml',
            ZipArchive::FL_NOCASE
        );

        $zip->close();

        if ( ! $has_content_types || ! $has_document ) {
            return new WP_Error(
                'invalid_docx_structure',
                'Файл не содержит обязательную структуру документа Word.'
            );
        }
    }

    if ( 'doc' === $extension ) {

        $allowed_doc_mimes = array(
            'application/msword',
            'application/x-ole-storage',
            'application/CDFV2',
            'application/octet-stream',
        );

        if ( ! in_array( $detected_mime, $allowed_doc_mimes, true ) ) {
            return new WP_Error(
                'invalid_doc_mime',
                'Содержимое файла не соответствует формату DOC.'
            );
        }

        $handle = fopen( $tmp_name, 'rb' );

        if ( false === $handle ) {
            return new WP_Error(
                'unreadable_doc',
                'Не удалось проверить файл DOC.'
            );
        }

        $signature = fread( $handle, 8 );
        fclose( $handle );

        $expected_signature = hex2bin(
            'D0CF11E0A1B11AE1'
        );

        if ( $signature !== $expected_signature ) {
            return new WP_Error(
                'invalid_doc_signature',
                'Файл не содержит корректную сигнатуру DOC.'
            );
        }
    }

    return array(
        'original_filename' => $original_filename,
        'extension'         => $extension,
        'detected_mime'     => $detected_mime,
        'file_size'         => $file_size,
    );
}