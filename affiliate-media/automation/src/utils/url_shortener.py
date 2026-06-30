"""URL 短縮（Bitly 対応、未設定時は原文 URL）。"""

from __future__ import annotations

import logging

import requests

from config.settings import Settings, get_settings
from src.utils.retry import with_exponential_backoff

logger = logging.getLogger(__name__)


class UrlShortener:
    def __init__(self, settings: Settings | None = None):
        self.settings = settings or get_settings()
        self._shorten = with_exponential_backoff(
            max_attempts=self.settings.retry_max_attempts,
            base_delay=self.settings.retry_base_delay,
            max_delay=self.settings.retry_max_delay,
        )(self._shorten_impl)

    def shorten(self, url: str) -> str:
        if not self.settings.bitly_access_token:
            return url
        try:
            return self._shorten(url)
        except Exception as exc:
            logger.warning("URL shortening failed, using original: %s", exc)
            return url

    def _shorten_impl(self, url: str) -> str:
        response = requests.post(
            "https://api-ssl.bitly.com/v4/shorten",
            headers={
                "Authorization": f"Bearer {self.settings.bitly_access_token}",
                "Content-Type": "application/json",
            },
            json={"long_url": url},
            timeout=15,
        )
        if response.status_code == 429:
            response.raise_for_status()
        response.raise_for_status()
        data = response.json()
        short_url = data.get("link", url)
        logger.info("Shortened URL: %s -> %s", url[:50], short_url)
        return short_url
