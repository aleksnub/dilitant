"""Исполнение команд RecoveryProcess."""

from __future__ import annotations

import os
import subprocess

from pathlib import Path
from typing import Mapping

from .config import (
    COMMAND_TIMEOUT,
    DATABASE_PASSWORD_VARIABLE,
)


class RecoveryTestError(RuntimeError):
    """Типизированный отказ Recovery-испытания."""


def require(condition: bool, message: str) -> None:
    if not condition:
        raise RecoveryTestError(message)


def redact_diagnostic(
    value: str,
    *,
    env: Mapping[str, str] | None = None,
) -> str:
    source = (
        os.environ
        if env is None
        else env
    )

    password = source.get(
        DATABASE_PASSWORD_VARIABLE
    )

    if password:
        value = value.replace(
            password,
            "[REDACTED]",
        )

    return value


def invoke(
    args: list[str],
    *,
    check: bool = True,
    timeout: int = COMMAND_TIMEOUT,
    env: Mapping[str, str] | None = None,
    cwd: Path | str | None = None,
) -> subprocess.CompletedProcess[str]:
    process_env = (
        dict(os.environ)
        if env is None
        else dict(env)
    )

    try:
        result = subprocess.run(
            args,
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
            timeout=timeout,
            check=False,
            env=process_env,
            cwd=cwd,
        )

    except subprocess.TimeoutExpired:
        raise RecoveryTestError(
            "Recovery command timed out."
        ) from None

    except OSError:
        raise RecoveryTestError(
            "Unable to execute recovery command."
        ) from None

    if check and result.returncode != 0:
        detail = (
            result.stderr.strip()
            or result.stdout.strip()
            or "No diagnostic output."
        )

        detail = redact_diagnostic(
            detail,
            env=process_env,
        )

        raise RecoveryTestError(
            f"Command failed ({result.returncode}): "
            f"{args[0]}\n{detail[-4000:]}"
        )

    return result