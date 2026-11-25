from __future__ import annotations

import argparse
import json
import subprocess
import sys

from pathlib import Path
from typing import Any


BASE = Path(__file__).resolve().parent
PHP_ENTRY = "/tests/integration/editorial/article-http/fixtures.php"

COMMANDS = (
    "create",
    "create-v2",
    "status",
    "verify",
    "cleanup",
)

STATES = {
    "CREATING",
    "READY",
    "TESTING",
    "FAILED",
    "CLEANING",
    "CLEANED",
    "CLEANUP_FAILED",
}


class ManifestError(RuntimeError):
    pass


def validate_manifest(
    data: Any,
    command: str,
) -> dict[str, Any]:
    if not isinstance(data, dict):
        raise ManifestError("Invalid manifest response.")

    version = data.get("schema_version")

    if (
        type(version) is not int
        or version not in (1, 2)
        or data.get("database_name") != "dilitant_land_test"
        or data.get("table_prefix") != "wp_test_"
    ):
        raise ManifestError("Invalid manifest identity.")

    state = data.get("state")

    if not isinstance(state, str) or state not in STATES:
        raise ManifestError("Invalid manifest state.")

    expected_states = {
        "create": {"READY"},
        "create-v2": {"READY"},
        "verify": {"READY", "TESTING"},
        "cleanup": {"CLEANED"},
    }

    if (
        command in expected_states
        and state not in expected_states[command]
    ):
        raise ManifestError("Unexpected manifest state.")

    expected_versions = {
        "create": 1,
        "create-v2": 2,
    }

    if (
        command in expected_versions
        and version != expected_versions[command]
    ):
        raise ManifestError("Unexpected manifest schema version.")

    objects = data.get("objects")

    if not isinstance(objects, list):
        raise ManifestError("Invalid manifest registry.")

    limits = {
        "post": 10,
        "category": 4,
    }

    if version == 2:
        limits["user"] = 4

    counts = dict.fromkeys(limits, 0)

    pending = 0
    seen_ids = set()

    for index, obj in enumerate(objects):
        if not isinstance(obj, dict):
            raise ManifestError("Invalid manifest object.")

        kind = obj.get("kind")
        object_id = obj.get("id")
        taxonomy_id = obj.get("taxonomy_id")

        if (
            not isinstance(kind, str)
            or kind not in limits
            or type(object_id) is not int
            or object_id < 0
            or type(taxonomy_id) is not int
            or taxonomy_id < 0
            or not isinstance(obj.get("identity"), dict)
        ):
            raise ManifestError("Invalid object identifiers.")

        if kind == "category":
            if (object_id == 0) != (taxonomy_id == 0):
                raise ManifestError("Invalid category identifiers.")
        elif taxonomy_id != 0:
            raise ManifestError("Unexpected taxonomy identifier.")

        counts[kind] += 1

        if object_id == 0:
            pending += 1

            if index != len(objects) - 1:
                raise ManifestError("Invalid pending intent position.")
        else:
            key = (kind, object_id)

            if key in seen_ids:
                raise ManifestError("Duplicate manifest identifier.")

            seen_ids.add(key)

    if pending > 1:
        raise ManifestError("Multiple pending intents.")

    for kind, limit in limits.items():
        if counts[kind] > limit:
            raise ManifestError(
                f"Excessive manifest object count: {kind}"
            )

    if state in {"READY", "TESTING", "CLEANED"} and pending:
        raise ManifestError("Unexpected unresolved manifest object.")

    if state in {"READY", "TESTING"}:
        if counts != limits:
            raise ManifestError("Incomplete manifest registry.")

        snapshot = data.get("snapshot")

        if not isinstance(snapshot, dict):
            raise ManifestError("Manifest snapshot unavailable.")

        if version == 2:
            if (
                snapshot.get("users_created") is not True
                or not isinstance(snapshot.get("user_ids"), list)
                or len(snapshot["user_ids"]) != 4
            ):
                raise ManifestError("Invalid user snapshot.")

    return data


def execute(command: str) -> dict[str, Any]:
    if command not in COMMANDS:
        raise ManifestError(f"Unsupported command: {command}")

    result = subprocess.run(
        [
            sys.executable,
            "-B",
            str(BASE / "environment.py"),
            "cli",
            PHP_ENTRY,
            command,
        ],
        capture_output=True,
        text=True,
        timeout=120,
        check=False,
    )

    if result.returncode != 0:
        raise ManifestError(
            result.stderr.strip()
            or f"Fixture operation failed: {result.returncode}"
        )

    try:
        data = json.loads(result.stdout)
    except json.JSONDecodeError as error:
        raise ManifestError("Invalid manifest JSON.") from error

    return validate_manifest(data, command)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("command", choices=COMMANDS)
    args = parser.parse_args()

    try:
        data = execute(args.command)

        print(json.dumps(
            data,
            ensure_ascii=False,
            indent=2,
        ))

        return 0

    except (
        ManifestError,
        OSError,
        subprocess.TimeoutExpired,
    ) as error:
        print(f"FAIL: {error}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())