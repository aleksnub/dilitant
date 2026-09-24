<?php
/**
 * Системный статус заявок на статьи.
 *
 * WordPress требует post_status для каждой записи.
 *
 * Для article_submission не подходят стандартные
 * publish, draft или private, потому что заявка
 * не является публикуемым материалом.
 *
 * Статус received означает только:
 * заявка успешно принята системой.
 *
 * Решение редактора хранится отдельно:
 * review_status = pending / approved / rejected.
 *
 * @package Dilitant_Land
 */


/**
 * Регистрирует системный статус received.
 */
function dilitant_land_register_article_submission_status() {

    register_post_status(
        'received',
        array(
            'label'                     => 'Получена',
            'public'                    => false,
            'internal'                  => false,
            'protected'                 => true,
            'private'                   => false,

            /*
             * Показываем количество таких записей
             * в административном интерфейсе WordPress.
             */
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,

            /*
             * WordPress использует эту строку
             * для счётчика записей данного статуса.
             */
            'label_count'               => _n_noop(
                'Получена <span class="count">(%s)</span>',
                'Получены <span class="count">(%s)</span>'
            ),
        )
    );
}


add_action(
    'init',
    'dilitant_land_register_article_submission_status'
);