"""指数バックオフ付きリトライとレートリミッター。"""

from __future__ import annotations

import random
import time
from collections.abc import Callable
from functools import wraps
from typing import ParamSpec, TypeVar

from openai import APIConnectionError, APITimeoutError, RateLimitError

from .logger import get_logger

logger = get_logger(__name__)

P = ParamSpec("P")
T = TypeVar("T")

RETRYABLE_EXCEPTIONS = (
    RateLimitError,
    APIConnectionError,
    APITimeoutError,
    ConnectionError,
    TimeoutError,
)


class RateLimiter:
    """トークンバケット方式の簡易レートリミッター。"""

    def __init__(self, requests_per_minute: int) -> None:
        self.min_interval = 60.0 / max(requests_per_minute, 1)
        self._last_request_at = 0.0

    def wait(self) -> None:
        now = time.monotonic()
        elapsed = now - self._last_request_at
        if elapsed < self.min_interval:
            time.sleep(self.min_interval - elapsed)
        self._last_request_at = time.monotonic()


def exponential_backoff_retry(
    *,
    max_retries: int = 5,
    base_delay: float = 1.0,
    max_delay: float = 60.0,
    retryable: tuple[type[Exception], ...] = RETRYABLE_EXCEPTIONS,
) -> Callable[[Callable[P, T]], Callable[P, T]]:
    """指数バックオフ + ジッターでリトライするデコレータ。"""

    def decorator(func: Callable[P, T]) -> Callable[P, T]:
        @wraps(func)
        def wrapper(*args: P.args, **kwargs: P.kwargs) -> T:
            last_exc: Exception | None = None
            for attempt in range(max_retries + 1):
                try:
                    return func(*args, **kwargs)
                except retryable as exc:
                    last_exc = exc
                    if attempt >= max_retries:
                        break
                    delay = min(base_delay * (2**attempt), max_delay)
                    jitter = random.uniform(0, delay * 0.1)
                    sleep_time = delay + jitter
                    logger.warning(
                        "Retry %d/%d after %.1fs (%s: %s)",
                        attempt + 1,
                        max_retries,
                        sleep_time,
                        type(exc).__name__,
                        exc,
                    )
                    time.sleep(sleep_time)
            assert last_exc is not None
            raise last_exc

        return wrapper

    return decorator
