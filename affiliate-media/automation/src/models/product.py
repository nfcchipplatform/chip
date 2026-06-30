"""商品データモデル。"""

from __future__ import annotations

from datetime import datetime
from typing import Any

from pydantic import BaseModel, Field, field_validator


class Product(BaseModel):
    id: str
    source: str
    title: str
    actresses: list[str] = Field(default_factory=list)
    genres: list[str] = Field(default_factory=list)
    thumbnail_url: str
    affiliate_url: str
    price: int | None = None
    release_date: str | None = None
    description: str | None = None
    intro_text: str | None = None
    slug: str
    updated_at: str = Field(default_factory=lambda: datetime.utcnow().isoformat())

    @field_validator("actresses", "genres", mode="before")
    @classmethod
    def normalize_list(cls, value: Any) -> list[str]:
        if value is None:
            return []
        if isinstance(value, list):
            return [str(v).strip() for v in value if str(v).strip()]
        if isinstance(value, str):
            for sep in ["|", "、", ",", "／", "/"]:
                if sep in value:
                    return [p.strip() for p in value.split(sep) if p.strip()]
            return [value.strip()] if value.strip() else []
        return [str(value).strip()] if str(value).strip() else []


class ProductsPayload(BaseModel):
    generated_at: str
    count: int
    products: list[Product]
