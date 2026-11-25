<?php

declare(strict_types=1);

final class DilitantFixtureUserCleanup
{
    public static function remove(
        array $record,
        string $run_id
    ): void {
        DilitantFixtureUserResolution::guard();

        DilitantFixtureUserIdentity::validate_record(
            $record,
            $run_id
        );

        $id = $record['id'];

        if ($id <= 0) {
            throw new RuntimeException(
                'Unresolved user cannot be deleted.'
            );
        }

        if (
            $id === DilitantFixtureUserResolution::protected_id()
            || $id === get_current_user_id()
        ) {
            throw new RuntimeException(
                'Protected or active user deletion rejected.'
            );
        }

        $result = DilitantFixtureUserResolution::resolve(
            $record,
            $run_id
        );

        if (
            $result['status']
            === DilitantUserResolutionStatus::BLOCKED
        ) {
            throw new RuntimeException(
                'User cleanup blocked: ' . $result['reason']
            );
        }

        if (
            $result['status']
            === DilitantUserResolutionStatus::ABSENT
        ) {
            DilitantFixtureUserDependencies::require_absent(
                $record,
                $run_id
            );

            return;
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';

        clean_user_cache($id);

        DilitantFixtureUserDependencies::require_deletable(
            $record,
            $run_id
        );

        DilitantFixtureUserResolution::guard();

        if (wp_delete_user($id, null) !== true) {
            throw new RuntimeException(
                'WordPress user deletion failed.'
            );
        }

        DilitantFixtureUserResolution::guard();

        DilitantFixtureUserDependencies::require_absent(
            $record,
            $run_id
        );
    }
}