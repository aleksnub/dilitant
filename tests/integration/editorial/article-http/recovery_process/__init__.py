"""Публичный интерфейс RecoveryProcess V2."""

from __future__ import annotations

from typing import Any

from .config import (
    BASE,
    ENVIRONMENT,
    MANIFEST,
    WORKER,
    CONTAINER_NAME,
    COMMAND_TIMEOUT,
    CHECKPOINT_TIMEOUT,
    DATABASE_PASSWORD_VARIABLE,
    IsolationIdentity,
    RecoveryConfig,
)

from .commands import (
    RecoveryTestError,
    require,
    invoke,
    redact_diagnostic,
)

from .validation import (
    parse_json,
    validate_manifest,
)

from .readiness import (
    verify_restart_environment,
    verify_restart_database,
)

from .checkpoint import (
    read_checkpoint,
    validate_checkpoint as _validate_checkpoint,
)

from .container import ContainerMixin

from .crash import CrashMixin

from .parallel import (
    RecoveryJob,
    run_parallel,
)


def validate_checkpoint(
    data: dict[str, Any],
    stage: str,
) -> None:
    _validate_checkpoint(
        data,
        stage,
        validate_manifest,
    )


class RecoveryProcess(
    ContainerMixin,
    CrashMixin,
):
    """Конфигурируемый исполнитель Recovery V2."""

    def __init__(
        self,
        config: RecoveryConfig | None = None,
    ) -> None:
        super().__init__(
            config
        )


__all__ = [
    "BASE",
    "ENVIRONMENT",
    "MANIFEST",
    "WORKER",
    "CONTAINER_NAME",
    "COMMAND_TIMEOUT",
    "CHECKPOINT_TIMEOUT",
    "DATABASE_PASSWORD_VARIABLE",
    "IsolationIdentity",
    "RecoveryConfig",
    "RecoveryTestError",
    "require",
    "invoke",
    "redact_diagnostic",
    "parse_json",
    "validate_manifest",
    "verify_restart_environment",
    "verify_restart_database",
    "read_checkpoint",
    "validate_checkpoint",
    "RecoveryProcess",
    "RecoveryJob",
    "run_parallel",
]