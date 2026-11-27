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


def scenario_ambiguity(
    suite: UserRecoverySuite,
) -> None:
    stage = "user-ambiguous"

    checkpoint = suite.crash(stage)

    manifest, extra = require_checkpoint(
        checkpoint,
        stage,
        "CREATING",
    )

    run_id = manifest["run_id"]
    pending = pending_user(manifest)

    user_id = positive_id(extra["id"])
    duplicate_id = positive_id(extra["duplicate_id"])

    require(
        user_id != duplicate_id,
        "Duplicate user ID equals original ID.",
    )

    before = require_probe(
        suite,
        run_id,
        [user_id, duplicate_id],
    )

    original = probe_user(before, user_id)
    duplicate = probe_user(before, duplicate_id)

    require_user_identity(
        original,
        pending["identity"],
        user_id,
    )

    duplicate_login = f"hfur-duplicate-{run_id}"

    require(
        duplicate["login"] == duplicate_login
        and duplicate["nicename"] == duplicate_login
        and duplicate["email"]
            == pending["identity"]["email"],
        "Unexpected ambiguity fixture identity.",
    )

    require_dependencies(
        duplicate["dependencies"],
        metadata=False,
    )

    blocked = suite.expect_blocked(
        run_id,
        "Ambiguous fixture user identity.",
    )

    require(
        blocked["objects"] == manifest["objects"],
        "Blocked recovery changed ambiguous registry.",
    )

    after = require_probe(
        suite,
        run_id,
        [user_id, duplicate_id],
    )

    require(
        after == before,
        "Blocked recovery modified user candidates.",
    )

    result = suite.worker(
        "repair-user-ambiguity",
        run_id,
        user_id,
        duplicate_id,
    )

    require(
        result == {"repaired": True},
        "Controlled ambiguity repair failed.",
    )

    repaired = require_probe(
        suite,
        run_id,
        [user_id],
    )

    require(
        probe_user(repaired, user_id) == original,
        "Original user changed during repair.",
    )

    cleaned = finish(suite, run_id)

    require_recovered_user(
        cleaned,
        pending,
        user_id,
    )


def scenario_substitution(
    suite: UserRecoverySuite,
) -> None:
    stage = "user-substituted"

    checkpoint = suite.crash(stage)

    manifest, extra = require_checkpoint(
        checkpoint,
        stage,
        "CREATING",
    )

    run_id = manifest["run_id"]
    original_id = positive_id(extra["id"])
    substituted_id = positive_id(extra["substituted_id"])

    require(
        original_id != substituted_id,
        "Invalid substituted user ID.",
    )

    require(
        len(manifest["objects"]) == 1,
        "Unexpected substituted registry size.",
    )

    record = manifest["objects"][0]

    require(
        record["kind"] == "user"
        and record["id"] == substituted_id
        and record["identity"] == extra["identity"],
        "Substitution was not persisted.",
    )

    before = require_probe(
        suite,
        run_id,
        [original_id],
    )

    require_user_identity(
        probe_user(before, original_id),
        record["identity"],
        original_id,
    )

    suite.assert_protected_user()

    blocked = suite.expect_blocked(
        run_id,
        "Protected or active user deletion rejected.",
    )

    require(
        blocked["objects"] == manifest["objects"],
        "Blocked recovery changed substituted registry.",
    )

    require(
        suite.probe(run_id) == before,
        "Blocked substitution changed original user.",
    )

    suite.assert_protected_user()

    result = suite.worker(
        "repair-user-substitution",
        run_id,
        original_id,
        substituted_id,
    )

    require(
        result == {"repaired": True},
        "Controlled substitution repair failed.",
    )

    repaired = suite.manifest("status")

    expected = dict(record)
    expected["id"] = original_id

    require(
        repaired["run_id"] == run_id
        and repaired["state"] == "CLEANUP_FAILED"
        and repaired["objects"] == [expected],
        "Original registry identifier was not restored.",
    )

    require(
        suite.probe(run_id) == before,
        "Substitution repair modified user identity.",
    )

    suite.assert_protected_user()

    cleaned = finish(suite, run_id)

    require(
        cleaned["objects"] == [expected],
        "Recovered registry identity mismatch.",
    )


def scenario_sql_failure(
    suite: UserRecoverySuite,
) -> None:
    stage = "user-sql-intent"

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
        "SQL failure fixture identity mismatch.",
    )

    before = require_probe(
        suite,
        run_id,
        [user_id],
    )

    require_user_identity(
        probe_user(before, user_id),
        pending["identity"],
        user_id,
    )

    blocked = suite.expect_worker_blocked(
        "user-sql-failure",
        run_id,
        "User SQL query failed.",
    )

    require(
        blocked["objects"] == manifest["objects"],
        "SQL failure changed pending user registry.",
    )

    require(
        suite.probe(run_id) == before,
        "SQL failure modified fixture user.",
    )

    suite.assert_protected_user()

    cleaned = finish(suite, run_id)

    require_recovered_user(
        cleaned,
        pending,
        user_id,
    )