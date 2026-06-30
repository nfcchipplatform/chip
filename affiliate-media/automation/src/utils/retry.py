"""指数バックオフ付きリトライ。"""

from __future__ import annotations

import functools
import logging
import random
import time
from collections.abc import Callable
from typing import ParamSpec, TypeVar

import requests

logger = logging.getLogger(__name__)

P = ParamSpec("P")
T = TypeVar("T")

DEFAULT_RETRYABLE = (
    requests.exceptions.ConnectionError,
    requests.exceptions.Timeout,
    requests.exceptions.HTTPError,
)


def with_exponential_backoff(
    *,
    max_attempts: int = 5,
    base_delay: float = 2.0,
    max_delay: float = 120.0,
    retryable_exceptions: tuple[type[Exception], ...] = DEFAULT_RETRYABLE,
) -> Callable[[Callable[P, T]], Callable[P, T]]:
    def decorator(func: Callable[P, T]) -> Callable[P, T]:
        @functools.wraps(func)
        def wrapper(*args: P.args, **kwargs: P.kwargs) -> T:
            last_exception: Exception | None = None

            for attempt in range(1, max_attempts + 1):
                try:
                    return func(*args, **kwargs)
                except retryable_exceptions as exc:
                    last_exception = exc
                    if attempt >= max_attempts:
                        break

                    delay = min(base_delay * (2 ** (attempt - 1)), max_delay)
                    jitter = random.uniform(0, delay * 0.1)
                    sleep_time = delay + jitter

                    logger.warning(
                        "%s failed (attempt %d/%d): %s. Retrying in %.2fs",
                        func.__name__,
                        attempt,
                        max_attempts,
                        exc,
                        sleep_time,
                    )
                    time.sleep(sleep_time)

            assert last_exception is not None
            raise last_exception

        return wrapper

    return decorator
