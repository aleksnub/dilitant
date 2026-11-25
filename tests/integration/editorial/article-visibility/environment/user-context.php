<?php

declare(strict_types=1);

function dilitant_visibility_with_user(
    int $user_id,
    callable $callback
) {
    dilitant_visibility_verify_environment();

    $previous_user_id = get_current_user_id();

    if ($user_id !== 0) {
        dilitant_test_assert(
            get_user_by('id', $user_id) instanceof WP_User,
            'Requested test user does not exist.'
        );
    }

    try {
        wp_set_current_user($user_id);

        dilitant_test_assert_same(
            $user_id,
            get_current_user_id(),
            'Unable to establish user context.'
        );

        return $callback();
    } finally {
        wp_set_current_user($previous_user_id);

        dilitant_test_assert_same(
            $previous_user_id,
            get_current_user_id(),
            'Original user context was not restored.'
        );
    }
}