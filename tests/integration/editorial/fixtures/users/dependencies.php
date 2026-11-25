<?php

declare(strict_types=1);

final class DilitantFixtureUserDependencies
{
    public static function inspect(int $id): array
    {
        global $wpdb;

        DilitantFixtureUserResolution::guard();

        if ($id <= 0) {
            throw new RuntimeException(
                'Invalid dependency user ID.'
            );
        }

        $queries = [
            'posts' =>
                "SELECT COUNT(*) AS n
                 FROM {$wpdb->posts}
                 WHERE post_author = %d",

            'links' =>
                "SELECT COUNT(*) AS n
                 FROM {$wpdb->links}
                 WHERE link_owner = %d",

            'comments' =>
                "SELECT COUNT(*) AS n
                 FROM {$wpdb->comments}
                 WHERE user_id = %d",

            'usermeta' =>
                "SELECT COUNT(*) AS n
                 FROM {$wpdb->usermeta}
                 WHERE user_id = %d",

            'sessions' =>
                "SELECT COUNT(*) AS n
                 FROM {$wpdb->usermeta}
                 WHERE user_id = %d
                   AND meta_key = 'session_tokens'",

            'application_passwords' =>
                "SELECT COUNT(*) AS n
                 FROM {$wpdb->usermeta}
                 WHERE user_id = %d
                   AND meta_key = '_application_passwords'",
        ];

        $result = [];

        foreach ($queries as $name => $sql) {
            $result[$name] =
                DilitantFixtureUserResolution::count(
                    $wpdb->prepare($sql, $id)
                );
        }

        DilitantFixtureUserResolution::guard();

        return $result;
    }

    public static function require_deletable(
        array $record,
        string $run_id
    ): void {
        if (($record['id'] ?? 0) <= 0) {
            throw new RuntimeException(
                'User deletion requires a confirmed ID.'
            );
        }

        $id = DilitantFixtureUserResolution::require_resolved(
            $record,
            $run_id
        );

        if (
            $id === DilitantFixtureUserResolution::protected_id()
            || $id === get_current_user_id()
        ) {
            throw new RuntimeException(
                'Refusing to delete protected or active user.'
            );
        }

        $dependencies = self::inspect($id);

        foreach ([
            'posts',
            'links',
            'comments',
            'sessions',
            'application_passwords',
        ] as $name) {
            if ($dependencies[$name] !== 0) {
                throw new RuntimeException(
                    "Fixture user has residual {$name}."
                );
            }
        }

        DilitantFixtureUserResolution::guard();
    }

    public static function require_absent(
        array $record,
        string $run_id
    ): void {
        DilitantFixtureUserIdentity::validate_record(
            $record,
            $run_id
        );

        $id = $record['id'];

        if ($id <= 0) {
            throw new RuntimeException(
                'Invalid confirmed user ID.'
            );
        }

        DilitantFixtureUserResolution::require_absent(
            $record,
            $run_id
        );

        foreach (self::inspect($id) as $name => $count) {
            if ($count !== 0) {
                throw new RuntimeException(
                    "Deleted user has residual {$name}."
                );
            }
        }

        DilitantFixtureUserResolution::guard();
    }
}