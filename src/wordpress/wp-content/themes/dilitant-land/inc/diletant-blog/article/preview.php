<?php
/**
 * Авторизация предварительного просмотра
 * статей блога «Дилетанты в Армении».
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D03 — Публичная доступность
 *
 * Ответственность:
 *     - проверка контекста Preview;
 *     - проверка аутентификации;
 *     - проверка объектных полномочий;
 *     - проверка допустимого статуса;
 *     - проверка идентичности записи;
 *     - проверка параметров Autosave Preview;
 *     - проверка действительности nonce;
 *     - определение допустимости отображения.
 *
 * Предварительный просмотр не изменяет
 * нормативную публичную доступность.
 *
 * Поддерживаются два режима:
 *
 *     1. Preview сохранённой записи.
 *     2. Autosave Preview с подтверждёнными
 *        preview_id и preview_nonce.
 *
 * Компонент не изменяет состояние запроса,
 * не отправляет HTTP-заголовки
 * и не выполняет перенаправления.
 *
 * @package Dilitant_Land
 */

defined( 'ABSPATH' ) || exit;


/**
 * Проверяет допустимость статуса записи
 * для предварительного просмотра.
 *
 * Корзина, ревизии, auto-draft
 * и неизвестные статусы исключаются.
 *
 * @internal
 *
 * @param WP_Post $post Запись WordPress.
 *
 * @return bool
 */
function dilitant_land_is_blog_preview_status_allowed(
    WP_Post $post
): bool {
    return in_array(
        $post->post_status,

        array(
            'publish',
            'draft',
            'pending',
            'private',
            'future',
        ),

        true
    );
}


/**
 * Проверяет параметры Autosave Preview.
 *
 * Допустимы два случая:
 *
 *     - оба параметра отсутствуют;
 *
 *     - оба параметра присутствуют,
 *       идентификатор соответствует записи,
 *       nonce действителен.
 *
 * Любой промежуточный вариант
 * приводит к закрытому отказу.
 *
 * @internal
 *
 * @param WP_Post $post Запрошенная запись.
 *
 * @return bool
 */
function dilitant_land_verify_blog_preview_parameters(
    WP_Post $post
): bool {
    /*
     * Проверяем наличие параметров,
     * а не только непустоту значений.
     *
     * Параметр с пустым или некорректным
     * значением не считается отсутствующим.
     */
    $has_preview_id = array_key_exists(
        'preview_id',
        $_GET
    );

    $has_preview_nonce = array_key_exists(
        'preview_nonce',
        $_GET
    );


    /*
     * Режим 1.
     *
     * Preview сохранённой записи.
     *
     * Отсутствие обоих параметров
     * допускается штатным WordPress.
     *
     * Аутентификация и edit_post
     * проверяются вызывающим интерфейсом.
     */
    if (
        ! $has_preview_id
        && ! $has_preview_nonce
    ) {
        return true;
    }


    /*
     * Неполная пара параметров.
     *
     * Недопустимо автоматически
     * переключаться на обычный Preview
     * при наличии одного параметра.
     */
    if (
        ! $has_preview_id
        || ! $has_preview_nonce
    ) {
        return false;
    }


    /*
     * Режим 2.
     *
     * Autosave Preview.
     */
    $raw_preview_id =
        $_GET['preview_id'];

    $raw_preview_nonce =
        $_GET['preview_nonce'];


    /*
     * HTTP-параметры должны быть
     * скалярными строками.
     *
     * Массивы, null и другие типы
     * отклоняются до обработки.
     */
    if (
        ! is_string( $raw_preview_id )
        || ! is_string( $raw_preview_nonce )
    ) {
        return false;
    }


    /*
     * WordPress применяет экранирование
     * входящих параметров запроса.
     *
     * Восстанавливаем исходные значения
     * перед проверкой.
     */
    $preview_id = wp_unslash(
        $raw_preview_id
    );

    $preview_nonce = wp_unslash(
        $raw_preview_nonce
    );


    /*
     * Идентификатор должен иметь
     * каноническое десятичное представление.
     *
     * Не используем absint(), поскольку
     * преобразование отрицательных чисел,
     * дробей и произвольных строк
     * не является проверкой идентичности.
     */
    if (
        1 !== preg_match(
            '/\A[1-9][0-9]*\z/',
            $preview_id
        )
    ) {
        return false;
    }


    /*
     * Критический инвариант AM-T11.8.
     *
     * preview_id обязан соответствовать
     * фактически запрошенной записи.
     *
     * Сравнение строк исключает
     * переполнение при преобразовании
     * произвольного входного числа.
     */
    if (
        (string) $post->ID
        !== $preview_id
    ) {
        return false;
    }


    /*
     * Пустой nonce не является
     * подтверждением предварительного
     * просмотра.
     */
    if ( '' === $preview_nonce ) {
        return false;
    }


    /*
     * Независимо проверяем nonce
     * штатным механизмом WordPress.
     *
     * Действие связано с конкретной
     * запрошенной записью.
     *
     * WordPress также связывает nonce
     * с текущим пользователем и сессией.
     */
    $nonce_result = wp_verify_nonce(
        $preview_nonce,

        'post_preview_' . $post->ID
    );


    /*
     * wp_verify_nonce() возвращает:
     *
     *     1     — действительный nonce;
     *     2     — действительный nonce
     *             предыдущего временного окна;
     *     false — недействительный nonce.
     */
    return in_array(
        $nonce_result,
        array( 1, 2 ),
        true
    );
}


/**
 * Проверяет полномочия текущего пользователя
 * на предварительный просмотр статьи.
 *
 * Все обязательные условия должны
 * подтверждаться одновременно.
 *
 * Положительный результат не изменяет
 * публичную доступность записи.
 *
 * @param WP_Post $post Запись WordPress.
 *
 * @return bool
 */
function dilitant_land_can_preview_blog_article(
    $post
): bool {
    /*
     * Проверяем переданный объект.
     */
    if ( ! $post instanceof WP_Post ) {
        return false;
    }


    /*
     * Preview Article Model допускается
     * исключительно для записей блога.
     */
    if (
        ! dilitant_land_is_blog_article(
            $post
        )
    ) {
        return false;
    }


    /*
     * Проверяем действительный контекст
     * предварительного просмотра.
     */
    if (
        ! is_singular( 'post' )
        || ! is_preview()
        || ! is_user_logged_in()
    ) {
        return false;
    }


    /*
     * Проверяем допустимый статус.
     */
    if (
        ! dilitant_land_is_blog_preview_status_allowed(
            $post
        )
    ) {
        return false;
    }


    /*
     * Проверяем идентичность объекта,
     * разрешённого главным запросом.
     */
    $queried_post = get_queried_object();

    if ( ! $queried_post instanceof WP_Post ) {
        return false;
    }

    if (
        'post' !== $queried_post->post_type
        || (int) $post->ID <= 0
        || (int) $queried_post->ID !== (int) $post->ID
        || (int) get_queried_object_id() !== (int) $post->ID
    ) {
        return false;
    }


    /*
     * Проверяем полномочия пользователя
     * относительно конкретной записи.
     *
     * Проверка роли или факта входа
     * в систему сама по себе недостаточна.
     */
    if (
        ! current_user_can(
            'edit_post',
            $post->ID
        )
    ) {
        return false;
    }


    /*
     * Проверяем согласованность параметров
     * предварительного просмотра.
     *
     * При наличии Autosave Preview
     * дополнительно проверяются:
     *
     *     - preview_id;
     *     - preview_nonce;
     *     - привязка к записи;
     *     - действительность nonce.
     */
    if (
        ! dilitant_land_verify_blog_preview_parameters(
            $post
        )
    ) {
        return false;
    }


    /*
     * Все обязательные условия
     * авторизации подтверждены.
     */
    return true;
}


/**
 * Определяет допустимость отображения
 * статьи в текущем запросе.
 *
 * Обычный публичный запрос:
 *
 *     требуется нормативная
 *     публичная доступность.
 *
 * Запрос Preview:
 *
 *     требуется самостоятельная
 *     авторизация предварительного
 *     просмотра.
 *
 * Публичный статус статьи не должен
 * использоваться для обхода проверки
 * предварительного просмотра её
 * неопубликованных изменений.
 *
 * @param WP_Post $post Запись WordPress.
 *
 * @return bool
 */
function dilitant_land_can_render_blog_article(
    $post
): bool {
    if ( ! $post instanceof WP_Post ) {
        return false;
    }


    /*
     * Preview является отдельным
     * режимом отображения.
     *
     * Даже опубликованная статья
     * в контексте Preview требует
     * проверки полномочий.
     */
    if ( is_preview() ) {
        return dilitant_land_can_preview_blog_article(
            $post
        );
    }


    /*
     * Обычный запрос допускается только
     * для нормативно публичной статьи.
     */
    return dilitant_land_is_public_blog_article(
        $post
    );
}