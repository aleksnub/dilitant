"""Единый пакет полной регрессии HF-M10-V2."""

from .commands import RegressionError
from .context import RegressionContext
from .scenarios import build_scenarios
from .suite import FixtureRegression, main

__all__ = [
    "RegressionError",
    "RegressionContext",
    "FixtureRegression",
    "build_scenarios",
    "main",
]