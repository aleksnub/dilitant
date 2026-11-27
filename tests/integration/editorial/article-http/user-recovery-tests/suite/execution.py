from __future__ import annotations

from typing import Any

from .config import require

from .process import (
    ProcessMixin,
    load_process_module,
    validate_process,
)

from .audit import AuditMixin
from .recovery import RecoveryMixin


class UserRecoverySuite(
    ProcessMixin,
    AuditMixin,
    RecoveryMixin,
):
    def __init__(
        self,
        *,
        process: Any = None,
        config: Any = None,
    ) -> None:
        self._module = load_process_module()

        require(
            process is None or config is None,
            "Specify either RecoveryProcess or RecoveryConfig.",
        )

        if process is None:
            process = self._module.RecoveryProcess(
                config=config,
            )

        validate_process(
            process,
            self._module,
        )

        self._process = process

        self._baseline: dict[str, Any] | None = None
        self._protected: dict[str, Any] | None = None

        self._user_ids: set[int] = set()
        self._post_ids: set[int] = set()
        self._run_ids: set[str] = set()

    def preflight(self) -> None:
        require(
            self._baseline is None,
            "User Recovery preflight already completed.",
        )

        validate_process(
            self._process,
            self._module,
        )

        self._process.environment("check")

        self._process.verify_restart_readiness()

        print(
            "PASS: HTTP container restart readiness",
            flush=True,
        )

        manifest = self.manifest("status")

        require(
            manifest["state"] == "CLEANED",
            "Previous fixture run is unfinished.",
        )

        require(
            self.worker("manifest") == manifest,
            "Independent manifest reading mismatch.",
        )

        require(
            self.probe(manifest["run_id"])["user_ids"] == [],
            "Residual initial fixture users.",
        )

        protected = self.worker("protected-user")

        self._validate_protected(protected)

        self._protected = protected

        self._remember_manifest(manifest)

        baseline = self.worker(
            "audit",
            *sorted(self._user_ids),
        )

        self._validate_audit(baseline)

        self._baseline = baseline

        self.assert_baseline()

        print(
            "PASS: User Recovery V2 preflight",
            flush=True,
        )

    def finalize(self) -> None:
        manifest = self.manifest("status")

        require(
            manifest["state"] == "CLEANED",
            "Final fixture run is unfinished.",
        )

        self.assert_baseline()

        for run_id in sorted(self._run_ids):
            probe = self.probe(run_id)

            require(
                probe["user_ids"] == []
                and probe["users"] == [],
                f"Residual users of fixture run {run_id}.",
            )

        self.assert_protected_user()

        print(
            f"Audited fixture runs: {len(self._run_ids)}",
            flush=True,
        )

        print(
            f"Audited user IDs: {len(self._user_ids)}",
            flush=True,
        )

        print(
            f"Audited post IDs: {len(self._post_ids)}",
            flush=True,
        )

        print(
            "PASS: User Recovery final acceptance",
            flush=True,
        )