from __future__ import annotations

import argparse
import json
import subprocess
import sys

from pathlib import Path
from typing import Any


BASE = Path(__file__).resolve().parent
PHP_ENTRY = "/tests/integration/editorial/article-http/fixtures.php"

SCHEMA_VERSION = 2

COMMANDS = (
    "create-v2",
    "status",
    "verify",
    "cleanup",
)

REJECTED_COMMANDS = (
    "create",
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

LIMITS = {
    "user": 4,
    "category": 4,
    "post": 10,
}


class ManifestError(RuntimeError):
    pass


def validate_manifest(
    data: Any,
    command: str,
) -> dict[str, Any]:
    if command not in COMMANDS:
        raise ManifestError(
            f"Unsupported command: {command}"
        )

    if not isinstance(data, dict):
        raise ManifestError(
            "Invalid manifest response."
        )

    version = data.get("schema_version")

    if (
        type(version) is not int
        or version != SCHEMA_VERSION
        or data.get("database_name") != "dilitant_land_test"
        or data.get("table_prefix") != "wp_test_"
    ):
        raise ManifestError(
            "Invalid manifest identity."
        )

    state = data.get("state")

    if not isinstance(state, str) or state not in STATES:
        raise ManifestError(
            "Invalid manifest state."
        )

    expected_states = {
        "create-v2": {"READY"},
        "verify": {"READY", "TESTING"},
        "cleanup": {"CLEANED"},
    }

    if (
        command in expected_states
        and state not in expected_states[command]
    ):
        raise ManifestError(
            "Unexpected manifest state."
        )

    objects = data.get("objects")

    if (
        not isinstance(objects, list)
        or len(objects) > sum(LIMITS.values())
    ):
        raise ManifestError(
            "Invalid manifest registry."
        )

    counts = dict.fromkeys(LIMITS, 0)

    pending = 0
    seen_ids = set()
    seen_taxonomy_ids = set()

    for index, obj in enumerate(objects):
        if not isinstance(obj, dict):
            raise ManifestError(
                "Invalid manifest object."
            )

        if set(obj) != {
            "kind",
            "id",
            "taxonomy_id",
            "identity",
        }:
            raise ManifestError(
                "Invalid manifest object structure."
            )

        kind = obj.get("kind")
        object_id = obj.get("id")
        taxonomy_id = obj.get("taxonomy_id")

        if (
            not isinstance(kind, str)
            or kind not in LIMITS
            or type(object_id) is not int
            or object_id < 0
            or type(taxonomy_id) is not int
            or taxonomy_id < 0
            or not isinstance(obj.get("identity"), dict)
        ):
            raise ManifestError(
                "Invalid object identifiers."
            )

        if kind == "category":
            if (object_id == 0) != (taxonomy_id == 0):
                raise ManifestError(
                    "Invalid category identifiers."
                )

            if taxonomy_id != 0:
                if taxonomy_id in seen_taxonomy_ids:
                    raise ManifestError(
                        "Duplicate taxonomy identifier."
                    )

                seen_taxonomy_ids.add(taxonomy_id)

        elif taxonomy_id != 0:
            raise ManifestError(
                "Unexpected taxonomy identifier."
            )

        counts[kind] += 1

        if object_id == 0:
            pending += 1

            if index != len(objects) - 1:
                raise ManifestError(
                    "Invalid pending intent position."
                )

        else:
            key = (kind, object_id)

            if key in seen_ids:
                raise ManifestError(
                    "Duplicate manifest identifier."
                )

            seen_ids.add(key)

    if pending > 1:
        raise ManifestError(
            "Multiple pending intents."
        )

    for kind, limit in LIMITS.items():
        if counts[kind] > limit:
            raise ManifestError(
                f"Excessive manifest object count: {kind}"
            )

    if (
        state in {"READY", "TESTING", "CLEANED"}
        and pending
    ):
        raise ManifestError(
            "Unexpected unresolved manifest object."
        )

    if "snapshot" not in data:
        raise ManifestError(
            "Manifest snapshot field unavailable."
        )

    snapshot = data["snapshot"]

    if (
        state in {"CREATING", "FAILED"}
        and snapshot is not None
    ):
        raise ManifestError(
            "Unexpected fixture snapshot."
        )

    if (
        state in {"READY", "TESTING"}
        and snapshot is None
    ):
        raise ManifestError(
            "Manifest snapshot unavailable."
        )

    if snapshot is not None:
        if not isinstance(snapshot, dict):
            raise ManifestError(
                "Invalid manifest snapshot."
            )

        if counts != LIMITS or pending:
            raise ManifestError(
                "Incomplete snapshot registry."
            )

        if set(snapshot) != {
            "run_id",
            "base_created",
            "query_created",
            "users_created",
            "user_ids",
            "category_ids",
            "post_ids",
            "fields",
        }:
            raise ManifestError(
                "Invalid snapshot structure."
            )

        if (
            snapshot["run_id"] != data.get("run_id")
            or snapshot["base_created"] is not True
            or snapshot["query_created"] is not True
            or snapshot["users_created"] is not True
            or not isinstance(snapshot["fields"], dict)
        ):
            raise ManifestError(
                "Invalid snapshot state."
            )

        for kind, field in (
            ("user", "user_ids"),
            ("category", "category_ids"),
            ("post", "post_ids"),
        ):
            expected_ids = [
                obj["id"]
                for obj in objects
                if obj["kind"] == kind
            ]

            actual_ids = snapshot[field]

            if (
                not isinstance(actual_ids, list)
                or any(
                    type(identifier) is not int
                    or identifier <= 0
                    for identifier in actual_ids
                )
                or actual_ids != expected_ids
            ):
                raise ManifestError(
                    f"Invalid snapshot identifiers: {field}"
                )

    return data


def execute(command: str) -> dict[str, Any]:
    if (
        command not in COMMANDS
        and command not in REJECTED_COMMANDS
    ):
        raise ManifestError(
            f"Unsupported command: {command}"
        )

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

    if command in REJECTED_COMMANDS:
        raise ManifestError(
            "Retired manifest command unexpectedly succeeded."
        )

    try:
        data = json.loads(result.stdout)

    except json.JSONDecodeError as error:
        raise ManifestError(
            "Invalid manifest JSON."
        ) from error

    return validate_manifest(
        data,
        command,
    )


def main() -> int:
    parser = argparse.ArgumentParser()

    parser.add_argument(
        "command",
        choices=COMMANDS + REJECTED_COMMANDS,
        metavar="{create-v2,status,verify,cleanup}",
    )

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
        print(
            f"FAIL: {error}",
            file=sys.stderr,
        )

        return 1


if __name__ == "__main__":
    raise SystemExit(main())