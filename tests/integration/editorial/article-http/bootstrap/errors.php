<?php
/**
 * Типизированные ошибки HTTP Test Bootstrap.
 *
 * Нормативная основа:
 *     HTTP-D02 — Fixtures & Authentication
 *     HF-D01 — Shared Test Bootstrap
 *
 * Ответственность:
 *     - классификация ошибок инициализации;
 *     - предоставление стабильных категорий отказа;
 *     - безопасная диагностическая отчётность.
 */

declare(strict_types=1);


if (PHP_SAPI !== 'cli') {
    http_response_code(403);

    exit(1);
}


/**
 * Базовая ошибка HTTP Bootstrap.
 */
class DilitantHttpBootstrapException
    extends RuntimeException
{
}


/**
 * Нарушение требований к окружению.
 */
final class DilitantHttpEnvironmentException
    extends DilitantHttpBootstrapException
{
}


/**
 * Ошибка загрузки WordPress.
 */
final class DilitantHttpWordPressException
    extends DilitantHttpBootstrapException
{
}


/**
 * Нарушение инвариантов изоляции.
 */
final class DilitantHttpIsolationException
    extends DilitantHttpBootstrapException
{
}


/**
 * Отсутствие обязательного интерфейса.
 */
final class DilitantHttpInterfaceException
    extends DilitantHttpBootstrapException
{
}


/**
 * Аварийное завершение HTTP Bootstrap.
 *
 * Вывод выполняется исключительно в STDERR.
 *
 * STDOUT резервируется для машинных результатов
 * последующих PHP-операций.
 */
function dilitant_http_bootstrap_abort(
    Throwable $error
): never {
    if ($error instanceof DilitantHttpBootstrapException) {
        $message = $error->getMessage();

    } else {
        /*
         * Не выводим произвольные сообщения
         * сторонних исключений: они могут
         * содержать чувствительные данные.
         */

        $message = 'Unexpected bootstrap error: '
            . get_class($error);
    }


    fwrite(
        STDERR,
        'ABORT: HTTP Bootstrap: '
        . $message
        . PHP_EOL
    );

    exit(1);
}