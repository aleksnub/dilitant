<?php

declare(strict_types=1);

trait DilitantVisibilityUserIdentity
{
    private function query(string $sql): array
    {
        global $wpdb;

        dilitant_visibility_verify_environment();

        $rows = $wpdb->get_results($sql, ARRAY_A);
        $error = $wpdb->last_error;

        if ($error !== '' || !is_array($rows)) {
            throw new RuntimeException(
                'Visibility user SQL query failed.'
            );
        }

        dilitant_visibility_verify_environment();

        return $rows;
    }

    private function count_rows(string $sql): int
    {
        $rows = $this->query($sql);

        if (
            count($rows) !== 1
            || !array_key_exists('n', $rows[0])
            || !ctype_digit((string) $rows[0]['n'])
        ) {
            throw new RuntimeException(
                'Invalid visibility user SQL count.'
            );
        }

        return (int) $rows[0]['n'];
    }

    private function protected_admin_id(): int
    {
        global $wpdb;

        $rows = $this->query(
            $wpdb->prepare(
                "SELECT ID, user_login
                 FROM {$wpdb->users}
                 WHERE user_login = %s",
                'testadmin'
            )
        );

        if (
            count($rows) !== 1
            || $rows[0]['user_login'] !== 'testadmin'
            || (int) $rows[0]['ID'] <= 0
        ) {
            throw new RuntimeException(
                'Protected test administrator identity unavailable.'
            );
        }

        return (int) $rows[0]['ID'];
    }

    private function identity_candidates(
        int $id,
        string $login
    ): array {
        global $wpdb;

        return $this->query(
            $wpdb->prepare(
                "SELECT ID, user_login, user_email
                 FROM {$wpdb->users}
                 WHERE ID = %d
                    OR user_login = %s
                    OR user_email = %s
                 ORDER BY ID",
                $id,
                $login,
                $login . '@example.invalid'
            )
        );
    }

    private function resolve_owned_user(
        int $id,
        string $login
    ): bool {
        if (
            $id <= 0
            || !array_key_exists($id, $this->created_ids)
            || $this->created_ids[$id] !== $login
            || !in_array(
                $login,
                [
                    'am-vis-subscriber-' . $this->run_id,
                    'am-vis-author-' . $this->run_id,
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Invalid visibility user registry identity.'
            );
        }

        $rows = $this->identity_candidates($id, $login);

        if ($rows === []) {
            return false;
        }

        if (
            count($rows) !== 1
            || (int) $rows[0]['ID'] !== $id
            || $rows[0]['user_login'] !== $login
            || $rows[0]['user_email']
                !== $login . '@example.invalid'
        ) {
            throw new RuntimeException(
                'Visibility user identity is ambiguous or changed.'
            );
        }

        return true;
    }
}