<?php
/**
 * Регистрация административных блоков заявки на фотографию.
 *
 * Этот файл определяет только структуру экрана wp-admin.
 * Вывод данных и их сохранение находятся в других модулях.
 *
 * @package Dilitant_Land
 */


/**
 * Регистрация административных панелей photo_submission.
 */
function dilitant_land_photo_submission_meta_boxes() {
        /*
     * Стандартный блок WordPress «Опубликовать» здесь
     * не имеет предметного смысла.
     *
     * Состоянием заявки управляет наша модель модерации,
     * а не post_status WordPress.
     */
    remove_meta_box(
        'submitdiv',
        'photo_submission',
        'side'
    );
    add_meta_box(
        'dilitant-land-photo-submission-submitter',
        'Отправитель',
        'dilitant_land_photo_submission_submitter_box',
        'photo_submission',
        'normal',
        'high'
    );

    add_meta_box(
        'dilitant-land-photo-submission-photo',
        'Сведения о фотографии',
        'dilitant_land_photo_submission_photo_box',
        'photo_submission',
        'normal',
        'high'
    );

    add_meta_box(
        'dilitant-land-photo-submission-rights',
        'Права и основание публикации',
        'dilitant_land_photo_submission_rights_box',
        'photo_submission',
        'normal',
        'default'
    );

    add_meta_box(
        'dilitant-land-photo-submission-file',
        'Проверка файла',
        'dilitant_land_photo_submission_file_box',
        'photo_submission',
        'normal',
        'default'
    );

    add_meta_box(
        'dilitant-land-photo-submission-review',
        'Модерация',
        'dilitant_land_photo_submission_review_box',
        'photo_submission',
        'side',
        'high'
    );
}

add_action(
    'add_meta_boxes',
    'dilitant_land_photo_submission_meta_boxes'
);