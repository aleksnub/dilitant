<?php

declare(strict_types=1);

function hf_lu_protected_administrator(): void
{
    hf_lu_with_users(
        static function (
            DilitantVisibilityUsers $users
        ): void {
            $property = new ReflectionProperty(
                DilitantVisibilityUsers::class,
                'created_ids'
            );

            $registry = $property->getValue($users);

            $administrator = hf_lu_user_row(
                $users->administrator
            );

            $property->setValue(
                $users,
                [
                    $users->administrator =>
                        $registry[$users->author],
                ]
            );

            try {
                hf_lu_expect_rejection(
                    static function () use ($users): void {
                        $users->cleanup();
                    },
                    'Refusing to delete protected or current user.'
                );

                hf_lu_require(
                    hf_lu_user_row($users->administrator)
                        === $administrator,
                    'Protected administrator was modified.'
                );
            } finally {
                $property->setValue(
                    $users,
                    $registry
                );
            }
        }
    );
}

function hf_lu_changed_identity(): void
{
    hf_lu_with_users(
        static function (
            DilitantVisibilityUsers $users
        ): void {
            $id = $users->author;
            $original = hf_lu_user_row($id);

            $changed_email =
                'hf-lu-' . bin2hex(random_bytes(8))
                . '@example.invalid';

            $result = wp_update_user([
                'ID' => $id,
                'user_email' => $changed_email,
            ]);

            hf_lu_require(
                $result === $id,
                'Unable to inject identity change.'
            );

            try {
                hf_lu_expect_rejection(
                    static function () use ($users): void {
                        $users->cleanup();
                    },
                    'Visibility user identity is ambiguous or changed.'
                );

                hf_lu_require(
                    hf_lu_user_row($id)['user_email']
                        === $changed_email,
                    'Mismatched user was modified or removed.'
                );
            } finally {
                $result = wp_update_user([
                    'ID' => $id,
                    'user_email' => $original['user_email'],
                ]);

                hf_lu_require(
                    $result === $id,
                    'Unable to restore user identity.'
                );
            }
        }
    );
}

function hf_lu_current_user(): void
{
    hf_lu_with_users(
        static function (
            DilitantVisibilityUsers $users
        ): void {
            $id = $users->author;
            $original = hf_lu_user_row($id);

            dilitant_visibility_with_user(
                $id,
                static function () use (
                    $users,
                    $id,
                    $original
                ): void {
                    hf_lu_expect_rejection(
                        static function () use ($users): void {
                            $users->cleanup();
                        },
                        'Refusing to delete protected or current user.'
                    );

                    hf_lu_require(
                        hf_lu_user_row($id) === $original,
                        'Current user identity changed.'
                    );
                }
            );
        }
    );
}

function hf_lu_idempotent_cleanup(): void
{
    hf_lu_with_users(
        static function (
            DilitantVisibilityUsers $users
        ): void {
            $ids = [
                $users->subscriber,
                $users->author,
            ];

            $users->cleanup();
            $users->cleanup();

            hf_lu_require(
                $users->subscriber === 0
                && $users->author === 0,
                'Legacy User fields were not reset.'
            );

            foreach ($ids as $id) {
                hf_lu_assert_user_absent($id);
            }
        }
    );
}