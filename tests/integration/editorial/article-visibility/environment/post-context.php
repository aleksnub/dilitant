<?php

declare(strict_types=1);

function dilitant_visibility_with_trashed_post(
    DilitantArticleFixtures $fixtures,
    int $post_id,
    callable $callback
) {
    dilitant_visibility_verify_environment();

    dilitant_test_assert(
        in_array(
            $post_id,
            $fixtures->all_post_ids(),
            true
        ),
        'Requested post does not belong to fixtures.'
    );

    $post = get_post($post_id);

    dilitant_test_assert(
        $post instanceof WP_Post,
        'Trash fixture is missing.'
    );

    dilitant_test_assert_same(
        'post',
        $post->post_type,
        'Unexpected trash fixture type.'
    );

    $previous_status = $post->post_status;
    $previous_date = $post->post_date;
    $previous_date_gmt = $post->post_date_gmt;

    try {
        $result = wp_update_post([
            'ID' => $post_id,
            'post_status' => 'trash',
        ], true);

        if (is_wp_error($result)) {
            throw new RuntimeException(
                $result->get_error_message()
            );
        }

        dilitant_test_assert_same(
            $post_id,
            (int) $result,
            'Unable to establish trash fixture.'
        );

        $trashed = get_post($post_id);

        dilitant_test_assert(
            $trashed instanceof WP_Post,
            'Trash fixture disappeared.'
        );

        dilitant_test_assert_same(
            'trash',
            $trashed->post_status,
            'Trash status was not applied.'
        );

        return $callback($trashed);
    } finally {
        $result = wp_update_post([
            'ID' => $post_id,
            'post_status' => $previous_status,
            'post_date' => $previous_date,
            'post_date_gmt' => $previous_date_gmt,
        ], true);

        if (is_wp_error($result)) {
            throw new RuntimeException(
                $result->get_error_message()
            );
        }

        dilitant_test_assert_same(
            $post_id,
            (int) $result,
            'Unable to restore trash fixture.'
        );

        $restored = get_post($post_id);

        dilitant_test_assert(
            $restored instanceof WP_Post,
            'Restored trash fixture is missing.'
        );

        dilitant_test_assert_same(
            $previous_status,
            $restored->post_status,
            'Original post status was not restored.'
        );
    }
}