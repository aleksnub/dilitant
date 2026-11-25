<?php

declare(strict_types=1);

trait DilitantVisibilityUserCreation
{
    private function create_user(string $role): int
    {
        dilitant_visibility_verify_environment();

        dilitant_test_assert(
            in_array(
                $role,
                ['subscriber', 'author'],
                true
            ),
            'Unexpected fixture user role.'
        );

        $login = 'am-vis-' . $role . '-' . $this->run_id;

        dilitant_test_assert_same(
            [],
            $this->identity_candidates(0, $login),
            'Fixture user identity already exists.'
        );

        $result = wp_insert_user([
            'user_login' => $login,
            'user_pass' => wp_generate_password(32, true, true),
            'user_email' => $login . '@example.invalid',
            'display_name' => $login,
            'role' => $role,
        ]);

        if (
            is_wp_error($result)
            || !is_int($result)
            || $result <= 0
        ) {
            throw new RuntimeException(
                'Unable to create fixture user.'
            );
        }

        $id = $result;

        $this->created_ids[$id] = $login;

        dilitant_test_assert(
            $this->resolve_owned_user($id, $login),
            'Created fixture user identity unavailable.'
        );

        dilitant_test_assert(
            $id !== $this->administrator
            && $id !== $this->protected_admin_id(),
            'Fixture user conflicts with protected administrator.'
        );

        clean_user_cache($id);

        $user = get_user_by('id', $id);

        dilitant_test_assert(
            $user instanceof WP_User,
            'Created fixture user is missing.'
        );

        dilitant_test_assert_same(
            [$role],
            array_values($user->roles),
            'Fixture user has unexpected roles.'
        );

        return $id;
    }
}