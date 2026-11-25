from __future__ import annotations

import copy

from process import require
from suite import RecoverySuiteBase


SCENARIOS = {
    1: "Empty manifest",
    2: "Intent without object",
    3: "Unconfirmed category",
    4: "Unconfirmed post",
    5: "Partially confirmed fixtures",
    6: "Interrupted cleanup",
    7: "Ambiguous identity",
    8: "Container crash",
    9: "Recovery SQL failure",
    10: "Partial category",
}


class RecoverySuite(RecoverySuiteBase):
    def scenario_1(self) -> None:
        checkpoint = self.crash(
            "empty",
            "empty",
        )

        data = checkpoint["manifest"]

        require(
            data["state"] == "CREATING"
            and data["objects"] == []
            and data["snapshot"] is None,
            "Expected empty manifest.",
        )

        observed = self.probe(data["run_id"])

        self.assert_empty_probe(observed)
        self.finish(checkpoint, [], observed)

    def scenario_2(self) -> None:
        checkpoint = self.crash(
            "intent",
            "intent",
        )

        self.pending(checkpoint, "category", 1)

        observed = self.probe(
            checkpoint["manifest"]["run_id"]
        )

        self.assert_empty_probe(observed)
        self.finish(checkpoint, [], observed)

    def scenario_3(self) -> None:
        checkpoint = self.crash(
            "category",
            "category",
        )

        self.pending(checkpoint, "category", 1)

        extra = checkpoint["extra"]
        object_id = extra["id"]
        taxonomy_id = extra["taxonomy_id"]

        observed = self.probe(
            checkpoint["manifest"]["run_id"]
        )

        require(
            observed == {
                "post_ids": [],
                "term_ids": [object_id],
                "taxonomy_ids": [taxonomy_id],
                "partial_term_ids": [],
            },
            "Unconfirmed category was not created.",
        )

        expected = self.recovered_registry(
            checkpoint,
            object_id,
            taxonomy_id,
        )

        self.finish(
            checkpoint,
            expected,
            observed,
        )

    def scenario_unconfirmed_post(
        self,
        *,
        container: bool = False,
    ) -> None:
        stage = "container" if container else "post"

        checkpoint = self.crash(
            stage,
            stage,
            container=container,
        )

        self.pending(checkpoint, "post", 5)

        object_id = checkpoint["extra"]["id"]

        observed = self.probe(
            checkpoint["manifest"]["run_id"]
        )

        require(
            object_id in observed["post_ids"]
            and len(observed["term_ids"]) == 4
            and len(observed["taxonomy_ids"]) == 4
            and not observed["partial_term_ids"],
            "Unconfirmed post or base categories unavailable.",
        )

        expected = self.recovered_registry(
            checkpoint,
            object_id,
        )

        self.finish(
            checkpoint,
            expected,
            observed,
        )

    def scenario_4(self) -> None:
        self.scenario_unconfirmed_post()

    def scenario_5(self) -> None:
        checkpoint = self.crash(
            "confirmed",
            "confirmed",
        )

        data = checkpoint["manifest"]
        objects = data["objects"]

        require(
            data["state"] == "CREATING"
            and data["snapshot"] is None
            and len(objects) == 5
            and all(item["id"] > 0 for item in objects)
            and objects[-1]["kind"] == "post"
            and objects[-1]["id"] == checkpoint["extra"]["id"],
            "Expected partially confirmed registry.",
        )

        observed = self.probe(data["run_id"])

        require(
            objects[-1]["id"] in observed["post_ids"]
            and len(observed["term_ids"]) == 4,
            "Partially confirmed objects unavailable.",
        )

        self.finish(
            checkpoint,
            objects,
            observed,
        )

    def scenario_6(self) -> None:
        ready = self.manifest("create")

        checkpoint = self.crash(
            "cleanup-crash",
            "cleanup",
        )

        data = checkpoint["manifest"]

        expected = copy.deepcopy(ready)
        expected["state"] = "CLEANING"

        require(
            data == expected,
            "Interrupted cleanup modified the registry.",
        )

        deleted_id = checkpoint["extra"]["deleted_id"]

        require(
            deleted_id == ready["snapshot"]["fields"]["password_post"],
            "Unexpected interrupted cleanup target.",
        )

        observed = self.probe(data["run_id"])

        registered_posts = {
            item["id"]
            for item in data["objects"]
            if item["kind"] == "post"
        }

        require(
            deleted_id not in observed["post_ids"]
            and (
                registered_posts - {deleted_id}
            ).issubset(observed["post_ids"]),
            "Unexpected partial cleanup state.",
        )

        require(
            len(observed["term_ids"]) == 4,
            "Categories were removed prematurely.",
        )

        self.finish(
            checkpoint,
            data["objects"],
            observed,
        )

    def scenario_7(self) -> None:
        checkpoint = self.crash(
            "ambiguous",
            "ambiguous",
        )

        self.pending(checkpoint, "post", 5)

        run_id = checkpoint["manifest"]["run_id"]
        extra = checkpoint["extra"]

        original_id = extra["id"]
        duplicate_id = extra["duplicate_id"]

        observed = self.probe(run_id)

        require(
            original_id != duplicate_id
            and original_id in observed["post_ids"]
            and duplicate_id in observed["post_ids"],
            "Ambiguous objects unavailable.",
        )

        self.expect_blocked(
            checkpoint,
            observed,
            "Ambiguous or incomplete post identity.",
        )

        self.worker(
            "repair-ambiguity",
            run_id,
            original_id,
            duplicate_id,
        )

        repaired = self.probe(run_id)

        require(
            original_id in repaired["post_ids"]
            and duplicate_id not in repaired["post_ids"],
            "Controlled ambiguity repair failed.",
        )

        expected = self.recovered_registry(
            checkpoint,
            original_id,
        )

        self.finish(
            checkpoint,
            expected,
            observed,
        )

    def scenario_8(self) -> None:
        self.scenario_unconfirmed_post(
            container=True,
        )

    def scenario_9(self) -> None:
        checkpoint = self.crash(
            "sql-intent",
            "sql-intent",
        )

        self.pending(checkpoint, "post", 5)

        observed = self.probe(
            checkpoint["manifest"]["run_id"]
        )

        require(
            observed["post_ids"] == []
            and len(observed["term_ids"]) == 4,
            "Unexpected SQL failure test state.",
        )

        self.expect_blocked(
            checkpoint,
            observed,
            "Post recovery SQL failed.",
            sql_failure=True,
        )

        self.finish(
            checkpoint,
            checkpoint["manifest"]["objects"][:-1],
            observed,
        )

    def scenario_10(self) -> None:
        checkpoint = self.crash(
            "partial-category",
            "partial-category",
        )

        self.pending(checkpoint, "category", 1)

        run_id = checkpoint["manifest"]["run_id"]
        term_id = checkpoint["extra"]["term_id"]

        observed = self.probe(run_id)

        require(
            observed == {
                "post_ids": [],
                "term_ids": [term_id],
                "taxonomy_ids": [],
                "partial_term_ids": [term_id],
            },
            "Expected incomplete category taxonomy.",
        )

        self.expect_blocked(
            checkpoint,
            observed,
            "Incomplete or mismatched category identity.",
        )

        self.worker(
            "repair-partial-category",
            run_id,
            term_id,
        )

        self.assert_empty_probe(
            self.probe(run_id)
        )

        self.finish(
            checkpoint,
            [],
            observed,
        )

    def run(self, scenarios: list[int]) -> None:
        self.run_cases(
            scenarios,
            SCENARIOS,
        )