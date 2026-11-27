"""Изоляция ресурсов RecoveryProcess."""

from __future__ import annotations

import fcntl
import hashlib
import os
import stat
import tempfile

from contextlib import ExitStack, contextmanager
from pathlib import Path
from threading import RLock
from typing import Iterator

from .commands import (
    RecoveryTestError,
    invoke,
    require,
)

from .config import (
    DATABASE,
    TABLE_PREFIX,
    RecoveryConfig,
)

from .validation import parse_json


def resource_keys(
    config: RecoveryConfig,
) -> frozenset[str]:
    keys = {
        f"container:{config.container_name}",
    }

    if config.isolation is not None:
        identity = config.isolation

        keys.update({
            f"database:{identity.database_server_id}:{DATABASE}",
            f"http-port:{identity.http_port}",
        })

    return frozenset(keys)


def lock_directory() -> Path:
    directory = (
        Path(tempfile.gettempdir())
        / f"dilitant-recovery-{os.getuid()}"
    )

    try:
        directory.mkdir(
            mode=0o700,
            exist_ok=True,
        )

        info = directory.lstat()

    except OSError:
        raise RecoveryTestError(
            "Recovery lock directory unavailable."
        ) from None

    require(
        stat.S_ISDIR(info.st_mode)
        and info.st_uid == os.getuid()
        and stat.S_IMODE(info.st_mode) == 0o700,
        "Unsafe Recovery lock directory.",
    )

    return directory


@contextmanager
def resource_lock(
    key: str,
    *,
    shared: bool = False,
) -> Iterator[None]:
    digest = hashlib.sha256(
        key.encode("utf-8")
    ).hexdigest()

    filename = lock_directory() / digest

    try:
        descriptor = os.open(
            filename,
            os.O_CREAT
            | os.O_RDWR
            | os.O_CLOEXEC
            | os.O_NOFOLLOW,
            0o600,
        )

    except OSError:
        raise RecoveryTestError(
            "Recovery resource lock unavailable."
        ) from None

    try:
        info = os.fstat(descriptor)

        require(
            stat.S_ISREG(info.st_mode)
            and info.st_uid == os.getuid()
            and stat.S_IMODE(info.st_mode) == 0o600,
            "Unsafe Recovery resource lock.",
        )

        mode = (
            fcntl.LOCK_SH
            if shared
            else fcntl.LOCK_EX
        )

        try:
            fcntl.flock(
                descriptor,
                mode | fcntl.LOCK_NB,
            )

        except BlockingIOError:
            raise RecoveryTestError(
                f"Recovery resource is busy: {key}"
            ) from None

        except OSError:
            raise RecoveryTestError(
                "Unable to acquire Recovery resource lock."
            ) from None

        try:
            yield

        finally:
            fcntl.flock(
                descriptor,
                fcntl.LOCK_UN,
            )

    finally:
        os.close(descriptor)


class ResourceLease:
    """Реентерабельная блокировка экземпляра."""

    def __init__(
        self,
        config: RecoveryConfig,
    ) -> None:
        self._config = config
        self._mutex = RLock()
        self._depth = 0

    @contextmanager
    def hold(self) -> Iterator[None]:
        with self._mutex:
            if self._depth:
                self._depth += 1

                try:
                    yield

                finally:
                    self._depth -= 1

                return

            with ExitStack() as stack:
                stack.enter_context(
                    resource_lock(
                        "recovery-global-gate",
                        shared=self._config.is_isolated,
                    )
                )

                for key in sorted(
                    resource_keys(self._config)
                ):
                    stack.enter_context(
                        resource_lock(key)
                    )

                self._depth = 1

                try:
                    yield

                finally:
                    self._depth = 0


def verify_isolation(
    config: RecoveryConfig,
    container_id: str,
) -> None:
    if config.isolation is None:
        return

    result = invoke(
        [
            *config.isolation_command,
            config.container_name,
            container_id,
            config.worker,
        ],
        env=config.env,
        timeout=config.command_timeout,
    )

    require(
        not result.stderr.strip(),
        "Unexpected isolation verification diagnostic.",
    )

    observed = parse_json(
        result.stdout
    )

    expected = {
        "container_id": container_id,
        "container_name": config.container_name,
        "database_server_id":
            config.isolation.database_server_id,
        "database_name": DATABASE,
        "table_prefix": TABLE_PREFIX,
        "http_port": config.isolation.http_port,
        "worker": config.worker,
        "test_mode": True,
    }

    require(
        set(observed) == set(expected)
        and type(observed.get("http_port")) is int
        and observed.get("test_mode") is True
        and observed == expected,
        "Recovery isolation identity mismatch.",
    )