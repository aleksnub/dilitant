from __future__ import annotations

import copy

from typing import Any

from process import RecoveryProcess, invoke, require


class RecoverySuiteBase(RecoveryProcess):
    def __init__(self) -> None:
        super().__init__()

        current = self.manifest("status")

        require(
            current["state"] == "CLEANED",
            "Cleaned manifest required before recovery tests.",
        )

        audit = self.worker("audit")

        self.baseline = audit["baseline"]

        print(
            "PASS: Initial independent database audit",
            flush=True,
        )

    def probe(self, run_id: str) -> dict[str, Any]:
        return self.worker("probe", run_id)

    @staticmethod
    def pending(
        checkpoint: dict[str, Any],
        kind: str,
        count: int,
    ) -> dict[str, Any]:
        data = checkpoint["manifest"]

        require(
            data["state"] == "CREATING",
            "Expected CREATING manifest.",
        )

        require(
            data["snapshot"] is None,
            "Unexpected snapshot during creation.",
        )

        objects = data["objects"]

        require(
            len(objects) == count,
            "Unexpected registry size.",
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
            "Expected one pending intent.",
        )

        return pending[0]

    @staticmethod
    def recovered_registry(
        checkpoint: dict[str, Any],
        object_id: int,
        taxonomy_id: int = 0,
    ) -> list[dict[str, Any]]:
        require(
            type(object_id) is int and object_id > 0,
            "Invalid recovered object ID.",
        )

        require(
            type(taxonomy_id) is int and taxonomy_id >= 0,
            "Invalid recovered taxonomy ID.",
        )

        objects = copy.deepcopy(
            checkpoint["manifest"]["objects"]
        )

        require(
            bool(objects) and objects[-1]["id"] == 0,
            "Expected pending registry entry.",
        )

        objects[-1]["id"] = object_id
        objects[-1]["taxonomy_id"] = taxonomy_id

        return objects

    @staticmethod
    def assert_empty_probe(
        probe: dict[str, Any],
    ) -> None:
        require(
            probe == {
                "post_ids": [],
                "term_ids": [],
                "taxonomy_ids": [],
                "partial_term_ids": [],
            },
            f"Residual fixture identities: {probe}",
        )

    def finish(
        self,
        checkpoint: dict[str, Any],
        expected_objects: list[dict[str, Any]],
        observed: dict[str, Any],
    ) -> None:
        expected = copy.deepcopy(
            checkpoint["manifest"]
        )

        expected["state"] = "CLEANED"
        expected["objects"] = expected_objects

        cleaned = self.manifest("cleanup")

        require(
            cleaned == expected,
            "Recovered manifest differs from expected registry.",
        )

        require(
            self.manifest("status") == cleaned,
            "CLEANED manifest was not persisted.",
        )

        require(
            self.manifest("cleanup") == cleaned,
            "Recovered cleanup is not idempotent.",
        )

        self.assert_empty_probe(
            self.probe(cleaned["run_id"])
        )

        post_ids = set(observed["post_ids"])

        post_ids.update(
            item["id"]
            for item in expected_objects
            if item["kind"] == "post"
        )

        audit = self.worker(
            "audit",
            *sorted(post_ids),
        )

        require(
            audit["baseline"] == self.baseline,
            "Existing database identities changed.",
        )

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
        run_id = checkpoint["manifest"]["run_id"]

        if sql_failure:
            self.assert_container()

            result = invoke(
                self.worker_args(
                    "sql-failure",
                    run_id,
                ),
                check=False,
            )

            require(
                "HF09 SQL failure injected" in result.stderr,
                "SQL failure injection was not executed.",
            )
        else:
            result = self.manifest_command(
                "cleanup",
                check=False,
            )

        require(
            result.returncode == 1,
            "Expected cleanup rejection.",
        )

        require(
            diagnostic in result.stderr,
            f"Unexpected rejection: {result.stderr}",
        )

        expected = copy.deepcopy(
            checkpoint["manifest"]
        )

        expected["state"] = "CLEANUP_FAILED"

        require(
            self.manifest("status") == expected,
            "Rejected recovery modified the registry.",
        )

        require(
            self.probe(run_id) == observed,
            "Rejected recovery modified fixture identities.",
        )

        print(
            "PASS: Recovery failed closed",
            flush=True,
        )

    def run_cases(
        self,
        scenarios: list[int],
        names: dict[int, str],
    ) -> None:
        passed = 0

        for number in scenarios:
            name = f"HF-M09.{number} {names[number]}"

            print(f"\n=== {name} ===", flush=True)

            require(
                self.manifest("status")["state"] == "CLEANED",
                "Previous recovery scenario is unfinished.",
            )

            scenario = getattr(
                self,
                f"scenario_{number}",
            )

            scenario()

            passed += 1

            print(f"PASS: {name}", flush=True)

        print("\n=== RESULT ===")
        print(f"Total: {len(scenarios)}")
        print(f"Passed: {passed}")
        print("Failed: 0")

        if scenarios == list(names):
            print("PASS: HF-M09")
        else:
            print("PASS: Selected HF-M09 scenarios")