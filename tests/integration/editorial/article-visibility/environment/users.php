<?php

declare(strict_types=1);

final class DilitantVisibilityUsers
{
    use DilitantVisibilityUserIdentity;
    use DilitantVisibilityUserCreation;
    use DilitantVisibilityUserCleanup;

    private string $run_id;

    private array $created_ids = [];

    public int $administrator = 0;
    public int $subscriber = 0;
    public int $author = 0;

    public function __construct()
    {
        dilitant_visibility_verify_environment();

        $this->run_id = bin2hex(random_bytes(6));
    }

    public function create(): void
    {
        dilitant_visibility_verify_environment();

        dilitant_test_assert_same(
            [],
            $this->created_ids,
            'Visibility users already exist.'
        );

        $this->administrator = $this->protected_admin_id();

        $administrator = get_user_by(
            'id',
            $this->administrator
        );

        dilitant_test_assert(
            $administrator instanceof WP_User,
            'Test administrator is missing.'
        );

        dilitant_test_assert(
            user_can($administrator, 'manage_options'),
            'Test administrator lacks required capabilities.'
        );

        $this->subscriber = $this->create_user('subscriber');
        $this->author = $this->create_user('author');

        dilitant_test_assert_same(
            2,
            count($this->created_ids),
            'Unexpected additional user count.'
        );
    }

    public function cleanup(): void
    {
        dilitant_visibility_verify_environment();

        require_once ABSPATH . 'wp-admin/includes/user.php';

        $errors = [];

        foreach (
            array_reverse($this->created_ids, true)
            as $id => $login
        ) {
            try {
                $this->remove_user($id, $login);

                unset($this->created_ids[$id]);

                if ($this->subscriber === $id) {
                    $this->subscriber = 0;
                }

                if ($this->author === $id) {
                    $this->author = 0;
                }
            } catch (Throwable $error) {
                $errors[] = $error->getMessage();
            }
        }

        if ($errors !== []) {
            throw new RuntimeException(
                implode(PHP_EOL, $errors)
            );
        }

        $this->subscriber = 0;
        $this->author = 0;

        echo "PASS: visibility user cleanup\n";
    }
}