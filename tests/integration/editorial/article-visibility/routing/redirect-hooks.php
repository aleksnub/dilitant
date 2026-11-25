<?php
/**
 * Управление обработчиками
 * перенаправления в испытаниях AM-D03.
 *
 * Нормативная основа:
 *     ТП-03 — Editorial
 *     EDC-01 — Article Model
 *     AM-D03 — Публичная доступность
 *
 * Ответственность:
 *     - сохранение регистрации Hooks;
 *     - восстановление приоритетов;
 *     - восстановление исходного
 *       состояния обработчиков;
 *     - проверка полноты восстановления.
 *
 * Производственная конфигурация
 * не изменяется за пределами испытания.
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
 * Сохраняет состояние обработчиков,
 * удаляемых производственным отказом.
 *
 * Для каждого обработчика фиксируется:
 *
 *     int   — приоритет регистрации;
 *     false — обработчик отсутствует.
 */
function dilitant_visibility_capture_redirect_hooks(): array
{
    $functions = [
        'redirect_canonical',

        'wp_old_slug_redirect',
    ];

    $state = [];

    foreach ($functions as $function) {
        $state[$function] =
            has_action(
                'template_redirect',

                $function
            );
    }

    return $state;
}


/**
 * Восстанавливает исходную регистрацию
 * обработчиков перенаправления.
 */
function dilitant_visibility_restore_redirect_hooks(
    array $state
): void {
    foreach (
        $state as $function => $priority
    ) {
        /*
         * Определяем текущее состояние.
         */
        $current = has_action(
            'template_redirect',

            $function
        );


        /*
         * Если состояние не изменилось,
         * дополнительных действий
         * не требуется.
         */
        if ($current === $priority) {
            continue;
        }


        /*
         * Удаляем регистрацию,
         * отличающуюся от исходной.
         */
        if ($current !== false) {
            remove_action(
                'template_redirect',

                $function,

                $current
            );
        }


        /*
         * Восстанавливаем первоначальную
         * регистрацию и её приоритет.
         */
        if ($priority !== false) {
            add_action(
                'template_redirect',

                $function,

                $priority,

                1
            );
        }
    }


    /*
     * Подтверждаем полное восстановление
     * отслеживаемых обработчиков.
     */
    dilitant_test_assert_same(
        $state,

        dilitant_visibility_capture_redirect_hooks(),

        'Redirect hooks were not restored.'
    );
}