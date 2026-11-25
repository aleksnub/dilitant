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

require_once __DIR__ . '/lock.php';
require_once __DIR__ . '/users/identity.php';
require_once __DIR__ . '/users/resolution.php';
require_once __DIR__ . '/users/permissions.php';
require_once __DIR__ . '/users/creation.php';
require_once __DIR__ . '/users/dependencies.php';
require_once __DIR__ . '/users/cleanup.php';

trait DilitantArticleFixtureUsers
{
    private array $user_ids = [];
    private bool $users_created = false;

    public int $administrator = 0;
    public int $subscriber = 0;
    public int $author_a = 0;
    public int $author_b = 0;

    public function create_users(): void
    {
        $this->verify_environment();

        DilitantFixtureUserResolution::guard();

        if (!$this->journal instanceof DilitantFixtureJournal) {
            throw new RuntimeException(
                'User creation requires a fixture journal.'
            );
        }

        if (
            $this->users_created
            || $this->user_ids !== []
            || $this->post_ids !== []
            || $this->category_ids !== []
            || $this->owned_objects !== []
            || $this->base_created
            || $this->query_created
        ) {
            throw new RuntimeException(
                'Invalid user creation lifecycle.'
            );
        }

        foreach (DilitantFixtureUserIdentity::slots() as $slot) {
            if ($this->{$slot} !== 0) {
                throw new RuntimeException(
                    'Fixture user slot already initialized.'
                );
            }
        }

        foreach (DilitantFixtureUserIdentity::slots() as $slot) {
            $record = DilitantFixtureUserCreation::create(
                $this->run_id,
                $slot,
                $this->journal
            );

            $this->register_confirmed_user($record);
        }

        $this->verify_users();

        $this->users_created = true;
    }

    private function register_confirmed_user(
        array $record
    ): void {
        DilitantFixtureUserIdentity::validate_record(
            $record,
            $this->run_id
        );

        $id = $record['id'];
        $slot = $record['identity']['slot'];
        $key = 'user:' . $id;

        if (
            $id <= 0
            || isset($this->owned_objects[$key])
            || in_array($id, $this->user_ids, true)
            || $this->{$slot} !== 0
        ) {
            throw new RuntimeException(
                'Duplicate fixture user registration.'
            );
        }

        DilitantFixtureUserResolution::require_resolved(
            $record,
            $this->run_id
        );

        $this->owned_objects[$key] = $record;
        $this->user_ids[] = $id;
        $this->{$slot} = $id;
    }

    private function restore_user_record(
        array $record
    ): void {
        DilitantFixtureUserIdentity::validate_record(
            $record,
            $this->run_id
        );

        $id = $record['id'];
        $slot = $record['identity']['slot'];
        $key = 'user:' . $id;

        if (
            $id <= 0
            || isset($this->owned_objects[$key])
            || in_array($id, $this->user_ids, true)
            || $this->{$slot} !== 0
        ) {
            throw new RuntimeException(
                'Invalid restored user record.'
            );
        }

        $this->owned_objects[$key] = $record;
        $this->user_ids[] = $id;
        $this->{$slot} = $id;
    }

    public function verify_users(): void
    {
        DilitantFixtureUserResolution::guard();

        if (count($this->user_ids) !== 4) {
            throw new RuntimeException(
                'Incomplete fixture user registry.'
            );
        }

        $expected_ids = [];

        foreach (DilitantFixtureUserIdentity::slots() as $slot) {
            $id = $this->{$slot};

            $record = $this->owned_record('user', $id);

            if (
                $id <= 0
                || $record['identity']['slot'] !== $slot
            ) {
                throw new RuntimeException(
                    'Fixture user slot mismatch.'
                );
            }

            DilitantFixtureUserPermissions::verify(
                $record,
                $this->run_id
            );

            $expected_ids[] = $id;
        }

        if ($expected_ids !== $this->user_ids) {
            throw new RuntimeException(
                'Fixture user registry order mismatch.'
            );
        }
    }

    public function all_user_ids(): array
    {
        return $this->user_ids;
    }

    private function cleanup_users(): void
    {
        DilitantFixtureUserResolution::guard();

        foreach (array_reverse($this->user_ids) as $id) {
            $record = $this->owned_record('user', $id);

            DilitantFixtureUserCleanup::remove(
                $record,
                $this->run_id
            );
        }

        foreach ($this->user_ids as $id) {
            DilitantFixtureUserDependencies::require_absent(
                $this->owned_record('user', $id),
                $this->run_id
            );
        }

        DilitantFixtureUserResolution::guard();
    }

    private function reset_users(): void
    {
        foreach ($this->user_ids as $id) {
            unset($this->owned_objects['user:' . $id]);
        }

        foreach (DilitantFixtureUserIdentity::slots() as $slot) {
            $this->{$slot} = 0;
        }

        $this->user_ids = [];
        $this->users_created = false;
    }
}