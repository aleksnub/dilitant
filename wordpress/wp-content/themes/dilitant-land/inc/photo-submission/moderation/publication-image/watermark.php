<?php
/**
 * Создание новой JPEG-копии с водяным знаком.
 *
 * Модуль получает путь к уже проверенной приватной
 * safe-копии, декодирует её через Imagick, создаёт
 * новое изображение, удаляет метаданные и наносит
 * водяной знак.
 *
 * Никакие файлы WordPress и записи БД здесь
 * не создаются и не изменяются.
 *
 * @package Dilitant_Land
 */


/**
 * Текст водяного знака.
 */
if ( ! defined( 'DILITANT_LAND_ARCHIVE_PHOTO_WATERMARK' ) ) {
    define(
        'DILITANT_LAND_ARCHIVE_PHOTO_WATERMARK',
        'Дилетанты в Армении'
    );
}


/**
 * Шрифт водяного знака.
 *
 * Nimbus-Sans-Bold проверен в текущем окружении Imagick:
 * кириллица отображается корректно.
 */
if ( ! defined( 'DILITANT_LAND_ARCHIVE_PHOTO_WATERMARK_FONT' ) ) {
    define(
        'DILITANT_LAND_ARCHIVE_PHOTO_WATERMARK_FONT',
        'Nimbus-Sans-Bold'
    );
}


/**
 * Создаёт новый JPEG с водяным знаком.
 *
 * Входной файл считается уже прошедшим проверки source.php,
 * однако здесь он всё равно полностью декодируется Imagick.
 *
 * Исходный safe-файл не изменяется.
 *
 * @param string $safe_path Абсолютный путь к приватному safe JPEG.
 *
 * @return string|WP_Error Бинарное содержимое нового JPEG либо ошибка.
 */
function dilitant_land_photo_submission_create_watermarked_jpeg(
    $safe_path
) {
    $safe_path = (string) $safe_path;

    if ( '' === $safe_path ) {
        return new WP_Error(
            'photo_submission_watermark_empty_source',
            'Не указан путь к безопасной копии.'
        );
    }

    if (
        ! is_file( $safe_path )
        || ! is_readable( $safe_path )
    ) {
        return new WP_Error(
            'photo_submission_watermark_source_unavailable',
            'Безопасная копия недоступна для обработки.'
        );
    }

    if ( ! class_exists( 'Imagick' ) ) {
        return new WP_Error(
            'photo_submission_watermark_no_imagick',
            'На сервере недоступен Imagick.'
        );
    }

    $image      = null;
    $background = null;
    $text       = null;

    try {
        $image = new Imagick();

        /*
         * Ограничиваем ресурсы до чтения изображения.
         *
         * Safe-копия уже была обработана системой,
         * но повторная защита нужна и на этапе публикации.
         */
        $image->setResourceLimit(
            Imagick::RESOURCETYPE_MEMORY,
            128 * 1024 * 1024
        );

        $image->setResourceLimit(
            Imagick::RESOURCETYPE_MAP,
            256 * 1024 * 1024
        );

        $image->setResourceLimit(
            Imagick::RESOURCETYPE_DISK,
            512 * 1024 * 1024
        );

        /*
         * Полностью декодируем приватный safe JPEG.
         */
        $image->readImage( $safe_path );

        /*
         * Safe-копия должна содержать ровно одно изображение.
         */
        if ( 1 !== $image->getNumberImages() ) {
            throw new RuntimeException(
                'Безопасная копия содержит недопустимое количество кадров.'
            );
        }

        $image->setIteratorIndex( 0 );

        $width  = (int) $image->getImageWidth();
        $height = (int) $image->getImageHeight();

        if ( $width <= 0 || $height <= 0 ) {
            throw new RuntimeException(
                'Не удалось определить размеры изображения.'
            );
        }

        /*
         * Повторяем критические ограничения после
         * фактического декодирования изображения.
         */
        if ( $width > 10000 || $height > 10000 ) {
            throw new RuntimeException(
                'Размеры изображения превышают допустимый предел.'
            );
        }

        if (
            $width
            > intdiv(
                25000000,
                $height
            )
        ) {
            throw new RuntimeException(
                'Изображение содержит слишком много пикселей.'
            );
        }

        /*
         * Удаляем метаданные исходной safe-копии.
         *
         * Публичная версия не должна наследовать EXIF,
         * комментарии и другие служебные данные.
         */
        $image->stripImage();

        /*
         * Явно формируем JPEG.
         */
        $image->setImageFormat( 'jpeg' );
        $image->setImageCompression(
            Imagick::COMPRESSION_JPEG
        );
        $image->setImageCompressionQuality( 88 );

        /*
         * Размер текста зависит от ширины фотографии.
         */
        $font_size = (int) round(
            $width * 0.035
        );

        $font_size = max(
            16,
            $font_size
        );

        $font_size = min(
            54,
            $font_size
        );

        /*
         * Создаём объект только для текста.
         */
        $text = new ImagickDraw();

        /*
         * Используем явно заданный шрифт с проверенной
         * поддержкой кириллицы.
         */
        $text->setFont(
            DILITANT_LAND_ARCHIVE_PHOTO_WATERMARK_FONT
        );

        $text->setFontSize(
            $font_size
        );

        $text->setFillColor(
            new ImagickPixel( 'white' )
        );

        /*
         * Получаем реальные размеры надписи.
         */
        $metrics = $image->queryFontMetrics(
            $text,
            DILITANT_LAND_ARCHIVE_PHOTO_WATERMARK
        );

        $text_width = (int) ceil(
            $metrics['textWidth']
        );

        $text_height = (int) ceil(
            $metrics['textHeight']
        );

        $padding = max(
            12,
            (int) round(
                $font_size * 0.65
            )
        );

        $box_width =
            $text_width
            + ( 2 * $padding );

        $box_height =
            $text_height
            + ( 2 * $padding );

        /*
         * Если надпись не помещается по ширине,
         * постепенно уменьшаем размер шрифта.
         */
        while (
            $box_width > $width
            && $font_size > 12
        ) {
            $font_size--;

            $text->setFontSize(
                $font_size
            );

            $metrics = $image->queryFontMetrics(
                $text,
                DILITANT_LAND_ARCHIVE_PHOTO_WATERMARK
            );

            $text_width = (int) ceil(
                $metrics['textWidth']
            );

            $text_height = (int) ceil(
                $metrics['textHeight']
            );

            $padding = max(
                8,
                (int) round(
                    $font_size * 0.65
                )
            );

            $box_width =
                $text_width
                + ( 2 * $padding );

            $box_height =
                $text_height
                + ( 2 * $padding );
        }

        /*
         * Размещаем водяной знак в правом нижнем углу.
         */
        $box_x = max(
            0,
            $width - $box_width
        );

        $box_y = max(
            0,
            $height - $box_height
        );

        /*
         * Полупрозрачная тёмная подложка обеспечивает
         * читаемость надписи независимо от фотографии.
         */
        $background = new ImagickDraw();

        $background->setFillColor(
            new ImagickPixel(
                'rgba(0, 0, 0, 0.55)'
            )
        );

        $background->rectangle(
            $box_x,
            $box_y,
            $width,
            $height
        );

        $image->drawImage(
            $background
        );

        /*
         * Imagick размещает текст относительно базовой линии.
         */
        $ascender = isset(
            $metrics['ascender']
        )
            ? (float) $metrics['ascender']
            : (float) $font_size;

        $text_x =
            $box_x
            + $padding;

        $text_y =
            $box_y
            + $padding
            + $ascender;

        $image->annotateImage(
            $text,
            $text_x,
            $text_y,
            0,
            DILITANT_LAND_ARCHIVE_PHOTO_WATERMARK
        );

        /*
         * После обработки ещё раз удаляем профили
         * и служебные метаданные.
         */
        $image->stripImage();

        $image->setImageFormat(
            'jpeg'
        );

        $image->setImageCompression(
            Imagick::COMPRESSION_JPEG
        );

        $image->setImageCompressionQuality(
            88
        );

        /*
         * Получаем бинарное содержимое именно новой
         * производной JPEG-копии.
         */
        $jpeg_blob = $image->getImageBlob();

        if (
            ! is_string( $jpeg_blob )
            || '' === $jpeg_blob
        ) {
            throw new RuntimeException(
                'Не удалось сформировать публичный JPEG.'
            );
        }

        /*
         * Освобождаем ресурсы Imagick до возврата результата.
         */
        $background->clear();
        $background->destroy();
        $background = null;

        $text->clear();
        $text->destroy();
        $text = null;

        $image->clear();
        $image->destroy();
        $image = null;

        return $jpeg_blob;
    } catch ( Throwable $exception ) {
        if ( $background instanceof ImagickDraw ) {
            $background->clear();
            $background->destroy();
        }

        if ( $text instanceof ImagickDraw ) {
            $text->clear();
            $text->destroy();
        }

        if ( $image instanceof Imagick ) {
            $image->clear();
            $image->destroy();
        }

        return new WP_Error(
            'photo_submission_watermark_failed',
            'Не удалось создать изображение с водяным знаком: '
            . $exception->getMessage()
        );
    }
}