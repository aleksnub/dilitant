"""Проверка готовности к пересозданию окружения."""

from __future__ import annotations

import subprocess
import sys

from .config import (
    BASE,
    RecoveryConfig,
)

from .commands import (
    RecoveryTestError,
    invoke,
    redact_diagnostic,
    require,
)


ENVIRONMENT_READINESS_CODE = """
import sys

from environment.config import create_config

from environment.preflight import EnvironmentPreflight

from environment.process import ProcessRunner

from environment.errors import EnvironmentError


config = create_config()

process = ProcessRunner(
    default_timeout=config.command_timeout,
)

try:
    EnvironmentPreflight(
        config,
        process,
    ).verify_environment()

except EnvironmentError as error:
    print(
        f"FAIL: {error}",
        file=sys.stderr,
        flush=True,
    )

    raise SystemExit(1)

raise SystemExit(0)
"""


DATABASE_READINESS_CODE = """
import sys

from environment.config import create_config

from environment.errors import EnvironmentError

from environment.database_readiness import (
    DatabaseReadinessVerifier,
)

try:
    verifier = DatabaseReadinessVerifier(
        create_config(),
    )

    verifier.verify()

except EnvironmentError as error:
    print(
        str(error),
        file=sys.stderr,
        flush=True,
    )

    raise SystemExit(1)

except Exception:
    print(
        "Unexpected database readiness failure.",
        file=sys.stderr,
        flush=True,
    )

    raise SystemExit(1)

print(
    "DATABASE_READINESS_CONFIRMED",
    flush=True,
)

raise SystemExit(0)
"""


DATABASE_DIAGNOSTIC_CODES = frozenset({
    "INVALID_ENVIRONMENT",
    "INVALID_DATABASE_ENDPOINT",
    "INVALID_DATABASE_PORT",
    "MYSQLI_INITIALIZATION_FAILED",
    "CONNECT_TIMEOUT_CONFIGURATION_FAILED",
    "READ_TIMEOUT_UNAVAILABLE",
    "READ_TIMEOUT_CONFIGURATION_FAILED",
    "INVALID_QUERY_RESULT",
    "INVALID_QUERY_ROW_COUNT",
    "INVALID_QUERY_RESPONSE",
    "INCOMPLETE_QUERY_RESPONSE",
    "CONNECTION_NOT_CONFIRMED",
    "SQL_AUTHENTICATION_FAILED",
    "SQL_AUTHORIZATION_FAILED",
    "DATABASE_NOT_FOUND",
    "DATABASE_UNREACHABLE",
    "SQL_OPERATION_FAILED",
    "DATABASE_READINESS_FAILED",
    "UNCLASSIFIED_PROBE_FAILURE",
})


DATABASE_DIAGNOSTICS = frozenset({
    "Test database password is missing.",
    "Unexpected SQL database identity.",
    "Unexpected authenticated SQL account.",
    "Unexpected SQL connection identity.",
    "Invalid SQL connection account.",
    "SQL connection was not confirmed.",
    "Database readiness was not confirmed.",
    "Unexpected database readiness response fields.",
    "Invalid database readiness response.",
    "Unexpected database readiness response.",
    "Database readiness operation timed out.",
    "Unable to execute database readiness operation.",
    "Database readiness source image is unavailable.",
    "Invalid database readiness image identity.",
    "Unable to verify database readiness cleanup.",
    "Ambiguous database readiness container.",
    "Invalid database readiness container ID.",
    "Database readiness container ownership "
    "could not be confirmed.",
    "Database readiness container cleanup "
    "could not be confirmed.",
    "Unexpected database readiness failure.",
}) | frozenset(
    f"Database readiness rejected: {code}"
    for code in DATABASE_DIAGNOSTIC_CODES
)


def _execute_readiness(
    code: str,
    *,
    operation: str,
    config: RecoveryConfig,
) -> subprocess.CompletedProcess[str]:
    try:
        return subprocess.run(
            [
                sys.executable,
                "-B",
                "-c",
                code,
            ],
            cwd=str(BASE),
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
            timeout=config.command_timeout,
            check=False,
            env=dict(config.env),
        )

    except subprocess.TimeoutExpired:
        raise RecoveryTestError(
            f"{operation} verification timed out."
        ) from None

    except OSError:
        raise RecoveryTestError(
            f"Unable to execute {operation.lower()} verification."
        ) from None


def _verify_custom_readiness(
    config: RecoveryConfig,
    operation: str,
    confirmation: str,
) -> None:
    require(
        config.is_isolated
        and bool(config.readiness_command),
        "Isolated readiness verifier unavailable.",
    )

    result = invoke(
        [
            *config.readiness_command,
            operation,
            config.container_name,
        ],
        check=False,
        env=config.env,
        timeout=config.command_timeout,
    )

    require(
        result.returncode == 0,
        f"Isolated Recovery {operation} readiness rejected.",
    )

    require(
        result.stdout.strip() == confirmation
        and not result.stderr.strip(),
        "Invalid isolated readiness confirmation.",
    )


def verify_restart_environment(
    config: RecoveryConfig | None = None,
) -> None:
    if config is None:
        config = RecoveryConfig()

    if config.is_isolated:
        _verify_custom_readiness(
            config,
            "environment",
            "ENVIRONMENT_READINESS_CONFIRMED",
        )

        return

    require(
        config.environment.is_file(),
        "HTTP Environment entry point is unavailable.",
    )

    required_files = (
        BASE / "environment/config.py",
        BASE / "environment/preflight.py",
        BASE / "environment/process.py",
    )

    for source in required_files:
        require(
            source.is_file(),
            f"Restart readiness component is unavailable: {source}",
        )

    result = _execute_readiness(
        ENVIRONMENT_READINESS_CODE,
        operation="Restart environment",
        config=config,
    )

    if result.returncode != 0:
        diagnostic = (
            result.stderr.strip()
            or "Restart environment verification failed."
        )

        diagnostic = redact_diagnostic(
            diagnostic,
            env=config.env,
        )

        raise RecoveryTestError(
            "HTTP container restart is not ready.\n"
            + diagnostic[-4000:]
        )


def verify_restart_database(
    config: RecoveryConfig | None = None,
) -> None:
    if config is None:
        config = RecoveryConfig()

    if config.is_isolated:
        _verify_custom_readiness(
            config,
            "database",
            "DATABASE_READINESS_CONFIRMED",
        )

        return

    component = (
        BASE
        / "environment"
        / "database_readiness"
        / "__init__.py"
    )

    require(
        component.is_file(),
        "Database readiness component is unavailable.",
    )

    result = _execute_readiness(
        DATABASE_READINESS_CODE,
        operation="Database readiness",
        config=config,
    )

    if result.returncode != 0:
        diagnostic = result.stderr.strip()

        if diagnostic not in DATABASE_DIAGNOSTICS:
            diagnostic = (
                "Database readiness verification failed."
            )

        raise RecoveryTestError(
            "HTTP container restart is not ready.\n"
            + redact_diagnostic(
                diagnostic,
                env=config.env,
            )
        )

    require(
        result.stdout.strip()
        == "DATABASE_READINESS_CONFIRMED",
        "Database readiness confirmation is missing.",
    )

    require(
        not result.stderr.strip(),
        "Unexpected database readiness diagnostic output.",
    )