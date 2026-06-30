"""FANZA (DMM Affiliate) CSV パーサー。"""

from __future__ import annotations

import re
import unicodedata

import pandas as pd

from src.models.product import Product
from src.parsers.base import BaseParser


class FanzaParser(BaseParser):
    """
    FANZA アフィリエイト商品 CSV パーサー。

    FANZA の商品データ CSV はバージョンによりカラム名が異なるため、
    代表的なカラム名をマッピングで吸収する。
    """

    source_name = "fanza"

    COLUMN_ALIASES: dict[str, list[str]] = {
        "id": ["商品ID", "content_id", "cid", "品番"],
        "title": ["タイトル", "title", "商品名"],
        "actresses": ["女優名", "actress", "出演者", "女優"],
        "genres": ["ジャンル", "genre", "カテゴリ"],
        "thumbnail_url": ["サムネイルURL", "imageURL", "パッケージ画像URL", "sampleImageURL"],
        "affiliate_url": ["アフィリエイトURL", "affiliateURL", "URL", "商品URL"],
        "price": ["価格", "price", "定価"],
        "release_date": ["発売日", "release_date", "配信開始日"],
        "description": ["商品説明", "description", "概要"],
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
            id=f"fanza-{product_id}",
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
