"""X (Twitter) API 投稿。"""

from __future__ import annotations

import logging

import tweepy

from config.settings import Settings, get_settings

logger = logging.getLogger(__name__)


class XPoster:
    """X API v2 によるツイート投稿。"""

    def __init__(self, settings: Settings | None = None):
        self.settings = settings or get_settings()
        self._client: tweepy.Client | None = None

    @property
    def configured(self) -> bool:
        return all(
            [
                self.settings.x_api_key,
                self.settings.x_api_secret,
                self.settings.x_access_token,
                self.settings.x_access_token_secret,
            ]
        )

    def _get_client(self) -> tweepy.Client:
        if self._client is None:
            if not self.configured:
                raise ValueError(
                    "X API credentials not configured. Set X_API_KEY, X_API_SECRET, "
                    "X_ACCESS_TOKEN, X_ACCESS_TOKEN_SECRET in .env"
                )
            self._client = tweepy.Client(
                consumer_key=self.settings.x_api_key,
                consumer_secret=self.settings.x_api_secret,
                access_token=self.settings.x_access_token,
                access_token_secret=self.settings.x_access_token_secret,
                wait_on_rate_limit=True,
            )
        return self._client

    def post(self, text: str) -> str:
        """ツイートを投稿し、tweet ID を返す。"""
        client = self._get_client()

        last_error: Exception | None = None
        max_attempts = self.settings.retry_max_attempts

        for attempt in range(1, max_attempts + 1):
            try:
                response = client.create_tweet(text=text)
                tweet_id = str(response.data["id"])
                logger.info("Tweet posted successfully: %s", tweet_id)
                return tweet_id
            except tweepy.TooManyRequests as exc:
                last_error = exc
                logger.warning("Rate limit hit (attempt %d/%d)", attempt, max_attempts)
                if attempt >= max_attempts:
                    break
            except tweepy.TwitterServerError as exc:
                last_error = exc
                logger.warning("Twitter server error (attempt %d/%d): %s", attempt, max_attempts, exc)
                if attempt >= max_attempts:
                    break
            except tweepy.TweepyException as exc:
                logger.error("Tweet failed: %s", exc)
                raise

        assert last_error is not None
        raise last_error
