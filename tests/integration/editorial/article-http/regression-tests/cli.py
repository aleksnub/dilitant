"""CLI-регрессия редакционного функционала.

HF-M10-V2.1 — CLI Bootstrap.
HF-M10-V2.2 — Article Identity.
HF-M10-V2.3 — Article Query.
HF-M10-V2.4 — Article Catalog.
HF-M10-V2.5 — Article Visibility.
"""

from __future__ import annotations

import re

from typing import TYPE_CHECKING

from .config import (
    ROOT,
    EDITORIAL,
    NETWORK,
    PRODUCTION_CONTAINER,
    REQUIRED_ENVIRONMENT,
    CLI_TESTS,
)

from .commands import (
    require,
    require_default_environment,
)

if TYPE_CHECKING:
    from .context import RegressionContext


def resolve_production_image(
    context: RegressionContext,
) -> str:
    context.assert_shared_context()

    require_default_environment(
        context.config
    )

    image = context.execute([
        "docker",
        "inspect",
        "--format",
        "{{.Image}}",
        PRODUCTION_CONTAINER,
    ]).strip()

    require(
        re.fullmatch(
            r"sha256:[0-9a-f]{64}",
            image,
        ) is not None,
        "Invalid production image identity.",
    )

    return image


def cli_command(
    context: RegressionContext,
    filename: str,
) -> str:
    context.assert_shared_context()

    require_default_environment(
        context.config
    )

    require(
        isinstance(filename, str)
        and re.fullmatch(
            r"[a-zA-Z0-9_-]+\.php",
            filename,
        ) is not None,
        "Invalid CLI test filename.",
    )

    require(
        filename in CLI_TESTS.values(),
        "Unregistered CLI test filename.",
    )

    require(
        re.fullmatch(
            r"sha256:[0-9a-f]{64}",
            context.production_image,
        ) is not None,
        "Invalid CLI image identity.",
    )

    for name in REQUIRED_ENVIRONMENT:
        require(
            bool(context.config.env.get(name)),
            f"Required environment variable "
            f"missing: {name}",
        )

    args = [
        "docker",
        "run",
        "--rm",
        "--network",
        NETWORK,
        "--mount",
        (
            "type=bind,"
            f"source={ROOT / 'src/wordpress'},"
            "target=/var/www/html,readonly"
        ),
        "--mount",
        (
            "type=bind,"
            f"source={ROOT / 'tests'},"
            "target=/tests,readonly"
        ),
        "--tmpfs",
        "/var/www/dilitant-private",
    ]

    for name in REQUIRED_ENVIRONMENT:
        args.extend([
            "-e",
            name,
        ])

    args.extend([
        "--entrypoint",
        "php",
        context.production_image,
        f"{EDITORIAL}/{filename}",
    ])

    return context.execute(
        args,
        require_container=False,
    )


def cli_test(
    context: RegressionContext,
    filename: str,
) -> None:
    output = cli_command(
        context,
        filename,
    )

    context.report(
        output
    )

    require(
        bool(output.strip()),
        f"No test output: {filename}",
    )

    require(
        re.search(
            r"^\s*(?:FAIL|ERROR|Fatal error)\s*:",
            output,
            re.MULTILINE | re.IGNORECASE,
        ) is None,
        f"Failure reported by {filename}",
    )

    failures = re.findall(
        r"^\s*Failed:\s*(\d+)\s*$",
        output,
        re.MULTILINE | re.IGNORECASE,
    )

    require(
        all(
            int(value) == 0
            for value in failures
        ),
        f"Failed assertions in {filename}",
    )

    context.assert_shared_context()


def case_1(
    context: RegressionContext,
) -> None:
    cli_test(
        context,
        CLI_TESTS[1],
    )


def case_2(
    context: RegressionContext,
) -> None:
    cli_test(
        context,
        CLI_TESTS[2],
    )


def case_3(
    context: RegressionContext,
) -> None:
    cli_test(
        context,
        CLI_TESTS[3],
    )


def case_4(
    context: RegressionContext,
) -> None:
    cli_test(
        context,
        CLI_TESTS[4],
    )


def case_5(
    context: RegressionContext,
) -> None:
    cli_test(
        context,
        CLI_TESTS[5],
    )