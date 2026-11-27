<?php

declare(strict_types=1);

trait DilitantManifestIdentityValidation
{
    private static function validate_category_position(
        array $identity,
        string $run_id,
        int $position,
        array $category_ids
    ): void {
        $expected = match ($position) {
            0 => [
                'name' => 'AM Root ' . $run_id,
                'slug' => 'diletant-armenia',
                'parent' => 0,
            ],

            1 => [
                'name' => 'AM Child ' . $run_id,
                'slug' => 'am-child-' . $run_id,
                'parent' => $category_ids[0] ?? null,
            ],

            2 => [
                'name' => 'AM Grandchild ' . $run_id,
                'slug' => 'am-grandchild-' . $run_id,
                'parent' => $category_ids[1] ?? null,
            ],

            3 => [
                'name' => 'AM Outside ' . $run_id,
                'slug' => 'am-outside-' . $run_id,
                'parent' => 0,
            ],

            default => throw new RuntimeException(
                'Unexpected fixture category position.'
            ),
        };

        if (
            $identity['name'] !== $expected['name']
            || $identity['slug'] !== $expected['slug']
            || $identity['parent'] !== $expected['parent']
        ) {
            throw new RuntimeException(
                'Fixture category identity or hierarchy mismatch.'
            );
        }
    }

    private static function validate_post_position(
        array $identity,
        string $run_id,
        int $position,
        array $user_fields
    ): void {
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

        $expected = $expected_posts[$position] ?? null;

        if ($expected === null) {
            throw new RuntimeException(
                'Unexpected fixture post position.'
            );
        }

        [$title, $type, $slot] = $expected;

        if (
            !isset($user_fields[$slot])
            || $identity['title'] !== $title . ' ' . $run_id
            || $identity['type'] !== $type
            || $identity['author_id'] !== $user_fields[$slot]
        ) {
            throw new RuntimeException(
                'Fixture post identity or author policy mismatch.'
            );
        }
    }

    private static function validate_object(
        array $object,
        string $run_id
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
            !in_array(
                $kind,
                ['post', 'category', 'user'],
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
            throw new RuntimeException(
                'Invalid fixture object.'
            );
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
            || !str_ends_with(
                $identity['title'],
                ' ' . $run_id
            )
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
            throw new RuntimeException(
                'Invalid post identity.'
            );
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
            || !str_ends_with(
                $identity['name'],
                ' ' . $run_id
            )
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
            throw new RuntimeException(
                'Invalid category identity.'
            );
        }
    }
}