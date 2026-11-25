<?php
/**
 * Подготовка тестовых данных
 * расширенной проверки Preview.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D03 — Публичная доступность
 *
 * Ответственность:
 *     - разрешение существующих Fixtures;
 *     - проверка идентичности записей;
 *     - проверка публичной доступности;
 *     - создание действительных nonce;
 *     - проверка привязки nonce к записи;
 *     - проверка привязки nonce к пользователю;
 *     - подготовка фабрик сценариев.
 *
 * Компонент не создаёт дополнительных
 * публикаций, категорий или пользователей.
 */

declare(strict_types=1);

if (
    PHP_SAPI !== 'cli'
    || !defined('DILITANT_TEST_DB_NAME')
    || !defined('DB_NAME')
    || DB_NAME !== DILITANT_TEST_DB_NAME
) {
    exit(1);
}


/**
 * Подготавливает проверенные тестовые
 * данные для сценариев PV-01–PV-18.
 *
 * Возвращает:
 *
 *     draft
 *         Закрытая тестовая статья.
 *
 *     other
 *         Другая закрытая статья.
 *
 *     public
 *         Публично доступная статья.
 *
 *     draft_id
 *         Канонический ID черновика.
 *
 *     other_id
 *         Канонический ID другой статьи.
 *
 *     valid_nonce
 *         Nonce администратора для черновика.
 *
 *     other_nonce
 *         Nonce администратора для другой статьи.
 *
 *     subscriber_nonce
 *         Nonce подписчика для черновика.
 *
 *     overflow_id
 *         Строковое число за пределами
 *         диапазона PHP integer.
 *
 * @internal
 */
function dilitant_visibility_prepare_preview_validation_fixtures(
    DilitantArticleFixtures $fixtures,
    DilitantVisibilityUsers $users
): array {
    dilitant_visibility_verify_environment();


    /*
     * Закрытая статья.
     */
    $draft =
        dilitant_visibility_get_post(
            $fixtures->draft_post
        );


    /*
     * Другая закрытая статья.
     *
     * Используется для отрицательной
     * проверки объектной привязки nonce.
     */
    $other =
        dilitant_visibility_get_post(
            $fixtures->private_post
        );


    /*
     * Публичная статья.
     *
     * Используется для проверки
     * невозможности обхода авторизации
     * через публичный статус.
     */
    $public =
        dilitant_visibility_get_post(
            $fixtures->root_post
        );


    /*
     * Подтверждаем нормативную
     * семантику исходных объектов.
     */
    dilitant_visibility_assert_identity(
        $draft,
        true,
        false
    );

    dilitant_visibility_assert_identity(
        $other,
        true,
        false
    );

    dilitant_visibility_assert_identity(
        $public,
        true,
        true
    );


    /*
     * Канонические идентификаторы.
     */
    $draft_id =
        (string) $draft->ID;

    $other_id =
        (string) $other->ID;


    /*
     * Генерируем nonce администратора
     * для двух различных записей.
     */
    $administrator_nonces =
        dilitant_visibility_with_user(
            $users->administrator,

            static function () use (
                $draft,
                $other
            ): array {
                /*
                 * Подтверждаем полномочия
                 * относительно обеих записей.
                 */
                dilitant_visibility_assert_edit_permission(
                    (int) $draft->ID,
                    true
                );

                dilitant_visibility_assert_edit_permission(
                    (int) $other->ID,
                    true
                );

                return [
                    'draft' => wp_create_nonce(
                        'post_preview_' . $draft->ID
                    ),

                    'other' => wp_create_nonce(
                        'post_preview_' . $other->ID
                    ),
                ];
            }
        );


    /*
     * Nonce того же черновика,
     * сформированный другим пользователем.
     */
    $subscriber_nonce =
        dilitant_visibility_with_user(
            $users->subscriber,

            static function () use (
                $draft
            ): string {
                return wp_create_nonce(
                    'post_preview_' . $draft->ID
                );
            }
        );


    $valid_nonce =
        $administrator_nonces['draft'];

    $other_nonce =
        $administrator_nonces['other'];


    /*
     * Независимая проверка исходных nonce.
     *
     * Используем штатный wp_verify_nonce(),
     * а не производственную функцию
     * Article Model.
     */
    dilitant_visibility_with_user(
        $users->administrator,

        static function () use (
            $draft,
            $other,
            $valid_nonce,
            $other_nonce,
            $subscriber_nonce
        ): void {
            /*
             * Положительный контроль:
             * nonce текущей записи.
             */
            dilitant_test_assert(
                in_array(
                    wp_verify_nonce(
                        $valid_nonce,

                        'post_preview_' . $draft->ID
                    ),

                    [1, 2],

                    true
                ),

                'Valid fixture nonce was rejected.'
            );


            /*
             * Положительный контроль:
             * nonce другой записи действителен
             * относительно собственной записи.
             */
            dilitant_test_assert(
                in_array(
                    wp_verify_nonce(
                        $other_nonce,

                        'post_preview_' . $other->ID
                    ),

                    [1, 2],

                    true
                ),

                'Other post fixture nonce was rejected.'
            );


            /*
             * Отрицательный контроль:
             * nonce другой записи не должен
             * разрешаться для черновика.
             */
            dilitant_test_assert_same(
                false,

                wp_verify_nonce(
                    $other_nonce,

                    'post_preview_' . $draft->ID
                ),

                'Other post nonce matches draft.'
            );


            /*
             * Отрицательный контроль:
             * nonce другого пользователя
             * не должен разрешаться
             * в контексте администратора.
             */
            dilitant_test_assert_same(
                false,

                wp_verify_nonce(
                    $subscriber_nonce,

                    'post_preview_' . $draft->ID
                ),

                'Other user nonce matches administrator.'
            );
        }
    );


    /*
     * Значение за пределами
     * допустимого диапазона integer.
     *
     * Сохраняется как строка.
     */
    $overflow_id =
        (string) PHP_INT_MAX . '0';


    return [
        'draft' =>
            $draft,

        'other' =>
            $other,

        'public' =>
            $public,

        'draft_id' =>
            $draft_id,

        'other_id' =>
            $other_id,

        'valid_nonce' =>
            $valid_nonce,

        'other_nonce' =>
            $other_nonce,

        'subscriber_nonce' =>
            $subscriber_nonce,

        'overflow_id' =>
            $overflow_id,
    ];
}


/**
 * Создаёт фабрику сценариев
 * Preview закрытой статьи.
 *
 * Во всех таких сценариях
 * пользователь имеет edit_post.
 *
 * Поэтому отрицательный результат
 * должен быть обусловлен параметрами
 * Preview, а не отсутствием полномочий.
 *
 * @internal
 */
function dilitant_visibility_make_draft_validation_factory(
    WP_Post $draft,
    DilitantVisibilityUsers $users
): callable {
    return static function (
        string $description,
        array $args,
        bool $expected_preview
    ) use (
        $draft,
        $users
    ): array {
        return [
            'description' =>
                $description,

            'post' =>
                $draft,

            'user_id' =>
                $users->administrator,

            'request_args' =>
                $args,

            'expected_edit_permission' =>
                true,

            'expected_preview' =>
                $expected_preview,

            'expected_public' =>
                false,
        ];
    };
}


/**
 * Создаёт фабрику сценариев
 * Preview публичной статьи.
 *
 * Ожидаемая публичность сохраняется
 * независимо от разрешения Preview.
 *
 * @internal
 */
function dilitant_visibility_make_public_validation_factory(
    WP_Post $public
): callable {
    return static function (
        string $description,
        int $user_id,
        bool $expected_edit_permission,
        bool $expected_preview
    ) use (
        $public
    ): array {
        return [
            'description' =>
                $description,

            'post' =>
                $public,

            'user_id' =>
                $user_id,

            'request_args' =>
                [],

            'expected_edit_permission' =>
                $expected_edit_permission,

            'expected_preview' =>
                $expected_preview,

            'expected_public' =>
                true,
        ];
    };
}