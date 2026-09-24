<?php
/**
 * Работа с приватным карантином фотографий.
 *
 * Недоверенные оригиналы хранятся вне публичного
 * каталога WordPress и не должны иметь HTTP-адреса.
 *
 * @package Dilitant_Land
 */


/**
 * Возвращает абсолютный путь к каталогу карантина.
 *
 * @return string
 */
function dilitant_land_photo_submission_quarantine_dir() {
    return dilitant_land_private_storage_path( 'photo-quarantine' );
}


/**
 * Проверяет готовность каталога карантина.
 *
 * Каталог должен:
 * - существовать;
 * - быть каталогом;
 * - быть доступным PHP-процессу для записи.
 *
 * @return true|WP_Error
 */
function dilitant_land_photo_submission_quarantine_is_ready() {
    $quarantine_dir =
        dilitant_land_photo_submission_quarantine_dir();

    if ( ! is_dir( $quarantine_dir ) ) {
        return new WP_Error(
            'photo_submission_quarantine_missing',
            'Каталог карантина не существует.'
        );
    }

    if ( ! is_writable( $quarantine_dir ) ) {
        return new WP_Error(
            'photo_submission_quarantine_not_writable',
            'Каталог карантина недоступен для записи.'
        );
    }

    return true;
}


/**
 * Перемещает настоящий HTTP-upload в приватный карантин.
 *
 * Функция не определяет тип изображения и не декодирует файл.
 * Исходное имя посетителя не используется как имя файла
 * в карантине.
 *
 * @param string $temporary_path Путь к временному файлу PHP.
 *
 * @return string|WP_Error Внутреннее имя файла в карантине
 *                         или объект ошибки.
 */
function dilitant_land_photo_submission_move_to_quarantine(
    $temporary_path
) {
    $ready =
        dilitant_land_photo_submission_quarantine_is_ready();

    if ( is_wp_error( $ready ) ) {
        return $ready;
    }

    $temporary_path = (string) $temporary_path;

    if (
        '' === $temporary_path
        || ! is_uploaded_file( $temporary_path )
    ) {
        return new WP_Error(
            'photo_submission_invalid_upload',
            'Файл не является корректной HTTP-загрузкой.'
        );
    }

    /*
     * Создаём случайное внутреннее имя.
     *
     * Расширение намеренно отсутствует:
     * настоящий тип файла ещё не определён.
     */
    try {
        $quarantine_filename =
            bin2hex( random_bytes( 32 ) );
    } catch ( Exception $exception ) {
        return new WP_Error(
            'photo_submission_random_name_failed',
            'Не удалось создать безопасное имя файла.'
        );
    }

    $quarantine_path =
        trailingslashit(
            dilitant_land_photo_submission_quarantine_dir()
        )
        . $quarantine_filename;

    /*
     * move_uploaded_file() дополнительно проверяет,
     * что источник действительно был загружен через PHP.
     */
    if (
        ! move_uploaded_file(
            $temporary_path,
            $quarantine_path
        )
    ) {
        return new WP_Error(
            'photo_submission_quarantine_move_failed',
            'Не удалось переместить файл в карантин.'
        );
    }

    /*
     * Внешнему коду возвращаем только внутреннее имя,
     * а не абсолютный путь файловой системы.
     */
    return $quarantine_filename;
}