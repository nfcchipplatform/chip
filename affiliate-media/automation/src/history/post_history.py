"""投稿済み作品の履歴管理。"""

from __future__ import annotations

import json
import logging
from datetime import datetime, timedelta
from pathlib import Path

logger = logging.getLogger(__name__)


class PostHistory:
    """重複投稿防止のための投稿履歴。"""

    def __init__(self, history_file: Path, *, cooldown_days: int = 30):
        self.history_file = history_file
        self.cooldown_days = cooldown_days
        self._records: dict[str, str] = {}
        self._load()

    def _load(self) -> None:
        if not self.history_file.exists():
            return
        with self.history_file.open(encoding="utf-8") as f:
            data = json.load(f)
        self._records = data.get("posted", {})

    def _save(self) -> None:
        self.history_file.parent.mkdir(parents=True, exist_ok=True)
        with self.history_file.open("w", encoding="utf-8") as f:
            json.dump(
                {"posted": self._records, "updated_at": datetime.utcnow().isoformat()},
                f,
                ensure_ascii=False,
                indent=2,
            )

    def is_recently_posted(self, product_id: str) -> bool:
        posted_at = self._records.get(product_id)
        if not posted_at:
            return False
        try:
            dt = datetime.fromisoformat(posted_at)
        except ValueError:
            return False
        return datetime.utcnow() - dt < timedelta(days=self.cooldown_days)

    def filter_available(self, product_ids: list[str]) -> list[str]:
        return [pid for pid in product_ids if not self.is_recently_posted(pid)]

    def mark_posted(self, product_id: str) -> None:
        self._records[product_id] = datetime.utcnow().isoformat()
        self._save()
        logger.info("Marked product %s as posted", product_id)

    @property
    def posted_count(self) -> int:
        return len(self._records)
