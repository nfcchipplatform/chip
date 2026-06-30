"""pandas ベースのデータクレンジング。"""

from __future__ import annotations

import logging
import re
from urllib.parse import urlparse

import pandas as pd

from src.models.product import Product

logger = logging.getLogger(__name__)


class ProductCleaner:
    """Product リストの抽出・クレンジング・重複排除。"""

    REQUIRED_FIELDS = ("title", "thumbnail_url", "affiliate_url")

    def __init__(self, *, min_title_length: int = 3, dedupe_by: str = "id"):
        self.min_title_length = min_title_length
        self.dedupe_by = dedupe_by

    @staticmethod
    def _is_valid_url(url: str) -> bool:
        try:
            parsed = urlparse(url)
            return parsed.scheme in ("http", "https") and bool(parsed.netloc)
        except Exception:
            return False

    @staticmethod
    def _clean_text(text: str | None) -> str | None:
        if not text:
            return None
        cleaned = re.sub(r"\s+", " ", str(text)).strip()
        return cleaned or None

    @staticmethod
    def _clean_list(items: list[str]) -> list[str]:
        seen: set[str] = set()
        result: list[str] = []
        for item in items:
            normalized = re.sub(r"\s+", " ", item).strip()
            if normalized and normalized not in seen:
                seen.add(normalized)
                result.append(normalized)
        return result

    def clean_product(self, product: Product) -> Product | None:
        title = self._clean_text(product.title)
        if not title or len(title) < self.min_title_length:
            return None

        thumbnail_url = self._clean_text(product.thumbnail_url)
        affiliate_url = self._clean_text(product.affiliate_url)

        if not thumbnail_url or not affiliate_url:
            return None
        if not self._is_valid_url(thumbnail_url) or not self._is_valid_url(affiliate_url):
            return None

        return product.model_copy(
            update={
                "title": title,
                "thumbnail_url": thumbnail_url,
                "affiliate_url": affiliate_url,
                "actresses": self._clean_list(product.actresses),
                "genres": self._clean_list(product.genres),
                "description": self._clean_text(product.description),
            }
        )

    def clean(self, products: list[Product]) -> list[Product]:
        cleaned: list[Product] = []
        seen_keys: set[str] = set()

        for product in products:
            result = self.clean_product(product)
            if not result:
                continue

            key = getattr(result, self.dedupe_by, result.id)
            if key in seen_keys:
                continue
            seen_keys.add(key)
            cleaned.append(result)

        logger.info("Cleaned %d -> %d products", len(products), len(cleaned))
        return cleaned

    def to_dataframe(self, products: list[Product]) -> pd.DataFrame:
        records = [p.to_frontend_dict() for p in products]
        return pd.DataFrame(records)

    def from_dataframe(self, df: pd.DataFrame) -> list[Product]:
        products: list[Product] = []
        for record in df.to_dict(orient="records"):
            try:
                products.append(Product(**record))
            except Exception as exc:
                logger.debug("Skip invalid row: %s", exc)
        return products
