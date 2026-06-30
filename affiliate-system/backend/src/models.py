"""商品データの型定義。"""

from __future__ import annotations

from datetime import datetime
from typing import Any

from pydantic import BaseModel, Field, field_validator


class ProductRecord(BaseModel):
    """フロントエンドへ渡す正規化済み商品レコード。"""

    product_id: str
    title: str
    actress: str = ""
    genre: list[str] = Field(default_factory=list)
    thumbnail_url: str = ""
    affiliate_link: str
    price: int | None = None
    release_date: str | None = None
    intro: str = ""
    slug: str = ""
    updated_at: str = Field(default_factory=lambda: datetime.utcnow().isoformat() + "Z")

    @field_validator("genre", mode="before")
    @classmethod
    def normalize_genre(cls, value: Any) -> list[str]:
        if value is None or (isinstance(value, float) and str(value) == "nan"):
            return []
        if isinstance(value, list):
            return [str(g).strip() for g in value if str(g).strip()]
        text = str(value).strip()
        if not text:
            return []
        # 区切り文字: 全角/半角スラッシュ、カンマ、パイプ
        for sep in ["|", "／", "/", "、", ","]:
            if sep in text:
                return [g.strip() for g in text.split(sep) if g.strip()]
        return [text]

    def to_frontend_dict(self) -> dict[str, Any]:
        return self.model_dump(mode="json")


class ProcessedCatalog(BaseModel):
    """フロントエンド用カタログ JSON のルート構造。"""

    version: str = "1.0"
    source: str
    generated_at: str
    total_count: int
    products: list[ProductRecord]

    def to_dict(self) -> dict[str, Any]:
        return {
            "version": self.version,
            "source": self.source,
            "generated_at": self.generated_at,
            "total_count": self.total_count,
            "products": [p.to_frontend_dict() for p in self.products],
        }
