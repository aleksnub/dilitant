<?php

declare(strict_types=1);

trait DilitantManifestValidation
{
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

        $version = $data['schema_version'];
        $run_id = $data['run_id'];
        $state = $data['state'];

        if (
            !in_array($version, [1, 2], true)
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
            || count($data['objects']) > ($version === 1 ? 14 : 18)
        ) {
            throw new RuntimeException('Invalid fixture manifest.');
        }

        $created = DateTimeImmutable::createFromFormat(
            DATE_ATOM,
            $data['created_at']
        );

        if (
            $created === false
            || $created->format(DATE_ATOM) !== $data['created_at']
        ) {
            throw new RuntimeException('Invalid manifest timestamp.');
        }

        self::validate_registry($data);

        if ($data['snapshot'] !== null) {
            self::validate_snapshot(
                $data['snapshot'],
                $run_id,
                $data['objects'],
                $version
            );
        }

        if (
            in_array($state, ['CREATING', 'FAILED'], true)
            && $data['snapshot'] !== null
        ) {
            throw new RuntimeException('Unexpected fixture snapshot.');
        }

        if (
            in_array($state, ['READY', 'TESTING'], true)
            && $data['snapshot'] === null
        ) {
            throw new RuntimeException('Fixture snapshot required.');
        }
    }

    private static function validate_registry(array $data): void
    {
        $version = $data['schema_version'];

        $seen_ids = [];
        $seen_identities = [];
        $taxonomy_ids = [];
        $category_ids = [];

        $user_ids = [];
        $user_fields = [];
        $user_values = [];

        $users = 0;
        $categories = 0;
        $posts = 0;
        $pending = 0;

        $slots = DilitantFixtureUserIdentity::slots();

        foreach ($data['objects'] as $index => $object) {
            if (!is_array($object)) {
                throw new RuntimeException('Invalid object record.');
            }

            self::validate_object(
                $object,
                $data['run_id'],
                $version
            );

            $kind = $object['kind'];
            $id = $object['id'];
            $taxonomy_id = $object['taxonomy_id'];
            $identity = $object['identity'];

            if ($kind === 'user') {
                $slot = $identity['slot'];

                if (
                    $categories !== 0
                    || $posts !== 0
                    || ($slots[$users] ?? null) !== $slot
                ) {
                    throw new RuntimeException(
                        'Invalid user registration order.'
                    );
                }

                ++$users;

                foreach (['slot', 'login', 'email', 'nicename'] as $field) {
                    $value = $field . ':' . $identity[$field];

                    if (isset($user_values[$value])) {
                        throw new RuntimeException(
                            'Duplicate fixture user identity.'
                        );
                    }

                    $user_values[$value] = true;
                }

                $key = 'user:' . $slot;

                if ($id !== 0) {
                    $user_ids[] = $id;
                    $user_fields[$slot] = $id;
                }
            } elseif ($kind === 'category') {
                ++$categories;

                if (
                    $version === 2
                    && (count($user_ids) !== 4 || $posts !== 0)
                ) {
                    throw new RuntimeException(
                        'Invalid category registration order.'
                    );
                }

                $key = 'category:' . $identity['slug'];

                if (
                    $identity['parent'] !== 0
                    && !in_array(
                        $identity['parent'],
                        $category_ids,
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'Unregistered category parent.'
                    );
                }
            } else {
                ++$posts;

                $key = 'post:' . $identity['content_marker'];

                if ($version === 2) {
                    if (
                        count($user_ids) !== 4
                        || count($category_ids) !== 4
                    ) {
                        throw new RuntimeException(
                            'Post creation prerequisites unavailable.'
                        );
                    }

                    $expected_posts = [
                        [
                            'AM Root Article',
                            'post',
                            'administrator',
                        ],
                        [
                            'AM Child Article',
                            'post',
                            'administrator',
                        ],
                        [
                            'AM Grandchild Article',
                            'post',
                            'administrator',
                        ],
                        [
                            'AM Outside Article',
                            'post',
                            'administrator',
                        ],
                        [
                            'AM Draft Article',
                            'post',
                            'author_a',
                        ],
                        [
                            'AM Page Fixture',
                            'page',
                            'administrator',
                        ],
                        [
                            'AM Archive Photo Fixture',
                            'archive_photo',
                            'administrator',
                        ],
                        [
                            'AM Private Article',
                            'post',
                            'author_b',
                        ],
                        [
                            'AM Future Article',
                            'post',
                            'administrator',
                        ],
                        [
                            'AM Password Protected Article',
                            'post',
                            'administrator',
                        ],
                    ];

                    $expected = $expected_posts[$posts - 1] ?? null;

                    if ($expected === null) {
                        throw new RuntimeException(
                            'Unexpected fixture post position.'
                        );
                    }

                    [$title, $type, $slot] = $expected;

                    if (
                        $identity['title']
                            !== $title . ' ' . $data['run_id']
                        || $identity['type'] !== $type
                        || $identity['author_id']
                            !== $user_fields[$slot]
                    ) {
                        throw new RuntimeException(
                            'Fixture post identity or author policy mismatch.'
                        );
                    }
                }
            }

            if (isset($seen_identities[$key])) {
                throw new RuntimeException('Duplicate fixture identity.');
            }

            $seen_identities[$key] = true;

            if ($id === 0) {
                ++$pending;

                if ($index !== array_key_last($data['objects'])) {
                    throw new RuntimeException(
                        'Invalid pending intent position.'
                    );
                }

                continue;
            }

            $key = $kind . ':' . $id;

            if (isset($seen_ids[$key])) {
                throw new RuntimeException('Duplicate fixture ID.');
            }

            $seen_ids[$key] = true;

            if ($kind === 'category') {
                if (isset($taxonomy_ids[$taxonomy_id])) {
                    throw new RuntimeException(
                        'Duplicate taxonomy ID.'
                    );
                }

                $taxonomy_ids[$taxonomy_id] = true;
                $category_ids[] = $id;
            }
        }

        if (
            $pending > 1
            || $users > ($version === 1 ? 0 : 4)
            || $categories > 4
            || $posts > 10
            || (
                $pending !== 0
                && in_array(
                    $data['state'],
                    ['READY', 'TESTING', 'CLEANED'],
                    true
                )
            )
        ) {
            throw new RuntimeException('Invalid manifest registry.');
        }
    }

    private static function validate_object(
        array $object,
        string $run_id,
        int $version
    ): void {
        self::require_keys($object, [
            'kind',
            'id',
            'taxonomy_id',
            'identity',
        ]);

        $kind = $object['kind'];
        $id = $object['id'];
        $taxonomy_id = $object['taxonomy_id'];

        if (
            !in_array($version, [1, 2], true)
            || !in_array(
                $kind,
                $version === 1
                    ? ['post', 'category']
                    : ['post', 'category', 'user'],
                true
            )
            || !is_int($id)
            || $id < 0
            || !is_int($taxonomy_id)
            || $taxonomy_id < 0
            || !is_array($object['identity'])
            || ($kind !== 'category' && $taxonomy_id !== 0)
            || (
                $kind === 'category'
                && (($id === 0) !== ($taxonomy_id === 0))
            )
        ) {
            throw new RuntimeException('Invalid fixture object.');
        }

        switch ($kind) {
            case 'post':
                self::validate_post_identity(
                    $object['identity'],
                    $run_id
                );
                break;

            case 'category':
                self::validate_category_identity(
                    $object['identity'],
                    $run_id
                );
                break;

            case 'user':
                DilitantFixtureUserIdentity::validate_record(
                    $object,
                    $run_id
                );
                break;
        }
    }

    private static function validate_post_identity(
        array $identity,
        string $run_id
    ): void {
        self::require_keys($identity, [
            'title',
            'type',
            'author_id',
            'content_marker',
        ]);

        if (
            !is_string($identity['title'])
            || !str_ends_with($identity['title'], ' ' . $run_id)
            || !in_array(
                $identity['type'],
                ['post', 'page', 'archive_photo'],
                true
            )
            || !is_int($identity['author_id'])
            || $identity['author_id'] <= 0
            || !is_string($identity['content_marker'])
            || preg_match(
                '/\AAM-CONTENT-' . $run_id . '-[0-9a-f]{16}\z/',
                $identity['content_marker']
            ) !== 1
        ) {
            throw new RuntimeException('Invalid post identity.');
        }
    }

    private static function validate_category_identity(
        array $identity,
        string $run_id
    ): void {
        self::require_keys($identity, [
            'name',
            'slug',
            'parent',
        ]);

        if (
            !is_string($identity['name'])
            || !str_ends_with($identity['name'], ' ' . $run_id)
            || !is_string($identity['slug'])
            || (
                $identity['slug'] !== 'diletant-armenia'
                && !str_ends_with(
                    $identity['slug'],
                    '-' . $run_id
                )
            )
            || !is_int($identity['parent'])
            || $identity['parent'] < 0
            || (
                $identity['slug'] === 'diletant-armenia'
                && $identity['parent'] !== 0
            )
        ) {
            throw new RuntimeException('Invalid category identity.');
        }
    }

    private static function validate_snapshot(
        mixed $snapshot,
        string $run_id,
        array $objects,
        int $version
    ): void {
        if (!is_array($snapshot)) {
            throw new RuntimeException('Invalid fixture snapshot.');
        }

        $keys = [
            'run_id',
            'base_created',
            'query_created',
            'category_ids',
            'post_ids',
            'fields',
        ];

        if ($version === 1) {
            $keys[] = 'author_id';
        } else {
            $keys[] = 'users_created';
            $keys[] = 'user_ids';
        }

        self::require_keys($snapshot, $keys);

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
                    $user_fields[$object['identity']['slot']] = $id;
                    break;

                case 'category':
                    $categories[] = $id;
                    break;

                case 'post':
                    $posts[] = $id;

                    if (
                        $version === 1
                        && $object['identity']['author_id']
                            !== $snapshot['author_id']
                    ) {
                        throw new RuntimeException(
                            'Snapshot author mismatch.'
                        );
                    }

                    break;
            }
        }

        if (
            $snapshot['run_id'] !== $run_id
            || $snapshot['base_created'] !== true
            || $snapshot['query_created'] !== true
            || $snapshot['category_ids'] !== $categories
            || $snapshot['post_ids'] !== $posts
            || !is_array($snapshot['fields'])
            || count($categories) !== 4
            || count($posts) !== 10
        ) {
            throw new RuntimeException('Fixture snapshot mismatch.');
        }

        if ($version === 1) {
            if (
                $users !== []
                || !is_int($snapshot['author_id'])
                || $snapshot['author_id'] <= 0
            ) {
                throw new RuntimeException(
                    'Invalid legacy snapshot.'
                );
            }
        } else {
            if (
                $snapshot['users_created'] !== true
                || $snapshot['user_ids'] !== $users
                || count($users) !== 4
                || array_keys($user_fields)
                    !== DilitantFixtureUserIdentity::slots()
            ) {
                throw new RuntimeException(
                    'Invalid user snapshot.'
                );
            }
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

        if ($version === 2) {
            $expected = array_merge($user_fields, $expected);
        }

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
            throw new RuntimeException('Snapshot root mismatch.');
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