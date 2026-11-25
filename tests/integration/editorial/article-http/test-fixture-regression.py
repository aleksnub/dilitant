from __future__ import annotations

import json
import os
import re
import subprocess
import sys

from pathlib import Path
from typing import Any, Callable


BASE = Path(__file__).resolve().parent
ROOT = BASE.parents[3]

sys.path.insert(0, str(BASE / "recovery-tests"))

from process import RecoveryProcess, RecoveryTestError, require


ENVIRONMENT = BASE / "environment.py"
MANIFEST = BASE / "manifest.py"
RECOVERY = BASE / "test-fixture-recovery.py"

EDITORIAL = "/tests/integration/editorial"
HTTP = f"{EDITORIAL}/article-http"

PRODUCTION_CONTAINER = "dilitant_land_wp"
NETWORK = "dilitant_land_dilitant_land_network"

TIMEOUT = 180
RECOVERY_TIMEOUT = 1800

REQUIRED_ENVIRONMENT = (
    "DILITANT_TEST_MODE",
    "DILITANT_TEST_DB_NAME",
    "DILITANT_TEST_DB_USER",
    "DILITANT_TEST_DB_HOST",
    "DILITANT_TEST_DB_PASSWORD",
)


class RegressionError(RuntimeError):
    pass


def execute(
    args: list[str],
    *,
    timeout: int = TIMEOUT,
) -> str:
    result = subprocess.run(
        args,
        capture_output=True,
        text=True,
        encoding="utf-8",
        errors="replace",
        timeout=timeout,
        check=False,
    )

    if result.returncode != 0:
        diagnostic = "\n".join(
            part.strip()
            for part in (result.stdout, result.stderr)
            if part.strip()
        )

        raise RegressionError(
            f"Command failed: exit {result.returncode}\n"
            f"{diagnostic[-6000:]}"
        )

    return result.stdout


def python_command(
    script: Path,
    *args: str,
    timeout: int = TIMEOUT,
) -> str:
    return execute(
        [
            sys.executable,
            "-B",
            str(script),
            *args,
        ],
        timeout=timeout,
    )


def report(output: str) -> None:
    if output.strip():
        print(output.strip(), flush=True)


def require_output(
    output: str,
    expected: str,
) -> None:
    require(
        expected in output,
        f"Expected test result missing: {expected}",
    )


class FixtureRegression:
    def __init__(self) -> None:
        self.passed = 0
        self.active_case = None
        self.post_ids: set[int] = set()

        self.verify_environment()

        self.process = RecoveryProcess()

        self.initial_manifest = self.process.manifest("status")

        require(
            self.initial_manifest["state"] == "CLEANED",
            "Previous fixture run is unfinished.",
        )

        self.register_post_ids(self.initial_manifest)

        self.baseline = self.audit()["baseline"]

        self.production_image = self.resolve_production_image()

        print(
            "PASS: Regression preflight",
            flush=True,
        )

    @staticmethod
    def verify_environment() -> None:
        for name in REQUIRED_ENVIRONMENT:
            require(
                bool(os.environ.get(name)),
                f"Required environment variable missing: {name}",
            )

        expected = {
            "DILITANT_TEST_MODE": "1",
            "DILITANT_TEST_DB_NAME": "dilitant_land_test",
            "DILITANT_TEST_DB_USER": "dilitant_test",
            "DILITANT_TEST_DB_HOST": "dilitant_land_db:3306",
        }

        for name, value in expected.items():
            require(
                os.environ.get(name) == value,
                f"Unexpected test environment value: {name}",
            )

        required_paths = (
            ROOT / "src/wordpress",
            ROOT / "tests",
            ENVIRONMENT,
            MANIFEST,
            RECOVERY,
        )

        for path in required_paths:
            require(
                path.exists(),
                f"Required path unavailable: {path}",
            )

    @staticmethod
    def resolve_production_image() -> str:
        image = execute([
            "docker",
            "inspect",
            "--format",
            "{{.Image}}",
            PRODUCTION_CONTAINER,
        ]).strip()

        require(
            re.fullmatch(
                r"sha256:[0-9a-f]{64}",
                image,
            ) is not None,
            "Invalid production image identity.",
        )

        return image

    def environment(self, command: str) -> None:
        report(
            python_command(
                ENVIRONMENT,
                command,
            )
        )

    def register_post_ids(
        self,
        manifest: dict[str, Any],
    ) -> None:
        for item in manifest["objects"]:
            if item["kind"] == "post" and item["id"] > 0:
                self.post_ids.add(item["id"])

    def audit(self) -> dict[str, Any]:
        result = self.process.worker(
            "audit",
            *sorted(self.post_ids),
        )

        checks = result.get("checks")
        baseline = result.get("baseline")

        require(
            isinstance(checks, dict)
            and bool(checks)
            and all(
                type(value) is int and value == 0
                for value in checks.values()
            ),
            "Independent SQL audit failed.",
        )

        require(
            isinstance(baseline, dict)
            and bool(baseline),
            "Database baseline unavailable.",
        )

        return result

    def assert_baseline(self) -> None:
        result = self.audit()

        require(
            result["baseline"] == self.baseline,
            "Existing database identities changed.",
        )

    def assert_manifest(
        self,
        expected: dict[str, Any],
    ) -> None:
        require(
            self.process.manifest("status") == expected,
            "Persistent manifest changed unexpectedly.",
        )

    def cli_command(self, filename: str) -> str:
        args = [
            "docker",
            "run",
            "--rm",
            "--network",
            NETWORK,
            "--mount",
            (
                "type=bind,"
                f"source={ROOT / 'src/wordpress'},"
                "target=/var/www/html,"
                "readonly"
            ),
            "--mount",
            (
                "type=bind,"
                f"source={ROOT / 'tests'},"
                "target=/tests,"
                "readonly"
            ),
            "--tmpfs",
            "/var/www/dilitant-private",
        ]

        for name in REQUIRED_ENVIRONMENT:
            args.extend(["-e", name])

        args.extend([
            "--entrypoint",
            "php",
            self.production_image,
            f"{EDITORIAL}/{filename}",
        ])

        return execute(args)

    def cli_test(self, filename: str) -> None:
        output = self.cli_command(filename)

        report(output)

        require(
            bool(output.strip()),
            f"No test output: {filename}",
        )

        require(
            re.search(
                r"^\s*(?:FAIL|ERROR)\s*:",
                output,
                re.MULTILINE,
            ) is None,
            f"Failure reported by {filename}",
        )

        failures = re.findall(
            r"^\s*Failed:\s*(\d+)\s*$",
            output,
            re.MULTILINE | re.IGNORECASE,
        )

        require(
            all(int(value) == 0 for value in failures),
            f"Failed assertions in {filename}",
        )

    def case_1(self) -> None:
        self.cli_test("bootstrap.php")

    def case_2(self) -> None:
        self.cli_test("test-article-identity.php")

    def case_3(self) -> None:
        self.cli_test("test-article-query.php")

    def case_4(self) -> None:
        self.cli_test("test-article-catalog.php")

    def case_5(self) -> None:
        self.cli_test("test-article-visibility.php")

    def case_6(self) -> None:
        self.environment("start")

        self.process = RecoveryProcess()

        output = python_command(
            ENVIRONMENT,
            "cli",
            "-r",
            (
                f'require "{HTTP}/bootstrap.php";'
                "dilitant_http_require_bootstrap();"
                "echo \"PASS: HTTP Bootstrap\\n\";"
            ),
        )

        report(output)

        require_output(
            output,
            "PASS: HTTP Bootstrap",
        )

        self.assert_manifest(self.initial_manifest)
        self.assert_baseline()

    def case_7(self) -> None:
        ready = self.process.manifest("create")

        require(
            ready["state"] == "READY",
            "Manifest creation did not produce READY.",
        )

        require(
            ready["run_id"]
            != self.initial_manifest["run_id"],
            "Fixture run ID was reused.",
        )

        objects = ready["objects"]

        posts = [
            item for item in objects
            if item["kind"] == "post"
        ]

        categories = [
            item for item in objects
            if item["kind"] == "category"
        ]

        require(
            len(objects) == 14
            and len(posts) == 10
            and len(categories) == 4
            and all(item["id"] > 0 for item in objects),
            "Incomplete fixture registry.",
        )

        self.register_post_ids(ready)

        require(
            self.process.manifest("status") == ready,
            "READY manifest was not persisted.",
        )

        require(
            self.process.manifest("verify") == ready,
            "Manifest verification changed the registry.",
        )

        duplicate = self.process.manifest_command(
            "create",
            check=False,
        )

        require(
            duplicate.returncode == 1
            and "Unfinished fixture run exists."
                in duplicate.stderr,
            "Duplicate fixture creation was not rejected.",
        )

        self.assert_manifest(ready)

        expected = dict(ready)
        expected["state"] = "CLEANED"

        cleaned = self.process.manifest("cleanup")

        require(
            cleaned == expected,
            "Unexpected cleaned manifest.",
        )

        require(
            self.process.manifest("cleanup") == cleaned,
            "Cleanup is not idempotent.",
        )

        self.assert_manifest(cleaned)
        self.assert_baseline()

        print("PASS: Manifest creation")
        print("PASS: Manifest verification")
        print("PASS: Duplicate creation rejected")
        print("PASS: Manifest cleanup")
        print("PASS: Cleanup idempotence")

    def case_8(self) -> None:
        before = self.process.manifest("status")

        require(
            before["state"] == "CLEANED",
            "Ownership tests require CLEANED manifest.",
        )

        output = python_command(
            ENVIRONMENT,
            "cli",
            f"{HTTP}/test-fixture-ownership.php",
        )

        report(output)

        for number in range(1, 6):
            require_output(
                output,
                f"PASS: HF-M08.{number} ",
            )

        require_output(output, "Total: 5")
        require_output(output, "Passed: 5")
        require_output(output, "Failed: 0")
        require_output(output, "PASS: HF-M08")
        require_output(
            output,
            "PASS: Persistent manifest unchanged",
        )

        self.assert_manifest(before)
        self.assert_baseline()

    def case_9(self) -> None:
        output = python_command(
            RECOVERY,
            timeout=RECOVERY_TIMEOUT,
        )

        report(output)

        for number in range(1, 11):
            require_output(
                output,
                f"PASS: HF-M09.{number} ",
            )

        require_output(output, "Total: 10")
        require_output(output, "Passed: 10")
        require_output(output, "Failed: 0")
        require_output(output, "PASS: HF-M09")

        require(
            output.count(
                "PASS: Independent cleanup audit"
            ) == 10,
            "Incomplete recovery audit coverage.",
        )

        require_output(
            output,
            "PASS: HTTP test container recreated",
        )

        self.process = RecoveryProcess()

        current = self.process.manifest("status")

        require(
            current["state"] == "CLEANED",
            "Recovery suite left unfinished manifest.",
        )

        self.register_post_ids(current)
        self.assert_baseline()

    def case_10(self) -> None:
        current = self.process.manifest("status")

        require(
            current["state"] == "CLEANED",
            "Final manifest is not CLEANED.",
        )

        require(
            all(
                item["id"] > 0
                for item in current["objects"]
            ),
            "Unresolved fixture intent remains.",
        )

        self.register_post_ids(current)

        audit = self.audit()

        require(
            audit["baseline"] == self.baseline,
            "Final database baseline mismatch.",
        )

        for name, count in audit["checks"].items():
            print(f"PASS: {name} = {count}")

        for name, data in audit["baseline"].items():
            print(
                f"PASS: {name} identities unchanged "
                f"({data['count']} records)"
            )

        print("PASS: Final manifest CLEANED")
        print("PASS: Independent SQL audit")

    def run_case(
        self,
        number: int,
        title: str,
        operation: Callable[[], None],
    ) -> None:
        self.active_case = f"HF-M10.{number}"

        print(
            f"\n=== {self.active_case} {title} ===",
            flush=True,
        )

        operation()

        self.passed += 1

        print(
            f"PASS: {self.active_case} {title}",
            flush=True,
        )

        self.active_case = None

    def run(self) -> None:
        cases = [
            (1, "CLI Bootstrap", self.case_1),
            (2, "Article Identity", self.case_2),
            (3, "Article Query", self.case_3),
            (4, "Article Catalog", self.case_4),
            (5, "Article Visibility", self.case_5),
            (6, "HTTP Bootstrap", self.case_6),
            (7, "Manifest Lifecycle", self.case_7),
            (8, "Fixture Ownership", self.case_8),
            (9, "Recovery", self.case_9),
            (10, "Independent SQL Audit", self.case_10),
        ]

        print(
            "\n=== CLI REGRESSION ===",
            flush=True,
        )

        self.environment("stop")

        for number, title, operation in cases:
            self.run_case(
                number,
                title,
                operation,
            )

        print("\n=== RESULT ===")
        print("Total: 10")
        print(f"Passed: {self.passed}")
        print("Failed: 0")
        print("Skipped: 0")
        print("PASS: HF-M10")

    def report_failure(self) -> None:
        failed = int(self.active_case is not None)
        skipped = 10 - self.passed - failed

        print("\n=== RESULT ===")
        print("Total: 10")
        print(f"Passed: {self.passed}")
        print(f"Failed: {failed}")
        print(f"Skipped: {skipped}")

        if self.active_case is not None:
            print(f"Failed case: {self.active_case}")


def main() -> int:
    suite = None

    try:
        suite = FixtureRegression()
        suite.run()

        return 0

    except (
        RegressionError,
        RecoveryTestError,
        OSError,
        KeyError,
        TypeError,
        ValueError,
        subprocess.TimeoutExpired,
    ) as error:
        print(
            f"FAIL: {error}",
            file=sys.stderr,
        )

        if suite is not None:
            suite.report_failure()

        print(
            "Regression stopped. "
            "Inspect environment and persistent manifest.",
            file=sys.stderr,
        )

        return 1

    except KeyboardInterrupt:
        print(
            "\nRegression interrupted.",
            file=sys.stderr,
        )

        return 130


if __name__ == "__main__":
    raise SystemExit(main())