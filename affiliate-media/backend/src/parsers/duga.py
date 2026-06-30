"""DUGA ASP CSV パーサー。"""

from __future__ import annotations

import re
import unicodedata

import pandas as pd

from src.models.product import Product
from src.parsers.base import BaseParser


class DugaParser(BaseParser):
    """
    DUGA アフィリエイト商品 CSV パーサー。

    DUGA の商品フィード CSV 形式に対応。
    """

    source_name = "duga"

    COLUMN_ALIASES: dict[str, list[str]] = {
        "id": ["product_id", "商品ID", "品番", "content_id"],
        "title": ["title", "タイトル", "商品名"],
        "actresses": ["actress", "女優名", "出演者", "performer"],
        "genres": ["genre", "ジャンル", "category"],
        "thumbnail_url": ["thumbnail", "thumbnail_url", "画像URL", "image_url"],
        "affiliate_url": ["affiliate_url", "link", "アフィリエイトURL", "url"],
        "price": ["price", "価格"],
        "release_date": ["release_date", "発売日", "配信日"],
        "description": ["description", "商品説明", "summary"],
    }

    def get_column_mapping(self) -> dict[str, str]:
        return {k: v[0] for k, v in self.COLUMN_ALIASES.items()}

    def _resolve_column(self, row: pd.Series, field: str) -> str | None:
        for alias in self.COLUMN_ALIASES.get(field, []):
            if alias in row.index and str(row[alias]).strip():
                return str(row[alias]).strip()
        return None

    @staticmethod
    def _make_slug(source: str, product_id: str, title: str) -> str:
        base = re.sub(r"[^\w\-]", "-", f"{product_id}-{title[:30]}")
        base = re.sub(r"-+", "-", base).strip("-").lower()
        return f"{source}-{base}"[:120]

    @staticmethod
    def _parse_price(value: str | None) -> int | None:
        if not value:
            return None
        digits = re.sub(r"[^\d]", "", value)
        return int(digits) if digits else None

    def parse_row(self, row: pd.Series) -> Product | None:
        product_id = self._resolve_column(row, "id")
        title = self._resolve_column(row, "title")
        thumbnail_url = self._resolve_column(row, "thumbnail_url")
        affiliate_url = self._resolve_column(row, "affiliate_url")

        if not all([product_id, title, thumbnail_url, affiliate_url]):
            return None

        actresses_raw = self._resolve_column(row, "actresses")
        genres_raw = self._resolve_column(row, "genres")

        return Product(
            id=f"duga-{product_id}",
            source=self.source_name,
            title=unicodedata.normalize("NFKC", title),
            actresses=actresses_raw or [],
            genres=genres_raw or [],
            thumbnail_url=thumbnail_url,
            affiliate_url=affiliate_url,
            price=self._parse_price(self._resolve_column(row, "price")),
            release_date=self._resolve_column(row, "release_date"),
            description=self._resolve_column(row, "description"),
            slug=self._make_slug(self.source_name, product_id, title),
        )
