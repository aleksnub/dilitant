"""Интерфейсы User Recovery Suite.

MR-05:
    - использование единого RecoveryProcess;
    - проверка происхождения исполнителя;
    - проверка действительной конфигурации;
    - отсутствие прямого исполнения команд;
    - делегирование операций управляемому API.
"""

from __future__ import annotations

from types import ModuleType
from typing import Any
from pathlib import Path

from .config import (
    PROCESS_FILE,
    WORKER,
    MANIFEST_COMMANDS,
    recovery_process,
    require,
    validate_run_id,
)

from recovery_process.validation import validate_manifest


def load_process_module() -> ModuleType:
    require(
        isinstance(
            recovery_process,
            ModuleType,
        ),
        "Invalid RecoveryProcess module.",
    )

    require(
        Path(recovery_process.__file__).resolve()
        == PROCESS_FILE.resolve(),
        "Unexpected RecoveryProcess module origin.",
    )

    require(
        recovery_process.validate_manifest
        is validate_manifest,
        "Unexpected RecoveryProcess validator binding.",
    )

    return recovery_process


def validate_process(
    process: Any,
    module: ModuleType,
) -> None:
    """Проверить действительный управляемый исполнитель."""

    require(
        module is recovery_process,
        "Unexpected RecoveryProcess module.",
    )

    require(
        type(process) is module.RecoveryProcess,
        "Invalid User Recovery executor.",
    )

    require(
        isinstance(
            process.config,
            module.RecoveryConfig,
        ),
        "Invalid User Recovery configuration.",
    )

    require(
        process.config.worker == WORKER,
        "Unexpected User Recovery Worker binding.",
    )

    require(
        process.config.environment.is_file(),
        "User Recovery Environment unavailable.",
    )

    require(
        process.config.manifest.is_file(),
        "User Recovery Manifest unavailable.",
    )

    require(
        isinstance(process.container_id, str)
        and bool(process.container_id),
        "User Recovery container identity unavailable.",
    )

    process.assert_container()


class ProcessMixin:
    """Прикладные интерфейсы User Recovery."""

    def container_id(self) -> str:
        self._process.assert_container()

        return self._process.container_id

    def manifest(
        self,
        command: str,
    ) -> dict[str, Any]:
        require(
            command in MANIFEST_COMMANDS,
            "Unsupported Manifest V2 command.",
        )

        return self._process.manifest(
            command
        )

    def worker(
        self,
        action: str,
        *args: Any,
    ) -> dict[str, Any]:
        data = self._process.worker(
            action,
            *args,
        )

        require(
            isinstance(data, dict),
            "Invalid User Recovery worker response.",
        )

        return data

    def probe(
        self,
        run_id: str,
    ) -> dict[str, Any]:
        validate_run_id(run_id)

        result = self.worker(
            "probe",
            run_id,
        )

        require(
            result.get("run_id") == run_id
            and isinstance(
                result.get("user_ids"),
                list,
            )
            and isinstance(
                result.get("users"),
                list,
            ),
            "Invalid independent user probe.",
        )

        return result

    def _require_run(
        self,
        run_id: str,
        states: set[str],
    ) -> dict[str, Any]:
        data = self.manifest(
            "status"
        )

        require(
            data["run_id"]
            == validate_run_id(run_id)
            and data["state"] in states,
            "Unexpected persistent recovery state.",
        )

        return data