"""Управляемое исполнение команд регрессии."""

from __future__ import annotations

import os
import re
import sys

from pathlib import Path

from recovery_process import (
    RecoveryConfig,
    RecoveryProcess,
)

from .config import (
    PASSWORD_ENV,
    TIMEOUT,
)


class RegressionError(RuntimeError):
    """Ошибка полной регрессии HF-M10-V2."""


def require(
    condition: bool,
    message: str,
) -> None:
    if not condition:
        raise RegressionError(message)


def redact(
    value: str,
    config: RecoveryConfig | None = None,
) -> str:
    env = (
        os.environ
        if config is None
        else config.env
    )

    password = env.get(
        PASSWORD_ENV,
        "",
    )

    if password:
        value = value.replace(
            password,
            "[REDACTED]",
        )

    return value


def report(
    output: str,
    config: RecoveryConfig,
) -> None:
    require(
        isinstance(output, str),
        "Invalid regression command output.",
    )

    if output.strip():
        print(
            redact(
                output.strip(),
                config,
            ),
            flush=True,
        )


def require_output(
    output: str,
    expected: str,
) -> None:
    require(
        expected in output.splitlines(),
        f"Expected test result missing: {expected}",
    )


def require_output_prefix(
    output: str,
    expected: str,
) -> None:
    require(
        any(
            line.startswith(expected)
            for line in output.splitlines()
        ),
        f"Expected test result missing: {expected}",
    )


def assert_process_configuration(
    process: RecoveryProcess,
    config: RecoveryConfig,
) -> None:
    require(
        type(process) is RecoveryProcess,
        "Unexpected RecoveryProcess class.",
    )

    require(
        isinstance(config, RecoveryConfig)
        and isinstance(
            process.config,
            RecoveryConfig,
        ),
        "Invalid RecoveryProcess configuration.",
    )

    require(
        process.config == config,
        "RecoveryProcess configuration changed.",
    )

    require(
        dict(process.config.env)
        == dict(config.env),
        "RecoveryProcess environment changed.",
    )


def require_default_environment(
    config: RecoveryConfig,
) -> None:
    require(
        isinstance(config, RecoveryConfig),
        "Invalid Recovery configuration.",
    )

    require(
        config.isolation is None,
        "Full regression requires the default environment. "
        "Independent environments require MR-07.",
    )


def execute(
    process: RecoveryProcess,
    config: RecoveryConfig,
    args: list[str],
    *,
    timeout: int = TIMEOUT,
    require_container: bool = True,
) -> str:
    """Исполнить вспомогательную команду.

    Используются конфигурация, окружение
    и ResourceLease действительного RecoveryProcess.
    """

    assert_process_configuration(
        process,
        config,
    )

    require(
        isinstance(args, list)
        and bool(args)
        and all(
            isinstance(argument, str)
            and "\x00" not in argument
            for argument in args
        ),
        "Invalid regression command arguments.",
    )

    require(
        type(timeout) is int
        and timeout > 0,
        "Invalid regression command timeout.",
    )

    require(
        type(require_container) is bool,
        "Invalid container verification flag.",
    )

    with process.session():
        assert_process_configuration(
            process,
            config,
        )

        if require_container:
            process.assert_container()

        result = process.invoke(
            args,
            check=False,
            timeout=timeout,
        )

        if require_container:
            process.assert_container()

        assert_process_configuration(
            process,
            config,
        )

        if result.returncode != 0:
            diagnostic = "\n".join(
                part.strip()
                for part in (
                    result.stdout,
                    result.stderr,
                )
                if part.strip()
            )

            raise RegressionError(
                f"Command failed: exit "
                f"{result.returncode}\n"
                + redact(
                    diagnostic,
                    config,
                )[-6000:]
            )

        require(
            re.search(
                r"^\s*(?:FAIL|ERROR|Fatal error)\s*:",
                result.stderr,
                re.MULTILINE | re.IGNORECASE,
            ) is None,
            "Command reported an error:\n"
            + redact(
                result.stderr,
                config,
            )[-6000:],
        )

        return result.stdout


def python_command(
    process: RecoveryProcess,
    config: RecoveryConfig,
    script: Path,
    *args: str,
    timeout: int = TIMEOUT,
) -> str:
    assert_process_configuration(
        process,
        config,
    )

    require(
        script.resolve()
        == config.environment.resolve(),
        "Unmanaged Python command is prohibited.",
    )

    require(
        bool(args)
        and args[0] == "cli",
        "Only managed Environment CLI commands "
        "are permitted.",
    )

    return execute(
        process,
        config,
        [
            sys.executable,
            "-B",
            str(config.environment),
            *args,
        ],
        timeout=timeout,
    )


def environment(
    process: RecoveryProcess,
    config: RecoveryConfig,
    command: str,
) -> None:
    assert_process_configuration(
        process,
        config,
    )

    require(
        command in {
            "start",
            "stop",
            "check",
        },
        "Unsupported Environment command.",
    )

    output = process.environment(
        command
    )

    require(
        isinstance(output, str),
        "Invalid Environment command result.",
    )

    assert_process_configuration(
        process,
        config,
    )

    report(
        output,
        config,
    )