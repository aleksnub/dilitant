"""Получение и проверка аварийных контрольных точек."""

from __future__ import annotations

import os
import selectors
import subprocess
import time

from typing import Any, Callable

from .config import CHECKPOINT_TIMEOUT

from .commands import require

from .validation import parse_json


def read_checkpoint(
    process: subprocess.Popen[bytes],
    *,
    timeout: int = CHECKPOINT_TIMEOUT,
) -> dict[str, Any]:
    require(
        process.stdout is not None,
        "Worker stdout unavailable.",
    )

    require(
        type(timeout) is int
        and timeout > 0,
        "Invalid checkpoint timeout.",
    )

    deadline = (
        time.monotonic()
        + timeout
    )

    received = bytearray()

    with selectors.DefaultSelector() as selector:
        selector.register(
            process.stdout,
            selectors.EVENT_READ,
        )

        while True:
            remaining = (
                deadline - time.monotonic()
            )

            require(
                remaining > 0,
                "Crash checkpoint timeout.",
            )

            require(
                bool(selector.select(remaining)),
                "Crash checkpoint timeout.",
            )

            chunk = os.read(
                process.stdout.fileno(),
                65536,
            )

            require(
                bool(chunk),
                "Worker exited before crash checkpoint.",
            )

            received.extend(chunk)

            require(
                len(received) <= 1024 * 1024,
                "Crash checkpoint is too large.",
            )

            if b"\n" not in received:
                continue

            line, remainder = received.split(
                b"\n",
                1,
            )

            require(
                not remainder.strip(),
                "Unexpected worker output.",
            )

            return parse_json(
                line.decode("utf-8")
            )


def validate_checkpoint(
    data: dict[str, Any],
    stage: str,
    manifest_validator: Callable[
        [dict[str, Any]],
        None,
    ],
) -> None:
    require(
        data.get("event") == "CRASH_READY"
        and data.get("stage") == stage,
        "Unexpected crash checkpoint.",
    )

    pid = data.get("pid")

    require(
        type(pid) is int
        and pid > 1,
        "Invalid worker PID.",
    )

    if data.get("extra") == []:
        data["extra"] = {}

    require(
        isinstance(
            data.get("manifest"),
            dict,
        )
        and isinstance(
            data.get("extra"),
            dict,
        ),
        "Incomplete crash checkpoint.",
    )

    manifest_validator(
        data["manifest"]
    )