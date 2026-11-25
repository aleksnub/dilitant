<?php
/**
 * Испытания Preview nonce.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D03 — Публичная доступность
 *
 * Сценарий:
 *     AM-T11.8 — действительный Preview nonce.
 *
 * Ответственность:
 *     - проверка штатного механизма WordPress;
 *     - проверка привязки nonce к записи;
 *     - проверка привязки nonce к пользователю;
 *     - проверка согласованности Article Model;
 *     - запуск расширенной проверки
 *       входных параметров Preview.
 *
 * Сценарий считается успешным
 * только после прохождения всех
 * обязательных внутренних проверок.
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


/*
 * Расширенная проверка параметров
 * производственной авторизации.
 */
require_once __DIR__
    . '/request-validation.php';


/**
 * Проверяет штатный механизм
 * Preview nonce ядра WordPress.
 *
 * Сохраняет ранее реализованные
 * проверки AM-T11.8.
 *
 * @internal
 */
function dilitant_visibility_test_native_preview_nonce(
    DilitantArticleFixtures $fixtures,
    DilitantVisibilityUsers $users
): void {
    dilitant_visibility_with_user(
        $users->administrator,

        static function () use (
            $fixtures,
            $users
        ): void {
            $post =
                dilitant_visibility_get_post(
                    $fixtures->draft_post
                );

            $other =
                dilitant_visibility_get_post(
                    $fixtures->private_post
                );

            $post_id = (int) $post->ID;

            $other_id = (int) $other->ID;


            /*
             * Подтверждаем полномочия
             * относительно обеих записей.
             */
            dilitant_visibility_assert_edit_permission(
                $post_id,
                true
            );

            dilitant_visibility_assert_edit_permission(
                $other_id,
                true
            );


            /*
             * Генерируем действительные
             * WordPress nonce.
             */
            $nonce = wp_create_nonce(
                'post_preview_' . $post_id
            );

            $other_nonce = wp_create_nonce(
                'post_preview_' . $other_id
            );

            dilitant_test_assert(
                is_string($nonce)
                && $nonce !== '',

                'Unable to generate preview nonce.'
            );

            dilitant_test_assert(
                $nonce !== $other_nonce,

                'Distinct posts produced identical preview nonce.'
            );


            /*
             * 1. Действительный nonce.
             */
            dilitant_visibility_check_native_nonce(
                [
                    'preview' => 'true',

                    'preview_id' =>
                        (string) $post_id,

                    'preview_nonce' =>
                        $nonce,
                ],

                false,

                true
            );


            /*
             * 2. Некорректный nonce.
             */
            dilitant_visibility_check_native_nonce(
                [
                    'preview' => 'true',

                    'preview_id' =>
                        (string) $post_id,

                    'preview_nonce' =>
                        'invalid-preview-nonce',
                ],

                true,

                false
            );


            /*
             * 3. Nonce другой записи.
             */
            dilitant_visibility_check_native_nonce(
                [
                    'preview' => 'true',

                    'preview_id' =>
                        (string) $post_id,

                    'preview_nonce' =>
                        $other_nonce,
                ],

                true,

                false
            );


            /*
             * 4. Nonce другого пользователя.
             */
            dilitant_visibility_with_user(
                $users->subscriber,

                static function () use (
                    $post_id,
                    $nonce
                ): void {
                    dilitant_visibility_check_native_nonce(
                        [
                            'preview' => 'true',

                            'preview_id' =>
                                (string) $post_id,

                            'preview_nonce' =>
                                $nonce,
                        ],

                        true,

                        false
                    );
                }
            );


            /*
             * 5. Preview сохранённой записи.
             *
             * Без параметров Autosave Preview
             * штатный обработчик не подключает
             * фильтр _set_preview.
             */
            dilitant_visibility_check_native_nonce(
                [
                    'preview' => 'true',
                ],

                false,

                false
            );


            /*
             * 6. Неполная пара параметров.
             *
             * Штатный обработчик WordPress
             * не активирует autosave filter.
             *
             * Это не означает разрешение
             * на уровне Article Model.
             *
             * Производственный отказ
             * дополнительно проверяется PV-02.
             */
            dilitant_visibility_check_native_nonce(
                [
                    'preview' => 'true',

                    'preview_id' =>
                        (string) $post_id,
                ],

                false,

                false
            );


            /*
             * 7. Согласованная авторизация
             * запрошенной записи.
             */
            dilitant_visibility_with_request_args(
                [
                    'preview' => 'true',

                    'preview_id' =>
                        (string) $post_id,

                    'preview_nonce' =>
                        $nonce,
                ],

                static function () use (
                    $post
                ): void {
                    dilitant_visibility_with_post_context(
                        $post,
                        true,

                        static function (
                            WP_Query $query
                        ) use ($post): void {
                            dilitant_visibility_assert_preview(
                                $post,
                                true,
                                true,
                                false
                            );

                            dilitant_visibility_assert_handler_decision(
                                $query,
                                false
                            );
                        }
                    );
                }
            );


            /*
             * 8. Nonce действителен,
             * но относится к другой записи.
             *
             * Сначала подтверждаем,
             * что ядро WordPress принимает
             * корректную пару параметров
             * для другой записи.
             */
            dilitant_visibility_check_native_nonce(
                [
                    'preview' => 'true',

                    'preview_id' =>
                        (string) $other_id,

                    'preview_nonce' =>
                        $other_nonce,
                ],

                false,

                true
            );


            /*
             * Затем проверяем,
             * что Article Model отклоняет
             * эту пару в контексте
             * фактически запрошенной записи.
             */
            dilitant_visibility_with_request_args(
                [
                    'preview' => 'true',

                    'preview_id' =>
                        (string) $other_id,

                    'preview_nonce' =>
                        $other_nonce,
                ],

                static function () use (
                    $post
                ): void {
                    dilitant_visibility_with_post_context(
                        $post,
                        true,

                        static function (
                            WP_Query $query
                        ) use ($post): void {
                            dilitant_visibility_assert_preview(
                                $post,
                                false,
                                false,
                                false
                            );

                            dilitant_visibility_assert_handler_decision(
                                $query,
                                true
                            );
                        }
                    );
                }
            );
        }
    );
}


/**
 * AM-T11.8 — проверка Preview nonce
 * и согласованности параметров запроса.
 *
 * Сохраняет внешний интерфейс,
 * используемый runner.
 */
function dilitant_visibility_test_t11_8(
    DilitantArticleFixtures $fixtures,
    DilitantVisibilityUsers $users
): void {
    /*
     * Независимые группы испытаний.
     *
     * Отказ одной группы
     * не отменяет проверку другой.
     */
    $groups = [
        'Native WordPress Preview' =>
            static function () use (
                $fixtures,
                $users
            ): void {
                dilitant_visibility_test_native_preview_nonce(
                    $fixtures,
                    $users
                );
            },

        'Article Model Request Validation' =>
            static function () use (
                $fixtures,
                $users
            ): void {
                dilitant_visibility_run_preview_request_validation(
                    $fixtures,
                    $users
                );
            },
    ];


    $errors = [];


    /*
     * Исполняем обе группы.
     */
    foreach (
        $groups as $name => $callback
    ) {
        try {
            $callback();

            echo 'PASS: AM-T11.8 '
                . $name
                . PHP_EOL;

        } catch (Throwable $error) {
            $errors[] =
                $name
                . ': '
                . $error->getMessage();

            fwrite(
                STDERR,

                'FAIL: AM-T11.8 '
                . $name
                . PHP_EOL
                . $error->getMessage()
                . PHP_EOL
            );
        }
    }


    /*
     * AM-T11.8 считается успешным
     * только при успешном завершении
     * обеих групп.
     */
    if ($errors !== []) {
        throw new RuntimeException(
            'AM-T11.8 preview authorization failed.'
            . PHP_EOL
            . implode(PHP_EOL, $errors)
        );
    }
}