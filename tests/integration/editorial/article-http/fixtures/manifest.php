<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/fixtures/lock.php';

require_once __DIR__ . '/recovery/storage.php';
require_once __DIR__ . '/recovery/validation.php';
require_once __DIR__ . '/recovery/lifecycle.php';
require_once __DIR__ . '/recovery/posts.php';
require_once __DIR__ . '/recovery/categories.php';
require_once __DIR__ . '/recovery/users.php';
require_once __DIR__ . '/recovery/pending.php';

final class DilitantHttpFixtureManifest implements DilitantFixtureJournal
{
    use DilitantManifestStorage;
    use DilitantManifestValidation;
    use DilitantManifestLifecycle;
    use DilitantManifestPendingRecovery;

    public const OPTION = 'dilitant_http_fixture_manifest_v1';

    private array $data;

    private function __construct(array $data)
    {
        self::validate($data);

        $this->data = $data;
    }

    public static function begin(
        string $run_id,
        int $schema_version = 1
    ): self {
        self::assert_environment();

        if (
            preg_match('/\A[0-9a-f]{12}\z/', $run_id) !== 1
            || !in_array($schema_version, [1, 2], true)
        ) {
            throw new RuntimeException(
                'Invalid manifest initialization.'
            );
        }

        $previous = self::read_storage();

        if ($previous !== null) {
            self::validate($previous);

            if ($previous['state'] !== 'CLEANED') {
                throw new RuntimeException(
                    'Unfinished fixture run exists.'
                );
            }

            if ($previous['run_id'] === $run_id) {
                throw new RuntimeException(
                    'Fixture run ID already used.'
                );
            }

            if (
                $previous['schema_version'] === 2
                && $schema_version === 1
            ) {
                throw new RuntimeException(
                    'Manifest schema downgrade rejected.'
                );
            }
        }

        $identity = [
            'name' => 'AM Root ' . $run_id,
            'slug' => 'diletant-armenia',
            'parent' => 0,
        ];

        $root = dilitant_recover_category($identity);

        if ($root['outcome'] !== DilitantRecoveryOutcome::ABSENT) {
            throw new RuntimeException(
                'Fixture root category already exists.'
            );
        }

        $data = [
            'schema_version' => $schema_version,
            'run_id' => $run_id,
            'database_name' => DILITANT_TEST_DB_NAME,
            'table_prefix' => DILITANT_TEST_TABLE_PREFIX,
            'container_origin' => gethostname(),
            'created_at' => gmdate(DATE_ATOM),
            'state' => 'CREATING',
            'objects' => [],
            'snapshot' => null,
        ];

        $instance = new self($data);
        $instance->persist($data, $previous);

        return $instance;
    }

    public static function load(): self
    {
        self::assert_environment();

        $data = self::read_storage();

        if ($data === null) {
            throw new RuntimeException(
                'Fixture manifest unavailable.'
            );
        }

        return new self($data);
    }

    public function data(): array
    {
        return $this->data;
    }

    public function state(): string
    {
        return $this->data['state'];
    }

    private static function assert_environment(): void
    {
        dilitant_http_require_bootstrap();
        dilitant_fixture_assert_lock();
    }
}