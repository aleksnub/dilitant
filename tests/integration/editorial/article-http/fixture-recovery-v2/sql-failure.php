<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';

function hfr2_arm_sql_failure(string $run_id): void
{
    global $wpdb;

    hfur_guard();

    $manifest = hfur_require_manifest(
        $run_id,
        ['CREATING']
    );

    $record = hfr2_pending(
        $run_id,
        'post',
        ['CREATING']
    );

    hfr2_verify_users($manifest);

    $probe = hfr2_probe($run_id);

    hfur_require(
        $probe['post_ids'] === []
        && count($probe['user_ids']) === 4
        && count($probe['term_ids']) === 4
        && count($probe['taxonomy_ids']) === 4
        && $probe['partial_term_ids'] === [],
        'Unexpected SQL failure fixture state.'
    );

    $identity = $record['identity'];

    hfur_require(
        isset(
            $identity['title'],
            $identity['content_marker'],
            $identity['author_id']
        )
        && is_string($identity['title'])
        && is_string($identity['content_marker'])
        && is_int($identity['author_id'])
        && str_contains(
            $identity['title'],
            $run_id
        )
        && str_starts_with(
            $identity['content_marker'],
            'AM-CONTENT-' . $run_id . '-'
        )
        && in_array(
            $identity['author_id'],
            $probe['user_ids'],
            true
        ),
        'Invalid SQL failure target identity.'
    );

    $injected = false;
    $filter = null;

    $previous_suppression = $wpdb->suppress_errors(true);

    $filter = static function (
        string $sql
    ) use (
        &$filter,
        &$injected,
        $identity
    ): string {
        if (
            $injected
            || !str_contains($sql, 'LOCATE(BINARY')
            || !str_contains($sql, 'post_content')
            || !str_contains($sql, $identity['content_marker'])
        ) {
            return $sql;
        }

        hfur_guard();

        $injected = true;

        remove_filter(
            'query',
            $filter,
            PHP_INT_MAX
        );

        fwrite(
            STDERR,
            "INJECT: HF-M09-V2 post resolution SQL failure\n"
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
            &$filter,
            &$injected,
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
                    "FAIL: Fixture SQL injection was not reached.\n"
                );

                exit(1);
            }
        }
    );

    hfur_guard();
}