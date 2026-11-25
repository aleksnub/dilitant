<?php

declare(strict_types=1);

function hf_lu_sql_failure(): void
{
    hf_lu_with_users(
        static function (
            DilitantVisibilityUsers $users
        ): void {
            global $wpdb;

            $id = $users->author;
            $original = hf_lu_user_row($id);

            $injected = false;

            $filter = static function (
                string $sql
            ) use (
                &$injected,
                $wpdb,
                $id
            ): string {
                if (
                    !$injected
                    && str_contains(
                        $sql,
                        "FROM {$wpdb->posts}"
                    )
                    && str_contains(
                        $sql,
                        "post_author = {$id}"
                    )
                    && str_contains($sql, 'COUNT(*) AS n')
                ) {
                    $injected = true;

                    return 'SELECT FROM';
                }

                return $sql;
            };

            $previous = $wpdb->suppress_errors(true);

            add_filter('query', $filter);

            try {
                hf_lu_expect_rejection(
                    static function () use ($users): void {
                        $users->cleanup();
                    },
                    'Visibility user SQL query failed.'
                );
            } finally {
                remove_filter('query', $filter);
                $wpdb->suppress_errors($previous);
            }

            hf_lu_require(
                $injected,
                'SQL failure was not injected.'
            );

            hf_lu_require(
                hf_lu_user_row($id) === $original,
                'User was removed after SQL failure.'
            );
        }
    );
}

function hf_lu_foreign_post(): void
{
    hf_lu_with_users(
        static function (
            DilitantVisibilityUsers $users
        ): void {
            $author_id = $users->author;
            $original = hf_lu_user_row($author_id);

            $post_id = wp_insert_post([
                'post_title' =>
                    'HF-LU-' . bin2hex(random_bytes(8)),
                'post_type' => 'page',
                'post_status' => 'draft',
                'post_author' => $author_id,
                'comment_status' => 'closed',
                'ping_status' => 'closed',
            ], true);

            hf_lu_require(
                is_int($post_id) && $post_id > 0,
                'Unable to create dependent test post.'
            );

            try {
                hf_lu_expect_rejection(
                    static function () use ($users): void {
                        $users->cleanup();
                    },
                    'Fixture user owns database records.'
                );

                $post = get_post($post_id);

                hf_lu_require(
                    $post instanceof WP_Post
                    && (int) $post->post_author === $author_id,
                    'Dependent post was modified or removed.'
                );

                hf_lu_require(
                    hf_lu_user_row($author_id) === $original,
                    'Post owner was removed.'
                );
            } finally {
                $post = get_post($post_id);

                hf_lu_require(
                    $post instanceof WP_Post
                    && (int) $post->post_author === $author_id,
                    'Dependent post identity changed.'
                );

                hf_lu_require(
                    wp_delete_post($post_id, true)
                        instanceof WP_Post,
                    'Dependent test post cleanup failed.'
                );
            }
        }
    );
}

function hf_lu_foreign_link(): void
{
    hf_lu_with_users(
        static function (
            DilitantVisibilityUsers $users
        ): void {
            global $wpdb;

            require_once ABSPATH
                . 'wp-admin/includes/bookmark.php';

            $author_id = $users->author;
            $original = hf_lu_user_row($author_id);

            $name = 'HF-LU-' . bin2hex(random_bytes(8));

            $link_id = wp_insert_link([
                'link_name' => $name,
                'link_url' => 'https://example.invalid/',
                'link_owner' => $author_id,
                'link_visible' => 'N',
            ], true);

            hf_lu_require(
                is_int($link_id) && $link_id > 0,
                'Unable to create dependent test link.'
            );

            try {
                $rows = hf_lu_query(
                    $wpdb->prepare(
                        "SELECT link_id, link_owner, link_name
                         FROM {$wpdb->links}
                         WHERE link_id = %d",
                        $link_id
                    )
                );

                hf_lu_require(
                    count($rows) === 1
                    && (int) $rows[0]['link_owner']
                        === $author_id
                    && $rows[0]['link_name'] === $name,
                    'Dependent link identity mismatch.'
                );

                hf_lu_expect_rejection(
                    static function () use ($users): void {
                        $users->cleanup();
                    },
                    'Fixture user owns links.'
                );

                hf_lu_require(
                    hf_lu_user_row($author_id) === $original,
                    'Link owner was removed.'
                );

                hf_lu_require(
                    hf_lu_count(
                        $wpdb->prepare(
                            "SELECT COUNT(*) AS n
                             FROM {$wpdb->links}
                             WHERE link_id = %d
                               AND link_owner = %d
                               AND link_name = %s",
                            $link_id,
                            $author_id,
                            $name
                        )
                    ) === 1,
                    'Dependent link was modified or removed.'
                );
            } finally {
                hf_lu_require(
                    hf_lu_count(
                        $wpdb->prepare(
                            "SELECT COUNT(*) AS n
                             FROM {$wpdb->links}
                             WHERE link_id = %d
                               AND link_name = %s",
                            $link_id,
                            $name
                        )
                    ) === 1,
                    'Dependent link cleanup identity mismatch.'
                );

                hf_lu_require(
                    wp_delete_link($link_id),
                    'Dependent test link cleanup failed.'
                );
            }
        }
    );
}

function hf_lu_foreign_comment(): void
{
    hf_lu_with_users(
        static function (
            DilitantVisibilityUsers $users
        ): void {
            global $wpdb;

            $author_id = $users->author;
            $original = hf_lu_user_row($author_id);

            $post_id = wp_insert_post([
                'post_title' =>
                    'HF-LU-' . bin2hex(random_bytes(8)),
                'post_type' => 'post',
                'post_status' => 'draft',
                'post_author' => $users->administrator,
                'comment_status' => 'open',
            ], true);

            hf_lu_require(
                is_int($post_id) && $post_id > 0,
                'Unable to create comment test post.'
            );

            $comment_id = 0;

            try {
                $marker = 'HF-LU-COMMENT-'
                    . bin2hex(random_bytes(8));

                $result = wp_insert_comment([
                    'comment_post_ID' => $post_id,
                    'comment_content' => $marker,
                    'comment_author' => $original['user_login'],
                    'comment_author_email' =>
                        $original['user_email'],
                    'comment_approved' => 0,
                    'user_id' => $author_id,
                ]);

                hf_lu_require(
                    is_int($result) && $result > 0,
                    'Unable to create dependent comment.'
                );

                $comment_id = $result;

                hf_lu_expect_rejection(
                    static function () use ($users): void {
                        $users->cleanup();
                    },
                    'Fixture user owns comments.'
                );

                hf_lu_require(
                    hf_lu_user_row($author_id) === $original,
                    'Comment owner was removed.'
                );

                hf_lu_require(
                    hf_lu_count(
                        $wpdb->prepare(
                            "SELECT COUNT(*) AS n
                             FROM {$wpdb->comments}
                             WHERE comment_ID = %d
                               AND comment_post_ID = %d
                               AND user_id = %d
                               AND comment_content = %s",
                            $comment_id,
                            $post_id,
                            $author_id,
                            $marker
                        )
                    ) === 1,
                    'Dependent comment was modified or removed.'
                );
            } finally {
                if ($comment_id > 0) {
                    $comment = get_comment($comment_id);

                    hf_lu_require(
                        $comment instanceof WP_Comment
                        && (int) $comment->comment_post_ID
                            === $post_id
                        && (int) $comment->user_id
                            === $author_id
                        && $comment->comment_content === $marker,
                        'Comment cleanup identity mismatch.'
                    );

                    hf_lu_require(
                        wp_delete_comment(
                            $comment_id,
                            true
                        ),
                        'Dependent comment cleanup failed.'
                    );
                }

                hf_lu_require(
                    wp_delete_post($post_id, true)
                        instanceof WP_Post,
                    'Comment test post cleanup failed.'
                );
            }
        }
    );
}