<?php
/**
 * AM-CQ-02 — исключение защищённых статей.
 * AM-CQ-03 — исключение неопубликованных статей.
 *
 * EDC-01 / AM-D02.
 *
 * Проверяется публичная выборка независимо
 * от входных аргументов и полномочий
 * текущего пользователя.
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
 * AM-CQ-02.
 *
 * Проверяет исключение опубликованной
 * статьи, защищённой паролем.
 */
function dilitant_catalog_test_cq02(
    DilitantArticleFixtures $fixtures
): void {
    $protected = get_post(
        $fixtures->password_post
    );

    dilitant_test_assert(
        $protected instanceof WP_Post,
        'Password fixture is missing.'
    );

    dilitant_test_assert_same(
        'publish',
        $protected->post_status,
        'Password fixture is not published.'
    );

    dilitant_test_assert(
        $protected->post_password !== '',
        'Password fixture is not protected.'
    );


    /*
     * Обычная публичная выборка.
     */
    $query = dilitant_catalog_execute();

    dilitant_catalog_assert_full_set(
        $query,
        $fixtures
    );

    dilitant_test_assert(
        !in_array(
            $fixtures->password_post,
            dilitant_test_query_ids($query),
            true
        ),
        'Protected article entered catalog.'
    );


    /*
     * Явное включение защищённой статьи
     * с передачей известного пароля.
     */
    $query = dilitant_catalog_execute([
        'has_password' => true,

        'post_password' =>
            $fixtures->fixture_password(),

        'post__in' => [
            $fixtures->password_post,
        ],
    ]);

    dilitant_catalog_assert_full_set(
        $query,
        $fixtures
    );


    /*
     * Попытка снять обязательное
     * ограничение has_password.
     */
    $query = dilitant_catalog_execute([
        'has_password' => null,
    ]);

    dilitant_catalog_assert_full_set(
        $query,
        $fixtures
    );
}


/**
 * AM-CQ-03.
 *
 * Проверяет исключение черновиков,
 * приватных и будущих публикаций.
 */
function dilitant_catalog_test_cq03(
    DilitantArticleFixtures $fixtures
): void {
    $forbidden = [
        $fixtures->draft_post,
        $fixtures->private_post,
        $fixtures->future_post,
    ];


    /*
     * Попытка включить неопубликованные
     * записи через произвольный статус.
     */
    $query = dilitant_catalog_execute([
        'post_status' => 'any',
        'post__in' => $forbidden,
    ]);

    dilitant_catalog_assert_full_set(
        $query,
        $fixtures
    );

    dilitant_test_assert_same(
        [],
        array_values(
            array_intersect(
                $forbidden,
                dilitant_test_query_ids($query)
            )
        ),
        'Unpublished articles entered catalog.'
    );


    /*
     * Публичный запрос в контексте
     * пользователя с административными
     * полномочиями.
     */
    $previous_user_id =
        get_current_user_id();

    $administrator = get_user_by(
        'login',
        'testadmin'
    );

    dilitant_test_assert(
        $administrator instanceof WP_User,
        'Test administrator is missing.'
    );

    try {
        wp_set_current_user(
            (int) $administrator->ID
        );

        dilitant_test_assert(
            current_user_can('manage_options'),
            'Test user is not an administrator.'
        );

        dilitant_test_assert_same(
            false,
            is_admin(),
            'Public query became administrative request.'
        );

        $query = dilitant_catalog_execute([
            'post_status' => [
                'publish',
                'private',
                'draft',
                'future',
            ],

            'perm' => 'readable',
        ]);

        dilitant_catalog_assert_full_set(
            $query,
            $fixtures
        );


        /*
         * Административные полномочия
         * не отменяют публичную политику.
         */
        $query = dilitant_catalog_execute([
            'post_status' => 'any',
            'has_password' => true,

            'post__in' =>
                $fixtures->excluded_post_ids(),
        ]);

        dilitant_catalog_assert_full_set(
            $query,
            $fixtures
        );

    } finally {
        wp_set_current_user(
            $previous_user_id
        );
    }

    dilitant_test_assert_same(
        $previous_user_id,
        get_current_user_id(),
        'Original user context was not restored.'
    );
}