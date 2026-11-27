"""Статический PHP-код независимого SQL Probe.

Не загружает WordPress.
Не изменяет тестовую БД.
Не выводит секреты.
"""


DATABASE_READINESS_CODE = r"""
mysqli_report(
    MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT
);

function readiness_fail(
    string $code
): never {
    echo json_encode(
        [
            "status" => "FAILED",
            "code" => $code,
        ],
        JSON_THROW_ON_ERROR
    );

    echo PHP_EOL;

    exit(1);
}

$mode = getenv("DILITANT_TEST_MODE");

$database = getenv("DILITANT_TEST_DB_NAME");

$username = getenv("DILITANT_TEST_DB_USER");

$endpoint = getenv("DILITANT_TEST_DB_HOST");

$password = getenv("DILITANT_TEST_DB_PASSWORD");

if (
    $mode !== "1"
    || $database === false
    || $database === ""
    || $username === false
    || $username === ""
    || $endpoint === false
    || $endpoint === ""
    || $password === false
    || $password === ""
) {
    readiness_fail("INVALID_ENVIRONMENT");
}

if (
    preg_match(
        '/^([a-zA-Z0-9_.-]+):([0-9]{1,5})$/D',
        $endpoint,
        $matches
    ) !== 1
) {
    readiness_fail("INVALID_DATABASE_ENDPOINT");
}

$host = $matches[1];

$port = (int) $matches[2];

if ($port < 1 || $port > 65535) {
    readiness_fail("INVALID_DATABASE_PORT");
}

$connection = null;

try {
    $connection = mysqli_init();

    if (!$connection instanceof mysqli) {
        readiness_fail("MYSQLI_INITIALIZATION_FAILED");
    }

    if (
        !$connection->options(
            MYSQLI_OPT_CONNECT_TIMEOUT,
            5
        )
    ) {
        readiness_fail(
            "CONNECT_TIMEOUT_CONFIGURATION_FAILED"
        );
    }

    if (!defined("MYSQLI_OPT_READ_TIMEOUT")) {
        readiness_fail("READ_TIMEOUT_UNAVAILABLE");
    }

    if (
        !$connection->options(
            MYSQLI_OPT_READ_TIMEOUT,
            5
        )
    ) {
        readiness_fail(
            "READ_TIMEOUT_CONFIGURATION_FAILED"
        );
    }

    $connection->real_connect(
        $host,
        $username,
        $password,
        $database,
        $port
    );

    unset($password);

    $result = $connection->query(
        "SELECT
            DATABASE() AS database_name,
            CURRENT_USER() AS authenticated_account,
            USER() AS connection_account,
            1 AS connection_ok"
    );

    if (!$result instanceof mysqli_result) {
        readiness_fail("INVALID_QUERY_RESULT");
    }

    if ($result->num_rows !== 1) {
        readiness_fail("INVALID_QUERY_ROW_COUNT");
    }

    $row = $result->fetch_assoc();

    $result->free();

    if (!is_array($row)) {
        readiness_fail("INVALID_QUERY_RESPONSE");
    }

    if (
        !isset(
            $row["database_name"],
            $row["authenticated_account"],
            $row["connection_account"],
            $row["connection_ok"]
        )
    ) {
        readiness_fail("INCOMPLETE_QUERY_RESPONSE");
    }

    if ((string) $row["connection_ok"] !== "1") {
        readiness_fail("CONNECTION_NOT_CONFIRMED");
    }

    $connection->close();

    $connection = null;

    echo json_encode(
        [
            "status" => "READY",

            "database_name" =>
                $row["database_name"],

            "authenticated_account" =>
                $row["authenticated_account"],

            "connection_account" =>
                $row["connection_account"],

            "connection_ok" => 1,
        ],
        JSON_THROW_ON_ERROR
    );

    echo PHP_EOL;

    exit(0);

} catch (mysqli_sql_exception $error) {
    unset($password);

    $code = (int) $error->getCode();

    switch ($code) {
        case 1045:
            readiness_fail(
                "SQL_AUTHENTICATION_FAILED"
            );

        case 1044:
            readiness_fail(
                "SQL_AUTHORIZATION_FAILED"
            );

        case 1049:
            readiness_fail(
                "DATABASE_NOT_FOUND"
            );

        case 2002:
        case 2003:
        case 2005:
            readiness_fail(
                "DATABASE_UNREACHABLE"
            );

        default:
            readiness_fail(
                "SQL_OPERATION_FAILED"
            );
    }

} catch (Throwable $error) {
    unset($password);

    readiness_fail(
        "DATABASE_READINESS_FAILED"
    );
}
"""