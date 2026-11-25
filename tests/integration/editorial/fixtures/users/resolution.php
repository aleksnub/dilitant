<?php

declare(strict_types=1);

enum DilitantUserResolutionStatus: string
{
    case ABSENT = 'ABSENT';
    case RESOLVED = 'RESOLVED';
    case BLOCKED = 'BLOCKED';
}

final class DilitantFixtureUserResolution
{
    public static function guard(): void
    {
        dilitant_http_require_bootstrap();
        dilitant_fixture_assert_lock();
    }

    public static function query(string $sql): array
    {
        global $wpdb;

        self::guard();

        $rows = $wpdb->get_results($sql, ARRAY_A);
        $error = $wpdb->last_error;

        self::guard();

        if ($error !== '' || !is_array($rows)) {
            throw new RuntimeException('User SQL query failed.');
        }

        return $rows;
    }

    public static function count(string $sql): int
    {
        $rows = self::query($sql);

        if (
            count($rows) !== 1
            || !isset($rows[0]['n'])
            || !ctype_digit((string) $rows[0]['n'])
        ) {
            throw new RuntimeException('Invalid user SQL count.');
        }

        return (int) $rows[0]['n'];
    }

    public static function protected_id(): int
    {
        global $wpdb;

        $rows = self::query(
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
                'Protected administrator identity unavailable.'
            );
        }

        return (int) $rows[0]['ID'];
    }

    public static function resolve(
        array $record,
        string $run_id
    ): array {
        global $wpdb;

        self::guard();

        DilitantFixtureUserIdentity::validate_record(
            $record,
            $run_id
        );

        $identity = $record['identity'];
        $expected_id = $record['id'];
        $protected_id = self::protected_id();

        if ($expected_id === $protected_id) {
            return self::blocked('Protected user ID.');
        }

        $rows = self::query(
            $wpdb->prepare(
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
                $expected_id,
                $expected_id
            )
        );

        if ($rows === []) {
            return [
                'status' => DilitantUserResolutionStatus::ABSENT,
                'id' => 0,
                'reason' => '',
            ];
        }

        if (count($rows) !== 1) {
            return self::blocked(
                'Ambiguous fixture user identity.'
            );
        }

        $row = $rows[0];
        $id = (int) $row['ID'];

        if (
            $id <= 0
            || $id === $protected_id
            || ($expected_id !== 0 && $expected_id !== $id)
            || !DilitantFixtureUserIdentity::matches(
                $identity,
                $row,
                $run_id
            )
        ) {
            return self::blocked(
                'Fixture user identity mismatch.'
            );
        }

        self::guard();

        return [
            'status' => DilitantUserResolutionStatus::RESOLVED,
            'id' => $id,
            'reason' => '',
        ];
    }

    public static function require_absent(
        array $record,
        string $run_id
    ): void {
        $result = self::resolve($record, $run_id);

        if (
            $result['status']
            !== DilitantUserResolutionStatus::ABSENT
        ) {
            throw new RuntimeException(
                'Fixture user identity collision.'
            );
        }
    }

    public static function require_resolved(
        array $record,
        string $run_id
    ): int {
        $result = self::resolve($record, $run_id);

        if (
            $result['status']
            !== DilitantUserResolutionStatus::RESOLVED
        ) {
            throw new RuntimeException(
                'Fixture user resolution failed: '
                . $result['reason']
            );
        }

        return $result['id'];
    }

    private static function blocked(string $reason): array
    {
        return [
            'status' => DilitantUserResolutionStatus::BLOCKED,
            'id' => 0,
            'reason' => $reason,
        ];
    }
}