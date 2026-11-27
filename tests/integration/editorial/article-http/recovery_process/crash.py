"""Аварийное завершение и проверка долговечности."""

from __future__ import annotations

import subprocess
import sys
import tempfile

from typing import Any

from .commands import (
    RecoveryTestError,
    redact_diagnostic,
    require,
)

from .checkpoint import (
    read_checkpoint,
    validate_checkpoint,
)

from .container import protected_operation

from .validation import validate_manifest


class CrashMixin:
    """Исполнение аварийных сценариев."""

    def terminate_failed_worker(
        self,
        process: subprocess.Popen[bytes],
        pid: int | None,
        action: str,
    ) -> None:
        if process.poll() is not None:
            return

        if pid is not None:
            try:
                self.kill_worker(
                    pid,
                    action,
                )

            except Exception as error:
                diagnostic = redact_diagnostic(
                    str(error),
                    env=self.config.env,
                )

                print(
                    f"Worker termination: {diagnostic}",
                    file=sys.stderr,
                )

        try:
            process.communicate(
                timeout=(
                    self.config.worker_timeout
                    + self.config.worker_kill_timeout
                    + 5
                ),
            )

        except subprocess.TimeoutExpired:
            process.kill()

            process.communicate(
                timeout=5,
            )

            raise RecoveryTestError(
                "Remote worker termination unconfirmed."
            ) from None

    @protected_operation
    def crash(
        self,
        action: str,
        stage: str,
        *,
        container: bool = False,
    ) -> dict[str, Any]:
        self.assert_container()

        if container:
            self.verify_restart_readiness()

        args = self.worker_args(action)

        args[5:5] = [
            "timeout",
            "-k",
            str(self.config.worker_kill_timeout),
            str(self.config.worker_timeout),
        ]

        checkpoint: dict[str, Any] | None = None

        pid: int | None = None

        with tempfile.TemporaryFile() as stderr:
            try:
                process = subprocess.Popen(
                    args,
                    stdout=subprocess.PIPE,
                    stderr=stderr,
                    bufsize=0,
                    env=dict(self.config.env),
                )

            except OSError:
                raise RecoveryTestError(
                    "Unable to start Recovery crash Worker."
                ) from None

            try:
                checkpoint = read_checkpoint(
                    process,
                    timeout=self.config.checkpoint_timeout,
                )

                candidate = checkpoint.get(
                    "pid"
                )

                if (
                    type(candidate) is int
                    and candidate > 1
                    and checkpoint.get("event")
                    == "CRASH_READY"
                ):
                    pid = candidate

                validate_checkpoint(
                    checkpoint,
                    stage,
                    validate_manifest,
                )

                require(
                    pid is not None
                    and process.poll() is None,
                    "Worker exited before termination.",
                )

                if container:
                    self.kill_container()

                else:
                    self.kill_worker(
                        pid,
                        action,
                    )

                code = process.wait(
                    timeout=self.config.termination_timeout,
                )

                if container:
                    require(
                        code != 0,
                        "Container crash worker exited successfully.",
                    )

                else:
                    require(
                        code == 137,
                        f"Expected SIGKILL exit 137, got {code}.",
                    )

            except BaseException as error:
                termination_error = None

                try:
                    self.terminate_failed_worker(
                        process,
                        pid,
                        action,
                    )

                except Exception as secondary:
                    termination_error = (
                        redact_diagnostic(
                            str(secondary),
                            env=self.config.env,
                        )
                    )

                stderr.seek(0)

                diagnostic = stderr.read().decode(
                    "utf-8",
                    errors="replace",
                ).strip()

                diagnostic = redact_diagnostic(
                    diagnostic,
                    env=self.config.env,
                )

                details = [
                    redact_diagnostic(
                        str(error),
                        env=self.config.env,
                    )
                ]

                if diagnostic:
                    details.append(
                        f"Worker: {diagnostic[-4000:]}"
                    )

                if termination_error:
                    details.append(
                        termination_error
                    )

                if isinstance(
                    error,
                    KeyboardInterrupt,
                ):
                    for detail in details:
                        if detail:
                            print(
                                detail,
                                file=sys.stderr,
                            )

                    raise

                raise RecoveryTestError(
                    "\n".join(details)
                ) from None

            finally:
                if process.stdout is not None:
                    process.stdout.close()

        require(
            checkpoint is not None,
            "Missing crash checkpoint.",
        )

        if container:
            self.recreate_container()

        persisted = self.manifest(
            "status"
        )

        require(
            persisted == checkpoint["manifest"],
            "Persistent manifest changed after crash.",
        )

        return checkpoint