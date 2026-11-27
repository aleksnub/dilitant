<?php

declare(strict_types=1);

function hfur_arm_partial_creation(
    string $run_id
): Closure {
    hfur_guard();

    hfur_run_id($run_id);

    $hook = static function (
        array $meta,
        WP_User $user,
        bool $update
    ) use ($run_id): array {
        if ($update) {
            return $meta;
        }

        hfur_guard();

        $record = hfur_pending_record($run_id);

        $id = (int) $user->ID;

        if (
            $id <= 0
            || $user->user_login
                !== $record['identity']['login']
        ) {
            return $meta;
        }

        hfur_require(
            hfur_require_owned_user(
                $record,
                $run_id
            ) === $id,
            'Partial user identity mismatch.'
        );

        $dependencies = hfur_user_dependencies($id);

        foreach ($dependencies as $name => $count) {
            hfur_require(
                $count === 0,
                "Unexpected partial user dependency: {$name}."
            );
        }

        hfur_checkpoint(
            'user-partial',
            [
                'id' => $id,
                'identity' => $record['identity'],
                'dependencies' => $dependencies,
            ]
        );
    };

    add_filter(
        'insert_user_meta',
        $hook,
        PHP_INT_MAX,
        3
    );

    return $hook;
}