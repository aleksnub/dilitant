<?php
/**
 * Безопасное перекодирование проверенных фотографий.
 *
 * Функция получает уже прошедший предварительную проверку файл,
 * декодирует изображение и создаёт новую копию.
 *
 * Исходный файл не изменяется.
 * Метаданные исходного файла в новую копию не переносятся.
 *
 * @package Dilitant_Land
 */


/**
 * Создаёт безопасную перекодированную копию изображения.
 *
 * Файл до вызова этой функции должен успешно пройти
 * dilitant_land_photo_submission_validate_file().
 *
 * Новая копия всегда создаётся как JPEG.
 *
 * @param string $source_path      Абсолютный путь к проверенному
 *                                 файлу в карантине.
 * @param string $destination_path Абсолютный путь для новой копии.
 *
 * @return array|WP_Error Сведения о созданной копии
 *                        или объект ошибки.
 */
function dilitant_land_photo_submission_recode_image(
    $source_path,
    $destination_path
) {
    $source_path      = (string) $source_path;
    $destination_path = (string) $destination_path;

    /*
     * Повторяем предварительную проверку непосредственно
     * перед декодированием.
     *
     * Это не позволяет случайно вызвать перекодирование
     * для файла, который не прошёл validation.php.
     */
    $validation =
        dilitant_land_photo_submission_validate_file(
            $source_path
        );

    if ( is_wp_error( $validation ) ) {
        return $validation;
    }

    if ( '' === $destination_path ) {
        return new WP_Error(
            'photo_submission_destination_missing',
            'Не указан путь для безопасной копии.'
        );
    }

    $destination_dir = dirname( $destination_path );

    if (
        ! is_dir( $destination_dir )
        || ! is_writable( $destination_dir )
    ) {
        return new WP_Error(
            'photo_submission_destination_not_writable',
            'Каталог для безопасной копии недоступен для записи.'
        );
    }

    try {
        /*
         * Устанавливаем ограничения ImageMagick для этой операции.
         *
         * Они являются дополнительной защитой.
         * Основная проверка размеров уже выполнена
         * до декодирования в validation.php.
         */
        $image = new Imagick();

        $image->setResourceLimit(
            Imagick::RESOURCETYPE_MEMORY,
            96 * 1024 * 1024
        );

        $image->setResourceLimit(
            Imagick::RESOURCETYPE_MAP,
            128 * 1024 * 1024
        );

        $image->setResourceLimit(
            Imagick::RESOURCETYPE_DISK,
            256 * 1024 * 1024
        );

        /*
         * Декодирование недоверенного оригинала происходит
         * только после предварительной проверки.
         */
        $image->readImage( $source_path );

        /*
         * Для фотографии ожидается один кадр.
         * Многокадровые изображения не принимаем.
         */
        if ( 1 !== $image->getNumberImages() ) {
            $image->clear();
            $image->destroy();

            return new WP_Error(
                'photo_submission_multiple_frames',
                'Многокадровые изображения не поддерживаются.'
            );
        }

        /*
         * Удаляем EXIF, комментарии, профили и другие
         * метаданные исходного файла.
         */
        $image->stripImage();

        /*
         * Создаём обычное изображение без прозрачности.
         *
         * JPEG не поддерживает прозрачность, поэтому
         * прозрачные области PNG/WebP получают белый фон.
         */
        $image->setImageBackgroundColor( 'white' );

        if ( $image->getImageAlphaChannel() ) {
            $image->setImageAlphaChannel(
                Imagick::ALPHACHANNEL_REMOVE
            );
        }

        /*
         * Безопасная копия имеет единый предсказуемый формат.
         */
        $image->setImageFormat( 'jpeg' );
        $image->setImageCompression(
            Imagick::COMPRESSION_JPEG
        );
        $image->setImageCompressionQuality( 90 );

        /*
         * Убираем оставшиеся свойства после преобразования.
         */
        $image->stripImage();

        if ( ! $image->writeImage( $destination_path ) ) {
            $image->clear();
            $image->destroy();

            return new WP_Error(
                'photo_submission_recode_write_failed',
                'Не удалось записать безопасную копию.'
            );
        }

        $width  = $image->getImageWidth();
        $height = $image->getImageHeight();

        $image->clear();
        $image->destroy();
    } catch ( ImagickException $exception ) {
        if ( isset( $image ) ) {
            $image->clear();
            $image->destroy();
        }

        return new WP_Error(
            'photo_submission_recode_failed',
            'Не удалось безопасно перекодировать изображение.'
        );
    }

    /*
     * Проверяем уже созданный нами файл.
     */
    if (
        ! is_file( $destination_path )
        || 0 >= filesize( $destination_path )
    ) {
        return new WP_Error(
            'photo_submission_recoded_file_invalid',
            'Безопасная копия изображения не была создана корректно.'
        );
    }

    return array(
        'path'        => $destination_path,
        'mime'        => 'image/jpeg',
        'file_size'   => (int) filesize( $destination_path ),
        'width'       => (int) $width,
        'height'      => (int) $height,
        'pixel_count' => (int) $width * (int) $height,
    );
}
