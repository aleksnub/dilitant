<?php

declare(strict_types=1);

final class DilitantFixtureUserCreation
{
    public static function create(
        string $run_id,
        string $slot,
        DilitantFixtureJournal $journal
    ): array {
        DilitantFixtureUserResolution::guard();

        $identity = DilitantFixtureUserIdentity::create(
            $run_id,
            $slot
        );

        $record = [
            'kind' => 'user',
            'id' => 0,
            'taxonomy_id' => 0,
            'identity' => $identity,
        ];

        DilitantFixtureUserResolution::require_absent(
            $record,
            $run_id
        );

        $journal->intent('user', $identity);

        DilitantFixtureUserResolution::guard();

        DilitantFixtureUserResolution::require_absent(
            $record,
            $run_id
        );

        $password = bin2hex(random_bytes(32));

        try {
            $id = wp_insert_user([
                'user_login' => $identity['login'],
                'user_email' => $identity['email'],
                'user_nicename' => $identity['nicename'],
                'display_name' => $identity['login'],
                'user_pass' => $password,
                'role' => $identity['role'],
                'show_admin_bar_front' => 'false',
            ]);
        } finally {
            unset($password);
        }

        DilitantFixtureUserResolution::guard();

        if (is_wp_error($id) || !is_int($id) || $id <= 0) {
            throw new RuntimeException(
                'Fixture user creation failed.'
            );
        }

        $record['id'] = $id;

        $resolved_id =
            DilitantFixtureUserResolution::require_resolved(
                $record,
                $run_id
            );

        if ($resolved_id !== $id) {
            throw new RuntimeException(
                'Created user ID mismatch.'
            );
        }

        DilitantFixtureUserPermissions::verify(
            $record,
            $run_id
        );

        DilitantFixtureUserResolution::guard();

        $journal->confirm($id, 0);

        DilitantFixtureUserResolution::guard();

        return $record;
    }
}