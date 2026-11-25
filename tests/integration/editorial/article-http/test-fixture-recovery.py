from __future__ import annotations

import argparse
import subprocess
import sys

from pathlib import Path


BASE = Path(__file__).resolve().parent

sys.path.insert(
    0,
    str(BASE / "recovery-tests"),
)

from process import RecoveryTestError
from scenarios import SCENARIOS, RecoverySuite


def main() -> int:
    parser = argparse.ArgumentParser()

    parser.add_argument(
        "--case",
        type=int,
        choices=list(SCENARIOS),
    )

    args = parser.parse_args()

    cases = (
        [args.case]
        if args.case is not None
        else list(SCENARIOS)
    )

    try:
        RecoverySuite().run(cases)

        return 0

    except (
        RecoveryTestError,
        OSError,
        KeyError,
        TypeError,
        ValueError,
        subprocess.TimeoutExpired,
    ) as error:
        print(f"FAIL: {error}", file=sys.stderr)

        print(
            "Recovery suite stopped. "
            "Inspect persistent manifest before retrying.",
            file=sys.stderr,
        )

        return 1

    except KeyboardInterrupt:
        return 130


if __name__ == "__main__":
    raise SystemExit(main())