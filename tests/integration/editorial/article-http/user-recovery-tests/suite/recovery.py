from __future__ import annotations

from typing import Any

from .config import (
    STATES,
    STAGES,
    CLEANUP_STAGES,
    SQL_INJECTION_MARKER,
    require,
)


class RecoveryMixin:
    def create_ready(self) -> dict[str, Any]:
        previous = self.manifest("status")

        require(
            previous["state"] == "CLEANED",
            "Cannot create over unfinished fixture run.",
        )

        self.assert_baseline()

        ready = self.manifest("create-v2")

        require(
            ready["state"] == "READY"
            and ready["run_id"] != previous["run_id"]
            and ready["run_id"] not in self._run_ids
            and len(ready["objects"]) == 18,
            "Unexpected new Manifest V2.",
        )

        self._remember_manifest(ready)

        require(
            self.manifest("status") == ready,
            "Ready manifest was not persisted.",
        )

        require(
            self.manifest("verify") == ready,
            "Ready fixture verification failed.",
        )

        return ready

    def crash(
        self,
        stage: str,
        *,
        container: bool = False,
    ) -> dict[str, Any]:
        require(
            stage in STAGES,
            "Unsupported User Recovery crash stage.",
        )

        require(
            container == (stage == "user-container"),
            "Unexpected container crash mode.",
        )

        previous = self.manifest("status")
        cleanup = stage in CLEANUP_STAGES

        require(
            previous["state"]
                == ("READY" if cleanup else "CLEANED"),
            "Invalid state before crash scenario.",
        )

        if not cleanup:
            self.assert_baseline()

        checkpoint = self._process.crash(
            stage,
            stage,
            container=container,
        )

        manifest = checkpoint["manifest"]

        require(
            manifest["state"]
                == ("CLEANING" if cleanup else "CREATING"),
            "Unexpected crash manifest state.",
        )

        if cleanup:
            require(
                manifest["run_id"] == previous["run_id"],
                "Cleanup crash run identity changed.",
            )
        else:
            require(
                manifest["run_id"] != previous["run_id"]
                and manifest["run_id"] not in self._run_ids,
                "Creation crash reused fixture run identity.",
            )

        self._remember_checkpoint(checkpoint, stage)

        require(
            self.manifest("status") == manifest,
            "Persistent crash manifest mismatch.",
        )

        self.assert_protected_user()

        return checkpoint

    def cleanup(
        self,
        run_id: str,
    ) -> dict[str, Any]:
        previous = self._require_run(run_id, STATES)

        cleaned = self.manifest("cleanup")

        require(
            cleaned["run_id"] == run_id
            and cleaned["state"] == "CLEANED",
            "Cleanup did not reach CLEANED.",
        )

        if previous["state"] == "CLEANED":
            require(
                cleaned == previous,
                "Idempotent cleanup modified manifest.",
            )

        self._remember_manifest(cleaned)

        require(
            self.manifest("status") == cleaned,
            "Cleaned manifest was not persisted.",
        )

        return cleaned

    def _verify_blocked(
        self,
        result: Any,
        run_id: str,
        diagnostic: str,
        *,
        sql_failure: bool = False,
    ) -> dict[str, Any]:
        require(
            result.returncode == 1,
            f"Expected exit code 1, got {result.returncode}.",
        )

        output = result.stdout + "\n" + result.stderr

        require(
            diagnostic in output,
            "Expected recovery diagnostic unavailable:\n"
            + output[-4000:],
        )

        if sql_failure:
            require(
                result.stderr.count(SQL_INJECTION_MARKER) == 1,
                "SQL fault injection was not executed exactly once.",
            )

        blocked = self._require_run(
            run_id,
            {"CLEANUP_FAILED"},
        )

        self.assert_protected_user()

        return blocked

    def expect_blocked(
        self,
        run_id: str,
        diagnostic: str,
    ) -> dict[str, Any]:
        self._require_run(
            run_id,
            {
                "CREATING",
                "CLEANING",
                "CLEANUP_FAILED",
            },
        )

        result = self._process.manifest_command(
            "cleanup",
            check=False,
        )

        return self._verify_blocked(
            result,
            run_id,
            diagnostic,
        )

    def expect_worker_blocked(
        self,
        action: str,
        run_id: str,
        diagnostic: str,
    ) -> dict[str, Any]:
        require(
            action == "user-sql-failure",
            "Unsupported negative worker action.",
        )

        self._require_run(
            run_id,
            {"CREATING"},
        )

        result = self._process.worker_command(
            action,
            run_id,
            check=False,
        )

        return self._verify_blocked(
            result,
            run_id,
            diagnostic,
            sql_failure=True,
        )