<?php
/**
 * Нормативная публичная доступность
 * статей блога «Дилетанты в Армении».
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D03 — Публичная доступность
 *
 * Публичная доступность не является
 * принадлежностью записи к блогу.
 *
 * Компонент не проверяет полномочия
 * на предварительный просмотр.
 *
 * @package Dilitant_Land
 */

defined( 'ABSPATH' ) || exit;


/**
 * Проверяет публичную доступность статьи.
 *
 * Обязательные условия:
 *
 *     1. Запись существует.
 *     2. Запись принадлежит блогу.
 *     3. Статус записи — publish.
 *     4. Парольная защита отсутствует.
 *
 * Полномочия текущего пользователя
 * не влияют на результат.
 *
 * @param int|WP_Post|null $post
 *
 * @return bool
 */
function dilitant_land_is_public_blog_article(
    $post = null
): bool {
    $post = get_post( $post );

    if ( ! $post instanceof WP_Post ) {
        return false;
    }

    return (
        dilitant_land_is_blog_article( $post )
        && 'publish' === $post->post_status
        && '' === $post->post_password
    );
}