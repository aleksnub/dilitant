<?php

declare(strict_types=1);

trait DilitantManifestRegistryValidation
{
    private static function validate_registry(array $data): void
    {
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
                throw new RuntimeException(
                    'Invalid object record.'
                );
            }

            self::validate_object(
                $object,
                $data['run_id']
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
                if (
                    count($user_ids) !== 4
                    || $posts !== 0
                ) {
                    throw new RuntimeException(
                        'Invalid category registration order.'
                    );
                }

                self::validate_category_position(
                    $identity,
                    $data['run_id'],
                    $categories,
                    $category_ids
                );

                ++$categories;

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
                if (
                    count($user_ids) !== 4
                    || count($category_ids) !== 4
                ) {
                    throw new RuntimeException(
                        'Post creation prerequisites unavailable.'
                    );
                }

                self::validate_post_position(
                    $identity,
                    $data['run_id'],
                    $posts,
                    $user_fields
                );

                ++$posts;

                $key = 'post:' . $identity['content_marker'];
            }

            if (isset($seen_identities[$key])) {
                throw new RuntimeException(
                    'Duplicate fixture identity.'
                );
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
                throw new RuntimeException(
                    'Duplicate fixture ID.'
                );
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
            || $users > 4
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
            throw new RuntimeException(
                'Invalid manifest registry.'
            );
        }

        if (
            in_array(
                $data['state'],
                ['READY', 'TESTING'],
                true
            )
            && (
                $users !== 4
                || $categories !== 4
                || $posts !== 10
            )
        ) {
            throw new RuntimeException(
                'Incomplete ready fixture registry.'
            );
        }
    }
}