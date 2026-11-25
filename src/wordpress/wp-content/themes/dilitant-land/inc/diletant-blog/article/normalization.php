<?php
/**
 * Нормализация входных значений Article Model.
 *
 * Ответственность:
 *     - проверка положительных целых чисел;
 *     - предотвращение целочисленного переполнения;
 *     - проверка списков идентификаторов;
 *     - удаление повторяющихся идентификаторов.
 *
 * Нормативная основа:
 *     EDC-01
 *     AM-D02
 *
 * @package Dilitant_Land
 */

defined( 'ABSPATH' ) || exit;


/**
 * Нормализует положительное целое число.
 *
 * Допускаются:
 *     - положительные целые числа;
 *     - строки из десятичных цифр,
 *       представляющие положительные целые числа.
 *
 * Не допускаются:
 *     - ноль;
 *     - отрицательные значения;
 *     - дробные числа;
 *     - логические значения;
 *     - массивы;
 *     - объекты;
 *     - числа за пределами PHP_INT_MAX.
 *
 * @internal
 *
 * @param mixed $value Исходное значение.
 *
 * @return int|null
 */
function dilitant_land_normalize_blog_positive_integer(
    $value
) {
    if ( is_int( $value ) ) {
        return $value > 0 ? $value : null;
    }

    if ( ! is_string( $value ) ) {
        return null;
    }

    if ( ! preg_match( '/\A[0-9]+\z/', $value ) ) {
        return null;
    }

    /*
     * Удаляем ведущие нули до проверки диапазона.
     */
    $value = ltrim( $value, '0' );

    if ( '' === $value ) {
        return null;
    }

    /*
     * Проверяем возможность безопасного
     * преобразования строки в PHP integer.
     */
    $maximum = (string) PHP_INT_MAX;

    if ( strlen( $value ) > strlen( $maximum ) ) {
        return null;
    }

    if (
        strlen( $value ) === strlen( $maximum )
        && strcmp( $value, $maximum ) > 0
    ) {
        return null;
    }

    return (int) $value;
}


/**
 * Нормализует список положительных идентификаторов.
 *
 * Некорректный элемент делает недействительным
 * весь переданный список.
 *
 * Порядок элементов сохраняется.
 * Повторяющиеся идентификаторы удаляются.
 *
 * Пустой список является допустимым результатом
 * нормализации. Его дальнейшая интерпретация
 * определяется политикой конкретного аргумента.
 *
 * @internal
 *
 * @param mixed $values Исходный список.
 *
 * @return int[]|null
 */
function dilitant_land_normalize_blog_id_list(
    $values
) {
    if ( ! is_array( $values ) ) {
        return null;
    }

    $ids = array();

    foreach ( $values as $value ) {
        $id =
            dilitant_land_normalize_blog_positive_integer(
                $value
            );

        if ( null === $id ) {
            return null;
        }

        $ids[] = $id;
    }

    return array_values(
        array_unique( $ids )
    );
}