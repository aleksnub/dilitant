<?php
/**
 * Подсистема безопасной обработки загружаемых фотографий.
 *
 * Общая последовательность:
 * 1. принять настоящий HTTP-upload;
 * 2. переместить оригинал в приватный карантин;
 * 3. проверить файл до полного декодирования;
 * 4. создать приватную безопасную JPEG-копию.
 *
 * @package Dilitant_Land
 */

require_once __DIR__ . '/processing/quarantine.php';
require_once __DIR__ . '/processing/validation.php';
require_once __DIR__ . '/processing/recoding.php';


/**
 * Полностью обрабатывает входящую фотографию.
 *
 * Оригинал сохраняется в приватном карантине.
 * Безопасная перекодированная копия сохраняется отдельно
 * и также не имеет публичного HTTP-адреса.
 *
 * @param string $temporary_path Путь к временному файлу,
 *                               созданному PHP при HTTP-upload.
 *
 * @return array|WP_Error Результат обработки или объект ошибки.
 */
function dilitant_land_photo_submission_process_upload(
    $temporary_path
) {
    /*
     * Этап 1.
     * Перемещаем настоящий HTTP-upload в карантин.
     */
    $quarantine_filename =
        dilitant_land_photo_submission_move_to_quarantine(
            $temporary_path
        );

    if ( is_wp_error( $quarantine_filename ) ) {
        return $quarantine_filename;
    }

    $quarantine_path =
        trailingslashit(
            dilitant_land_photo_submission_quarantine_dir()
        )
        . $quarantine_filename;

    /*
     * Этап 2.
     * Проверяем тип, размер и геометрию изображения
     * до полного декодирования.
     */
    $validation =
        dilitant_land_photo_submission_validate_file(
            $quarantine_path
        );

    if ( is_wp_error( $validation ) ) {
        return $validation;
    }

    /*
     * Этап 3.
     * Создаём случайное имя безопасной копии.
     *
     * Формат известен заранее: recoding.php всегда
     * создаёт новый JPEG.
     */
    try {
        $safe_filename =
            bin2hex( random_bytes( 32 ) ) . '.jpg';
    } catch ( Exception $exception ) {
        return new WP_Error(
            'photo_submission_safe_name_failed',
            'Не удалось создать имя безопасной копии.'
        );
    }

    $safe_dir = '/var/www/dilitant-private/photo-safe';

    if (
        ! is_dir( $safe_dir )
        || ! is_writable( $safe_dir )
    ) {
        return new WP_Error(
            'photo_submission_safe_dir_not_writable',
            'Каталог безопасных копий недоступен для записи.'
        );
    }

    $safe_path =
        trailingslashit( $safe_dir )
        . $safe_filename;

    /*
     * Этап 4.
     * Полностью декодируем уже проверенный оригинал
     * и создаём новый обезвреженный JPEG.
     */
    $recoded =
        dilitant_land_photo_submission_recode_image(
            $quarantine_path,
            $safe_path
        );

    if ( is_wp_error( $recoded ) ) {
        return $recoded;
    }

    /*
     * Наружу возвращаем внутренние имена и проверенные
     * характеристики, а не HTTP-адреса.
     */
    return array(
        'quarantine_filename' => $quarantine_filename,
        'safe_filename'       => $safe_filename,
        'file_size'           => $validation['file_size'],
        'detected_mime'       => $validation['detected_mime'],
        'width'               => $validation['width'],
        'height'              => $validation['height'],
        'pixel_count'         => $validation['pixel_count'],
        'safe_file_size'      => $recoded['file_size'],
        'safe_mime'           => $recoded['mime'],
    );
}