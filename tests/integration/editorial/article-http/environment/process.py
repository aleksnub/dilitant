"""Исполнение внешних программ без shell-интерпретации."""

from __future__ import annotations

import os
import subprocess
import sys

from collections.abc import Mapping, Sequence

from .errors import (
    DependencyError,
    ProcessError,
    ProcessTimeoutError,
)


class ProcessRunner:
    """Контролируемый исполнитель внешних команд."""

    def __init__(
        self,
        default_timeout: float = 60.0,
    ) -> None:
        self.default_timeout = default_timeout

    def run(
        self,
        arguments: Sequence[str],
        *,
        environment: Mapping[str, str] | None = None,
        timeout: float | None = None,
        check: bool = True,
        capture: bool = True,
    ) -> subprocess.CompletedProcess[str]:
        """Исполняет программу с явным списком аргументов."""

        if not arguments:
            raise ValueError("Empty command arguments.")

        effective_timeout = (
            self.default_timeout
            if timeout is None
            else timeout
        )

        process_environment = None

        if environment is not None:
            process_environment = os.environ.copy()
            process_environment.update(environment)

        try:
            result = subprocess.run(
                list(arguments),
                shell=False,
                check=False,
                text=True,
                encoding="utf-8",
                errors="replace",
                capture_output=capture,
                env=process_environment,
                timeout=effective_timeout,
            )

        except FileNotFoundError as error:
            raise DependencyError(
                f"Executable is unavailable: {arguments[0]}"
            ) from error

        except subprocess.TimeoutExpired as error:
            raise ProcessTimeoutError(
                f"Command timed out: {arguments[0]}"
            ) from error

        if check and result.returncode != 0:
            raise ProcessError(
                executable=arguments[0],
                returncode=result.returncode,
                stderr=result.stderr or "",
            )

        return result

    def output(
        self,
        arguments: Sequence[str],
        *,
        timeout: float | None = None,
    ) -> str:
        """Возвращает stdout успешно исполненной команды."""

        result = self.run(
            arguments,
            timeout=timeout,
        )

        return result.stdout.strip()


def report_pass(message: str) -> None:
    """Фиксирует положительный результат проверки."""

    print(
        f"PASS: {message}",
        flush=True,
    )


def report_failure(message: str) -> None:
    """Фиксирует отрицательный результат проверки."""

    print(
        f"FAIL: {message}",
        file=sys.stderr,
        flush=True,
    )