<?php

declare(strict_types=1);

final class DilitantFixtureUserPermissions
{
    private const CAPABILITIES = [
        'administrator' => [
            'read' => true,
            'edit_posts' => true,
            'publish_posts' => true,
            'edit_others_posts' => true,
            'edit_private_posts' => true,
            'read_private_posts' => true,
            'manage_options' => true,
        ],
        'subscriber' => [
            'read' => true,
            'edit_posts' => false,
            'publish_posts' => false,
            'edit_others_posts' => false,
            'edit_private_posts' => false,
            'read_private_posts' => false,
            'manage_options' => false,
            'delete_users' => false,
            'promote_users' => false,
            'create_users' => false,
        ],
        'author' => [
            'read' => true,
            'edit_posts' => true,
            'publish_posts' => true,
            'edit_others_posts' => false,
            'edit_private_posts' => false,
            'read_private_posts' => false,
            'manage_options' => false,
            'delete_users' => false,
            'promote_users' => false,
            'create_users' => false,
        ],
    ];

    public static function verify(
        array $record,
        string $run_id
    ): void {
        global $wpdb;

        $id = DilitantFixtureUserResolution::require_resolved(
            $record,
            $run_id
        );

        $role = $record['identity']['role'];

        $rows = DilitantFixtureUserResolution::query(
            $wpdb->prepare(
                "SELECT meta_value
                 FROM {$wpdb->usermeta}
                 WHERE user_id = %d
                   AND meta_key = %s",
                $id,
                $wpdb->get_blog_prefix() . 'capabilities'
            )
        );

        if (count($rows) !== 1) {
            throw new RuntimeException(
                'Invalid user capabilities metadata.'
            );
        }

        $caps = unserialize(
            $rows[0]['meta_value'],
            ['allowed_classes' => false]
        );

        if ($caps !== [$role => true]) {
            throw new RuntimeException(
                'Unexpected individual user capabilities.'
            );
        }

        clean_user_cache($id);

        $user = get_userdata($id);

        if (
            !$user instanceof WP_User
            || (int) $user->ID !== $id
            || array_values($user->roles) !== [$role]
            || $user->caps !== [$role => true]
        ) {
            throw new RuntimeException(
                'Fixture user role mismatch.'
            );
        }

        foreach (self::CAPABILITIES[$role] as $cap => $expected) {
            if (user_can($user, $cap) !== $expected) {
                throw new RuntimeException(
                    "Fixture capability mismatch: {$cap}."
                );
            }
        }

        DilitantFixtureUserResolution::guard();
    }

    public static function verify_post_access(
        array $record,
        string $run_id,
        int $post_id,
        bool $expected
    ): void {
        self::verify($record, $run_id);

        $id = DilitantFixtureUserResolution::require_resolved(
            $record,
            $run_id
        );

        $post = get_post($post_id);

        if (
            $post_id <= 0
            || !$post instanceof WP_Post
            || (int) $post->ID !== $post_id
        ) {
            throw new RuntimeException(
                'Permission test post unavailable.'
            );
        }

        if (user_can($id, 'edit_post', $post_id) !== $expected) {
            throw new RuntimeException(
                'Object permission mismatch.'
            );
        }

        DilitantFixtureUserResolution::guard();
    }
}