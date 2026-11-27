from __future__ import annotations

from typing import TYPE_CHECKING

from .assertions import (
    require,
    positive_id,
    require_checkpoint,
    pending_user,
    require_dependencies,
    require_probe,
    probe_user,
    require_user_identity,
    require_recovered_user,
    finish,
)


if TYPE_CHECKING:
    from suite import UserRecoverySuite


def scenario_intent(
    suite: UserRecoverySuite,
) -> None:
    stage = "user-intent"

    checkpoint = suite.crash(stage)

    manifest, extra = require_checkpoint(
        checkpoint,
        stage,
        "CREATING",
    )

    run_id = manifest["run_id"]
    pending = pending_user(manifest)

    require(
        len(manifest["objects"]) == 1
        and manifest["snapshot"] is None
        and pending["identity"] == extra["identity"],
        "Unexpected initial user intent.",
    )

    require_probe(suite, run_id, [])

    cleaned = finish(suite, run_id)

    require(
        cleaned["objects"] == []
        and cleaned["snapshot"] is None,
        "Absent user intent was not removed.",
    )


def scenario_unconfirmed(
    suite: UserRecoverySuite,
) -> None:
    stage = "user-unconfirmed"

    checkpoint = suite.crash(stage)

    manifest, extra = require_checkpoint(
        checkpoint,
        stage,
        "CREATING",
    )

    run_id = manifest["run_id"]
    pending = pending_user(manifest)
    user_id = positive_id(extra["id"])

    require(
        pending["identity"] == extra["identity"],
        "Unconfirmed user identity mismatch.",
    )

    require_dependencies(
        extra["dependencies"],
        metadata=True,
    )

    probe = require_probe(
        suite,
        run_id,
        [user_id],
    )

    user = probe_user(probe, user_id)

    require_user_identity(
        user,
        pending["identity"],
        user_id,
    )

    require(
        user["dependencies"] == extra["dependencies"],
        "Unconfirmed user dependencies changed.",
    )

    cleaned = finish(suite, run_id)

    require_recovered_user(
        cleaned,
        pending,
        user_id,
    )


def scenario_partial_creation(
    suite: UserRecoverySuite,
) -> None:
    stage = "user-partial"

    checkpoint = suite.crash(stage)

    manifest, extra = require_checkpoint(
        checkpoint,
        stage,
        "CREATING",
    )

    run_id = manifest["run_id"]
    pending = pending_user(manifest)
    user_id = positive_id(extra["id"])

    require(
        extra["identity"] == pending["identity"],
        "Partial creation identity mismatch.",
    )

    require_dependencies(
        extra["dependencies"],
        metadata=False,
    )

    probe = require_probe(
        suite,
        run_id,
        [user_id],
    )

    user = probe_user(probe, user_id)

    require_user_identity(
        user,
        pending["identity"],
        user_id,
    )

    require_dependencies(
        user["dependencies"],
        metadata=False,
    )

    cleaned = finish(suite, run_id)

    require_recovered_user(
        cleaned,
        pending,
        user_id,
    )