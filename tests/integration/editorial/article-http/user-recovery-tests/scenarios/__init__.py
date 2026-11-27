from __future__ import annotations

from functools import partial
from typing import Callable, TYPE_CHECKING

from .creation import (
    scenario_intent,
    scenario_unconfirmed,
    scenario_partial_creation,
)

from .conflicts import (
    scenario_ambiguity,
    scenario_substitution,
    scenario_sql_failure,
)

from .cleanup import scenario_cleanup

from .durability import (
    scenario_idempotence,
    scenario_container,
    scenario_audit,
)


if TYPE_CHECKING:
    from suite import UserRecoverySuite


def build_scenarios(
    suite: UserRecoverySuite,
) -> dict[str, Callable[[], None]]:
    return {
        "HF-U-R01": partial(scenario_intent, suite),
        "HF-U-R02": partial(scenario_unconfirmed, suite),
        "HF-U-R03": partial(scenario_partial_creation, suite),
        "HF-U-R04": partial(scenario_ambiguity, suite),
        "HF-U-R05": partial(scenario_substitution, suite),
        "HF-U-R06": partial(scenario_sql_failure, suite),
        "HF-U-R07": partial(scenario_cleanup, suite),
        "HF-U-R08": partial(scenario_idempotence, suite),
        "HF-U-R09": partial(scenario_container, suite),
        "HF-U-R10": partial(scenario_audit, suite),
    }