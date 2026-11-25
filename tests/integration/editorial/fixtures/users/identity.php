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

final class DilitantFixtureUserIdentity
{
    private const SLOTS = [
        'administrator' => ['administrator', 'administrator'],
        'subscriber' => ['subscriber', 'subscriber'],
        'author_a' => ['author', 'author-a'],
        'author_b' => ['author', 'author-b'],
    ];

    private const KEYS = [
        'slot',
        'login',
        'email',
        'nicename',
        'role',
    ];

    public static function slots(): array
    {
        return array_keys(self::SLOTS);
    }

    public static function role(string $slot): string
    {
        return self::resolve_slot($slot)[0];
    }

    public static function create(
        string $run_id,
        string $slot
    ): array {
        self::validate_run_id($run_id);

        [$role, $segment] = self::resolve_slot($slot);

        $login = "am-http-{$segment}-{$run_id}";

        $identity = [
            'slot' => $slot,
            'login' => $login,
            'email' => $login . '@example.invalid',
            'nicename' => 'am-user-' . $run_id . '-'
                . bin2hex(random_bytes(8)),
            'role' => $role,
        ];

        self::validate($identity, $run_id);

        return $identity;
    }

    public static function validate(
        array $identity,
        string $run_id
    ): void {
        self::validate_run_id($run_id);
        self::validate_keys($identity, self::KEYS);

        foreach (self::KEYS as $key) {
            if (!is_string($identity[$key])) {
                throw new RuntimeException(
                    "Invalid user identity field: {$key}."
                );
            }
        }

        [$role, $segment] = self::resolve_slot(
            $identity['slot']
        );

        $login = "am-http-{$segment}-{$run_id}";

        if ($identity['login'] !== $login) {
            throw new RuntimeException(
                'Invalid fixture user login.'
            );
        }

        if ($identity['email'] !== $login . '@example.invalid') {
            throw new RuntimeException(
                'Invalid fixture user email.'
            );
        }

        if ($identity['role'] !== $role) {
            throw new RuntimeException(
                'Invalid fixture user role.'
            );
        }

        if (
            preg_match(
                '/\Aam-user-' . $run_id . '-[0-9a-f]{16}\z/',
                $identity['nicename']
            ) !== 1
        ) {
            throw new RuntimeException(
                'Invalid fixture user nicename.'
            );
        }
    }

    public static function validate_record(
        array $record,
        string $run_id
    ): void {
        self::validate_keys($record, [
            'kind',
            'id',
            'taxonomy_id',
            'identity',
        ]);

        if (
            $record['kind'] !== 'user'
            || !is_int($record['id'])
            || $record['id'] < 0
            || $record['taxonomy_id'] !== 0
            || !is_array($record['identity'])
        ) {
            throw new RuntimeException(
                'Invalid fixture user record.'
            );
        }

        self::validate($record['identity'], $run_id);
    }

    public static function matches(
        array $identity,
        array $row,
        string $run_id
    ): bool {
        self::validate($identity, $run_id);

        return
            ($row['user_login'] ?? null) === $identity['login']
            && ($row['user_email'] ?? null) === $identity['email']
            && ($row['user_nicename'] ?? null) === $identity['nicename'];
    }

    private static function resolve_slot(string $slot): array
    {
        if (!isset(self::SLOTS[$slot])) {
            throw new RuntimeException(
                'Unknown fixture user slot.'
            );
        }

        return self::SLOTS[$slot];
    }

    private static function validate_run_id(string $run_id): void
    {
        if (preg_match('/\A[0-9a-f]{12}\z/', $run_id) !== 1) {
            throw new RuntimeException(
                'Invalid fixture run ID.'
            );
        }
    }

    private static function validate_keys(
        array $data,
        array $expected
    ): void {
        $actual = array_keys($data);

        sort($actual);
        sort($expected);

        if ($actual !== $expected) {
            throw new RuntimeException(
                'Invalid user identity structure.'
            );
        }
    }
}