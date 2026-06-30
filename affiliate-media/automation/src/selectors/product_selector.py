"""投稿する作品の選定ロジック。"""

from __future__ import annotations

import logging
import random
from datetime import datetime, timedelta

from src.history.post_history import PostHistory
from src.models.product import Product

logger = logging.getLogger(__name__)


class ProductSelector:
    """条件に基づいて投稿対象作品を選定。"""

    MODES = ("random", "new", "high_price")

    def __init__(
        self,
        *,
        mode: str = "random",
        new_release_days: int = 30,
        min_price: int = 2000,
        history: PostHistory | None = None,
    ):
        if mode not in self.MODES:
            raise ValueError(f"Invalid mode: {mode}. Choose from {self.MODES}")
        self.mode = mode
        self.new_release_days = new_release_days
        self.min_price = min_price
        self.history = history

    def _filter_by_history(self, products: list[Product]) -> list[Product]:
        if not self.history:
            return products
        return [p for p in products if not self.history.is_recently_posted(p.id)]

    def _filter_new(self, products: list[Product]) -> list[Product]:
        cutoff = datetime.utcnow() - timedelta(days=self.new_release_days)
        result: list[Product] = []
        for product in products:
            if not product.release_date:
                continue
            try:
                release = datetime.fromisoformat(product.release_date.replace("/", "-"))
            except ValueError:
                try:
                    release = datetime.strptime(product.release_date, "%Y-%m-%d")
                except ValueError:
                    continue
            if release >= cutoff:
                result.append(product)
        return result

    def _filter_high_price(self, products: list[Product]) -> list[Product]:
        return [p for p in products if p.price is not None and p.price >= self.min_price]

    def select(self, products: list[Product]) -> Product | None:
        candidates = self._filter_by_history(products)

        if self.mode == "new":
            candidates = self._filter_new(candidates)
            logger.info("New release filter: %d candidates", len(candidates))
        elif self.mode == "high_price":
            candidates = self._filter_high_price(candidates)
            logger.info("High price filter (>=%d): %d candidates", self.min_price, len(candidates))

        if not candidates:
            logger.warning("No candidates found for mode=%s", self.mode)
            return None

        selected = random.choice(candidates)
        logger.info("Selected product: %s (%s)", selected.id, selected.title)
        return selected
