"""Координатор полной регрессии HF-M10-V2."""

from __future__ import annotations

import sys

from recovery_process import (
    RecoveryConfig,
    RecoveryProcess,
)

from .config import (
    SCENARIO_NAMES,
    TOTAL_CASES,
)

from .commands import (
    require,
    redact,
    require_default_environment,
)

from .context import (
    RegressionContext,
    ProcessFactory,
)

from .scenarios import (
    Scenario,
    build_scenarios,
)


class FixtureRegression:
    """Координатор полного набора испытаний."""

    def __init__(
        self,
        *,
        config: RecoveryConfig | None = None,
        process: RecoveryProcess | None = None,
        process_factory: ProcessFactory | None = None,
    ) -> None:
        require(
            sum(
                value is not None
                for value in (
                    config,
                    process,
                    process_factory,
                )
            ) <= 1,
            "Specify either RecoveryConfig, "
            "RecoveryProcess or process factory.",
        )

        require(
            config is None
            or isinstance(config, RecoveryConfig),
            "Invalid Recovery configuration.",
        )

        require(
            process is None
            or type(process) is RecoveryProcess,
            "Unexpected RecoveryProcess class.",
        )

        require(
            process_factory is None
            or callable(process_factory),
            "Invalid RecoveryProcess factory.",
        )

        self._requested_config = config

        self._requested_process = process

        self._process_factory = process_factory

        self.context: RegressionContext | None = None

        self.executed = 0

        self.passed = 0

        self.failed = 0

        self.active_case: str | None = None

        self.failed_case: str | None = None

        self.infrastructure_failed = False

    @property
    def config(self) -> RecoveryConfig | None:
        if self.context is not None:
            return self.context.config

        if self._requested_process is not None:
            return self._requested_process.config

        return self._requested_config

    def preflight(self) -> None:
        require(
            self.context is None,
            "Regression preflight already completed.",
        )

        self.context = RegressionContext(
            config=self._requested_config,
            process=self._requested_process,
            process_factory=self._process_factory,
        )

        require_default_environment(
            self.context.config
        )

        self.context.assert_shared_context()

    def run_case(
        self,
        number: int,
        operation: Scenario,
    ) -> None:
        context = self.context

        require(
            context is not None,
            "Regression context unavailable.",
        )

        require(
            number in SCENARIO_NAMES,
            "Unknown regression scenario.",
        )

        require(
            callable(operation),
            "Invalid regression scenario operation.",
        )

        identifier = f"HF-M10-V2.{number}"

        title = SCENARIO_NAMES[number]

        self.active_case = identifier

        self.executed += 1

        print(
            f"\n=== {identifier} {title} ===",
            flush=True,
        )

        try:
            context.assert_shared_context()

            operation(context)

            context.assert_shared_context()

        except (Exception, KeyboardInterrupt):
            self.failed += 1

            self.failed_case = identifier

            raise

        else:
            self.passed += 1

            print(
                f"PASS: {identifier} {title}",
                flush=True,
            )

        finally:
            self.active_case = None

    def run(self) -> None:
        self.preflight()

        context = self.context

        require(
            context is not None,
            "Regression context unavailable.",
        )

        scenarios = build_scenarios()

        require_default_environment(
            context.config
        )

        context.assert_cleaned()

        context.process.verify_restart_readiness()

        print(
            "\n=== CLI REGRESSION ===",
            flush=True,
        )

        context.environment(
            "stop"
        )

        for number, operation in scenarios.items():
            self.run_case(
                number,
                operation,
            )

        require(
            self.executed == TOTAL_CASES
            and self.passed == TOTAL_CASES
            and self.failed == 0,
            "Incomplete regression coverage.",
        )

    def accepted(self) -> bool:
        return (
            self.executed == TOTAL_CASES
            and self.passed == TOTAL_CASES
            and self.failed == 0
            and not self.infrastructure_failed
        )

    def report_result(self) -> None:
        skipped = (
            TOTAL_CASES - self.executed
        )

        print("\n=== RESULT ===")

        print(f"Total: {TOTAL_CASES}")
        print(f"Executed: {self.executed}")
        print(f"Passed: {self.passed}")
        print(f"Failed: {self.failed}")
        print(f"Skipped: {skipped}")

        if self.failed_case is not None:
            print(
                f"Failed case: {self.failed_case}"
            )

        if self.infrastructure_failed:
            print("Infrastructure: FAILED")

        if self.accepted():
            print("PASS: HF-M10-V2")

        else:
            print("FAIL: HF-M10-V2")


def main(
    *,
    config: RecoveryConfig | None = None,
    process: RecoveryProcess | None = None,
    process_factory: ProcessFactory | None = None,
) -> int:
    suite = None

    interrupted = False

    effective_config = None

    print(
        "=== HF-M10-V2 FULL FIXTURE REGRESSION ===",
        flush=True,
    )

    try:
        require(
            sum(
                value is not None
                for value in (
                    config,
                    process,
                    process_factory,
                )
            ) <= 1,
            "Specify either RecoveryConfig, "
            "RecoveryProcess or process factory.",
        )

        if (
            config is None
            and process is None
            and process_factory is None
        ):
            config = RecoveryConfig()

        if isinstance(
            config,
            RecoveryConfig,
        ):
            effective_config = config

        elif type(process) is RecoveryProcess:
            effective_config = process.config

        suite = FixtureRegression(
            config=config,
            process=process,
            process_factory=process_factory,
        )

        suite.run()

    except KeyboardInterrupt:
        interrupted = True

        if (
            suite is not None
            and suite.failed == 0
        ):
            suite.infrastructure_failed = True

        print(
            "\nRegression interrupted.",
            file=sys.stderr,
            flush=True,
        )

    except Exception as error:
        if suite is not None:
            if suite.failed == 0:
                suite.infrastructure_failed = True

            if suite.config is not None:
                effective_config = suite.config

        if isinstance(
            effective_config,
            RecoveryConfig,
        ):
            diagnostic = redact(
                str(error),
                effective_config,
            )

        else:
            diagnostic = type(error).__name__

        print(
            f"FAIL: {diagnostic}",
            file=sys.stderr,
            flush=True,
        )

    if suite is None:
        print("\n=== RESULT ===")
        print(f"Total: {TOTAL_CASES}")
        print("Executed: 0")
        print("Passed: 0")
        print("Failed: 0")
        print(f"Skipped: {TOTAL_CASES}")
        print("Infrastructure: FAILED")
        print("FAIL: HF-M10-V2")

        return 130 if interrupted else 1

    suite.report_result()

    if interrupted:
        return 130

    if suite.accepted():
        return 0

    print(
        "Regression stopped. "
        "Inspect environment and persistent manifest.",
        file=sys.stderr,
        flush=True,
    )

    return 1