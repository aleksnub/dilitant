from __future__ import annotations

from copy import deepcopy
from typing import Any

from .config import (
    UserRecoverySuite,
    STAGES,
    CLEANUP_STAGE,
    READY_COUNT,
    SQL_INJECTION_MARKER,
    require,
    positive_id,
    validate_manifest,
)

from .audit import FixtureAuditMixin


class FixtureRecoverySuite(
    FixtureAuditMixin,
    UserRecoverySuite,
):
    def preflight(self) -> None:
        super().preflight()

        current = self.manifest("status")

        self.assert_empty_fixture_probe(
            self.fixture_probe(current["run_id"])
        )

        print(
            "PASS: Fixture Recovery V2 preflight",
            flush=True,
        )

    def crash(
        self,
        stage: str,
        *,
        container: bool = False,
    ) -> dict[str, Any]:
        require(
            stage in STAGES,
            "Unsupported fixture recovery stage.",
        )

        require(
            container == (stage == "fixture-container"),
            "Unexpected container crash mode.",
        )

        previous = self.manifest("status")
        cleanup = stage == CLEANUP_STAGE

        require(
            previous["state"]
            == ("READY" if cleanup else "CLEANED"),
            "Invalid state before fixture crash.",
        )

        if not cleanup:
            self.assert_baseline()

        checkpoint = self._process.crash(
            stage,
            stage,
            container=container,
        )

        manifest = checkpoint["manifest"]

        validate_manifest(manifest)

        require(
            manifest["state"]
            == ("CLEANING" if cleanup else "CREATING"),
            "Unexpected crash manifest state.",
        )

        if cleanup:
            require(
                manifest["run_id"] == previous["run_id"],
                "Cleanup crash changed run identity.",
            )
        else:
            require(
                manifest["run_id"] != previous["run_id"]
                and manifest["run_id"] not in self._run_ids,
                "Fixture run identity was reused.",
            )

        self._remember_manifest(manifest)

        require(
            self.manifest("status") == manifest,
            "Persistent crash manifest mismatch.",
        )

        self.assert_protected_user()

        return checkpoint

    @staticmethod
    def pending(
        checkpoint: dict[str, Any],
        kind: str,
        count: int,
    ) -> dict[str, Any]:
        manifest = checkpoint["manifest"]
        objects = manifest["objects"]

        require(
            manifest["state"] == "CREATING"
            and manifest["snapshot"] is None
            and len(objects) == count,
            "Unexpected pending manifest structure.",
        )

        pending = [
            item for item in objects
            if item["id"] == 0
        ]

        require(
            len(pending) == 1
            and pending[0] == objects[-1]
            and pending[0]["kind"] == kind
            and pending[0]["taxonomy_id"] == 0,
            "Expected exactly one pending fixture intent.",
        )

        return pending[0]

    @staticmethod
    def recovered_registry(
        checkpoint: dict[str, Any],
        identifier: int,
        taxonomy_id: int = 0,
    ) -> list[dict[str, Any]]:
        positive_id(identifier)

        require(
            type(taxonomy_id) is int
            and taxonomy_id >= 0,
            "Invalid recovered taxonomy ID.",
        )

        objects = deepcopy(
            checkpoint["manifest"]["objects"]
        )

        require(
            bool(objects)
            and objects[-1]["id"] == 0,
            "Expected pending fixture record.",
        )

        record = objects[-1]

        require(
            (
                record["kind"] == "category"
                and taxonomy_id > 0
            )
            or (
                record["kind"] == "post"
                and taxonomy_id == 0
            ),
            "Invalid recovered object kind.",
        )

        record["id"] = identifier
        record["taxonomy_id"] = taxonomy_id

        return objects

    def create_ready(self) -> dict[str, Any]:
        ready = super().create_ready()

        require(
            len(ready["objects"]) == READY_COUNT,
            "Incomplete Manifest V2 registry.",
        )

        observed = self.fixture_probe(ready["run_id"])

        self.assert_registry_probe(
            ready["objects"],
            observed,
            exact=True,
        )

        return ready

    def finish(
        self,
        checkpoint: dict[str, Any],
        expected_objects: list[dict[str, Any]],
        observed: dict[str, Any],
    ) -> None:
        expected = deepcopy(checkpoint["manifest"])

        expected["state"] = "CLEANED"
        expected["objects"] = expected_objects

        validate_manifest(expected)

        run_id = expected["run_id"]

        require(
            observed["run_id"] == run_id,
            "Observed fixture run identity mismatch.",
        )

        self.remember_fixture_probe(observed)

        cleaned = self.cleanup(run_id)

        require(
            cleaned == expected,
            "Recovered manifest differs from expected registry.",
        )

        require(
            self.manifest("status") == expected,
            "Recovered manifest was not persisted.",
        )

        require(
            self.cleanup(run_id) == expected,
            "Recovered cleanup is not idempotent.",
        )

        self.assert_empty_fixture_probe(
            self.fixture_probe(run_id)
        )

        self.assert_baseline()

        print(
            "PASS: Independent cleanup audit",
            flush=True,
        )

    def expect_blocked(
        self,
        checkpoint: dict[str, Any],
        observed: dict[str, Any],
        diagnostic: str,
        *,
        sql_failure: bool = False,
    ) -> None:
        manifest = checkpoint["manifest"]
        run_id = manifest["run_id"]

        require(
            self.manifest("status") == manifest,
            "Manifest changed before negative recovery.",
        )

        require(
            self.fixture_probe(run_id) == observed,
            "Fixture identities changed before negative recovery.",
        )

        if sql_failure:
            result = self._process.worker_command(
                "fixture-sql-failure",
                run_id,
                check=False,
            )

            require(
                result.stderr.count(
                    SQL_INJECTION_MARKER
                ) == 1,
                "Fixture SQL injection was not executed exactly once.",
            )

        else:
            result = self._process.manifest_command(
                "cleanup",
                check=False,
            )

        require(
            result.returncode == 1,
            "Expected recovery rejection.",
        )

        require(
            diagnostic in result.stderr,
            f"Expected recovery diagnostic: {diagnostic}",
        )

        expected = deepcopy(manifest)
        expected["state"] = "CLEANUP_FAILED"

        require(
            self.manifest("status") == expected,
            "Rejected recovery modified manifest registry.",
        )

        require(
            self.fixture_probe(run_id) == observed,
            "Rejected recovery modified fixture identities.",
        )

        self.remember_fixture_probe(observed)
        self.assert_protected_user()

        print(
            "PASS: Recovery failed closed",
            flush=True,
        )

    def finalize(self) -> None:
        super().finalize()

        for run_id in sorted(self._run_ids):
            self.assert_empty_fixture_probe(
                self.fixture_probe(run_id)
            )

        self.assert_baseline()

        print(
            "PASS: Fixture Recovery V2 final acceptance",
            flush=True,
        )