"""Единый реестр сценариев HF-M10-V2."""

from __future__ import annotations

from typing import (
    Callable,
    TYPE_CHECKING,
)

from .config import (
    SCENARIO_NAMES,
    TOTAL_CASES,
)

from .commands import require

from .cli import (
    case_1,
    case_2,
    case_3,
    case_4,
    case_5,
)

from .http import case_6

from .lifecycle import (
    case_7,
    case_8,
)

from .recovery import (
    case_9,
    case_10,
)

from .audit import (
    case_11,
    case_12,
)

if TYPE_CHECKING:
    from .context import RegressionContext


Scenario = Callable[
    ["RegressionContext"],
    None,
]


def build_scenarios() -> dict[int, Scenario]:
    scenarios = {
        1: case_1,
        2: case_2,
        3: case_3,
        4: case_4,
        5: case_5,
        6: case_6,
        7: case_7,
        8: case_8,
        9: case_9,
        10: case_10,
        11: case_11,
        12: case_12,
    }

    require(
        tuple(scenarios)
        == tuple(range(1, TOTAL_CASES + 1)),
        "Invalid regression case registry.",
    )

    require(
        tuple(SCENARIO_NAMES)
        == tuple(scenarios),
        "Regression scenario names mismatch.",
    )

    require(
        all(
            callable(operation)
            for operation in scenarios.values()
        ),
        "Invalid regression scenario operation.",
    )

    return scenarios