from __future__ import annotations

from copy import deepcopy

from .config import (
    USER_COUNT,
    CATEGORY_COUNT,
    CATEGORY_PENDING_COUNT,
    POST_PENDING_COUNT,
    CLEANUP_STAGE,
    require,
    positive_id,
)

from .suite import FixtureRecoverySuite


class FixtureScenarios:
    def __init__(
        self,
        suite: FixtureRecoverySuite,
    ) -> None:
        self.suite = suite

    def scenario_1(self) -> None:
        suite = self.suite

        checkpoint = suite.crash(
            "fixture-empty"
        )

        manifest = checkpoint["manifest"]

        require(
            manifest["objects"] == []
            and manifest["snapshot"] is None,
            "Expected empty Manifest V2.",
        )

        observed = suite.fixture_probe(
            manifest["run_id"]
        )

        suite.assert_empty_fixture_probe(observed)

        suite.finish(
            checkpoint,
            [],
            observed,
        )

    def scenario_2(self) -> None:
        suite = self.suite

        checkpoint = suite.crash(
            "fixture-intent"
        )

        suite.pending(
            checkpoint,
            "category",
            CATEGORY_PENDING_COUNT,
        )

        manifest = checkpoint["manifest"]
        objects = manifest["objects"]

        observed = suite.fixture_probe(
            manifest["run_id"]
        )

        expected = objects[:-1]

        require(
            len(expected) == USER_COUNT,
            "Expected confirmed users before category intent.",
        )

        suite.assert_registry_probe(
            expected,
            observed,
            exact=True,
        )

        suite.finish(
            checkpoint,
            expected,
            observed,
        )

    def scenario_3(self) -> None:
        suite = self.suite

        checkpoint = suite.crash(
            "fixture-category"
        )

        suite.pending(
            checkpoint,
            "category",
            CATEGORY_PENDING_COUNT,
        )

        manifest = checkpoint["manifest"]
        extra = checkpoint["extra"]

        identifier = positive_id(extra["id"])

        taxonomy_id = positive_id(
            extra["taxonomy_id"]
        )

        expected = suite.recovered_registry(
            checkpoint,
            identifier,
            taxonomy_id,
        )

        observed = suite.fixture_probe(
            manifest["run_id"]
        )

        suite.assert_registry_probe(
            expected,
            observed,
            exact=True,
        )

        suite.finish(
            checkpoint,
            expected,
            observed,
        )

    def unconfirmed_post(
        self,
        *,
        container: bool = False,
    ) -> None:
        suite = self.suite

        stage = (
            "fixture-container"
            if container
            else "fixture-post"
        )

        checkpoint = suite.crash(
            stage,
            container=container,
        )

        suite.pending(
            checkpoint,
            "post",
            POST_PENDING_COUNT,
        )

        manifest = checkpoint["manifest"]
        extra = checkpoint["extra"]

        identifier = positive_id(extra["id"])

        require(
            extra["taxonomy_id"] == 0,
            "Unexpected post taxonomy ID.",
        )

        expected = suite.recovered_registry(
            checkpoint,
            identifier,
        )

        observed = suite.fixture_probe(
            manifest["run_id"]
        )

        suite.assert_registry_probe(
            expected,
            observed,
            exact=True,
        )

        require(
            len(observed["user_ids"]) == USER_COUNT
            and len(observed["term_ids"]) == CATEGORY_COUNT,
            "Incomplete base fixture registry.",
        )

        suite.finish(
            checkpoint,
            expected,
            observed,
        )

    def scenario_4(self) -> None:
        self.unconfirmed_post()

    def scenario_5(self) -> None:
        suite = self.suite

        checkpoint = suite.crash(
            "fixture-confirmed"
        )

        manifest = checkpoint["manifest"]
        objects = manifest["objects"]

        identifier = positive_id(
            checkpoint["extra"]["id"]
        )

        require(
            manifest["state"] == "CREATING"
            and manifest["snapshot"] is None
            and len(objects) == POST_PENDING_COUNT
            and all(
                item["id"] > 0
                for item in objects
            )
            and objects[-1]["kind"] == "post"
            and objects[-1]["id"] == identifier,
            "Expected partially confirmed Manifest V2.",
        )

        observed = suite.fixture_probe(
            manifest["run_id"]
        )

        suite.assert_registry_probe(
            objects,
            observed,
            exact=True,
        )

        suite.finish(
            checkpoint,
            objects,
            observed,
        )

    def scenario_6(self) -> None:
        suite = self.suite

        ready = suite.create_ready()

        checkpoint = suite.crash(
            CLEANUP_STAGE
        )

        expected = deepcopy(ready)
        expected["state"] = "CLEANING"

        require(
            checkpoint["manifest"] == expected,
            "Interrupted cleanup changed Manifest V2.",
        )

        deleted_id = positive_id(
            checkpoint["extra"]["deleted_id"]
        )

        require(
            deleted_id
            == ready["snapshot"]["fields"]["password_post"],
            "Unexpected interrupted cleanup target.",
        )

        observed = suite.fixture_probe(
            ready["run_id"]
        )

        remaining = [
            record
            for record in ready["objects"]
            if not (
                record["kind"] == "post"
                and record["id"] == deleted_id
            )
        ]

        suite.assert_registry_probe(
            remaining,
            observed,
            exact=True,
        )

        require(
            deleted_id not in observed["post_ids"]
            and len(observed["user_ids"]) == USER_COUNT
            and len(observed["term_ids"]) == CATEGORY_COUNT,
            "Invalid interrupted cleanup state.",
        )

        suite.finish(
            checkpoint,
            ready["objects"],
            observed,
        )

    def scenario_7(self) -> None:
        suite = self.suite

        checkpoint = suite.crash(
            "fixture-ambiguous"
        )

        suite.pending(
            checkpoint,
            "post",
            POST_PENDING_COUNT,
        )

        manifest = checkpoint["manifest"]
        run_id = manifest["run_id"]
        extra = checkpoint["extra"]

        original_id = positive_id(extra["id"])

        duplicate_id = positive_id(
            extra["duplicate_id"]
        )

        require(
            original_id != duplicate_id,
            "Invalid ambiguous post identifiers.",
        )

        observed = suite.fixture_probe(run_id)

        expected = suite.recovered_registry(
            checkpoint,
            original_id,
        )

        suite.assert_registry_probe(
            expected,
            observed,
        )

        require(
            observed["post_ids"]
            == sorted([original_id, duplicate_id])
            and observed["partial_term_ids"] == []
            and len(observed["user_ids"]) == USER_COUNT
            and len(observed["term_ids"]) == CATEGORY_COUNT
            and len(observed["taxonomy_ids"]) == CATEGORY_COUNT,
            "Unexpected ambiguous fixture state.",
        )

        suite.expect_blocked(
            checkpoint,
            observed,
            "Ambiguous or incomplete post identity.",
        )

        result = suite.worker(
            "fixture-repair-ambiguity",
            run_id,
            original_id,
            duplicate_id,
        )

        require(
            result == {"repaired": True},
            "Controlled ambiguity repair failed.",
        )

        repaired = suite.fixture_probe(run_id)

        suite.assert_registry_probe(
            expected,
            repaired,
            exact=True,
        )

        require(
            duplicate_id not in repaired["post_ids"],
            "Duplicate post still exists.",
        )

        blocked = deepcopy(manifest)
        blocked["state"] = "CLEANUP_FAILED"

        require(
            suite.manifest("status") == blocked,
            "Controlled repair changed persistent registry.",
        )

        suite.finish(
            checkpoint,
            expected,
            observed,
        )

    def scenario_8(self) -> None:
        self.unconfirmed_post(
            container=True,
        )

    def scenario_9(self) -> None:
        suite = self.suite

        checkpoint = suite.crash(
            "fixture-sql-intent"
        )

        suite.pending(
            checkpoint,
            "post",
            POST_PENDING_COUNT,
        )

        manifest = checkpoint["manifest"]

        expected = manifest["objects"][:-1]

        observed = suite.fixture_probe(
            manifest["run_id"]
        )

        suite.assert_registry_probe(
            expected,
            observed,
            exact=True,
        )

        require(
            observed["post_ids"] == []
            and len(observed["user_ids"]) == USER_COUNT
            and len(observed["term_ids"]) == CATEGORY_COUNT,
            "Unexpected SQL failure fixture state.",
        )

        suite.expect_blocked(
            checkpoint,
            observed,
            "Post recovery SQL failed.",
            sql_failure=True,
        )

        suite.finish(
            checkpoint,
            expected,
            observed,
        )

    def scenario_10(self) -> None:
        suite = self.suite

        checkpoint = suite.crash(
            "fixture-partial-category"
        )

        suite.pending(
            checkpoint,
            "category",
            CATEGORY_PENDING_COUNT,
        )

        manifest = checkpoint["manifest"]
        run_id = manifest["run_id"]

        term_id = positive_id(
            checkpoint["extra"]["term_id"]
        )

        expected = manifest["objects"][:-1]

        observed = suite.fixture_probe(run_id)

        require(
            observed["user_ids"]
            == suite.registered_ids(expected, "user")
            and len(observed["user_ids"]) == USER_COUNT
            and observed["post_ids"] == []
            and observed["term_ids"] == [term_id]
            and observed["taxonomy_ids"] == []
            and observed["partial_term_ids"] == [term_id],
            "Expected partially created category.",
        )

        suite.expect_blocked(
            checkpoint,
            observed,
            "Incomplete or mismatched category identity.",
        )

        result = suite.worker(
            "fixture-repair-partial-category",
            run_id,
            term_id,
        )

        require(
            result == {"repaired": True},
            "Controlled partial category repair failed.",
        )

        repaired = suite.fixture_probe(run_id)

        suite.assert_registry_probe(
            expected,
            repaired,
            exact=True,
        )

        blocked = deepcopy(manifest)
        blocked["state"] = "CLEANUP_FAILED"

        require(
            suite.manifest("status") == blocked,
            "Partial category repair changed persistent registry.",
        )

        suite.finish(
            checkpoint,
            expected,
            observed,
        )


def build_scenarios(
    suite: FixtureRecoverySuite,
) -> dict:
    scenarios = FixtureScenarios(suite)

    return {
        1: scenarios.scenario_1,
        2: scenarios.scenario_2,
        3: scenarios.scenario_3,
        4: scenarios.scenario_4,
        5: scenarios.scenario_5,
        6: scenarios.scenario_6,
        7: scenarios.scenario_7,
        8: scenarios.scenario_8,
        9: scenarios.scenario_9,
        10: scenarios.scenario_10,
    }