<?php
/**
 * Нормативные сценарии проверки
 * параметров Preview.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D03 — Публичная доступность
 *     AM-T11.8 — Preview nonce
 *
 * Обязательные сценарии:
 *     PV-01–PV-18.
 *
 * Ответственность:
 *     - определение нормативного перечня;
 *     - формирование входных параметров;
 *     - определение ожидаемых решений;
 *     - сохранение идентификаторов.
 *
 * Ожидаемые результаты определяются
 * независимо от производственного кода.
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
 * Возвращает обязательный перечень
 * внутренних идентификаторов.
 */
function dilitant_visibility_expected_preview_validation_ids(): array
{
    $ids = [];

    for (
        $index = 1;
        $index <= DILITANT_PREVIEW_VALIDATION_TOTAL;
        $index++
    ) {
        $ids[] = sprintf(
            'PV-%02d',
            $index
        );
    }

    return $ids;
}


/**
 * Формирует нормативные сценарии
 * проверки параметров Preview.
 *
 * Каждый сценарий содержит:
 *
 *     description
 *     post
 *     user_id
 *     request_args
 *     expected_edit_permission
 *     expected_preview
 *     expected_public
 */
function dilitant_visibility_build_preview_validation_cases(
    DilitantArticleFixtures $fixtures,
    DilitantVisibilityUsers $users
): array {
    /*
     * Получаем проверенные
     * исходные тестовые данные.
     */
    $data =
        dilitant_visibility_prepare_preview_validation_fixtures(
            $fixtures,
            $users
        );


    $draft_id =
        $data['draft_id'];

    $valid_nonce =
        $data['valid_nonce'];

    $other_nonce =
        $data['other_nonce'];

    $subscriber_nonce =
        $data['subscriber_nonce'];

    $overflow_id =
        $data['overflow_id'];


    /*
     * Фабрика сценариев
     * для закрытой статьи.
     */
    $make_draft_case =
        dilitant_visibility_make_draft_validation_factory(
            $data['draft'],
            $users
        );


    /*
     * Фабрика сценариев
     * для публичной статьи.
     */
    $make_public_case =
        dilitant_visibility_make_public_validation_factory(
            $data['public']
        );


    /*
     * Нормативная таблица.
     */
    return [
        /*
         * PV-01.
         *
         * Preview сохранённой записи
         * без параметров autosave.
         */
        'PV-01' => $make_draft_case(
            'Saved post preview without autosave parameters',

            [],

            true
        ),


        /*
         * PV-02.
         *
         * Неполная пара: только ID.
         */
        'PV-02' => $make_draft_case(
            'Preview ID without nonce',

            [
                'preview_id' =>
                    $draft_id,
            ],

            false
        ),


        /*
         * PV-03.
         *
         * Неполная пара: только nonce.
         */
        'PV-03' => $make_draft_case(
            'Preview nonce without ID',

            [
                'preview_nonce' =>
                    $valid_nonce,
            ],

            false
        ),


        /*
         * PV-04.
         *
         * Пустой nonce.
         */
        'PV-04' => $make_draft_case(
            'Empty preview nonce',

            [
                'preview_id' =>
                    $draft_id,

                'preview_nonce' =>
                    '',
            ],

            false
        ),


        /*
         * PV-05.
         *
         * ID передан в виде массива.
         */
        'PV-05' => $make_draft_case(
            'Preview ID supplied as array',

            [
                'preview_id' =>
                    [$draft_id],

                'preview_nonce' =>
                    $valid_nonce,
            ],

            false
        ),


        /*
         * PV-06.
         *
         * Nonce передан в виде массива.
         */
        'PV-06' => $make_draft_case(
            'Preview nonce supplied as array',

            [
                'preview_id' =>
                    $draft_id,

                'preview_nonce' =>
                    [$valid_nonce],
            ],

            false
        ),


        /*
         * PV-07.
         *
         * Отрицательный идентификатор.
         */
        'PV-07' => $make_draft_case(
            'Negative preview ID',

            [
                'preview_id' =>
                    '-' . $draft_id,

                'preview_nonce' =>
                    $valid_nonce,
            ],

            false
        ),


        /*
         * PV-08.
         *
         * Нулевой идентификатор.
         */
        'PV-08' => $make_draft_case(
            'Zero preview ID',

            [
                'preview_id' =>
                    '0',

                'preview_nonce' =>
                    $valid_nonce,
            ],

            false
        ),


        /*
         * PV-09.
         *
         * Дробный идентификатор.
         */
        'PV-09' => $make_draft_case(
            'Fractional preview ID',

            [
                'preview_id' =>
                    $draft_id . '.0',

                'preview_nonce' =>
                    $valid_nonce,
            ],

            false
        ),


        /*
         * PV-10.
         *
         * Переполнение диапазона integer.
         */
        'PV-10' => $make_draft_case(
            'Preview ID integer overflow',

            [
                'preview_id' =>
                    $overflow_id,

                'preview_nonce' =>
                    $valid_nonce,
            ],

            false
        ),


        /*
         * PV-11.
         *
         * Неканонический идентификатор.
         */
        'PV-11' => $make_draft_case(
            'Preview ID with leading zero',

            [
                'preview_id' =>
                    '0' . $draft_id,

                'preview_nonce' =>
                    $valid_nonce,
            ],

            false
        ),


        /*
         * PV-12.
         *
         * Правильный ID,
         * недействительный nonce.
         */
        'PV-12' => $make_draft_case(
            'Invalid nonce for correct preview ID',

            [
                'preview_id' =>
                    $draft_id,

                'preview_nonce' =>
                    'invalid-preview-nonce',
            ],

            false
        ),


        /*
         * PV-13.
         *
         * Nonce другой записи.
         */
        'PV-13' => $make_draft_case(
            'Nonce belonging to another post',

            [
                'preview_id' =>
                    $draft_id,

                'preview_nonce' =>
                    $other_nonce,
            ],

            false
        ),


        /*
         * PV-14.
         *
         * Nonce другого пользователя.
         */
        'PV-14' => $make_draft_case(
            'Nonce belonging to another user',

            [
                'preview_id' =>
                    $draft_id,

                'preview_nonce' =>
                    $subscriber_nonce,
            ],

            false
        ),


        /*
         * PV-15.
         *
         * Корректная пара параметров
         * Autosave Preview.
         */
        'PV-15' => $make_draft_case(
            'Valid autosave preview parameters',

            [
                'preview_id' =>
                    $draft_id,

                'preview_nonce' =>
                    $valid_nonce,
            ],

            true
        ),


        /*
         * PV-16.
         *
         * Анонимный Preview
         * публичной статьи.
         */
        'PV-16' => $make_public_case(
            'Anonymous preview of public article',

            0,

            false,

            false
        ),


        /*
         * PV-17.
         *
         * Preview публичной статьи
         * пользователем без edit_post.
         */
        'PV-17' => $make_public_case(
            'Subscriber preview of public article',

            $users->subscriber,

            false,

            false
        ),


        /*
         * PV-18.
         *
         * Авторизованный Preview
         * публичной статьи.
         */
        'PV-18' => $make_public_case(
            'Authorized preview of public article',

            $users->administrator,

            true,

            true
        ),
    ];
}