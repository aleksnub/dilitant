<?php

declare(strict_types=1);

if (
    PHP_SAPI !== 'cli'
    || !defined('DILITANT_TEST_DB_NAME')
    || !defined('DB_NAME')
    || DB_NAME !== DILITANT_TEST_DB_NAME
) {
    exit(1);
}

function hfur_arm_sql_failure(string $run_id): void
{
    global $wpdb;

    hfur_guard();

    $manifest = hfur_require_manifest(
        $run_id,
        ['CREATING']
    );

    hfur_require(
        count($manifest['objects']) === 1
        && $manifest['snapshot'] === null,
        'Unexpected SQL failure manifest structure.'
    );

    $record = hfur_pending_record($run_id);

    $id = hfur_require_owned_user(
        $record,
        $run_id
    );

    hfur_require(
        $id !== hfur_protected_id()
        && $id !== get_current_user_id(),
        'Invalid SQL failure target.'
    );

    $identity = $record['identity'];

    $expected = $wpdb->prepare(
        "SELECT ID, user_login, user_email, user_nicename
         FROM {$wpdb->users}
         WHERE user_login = %s
            OR user_email = %s
            OR user_nicename = %s
            OR (%d > 0 AND ID = %d)
         ORDER BY ID",
        $identity['login'],
        $identity['email'],
        $identity['nicename'],
        0,
        0
    );

    $normalize = static fn(string $sql): string =>
        trim((string) preg_replace('/\s+/', ' ', $sql));

    $expected = $normalize($expected);

    $injected = false;
    $previous_suppression = $wpdb->suppress_errors(true);

    $filter = null;

    $filter = static function (
        string $sql
    ) use (
        &$filter,
        &$injected,
        $expected,
        $normalize
    ): string {
        if ($injected || $normalize($sql) !== $expected) {
            return $sql;
        }

        $injected = true;

        remove_filter(
            'query',
            $filter,
            PHP_INT_MAX
        );

        fwrite(
            STDERR,
            "INJECT: HF-U-R06 user resolution SQL failure\n"
        );

        fflush(STDERR);

        return 'SELECT FROM';
    };

    add_filter(
        'query',
        $filter,
        PHP_INT_MAX,
        1
    );

    register_shutdown_function(
        static function () use (
            &$injected,
            &$filter,
            $previous_suppression
        ): void {
            global $wpdb;

            remove_filter(
                'query',
                $filter,
                PHP_INT_MAX
            );

            $wpdb->suppress_errors(
                $previous_suppression
            );

            if (!$injected) {
                fwrite(
                    STDERR,
                    "FAIL: SQL fault injection was not reached.\n"
                );

                exit(1);
            }
        }
    );

    hfur_guard();
}