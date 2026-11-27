"""Независимый аудит User Recovery.

MR-05:
    - единый механизм исполнения RecoveryProcess;
    - сохранение независимых SQL-проверок;
    - отсутствие прямого запуска Docker;
    - отсутствие постоянного исторического исполнителя;
    - сохранение индивидуального окружения.
"""

from __future__ import annotations

from dataclasses import replace
from typing import Any

from .config import (
    AUDIT_CHECKS,
    AUDIT_TABLES,
    LEGACY_WORKER,
    require,
    positive_id,
    valid_digest,
)

from .validation import validate_manifest

from recovery_process import RecoveryProcess
from recovery_process.isolation import resource_keys


class AuditMixin:
    def _protected_id(self) -> int:
        require(
            self._protected is not None,
            "Protected administrator baseline unavailable.",
        )

        return positive_id(self._protected["id"])

    def _remember_user(
        self,
        identifier: Any,
    ) -> None:
        identifier = positive_id(identifier)

        require(
            identifier != self._protected_id(),
            "Protected administrator in fixture audit registry.",
        )

        self._user_ids.add(identifier)

    def _remember_manifest(
        self,
        manifest: dict[str, Any],
        *,
        substituted: bool = False,
    ) -> None:
        validate_manifest(manifest)

        self._run_ids.add(manifest["run_id"])

        for record in manifest["objects"]:
            identifier = record["id"]

            if identifier == 0:
                continue

            if record["kind"] == "user":
                if (
                    substituted
                    and identifier == self._protected_id()
                ):
                    continue

                self._remember_user(identifier)

            elif record["kind"] == "post":
                self._post_ids.add(
                    positive_id(identifier)
                )

    def _remember_checkpoint(
        self,
        checkpoint: dict[str, Any],
        stage: str,
    ) -> None:
        manifest = checkpoint["manifest"]
        extra = checkpoint["extra"]

        substituted = stage == "user-substituted"

        if substituted:
            require(
                extra.get("substituted_id")
                == self._protected_id(),
                "Unexpected substitution target.",
            )

        self._remember_manifest(
            manifest,
            substituted=substituted,
        )

        for field in ("id", "duplicate_id"):
            if field in extra:
                self._remember_user(extra[field])

    def _validate_protected(
        self,
        protected: dict[str, Any],
    ) -> None:
        require(
            isinstance(protected, dict)
            and set(protected) == {
                "id",
                "login",
                "metadata_count",
                "digest",
            }
            and protected["login"] == "testadmin"
            and type(protected["metadata_count"]) is int
            and protected["metadata_count"] >= 0
            and valid_digest(protected["digest"]),
            "Invalid protected administrator audit.",
        )

        positive_id(protected["id"])

    def _validate_audit(
        self,
        audit: dict[str, Any],
    ) -> None:
        require(
            isinstance(audit, dict)
            and set(audit) == {
                "checks",
                "baseline",
                "protected_user",
            },
            "Invalid independent audit structure.",
        )

        checks = audit["checks"]

        require(
            isinstance(checks, dict)
            and set(checks) == AUDIT_CHECKS
            and all(
                type(value) is int and value == 0
                for value in checks.values()
            ),
            "Independent residual audit failed.",
        )

        baseline = audit["baseline"]

        require(
            isinstance(baseline, dict)
            and set(baseline) == AUDIT_TABLES,
            "Incomplete independent table audit.",
        )

        for name, entry in baseline.items():
            require(
                isinstance(entry, dict)
                and set(entry) == {"count", "digest"}
                and type(entry["count"]) is int
                and entry["count"] >= 0
                and valid_digest(entry["digest"]),
                f"Invalid table audit: {name}.",
            )

        self._validate_protected(
            audit["protected_user"]
        )

        require(
            audit["protected_user"] == self._protected,
            "Protected administrator changed.",
        )

    def assert_protected_user(self) -> None:
        require(
            self._protected is not None,
            "Protected administrator baseline unavailable.",
        )

        current = self.worker(
            "protected-user"
        )

        self._validate_protected(current)

        require(
            current == self._protected,
            "Protected administrator data changed.",
        )

    def _audit_post_events(self) -> None:
        """Независимый аудит публикаций.

        До объединения PHP-контрактов используется
        существующий Worker аудита публикаций.

        Исполнение осуществляется исключительно
        через единый RecoveryProcess.
        """

        self._process.assert_container()

        source_config = self._process.config

        audit_config = replace(
            source_config,
            worker=LEGACY_WORKER,
        )

        require(
            resource_keys(audit_config)
            == resource_keys(source_config),
            "Post audit resource identity changed.",
        )

        require(
            dict(audit_config.env)
            == dict(source_config.env),
            "Post audit environment changed.",
        )

        audit_process = RecoveryProcess(
            config=audit_config,
        )

        require(
            audit_process.container_id
            == self._process.container_id,
            "Post audit container identity mismatch.",
        )

        data = audit_process.worker(
            "audit",
            *sorted(self._post_ids),
        )

        self._process.assert_container()

        require(
            isinstance(data, dict),
            "Invalid independent post audit response.",
        )

        checks = data.get("checks")

        expected_checks = {
            "fixture_posts",
            "fixture_terms",
            "orphan_postmeta",
            "orphan_termmeta",
            "orphan_taxonomy",
            "orphan_relationships",
            "orphan_comments",
            "orphan_commentmeta",
            "orphan_usermeta",
        }

        require(
            isinstance(checks, dict)
            and set(checks) == expected_checks
            and all(
                type(value) is int and value == 0
                for value in checks.values()
            ),
            "Independent post audit failed.",
        )

    def assert_baseline(self) -> None:
        require(
            self._baseline is not None,
            "Initial database baseline unavailable.",
        )

        data = self.manifest(
            "status"
        )

        require(
            data["state"] == "CLEANED",
            "Baseline audit requires CLEANED manifest.",
        )

        current = self.worker(
            "audit",
            *sorted(self._user_ids),
        )

        self._validate_audit(current)

        require(
            current == self._baseline,
            "Database baseline changed after recovery.",
        )

        self._audit_post_events()

        self.assert_protected_user()

        print(
            "PASS: Independent SQL baseline preserved",
            flush=True,
        )