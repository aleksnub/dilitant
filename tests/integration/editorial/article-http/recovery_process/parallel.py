"""Координатор параллельного Recovery."""

from __future__ import annotations

from concurrent.futures import (
    ThreadPoolExecutor,
)

from dataclasses import dataclass

from typing import (
    Any,
    Callable,
    Iterable,
    TYPE_CHECKING,
)

from .commands import require

from .isolation import resource_keys


if TYPE_CHECKING:
    from . import RecoveryProcess


@dataclass(frozen=True, slots=True)
class RecoveryJob:
    executor: RecoveryProcess

    operation: Callable[
        [RecoveryProcess],
        Any,
    ]


def validate_jobs(
    jobs: tuple[RecoveryJob, ...],
) -> None:
    from . import RecoveryProcess

    require(
        bool(jobs),
        "Empty parallel Recovery job registry.",
    )

    executors: set[str] = set()
    resources: set[str] = set()

    for job in jobs:
        require(
            isinstance(job, RecoveryJob)
            and isinstance(
                job.executor,
                RecoveryProcess,
            )
            and callable(job.operation),
            "Invalid parallel Recovery job.",
        )

        config = job.executor.config

        require(
            config.is_isolated,
            "Parallel Recovery requires verified isolation.",
        )

        require(
            config.executor_id not in executors,
            "Duplicate Recovery executor ID.",
        )

        executors.add(
            config.executor_id
        )

        current = resource_keys(
            config
        )

        conflict = resources.intersection(
            current
        )

        require(
            not conflict,
            "Parallel Recovery resource conflict: "
            + ", ".join(sorted(conflict)),
        )

        resources.update(
            current
        )


def _execute_job(
    job: RecoveryJob,
) -> Any:
    executor = job.executor

    with executor.session():
        executor.assert_container()
        executor.verify_restart_readiness()

        result = job.operation(
            executor
        )

        executor.assert_container()

        return result


def run_parallel(
    jobs: Iterable[RecoveryJob],
    *,
    max_workers: int | None = None,
) -> tuple[Any, ...]:
    registry = tuple(jobs)

    validate_jobs(
        registry
    )

    if max_workers is None:
        max_workers = len(registry)

    require(
        type(max_workers) is int
        and max_workers > 0,
        "Invalid Recovery parallelism limit.",
    )

    workers = min(
        max_workers,
        len(registry),
    )

    with ThreadPoolExecutor(
        max_workers=workers,
        thread_name_prefix="recovery",
    ) as pool:
        futures = [
            pool.submit(
                _execute_job,
                job,
            )
            for job in registry
        ]

        return tuple(
            future.result()
            for future in futures
        )