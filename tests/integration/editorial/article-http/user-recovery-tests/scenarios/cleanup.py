from __future__ import annotations

from typing import Any, TYPE_CHECKING

from .assertions import (
    require,
    positive_id,
    require_checkpoint,
    require_dependencies,
    require_probe,
    probe_user,
    require_user_identity,
    finish,
)


if TYPE_CHECKING:
    from suite import UserRecoverySuite


def cleanup_target(
    manifest: dict[str, Any],
) -> dict[str, Any]:
    records = [
        record
        for record in manifest["objects"]
        if record["kind"] == "user"
        and record["identity"]["slot"] == "author_b"
    ]

    require(
        len(records) == 1,
        "Cleanup target registry unavailable.",
    )

    positive_id(records[0]["id"])

    return records[0]


def metadata_ids(value: Any) -> set[int]:
    require(
        isinstance(value, list),
        "Invalid metadata ID list.",
    )

    ids = {positive_id(item) for item in value}

    require(
        len(ids) == len(value),
        "Duplicate metadata ID.",
    )

    return ids


def scenario_partial_cleanup(
    suite: UserRecoverySuite,
) -> None:
    ready = suite.create_ready()
    target = cleanup_target(ready)

    run_id = ready["run_id"]
    user_id = target["id"]

    stage = "user-cleanup-partial"

    checkpoint = suite.crash(stage)

    manifest, extra = require_checkpoint(
        checkpoint,
        stage,
        "CLEANING",
    )

    require(
        manifest["run_id"] == run_id
        and manifest["objects"] == ready["objects"]
        and manifest["snapshot"] == ready["snapshot"],
        "Cleanup changed the fixture registry.",
    )

    require(
        extra["id"] == user_id
        and extra["identity"] == target["identity"],
        "Partial cleanup target mismatch.",
    )

    initial = metadata_ids(
        extra["initial_metadata_ids"]
    )

    removed = metadata_ids(
        extra["removed_metadata_ids"]
    )

    remaining = metadata_ids(
        extra["remaining_metadata_ids"]
    )

    require(
        bool(removed)
        and bool(remaining)
        and not removed.intersection(remaining)
        and removed.union(remaining) == initial,
        "Invalid partially deleted metadata state.",
    )

    require_dependencies(
        extra["dependencies"],
        metadata=True,
    )

    require(
        extra["dependencies"]["usermeta"]
        == len(remaining),
        "Partial metadata count mismatch.",
    )

    expected_ids = [
        record["id"]
        for record in ready["objects"]
        if record["kind"] == "user"
    ]

    probe = require_probe(
        suite,
        run_id,
        expected_ids,
    )

    user = probe_user(probe, user_id)

    require_user_identity(
        user,
        target["identity"],
        user_id,
    )

    require(
        user["dependencies"] == extra["dependencies"],
        "Partial cleanup state changed after crash.",
    )

    cleaned = finish(suite, run_id)

    require(
        cleaned["objects"] == ready["objects"]
        and cleaned["snapshot"] == ready["snapshot"],
        "Recovery changed historical registry.",
    )

    print(
        "PASS: HF-U-R07.1 Partial user deletion",
        flush=True,
    )


def scenario_completed_cleanup(
    suite: UserRecoverySuite,
) -> None:
    ready = suite.create_ready()
    target = cleanup_target(ready)

    run_id = ready["run_id"]
    user_id = target["id"]

    stage = "user-cleanup-crash"

    checkpoint = suite.crash(stage)

    manifest, extra = require_checkpoint(
        checkpoint,
        stage,
        "CLEANING",
    )

    require(
        manifest["run_id"] == run_id
        and manifest["objects"] == ready["objects"]
        and manifest["snapshot"] == ready["snapshot"],
        "Completed deletion changed manifest registry.",
    )

    require(
        extra["id"] == user_id
        and extra["identity"] == target["identity"],
        "Completed deletion target mismatch.",
    )

    require_dependencies(
        extra["dependencies"],
        metadata=False,
    )

    expected_ids = [
        record["id"]
        for record in ready["objects"]
        if record["kind"] == "user"
        and record["id"] != user_id
    ]

    require_probe(
        suite,
        run_id,
        expected_ids,
    )

    cleaned = finish(suite, run_id)

    require(
        cleaned["objects"] == ready["objects"]
        and cleaned["snapshot"] == ready["snapshot"],
        "Repeated deletion changed historical registry.",
    )

    print(
        "PASS: HF-U-R07.2 Completed user deletion",
        flush=True,
    )


def scenario_cleanup(
    suite: UserRecoverySuite,
) -> None:
    scenario_partial_cleanup(suite)
    scenario_completed_cleanup(suite)