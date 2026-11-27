<?php

declare(strict_types=1);

require_once __DIR__ . '/validation-registry.php';
require_once __DIR__ . '/validation-identity.php';

trait DilitantManifestValidation
{
    use DilitantManifestRegistryValidation;
    use DilitantManifestIdentityValidation;

    private static function validate(array $data): void
    {
        self::require_keys($data, [
            'schema_version',
            'run_id',
            'database_name',
            'table_prefix',
            'container_origin',
            'created_at',
            'state',
            'objects',
            'snapshot',
        ]);

        $run_id = $data['run_id'];
        $state = $data['state'];

        if (
            $data['schema_version'] !== self::SCHEMA_VERSION
            || $data['database_name'] !== DILITANT_TEST_DB_NAME
            || $data['table_prefix'] !== DILITANT_TEST_TABLE_PREFIX
            || !is_string($run_id)
            || preg_match('/\A[0-9a-f]{12}\z/', $run_id) !== 1
            || !is_string($state)
            || !array_key_exists($state, self::TRANSITIONS)
            || !is_string($data['container_origin'])
            || $data['container_origin'] === ''
            || !is_string($data['created_at'])
            || !is_array($data['objects'])
            || !array_is_list($data['objects'])
            || count($data['objects']) > 18
        ) {
            throw new RuntimeException(
                'Invalid fixture manifest.'
            );
        }

        $created = DateTimeImmutable::createFromFormat(
            DATE_ATOM,
            $data['created_at']
        );

        if (
            $created === false
            || $created->format(DATE_ATOM) !== $data['created_at']
        ) {
            throw new RuntimeException(
                'Invalid manifest timestamp.'
            );
        }

        self::validate_registry($data);

        if ($data['snapshot'] !== null) {
            self::validate_snapshot(
                $data['snapshot'],
                $run_id,
                $data['objects']
            );
        }

        if (
            in_array($state, ['CREATING', 'FAILED'], true)
            && $data['snapshot'] !== null
        ) {
            throw new RuntimeException(
                'Unexpected fixture snapshot.'
            );
        }

        if (
            in_array($state, ['READY', 'TESTING'], true)
            && $data['snapshot'] === null
        ) {
            throw new RuntimeException(
                'Fixture snapshot required.'
            );
        }
    }

    private static function validate_snapshot(
        mixed $snapshot,
        string $run_id,
        array $objects
    ): void {
        if (!is_array($snapshot)) {
            throw new RuntimeException(
                'Invalid fixture snapshot.'
            );
        }

        self::require_keys($snapshot, [
            'run_id',
            'base_created',
            'query_created',
            'users_created',
            'user_ids',
            'category_ids',
            'post_ids',
            'fields',
        ]);

        $users = [];
        $categories = [];
        $posts = [];

        $user_fields = [];

        foreach ($objects as $object) {
            $id = $object['id'];

            if ($id <= 0) {
                throw new RuntimeException(
                    'Unresolved snapshot object.'
                );
            }

            switch ($object['kind']) {
                case 'user':
                    $users[] = $id;

                    $user_fields[
                        $object['identity']['slot']
                    ] = $id;
                    break;

                case 'category':
                    $categories[] = $id;
                    break;

                case 'post':
                    $posts[] = $id;
                    break;
            }
        }

        if (
            $snapshot['run_id'] !== $run_id
            || $snapshot['base_created'] !== true
            || $snapshot['query_created'] !== true
            || $snapshot['users_created'] !== true
            || $snapshot['user_ids'] !== $users
            || $snapshot['category_ids'] !== $categories
            || $snapshot['post_ids'] !== $posts
            || !is_array($snapshot['fields'])
            || count($users) !== 4
            || count($categories) !== 4
            || count($posts) !== 10
            || array_keys($user_fields)
                !== DilitantFixtureUserIdentity::slots()
        ) {
            throw new RuntimeException(
                'Fixture snapshot mismatch.'
            );
        }

        $fields = [
            'root_category',
            'child_category',
            'grandchild_category',
            'outside_category',
            'root_post',
            'child_post',
            'grandchild_post',
            'outside_post',
            'draft_post',
            'page_post',
            'archive_post',
            'private_post',
            'future_post',
            'password_post',
        ];

        $expected = array_combine(
            $fields,
            array_merge($categories, $posts)
        );

        $expected = array_merge(
            $user_fields,
            $expected
        );

        self::require_keys(
            $snapshot['fields'],
            array_keys($expected)
        );

        foreach ($expected as $field => $id) {
            if ($snapshot['fields'][$field] !== $id) {
                throw new RuntimeException(
                    "Snapshot field mismatch: {$field}"
                );
            }
        }

        $root = null;

        foreach ($objects as $object) {
            if (
                $object['kind'] === 'category'
                && $object['identity']['slug'] === 'diletant-armenia'
            ) {
                $root = $object['id'];
                break;
            }
        }

        if ($root !== $snapshot['fields']['root_category']) {
            throw new RuntimeException(
                'Snapshot root mismatch.'
            );
        }
    }

    private static function require_keys(
        array $data,
        array $expected
    ): void {
        $actual = array_keys($data);

        sort($actual, SORT_STRING);
        sort($expected, SORT_STRING);

        if ($actual !== $expected) {
            throw new RuntimeException(
                'Unexpected manifest structure.'
            );
        }
    }
}