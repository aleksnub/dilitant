#!/usr/bin/env python3
"""Командный интерфейс HTTP Environment."""

from __future__ import annotations

import signal
import sys

from environment.config import create_config

from environment.errors import EnvironmentError

from environment.lifecycle import EnvironmentLifecycle

from environment.process import report_failure


USAGE = """\
Usage:

    python3 environment.py start
    python3 environment.py check
    python3 environment.py status
    python3 environment.py stop
    python3 environment.py logs
    python3 environment.py cli <php arguments>

Compatible interface:

    bash environment.sh start
    bash environment.sh check
    bash environment.sh status
    bash environment.sh stop
    bash environment.sh logs
    bash environment.sh cli <php arguments>

The program manages only the isolated
dilitant_land_http_test container.
"""


class TerminationRequested(KeyboardInterrupt):
    """Получен запрос на завершение процесса."""


def handle_sigterm(
    signum: int,
    frame,
) -> None:
    """Передаёт SIGTERM механизму очистки Python."""

    raise TerminationRequested(
        "Termination requested."
    )


def main(
    arguments: list[str] | None = None,
) -> int:
    """Исполняет запрошенную операцию."""

    if arguments is None:
        arguments = sys.argv[1:]

    if not arguments:
        print(
            USAGE,
            file=sys.stderr,
        )

        return 1

    operation = arguments[0]

    remaining = arguments[1:]

    if operation in {
        "help",
        "-h",
        "--help",
    }:
        print(USAGE)

        return 0

    supported_operations = {
        "start",
        "check",
        "status",
        "stop",
        "logs",
        "cli",
    }

    if operation not in supported_operations:
        report_failure(
            f"Unknown operation: {operation}"
        )

        return 1

    if (
        operation != "cli"
        and remaining
    ):
        report_failure(
            f"Unexpected arguments for {operation}."
        )

        return 1

    lifecycle = EnvironmentLifecycle(
        create_config(),
    )

    try:
        if operation == "start":
            lifecycle.start()

        elif operation == "check":
            lifecycle.check()

        elif operation == "status":
            lifecycle.status()

        elif operation == "stop":
            lifecycle.stop()

        elif operation == "logs":
            lifecycle.logs()

        elif operation == "cli":
            return lifecycle.cli(
                remaining,
            )

        return 0

    except TerminationRequested:
        report_failure(
            "HTTP environment operation terminated."
        )

        return 143

    except KeyboardInterrupt:
        report_failure(
            "HTTP environment operation interrupted."
        )

        return 130

    except EnvironmentError as error:
        report_failure(
            str(error),
        )

        return 1

    except Exception as error:
        report_failure(
            f"Unexpected error: "
            f"{type(error).__name__}: {error}"
        )

        return 1


if __name__ == "__main__":
    signal.signal(
        signal.SIGTERM,
        handle_sigterm,
    )

    raise SystemExit(
        main(),
    )