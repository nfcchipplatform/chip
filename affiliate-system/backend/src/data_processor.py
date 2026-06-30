"""pandas によるデータ抽出・クレンジング。"""

from __future__ import annotations

import re
import unicodedata
from urllib.parse import urlparse

import pandas as pd

from config.settings import Settings
from src.models import ProductRecord
from src.utils.logger import get_logger

logger = get_logger(__name__)

REQUIRED_FIELDS = ("product_id", "title", "affiliate_link")
OPTIONAL_FIELDS = ("actress", "genre", "thumbnail_url", "price", "release_date")


def _normalize_text(value: object) -> str:
    if value is None or (isinstance(value, float) and pd.isna(value)):
        return ""
    text = unicodedata.normalize("NFKC", str(value)).strip()
    return re.sub(r"\s+", " ", text)


def _normalize_url(value: object) -> str:
    text = _normalize_text(value)
    if not text:
        return ""
    parsed = urlparse(text)
    if parsed.scheme not in ("http", "https"):
        return ""
    return text


def _normalize_actress(value: object) -> str:
    text = _normalize_text(value)
    if not text:
        return ""
    for sep in ["|", "／", "/", "、", ","]:
        if sep in text:
            names = [n.strip() for n in text.split(sep) if n.strip()]
            return "、".join(names)
    return text


def _normalize_price(value: object) -> int | None:
    text = _normalize_text(value)
    if not text:
        return None
    digits = re.sub(r"[^\d]", "", text)
    return int(digits) if digits else None


def _make_slug(product_id: str, title: str) -> str:
    """URL 用スラッグ（product_id を優先、フォールバックで title）。"""
    base = product_id or title
    slug = unicodedata.normalize("NFKC", base).lower()
    slug = re.sub(r"[^\w\-]+", "-", slug, flags=re.UNICODE)
    slug = re.sub(r"-{2,}", "-", slug).strip("-")
    return slug[:120] or "item"


def cleanse_dataframe(df: pd.DataFrame, settings: Settings) -> pd.DataFrame:
    """必要カラムの抽出・正規化・重複排除。"""
    working = df.copy()

    for field in REQUIRED_FIELDS + OPTIONAL_FIELDS:
        if field not in working.columns:
            working[field] = ""

    # テキスト正規化
    for col in ("product_id", "title", "genre", "release_date"):
        working[col] = working[col].map(_normalize_text)
    working["actress"] = working["actress"].map(_normalize_actress)

    working["thumbnail_url"] = working["thumbnail_url"].map(_normalize_url)
    working["affiliate_link"] = working["affiliate_link"].map(_normalize_url)
    working["price"] = working["price"].map(_normalize_price)

    # 必須フィールド欠損行を除外
    before = len(working)
    for col in REQUIRED_FIELDS:
        working = working[working[col].astype(str).str.len() > 0]
    logger.info("Dropped %d rows with missing required fields", before - len(working))

    # product_id 重複排除（最新行を優先）
    before = len(working)
    working = working.drop_duplicates(subset=["product_id"], keep="last")
    logger.info("Dropped %d duplicate product_id rows", before - len(working))

    if settings.limit > 0:
        working = working.head(settings.limit)
        logger.info("Limited to %d rows (settings.limit)", settings.limit)

    working["slug"] = working.apply(
        lambda row: _make_slug(row["product_id"], row["title"]),
        axis=1,
    )

    return working.reset_index(drop=True)


def dataframe_to_records(df: pd.DataFrame) -> list[ProductRecord]:
    """DataFrame → ProductRecord リスト。"""
    records: list[ProductRecord] = []
    for row in df.to_dict(orient="records"):
        records.append(
            ProductRecord(
                product_id=row["product_id"],
                title=row["title"],
                actress=row.get("actress", ""),
                genre=row.get("genre", []),
                thumbnail_url=row.get("thumbnail_url", ""),
                affiliate_link=row["affiliate_link"],
                price=row.get("price"),
                release_date=row.get("release_date") or None,
                slug=row.get("slug", ""),
            )
        )
    return records
