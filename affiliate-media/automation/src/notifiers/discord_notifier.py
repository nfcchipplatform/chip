"""Discord Webhook 通知。"""

from __future__ import annotations

import logging
from datetime import datetime

import requests

from config.settings import Settings, get_settings
from src.utils.retry import with_exponential_backoff

logger = logging.getLogger(__name__)


class DiscordNotifier:
    def __init__(self, settings: Settings | None = None):
        self.settings = settings or get_settings()
        self._send = with_exponential_backoff(
            max_attempts=self.settings.retry_max_attempts,
            base_delay=self.settings.retry_base_delay,
            max_delay=self.settings.retry_max_delay,
        )(self._send_impl)

    @property
    def enabled(self) -> bool:
        return bool(self.settings.discord_webhook_url)

    def notify_success(
        self,
        *,
        product_id: str,
        title: str,
        tweet_text: str,
        tweet_id: str | None = None,
    ) -> None:
        if not self.enabled or not self.settings.discord_notify_success:
            return

        fields = [
            {"name": "作品ID", "value": product_id, "inline": True},
            {"name": "タイトル", "value": title[:256], "inline": False},
        ]
        if tweet_id:
            fields.append(
                {
                    "name": "Tweet",
                    "value": f"https://x.com/i/status/{tweet_id}",
                    "inline": False,
                }
            )

        self._send(
            embed={
                "title": "✅ X 投稿成功",
                "color": 0x57F287,
                "fields": fields,
                "description": tweet_text[:500],
                "timestamp": datetime.utcnow().isoformat(),
            }
        )

    def notify_error(self, *, message: str, detail: str = "") -> None:
        if not self.enabled:
            return

        self._send(
            embed={
                "title": "❌ X Bot エラー",
                "color": 0xED4245,
                "description": message[:2000],
                "fields": [{"name": "詳細", "value": detail[:1000] or "—", "inline": False}],
                "timestamp": datetime.utcnow().isoformat(),
            }
        )

    def notify_info(self, *, message: str) -> None:
        if not self.enabled:
            return

        self._send(
            embed={
                "title": "ℹ️ X Bot",
                "color": 0x5865F2,
                "description": message[:2000],
                "timestamp": datetime.utcnow().isoformat(),
            }
        )

    def _send(self, *, embed: dict) -> None:
        if not self.enabled:
            logger.debug("Discord webhook not configured, skipping notification")
            return
        try:
            self._send_impl(embed=embed)
        except Exception as exc:
            logger.error("Discord notification failed: %s", exc)

    def _send_impl(self, *, embed: dict) -> None:
        response = requests.post(
            self.settings.discord_webhook_url,
            json={"embeds": [embed]},
            timeout=15,
        )
        if response.status_code == 429:
            response.raise_for_status()
        response.raise_for_status()
        logger.info("Discord notification sent")
