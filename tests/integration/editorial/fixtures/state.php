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

interface DilitantFixtureJournal
{
    public function intent(string $kind, array $identity): void;

    public function confirm(int $id, int $taxonomy_id = 0): void;
}

trait DilitantArticleFixtureState
{
    private ?DilitantFixtureJournal $journal = null;
    private array $owned_objects = [];

    public function attach_journal(DilitantFixtureJournal $journal): void
    {
        $this->verify_environment();
        dilitant_http_require_bootstrap();

        if (
            $this->base_created
            || $this->query_created
            || $this->users_created
            || $this->post_ids !== []
            || $this->category_ids !== []
            || $this->user_ids !== []
            || $this->owned_objects !== []
        ) {
            throw new RuntimeException('Fixtures already initialized.');
        }

        if ($this->journal !== null) {
            throw new RuntimeException('Journal already attached.');
        }

        $this->journal = $journal;
    }

    private function register_intent(
        string $kind,
        array $identity
    ): void {
        $this->journal?->intent($kind, $identity);
    }

    private function register_object(
        string $kind,
        int $id,
        array $identity,
        int $taxonomy_id = 0
    ): void {
        self::validate_object_identifiers($kind, $id, $taxonomy_id);

        $key = $kind . ':' . $id;

        if (isset($this->owned_objects[$key])) {
            throw new RuntimeException('Duplicate fixture object.');
        }

        if ($kind === 'post') {
            $this->post_ids[] = $id;
        } elseif ($kind === 'category') {
            $this->category_ids[] = $id;
        } else {
            throw new RuntimeException(
                'Users require dedicated registration.'
            );
        }

        $this->owned_objects[$key] = [
            'kind' => $kind,
            'id' => $id,
            'taxonomy_id' => $taxonomy_id,
            'identity' => $identity,
        ];

        $this->journal?->confirm($id, $taxonomy_id);
    }

    private static function validate_object_identifiers(
        string $kind,
        int $id,
        int $taxonomy_id
    ): void {
        if (
            $id <= 0
            || !in_array($kind, ['post', 'category', 'user'], true)
            || ($kind !== 'category' && $taxonomy_id !== 0)
            || ($kind === 'category' && $taxonomy_id <= 0)
        ) {
            throw new RuntimeException('Invalid fixture identifiers.');
        }
    }

    public function export_state(): array
    {
        $this->verify_environment();

        $fields = [];

        foreach (self::fixture_fields() as $field) {
            $fields[$field] = $this->{$field};
        }

        if ($this->user_ids !== [] || $this->users_created) {
            if (
                !$this->users_created
                || count($this->user_ids) !== 4
            ) {
                throw new RuntimeException(
                    'Incomplete fixture users.'
                );
            }

            $user_fields = [];

            foreach (DilitantFixtureUserIdentity::slots() as $slot) {
                $user_fields[$slot] = $this->{$slot};
            }

            return [
                'run_id' => $this->run_id,
                'base_created' => $this->base_created,
                'query_created' => $this->query_created,
                'users_created' => $this->users_created,
                'user_ids' => $this->user_ids,
                'category_ids' => $this->category_ids,
                'post_ids' => $this->post_ids,
                'fields' => array_merge($user_fields, $fields),
            ];
        }

        return [
            'run_id' => $this->run_id,
            'author_id' => $this->author_id,
            'base_created' => $this->base_created,
            'query_created' => $this->query_created,
            'category_ids' => $this->category_ids,
            'post_ids' => $this->post_ids,
            'fields' => $fields,
        ];
    }

    private static function category_fields(): array
    {
        return [
            'root_category',
            'child_category',
            'grandchild_category',
            'outside_category',
        ];
    }

    private static function post_fields(): array
    {
        return [
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
    }

    private static function fixture_fields(): array
    {
        return array_merge(
            self::category_fields(),
            self::post_fields()
        );
    }

    public static function restore_for_cleanup(
        string $run_id,
        array $objects
    ): self {
        dilitant_http_require_bootstrap();

        if (preg_match('/\A[0-9a-f]{12}\z/', $run_id) !== 1) {
            throw new RuntimeException('Invalid fixture run ID.');
        }

        if (!array_is_list($objects) || count($objects) > 18) {
            throw new RuntimeException('Invalid fixture registry.');
        }

        $instance = new self();
        $instance->run_id = $run_id;

        foreach ($objects as $object) {
            $instance->restore_object($object);
        }

        if (
            count($instance->user_ids) > 4
            || count($instance->category_ids) > 4
            || count($instance->post_ids) > 10
        ) {
            throw new RuntimeException(
                'Invalid restored fixture counts.'
            );
        }

        return $instance;
    }

    private function restore_object(array $object): void
    {
        $kind = $object['kind'] ?? null;
        $id = $object['id'] ?? null;
        $identity = $object['identity'] ?? null;
        $taxonomy_id = $object['taxonomy_id'] ?? null;

        if (
            !is_string($kind)
            || !is_int($id)
            || !is_array($identity)
            || !is_int($taxonomy_id)
        ) {
            throw new RuntimeException('Invalid fixture record.');
        }

        self::validate_object_identifiers($kind, $id, $taxonomy_id);

        if ($kind === 'user') {
            $this->restore_user_record($object);
            return;
        }

        $key = $kind . ':' . $id;

        if (isset($this->owned_objects[$key])) {
            throw new RuntimeException('Duplicate fixture record.');
        }

        if (
            $kind === 'category'
            && ($identity['slug'] ?? null) === self::ROOT_SLUG
        ) {
            if ($this->root_category !== 0) {
                throw new RuntimeException('Duplicate root category.');
            }

            $this->root_category = $id;
        }

        $this->register_object(
            $kind,
            $id,
            $identity,
            $taxonomy_id
        );
    }

    public static function restore_ready(array $manifest): self
    {
        dilitant_http_require_bootstrap();

        $version = $manifest['schema_version'] ?? null;

        if (
            !in_array($version, [1, 2], true)
            || ($manifest['database_name'] ?? null)
                !== DILITANT_TEST_DB_NAME
            || ($manifest['table_prefix'] ?? null)
                !== DILITANT_TEST_TABLE_PREFIX
        ) {
            throw new RuntimeException('Invalid manifest identity.');
        }

        if (
            !in_array(
                $manifest['state'] ?? null,
                ['READY', 'TESTING'],
                true
            )
        ) {
            throw new RuntimeException('Fixture run is not ready.');
        }

        if (
            !is_string($manifest['run_id'] ?? null)
            || !is_array($manifest['objects'] ?? null)
            || !is_array($manifest['snapshot'] ?? null)
        ) {
            throw new RuntimeException('Invalid manifest registry.');
        }

        $instance = self::restore_for_cleanup(
            $manifest['run_id'],
            $manifest['objects']
        );

        $instance->restore_snapshot(
            $manifest['snapshot'],
            $version
        );

        $instance->verify_restored_objects();

        if ($version === 2) {
            $instance->verify_users();
            $instance->users_created = true;
        }

        $instance->base_created = true;
        $instance->query_created = true;

        $instance->verify_query_fixtures();

        return $instance;
    }

    private function restore_snapshot(
        array $snapshot,
        int $version
    ): void {
        if (
            ($snapshot['run_id'] ?? null) !== $this->run_id
            || ($snapshot['base_created'] ?? null) !== true
            || ($snapshot['query_created'] ?? null) !== true
            || ($snapshot['category_ids'] ?? null)
                !== $this->category_ids
            || ($snapshot['post_ids'] ?? null) !== $this->post_ids
            || !is_array($snapshot['fields'] ?? null)
            || count($this->category_ids) !== 4
            || count($this->post_ids) !== 10
        ) {
            throw new RuntimeException('Fixture snapshot mismatch.');
        }

        if ($version === 1) {
            if (
                ($snapshot['author_id'] ?? null) !== $this->author_id
                || $this->user_ids !== []
            ) {
                throw new RuntimeException(
                    'Legacy fixture snapshot mismatch.'
                );
            }
        } else {
            if (
                ($snapshot['users_created'] ?? null) !== true
                || ($snapshot['user_ids'] ?? null)
                    !== $this->user_ids
                || count($this->user_ids) !== 4
            ) {
                throw new RuntimeException(
                    'User snapshot mismatch.'
                );
            }

            foreach (DilitantFixtureUserIdentity::slots() as $slot) {
                if (
                    $this->{$slot} <= 0
                    || ($snapshot['fields'][$slot] ?? null)
                        !== $this->{$slot}
                ) {
                    throw new RuntimeException(
                        "User snapshot slot mismatch: {$slot}"
                    );
                }
            }
        }

        if ($this->root_category !== $this->category_ids[0]) {
            throw new RuntimeException('Root category identity mismatch.');
        }

        foreach (self::category_fields() as $index => $field) {
            $this->{$field} = $this->category_ids[$index];
        }

        foreach (self::post_fields() as $index => $field) {
            $this->{$field} = $this->post_ids[$index];
        }

        foreach (self::fixture_fields() as $field) {
            if (($snapshot['fields'][$field] ?? null) !== $this->{$field}) {
                throw new RuntimeException(
                    "Fixture field mismatch: {$field}"
                );
            }
        }
    }

    private function verify_restored_objects(): void
    {
        foreach ($this->category_ids as $id) {
            $term = get_term($id, 'category');

            if (!$term instanceof WP_Term) {
                throw new RuntimeException(
                    "Fixture category {$id} missing."
                );
            }

            $this->verify_category_ownership($term);

            $record = $this->owned_record('category', $id);
            $parent = $record['identity']['parent'] ?? null;

            if (!is_int($parent) || (int) $term->parent !== $parent) {
                throw new RuntimeException(
                    "Fixture category {$id} parent mismatch."
                );
            }
        }

        foreach ($this->post_ids as $id) {
            $post = get_post($id);

            if (!$post instanceof WP_Post) {
                throw new RuntimeException(
                    "Fixture post {$id} missing."
                );
            }

            $this->verify_post_ownership($post);

            $record = $this->owned_record('post', $id);
            $author_id = $record['identity']['author_id'];

            if (
                $this->user_ids !== []
                && !in_array($author_id, $this->user_ids, true)
            ) {
                throw new RuntimeException(
                    "Unregistered fixture post author: {$id}"
                );
            }
        }
    }
}