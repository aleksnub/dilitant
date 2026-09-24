<?php
/**
 * Регистрация административных блоков заявки на статью.
 *
 * Этот файл определяет структуру экрана wp-admin.
 * Вывод данных и сохранение находятся
 * в других модулях.
 *
 * @package Dilitant_Land
 */


/**
 * Регистрирует административные панели
 * article_submission.
 */
function dilitant_land_article_submission_meta_boxes() {

    /*
     * Стандартный блок WordPress «Опубликовать»
     * заявке не нужен.
     *
     * article_submission — внутренняя заявка,
     * а не публикуемая статья.
     */
    remove_meta_box(
        'submitdiv',
        'article_submission',
        'side'
    );

    add_meta_box(
        'dilitant-land-article-submission-submitter',
        'Отправитель',
        'dilitant_land_article_submission_submitter_box',
        'article_submission',
        'normal',
        'high'
    );

    add_meta_box(
        'dilitant-land-article-submission-article',
        'Сведения о статье',
        'dilitant_land_article_submission_article_box',
        'article_submission',
        'normal',
        'high'
    );

    add_meta_box(
        'dilitant-land-article-submission-file',
        'Файл рукописи',
        'dilitant_land_article_submission_file_box',
        'article_submission',
        'normal',
        'default'
    );

    add_meta_box(
        'dilitant-land-article-submission-download',
        'Скачать рукопись',
        'dilitant_land_article_submission_download_control',
        'article_submission',
        'side',
        'high'
    );

    add_meta_box(
        'dilitant-land-article-submission-review',
        'Рассмотрение',
        'dilitant_land_article_submission_review_box',
        'article_submission',
        'side',
        'high'
    );
}


add_action(
    'add_meta_boxes',
    'dilitant_land_article_submission_meta_boxes'
);
/**
 * Убирает быстрое редактирование
 * из списка заявок на статьи.
 *
 * Открытие карточки заявки и удаление
 * при этом остаются доступными.
 *
 * @param array   $actions Действия строки.
 * @param WP_Post $post    Текущая запись.
 *
 * @return array
 */
function dilitant_land_article_submission_row_actions(
    $actions,
    $post
) {
    if ( 'article_submission' !== $post->post_type ) {
        return $actions;
    }

    unset( $actions['inline hide-if-no-js'] );

    return $actions;
}


add_filter(
    'post_row_actions',
    'dilitant_land_article_submission_row_actions',
    10,
    2
);