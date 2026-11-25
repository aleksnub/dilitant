from __future__ import annotations

import json
import os
import re
import selectors
import subprocess
import sys
import tempfile
import time

from pathlib import Path
from typing import Any


BASE = Path(__file__).resolve().parent.parent

ENVIRONMENT = BASE / "environment.py"
MANIFEST = BASE / "manifest.py"

WORKER = (
    "/tests/integration/editorial/"
    "article-http/recovery-tests/worker.php"
)

CONTAINER_NAME = "dilitant_land_http_test"

COMMAND_TIMEOUT = 150
CHECKPOINT_TIMEOUT = 45


class RecoveryTestError(RuntimeError):
    pass


def require(condition: bool, message: str) -> None:
    if not condition:
        raise RecoveryTestError(message)


def invoke(
    args: list[str],
    *,
    check: bool = True,
    timeout: int = COMMAND_TIMEOUT,
) -> subprocess.CompletedProcess[str]:
    result = subprocess.run(
        args,
        capture_output=True,
        text=True,
        encoding="utf-8",
        errors="replace",
        timeout=timeout,
        check=False,
    )

    if check and result.returncode != 0:
        detail = (
            result.stderr.strip()
            or result.stdout.strip()
            or "No diagnostic output."
        )

        raise RecoveryTestError(
            f"Command failed ({result.returncode}): "
            f"{args[0]}\n{detail[-4000:]}"
        )

    return result


def parse_json(raw: str) -> dict[str, Any]:
    try:
        data = json.loads(raw)
    except json.JSONDecodeError as error:
        raise RecoveryTestError(
            "Invalid JSON response."
        ) from error

    require(
        isinstance(data, dict),
        "Expected JSON object.",
    )

    return data


def validate_manifest(data: dict[str, Any]) -> None:
    require(
        type(data.get("schema_version")) is int
        and data["schema_version"] == 1
        and data.get("database_name") == "dilitant_land_test"
        and data.get("table_prefix") == "wp_test_",
        "Invalid manifest identity.",
    )

    run_id = data.get("run_id")

    require(
        isinstance(run_id, str)
        and re.fullmatch(r"[0-9a-f]{12}", run_id) is not None,
        "Invalid manifest run ID.",
    )

    require(
        isinstance(data.get("objects"), list),
        "Invalid manifest registry.",
    )


def read_checkpoint(
    process: subprocess.Popen[bytes],
) -> dict[str, Any]:
    require(
        process.stdout is not None,
        "Worker stdout unavailable.",
    )

    deadline = time.monotonic() + CHECKPOINT_TIMEOUT
    received = bytearray()

    with selectors.DefaultSelector() as selector:
        selector.register(
            process.stdout,
            selectors.EVENT_READ,
        )

        while True:
            remaining = deadline - time.monotonic()

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

            line, remainder = received.split(b"\n", 1)

            require(
                not remainder.strip(),
                "Unexpected worker output.",
            )

            return parse_json(line.decode("utf-8"))


def validate_checkpoint(
    data: dict[str, Any],
    stage: str,
) -> None:
    require(
        data.get("event") == "CRASH_READY"
        and data.get("stage") == stage,
        "Unexpected crash checkpoint.",
    )

    pid = data.get("pid")

    require(
        type(pid) is int and pid > 1,
        "Invalid worker PID.",
    )

    if data.get("extra") == []:
        data["extra"] = {}

    require(
        isinstance(data.get("manifest"), dict)
        and isinstance(data.get("extra"), dict),
        "Incomplete crash checkpoint.",
    )

    validate_manifest(data["manifest"])


class RecoveryProcess:
    def __init__(self) -> None:
        self.environment("check")
        self.container_id = self.resolve_container()

    def environment(self, command: str) -> str:
        return invoke([
            sys.executable,
            "-B",
            str(ENVIRONMENT),
            command,
        ]).stdout

    def resolve_container(self) -> str:
        result = invoke([
            "docker",
            "inspect",
            "--format",
            "{{.Id}} {{.State.Running}}",
            CONTAINER_NAME,
        ])

        fields = result.stdout.strip().split()

        require(
            len(fields) == 2
            and re.fullmatch(
                r"[0-9a-f]{64}",
                fields[0],
            ) is not None
            and fields[1] == "true",
            "HTTP test container is not running.",
        )

        return fields[0]

    def assert_container(self) -> None:
        require(
            self.resolve_container() == self.container_id,
            "HTTP test container identity changed.",
        )

    def manifest_command(
        self,
        command: str,
        *,
        check: bool = True,
    ) -> subprocess.CompletedProcess[str]:
        self.assert_container()

        return invoke(
            [
                sys.executable,
                "-B",
                str(MANIFEST),
                command,
            ],
            check=check,
        )

    def manifest(self, command: str) -> dict[str, Any]:
        result = self.manifest_command(command)
        data = parse_json(result.stdout)

        validate_manifest(data)

        return data

    def worker_args(
        self,
        action: str,
        *args: Any,
    ) -> list[str]:
        return [
            "docker",
            "exec",
            "--user",
            "33:33",
            self.container_id,
            "php",
            WORKER,
            action,
            *(str(arg) for arg in args),
        ]

    def worker(
        self,
        action: str,
        *args: Any,
    ) -> dict[str, Any]:
        self.assert_container()

        result = invoke(
            self.worker_args(action, *args)
        )

        return parse_json(result.stdout)

    def kill_worker(
        self,
        pid: int,
        action: str,
    ) -> None:
        require(
            type(pid) is int and pid > 1,
            "Invalid crash worker PID.",
        )

        self.assert_container()

        invoke([
            "docker",
            "exec",
            "--user",
            "33:33",
            self.container_id,
            "sh",
            "-ec",
            (
                'test -r "/proc/$1/cmdline"\n'
                'cmd=$(tr "\\000" "\\n" '
                '< "/proc/$1/cmdline")\n'
                'expected=$(printf "%s\\n" php "$2" "$3")\n'
                'test "$cmd" = "$expected"\n'
                'kill -KILL "$1"'
            ),
            "sh",
            str(pid),
            WORKER,
            action,
        ])

    def kill_container(self) -> None:
        self.assert_container()

        invoke([
            "docker",
            "kill",
            "--signal",
            "KILL",
            self.container_id,
        ])

        state = invoke([
            "docker",
            "inspect",
            "--format",
            "{{.State.Running}} {{.State.ExitCode}}",
            self.container_id,
        ]).stdout.strip()

        require(
            state == "false 137",
            f"Unexpected container crash state: {state}",
        )

    def recreate_container(self) -> None:
        previous = self.container_id

        self.environment("stop")
        self.environment("start")
        self.environment("check")

        self.container_id = self.resolve_container()

        require(
            self.container_id != previous,
            "Container was not recreated.",
        )

        print(
            "PASS: HTTP test container recreated",
            flush=True,
        )

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
                self.kill_worker(pid, action)
            except Exception as error:
                print(
                    f"Worker termination: {error}",
                    file=sys.stderr,
                )

        try:
            process.communicate(timeout=100)
        except subprocess.TimeoutExpired as error:
            process.kill()
            process.communicate(timeout=5)

            raise RecoveryTestError(
                "Remote worker termination unconfirmed."
            ) from error

    def crash(
        self,
        action: str,
        stage: str,
        *,
        container: bool = False,
    ) -> dict[str, Any]:
        self.assert_container()

        args = self.worker_args(action)

        args[5:5] = [
            "timeout",
            "-k",
            "5",
            "90",
        ]

        checkpoint: dict[str, Any] | None = None
        pid: int | None = None

        with tempfile.TemporaryFile() as stderr:
            process = subprocess.Popen(
                args,
                stdout=subprocess.PIPE,
                stderr=stderr,
                bufsize=0,
            )

            try:
                checkpoint = read_checkpoint(process)

                candidate = checkpoint.get("pid")

                if (
                    type(candidate) is int
                    and candidate > 1
                    and checkpoint.get("event") == "CRASH_READY"
                ):
                    pid = candidate

                validate_checkpoint(
                    checkpoint,
                    stage,
                )

                require(
                    pid is not None
                    and process.poll() is None,
                    "Worker exited before termination.",
                )

                if container:
                    self.kill_container()
                else:
                    self.kill_worker(pid, action)

                code = process.wait(timeout=20)

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
                    termination_error = str(secondary)

                stderr.seek(0)

                diagnostic = stderr.read().decode(
                    "utf-8",
                    errors="replace",
                ).strip()

                details = [str(error)]

                if diagnostic:
                    details.append(
                        f"Worker: {diagnostic[-4000:]}"
                    )

                if termination_error:
                    details.append(termination_error)

                if isinstance(error, KeyboardInterrupt):
                    for detail in details:
                        if detail:
                            print(detail, file=sys.stderr)
                    raise

                raise RecoveryTestError(
                    "\n".join(details)
                ) from error

            finally:
                if process.stdout is not None:
                    process.stdout.close()

        require(
            checkpoint is not None,
            "Missing crash checkpoint.",
        )

        if container:
            self.recreate_container()

        persisted = self.manifest("status")

        require(
            persisted == checkpoint["manifest"],
            "Persistent manifest changed after crash.",
        )

        return checkpoint