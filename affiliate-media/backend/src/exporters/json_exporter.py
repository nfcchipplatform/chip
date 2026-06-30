"""JSON 形式でのデータ出力。"""

from __future__ import annotations

import json
import logging
from collections import defaultdict
from datetime import datetime
from pathlib import Path

from src.models.product import Product

logger = logging.getLogger(__name__)


class JsonExporter:
    """フロントエンド向け JSON エクスポート。"""

    def __init__(self, output_dir: Path):
        self.output_dir = output_dir
        self.output_dir.mkdir(parents=True, exist_ok=True)

    def export_products(self, products: list[Product], filename: str = "products.json") -> Path:
        output_path = self.output_dir / filename
        payload = {
            "generated_at": datetime.utcnow().isoformat(),
            "count": len(products),
            "products": [p.to_frontend_dict() for p in products],
        }
        with output_path.open("w", encoding="utf-8") as f:
            json.dump(payload, f, ensure_ascii=False, indent=2)
        logger.info("Exported %d products to %s", len(products), output_path)
        return output_path

    def export_by_genre(self, products: list[Product]) -> Path:
        by_genre: dict[str, list[dict]] = defaultdict(list)
        for product in products:
            if not product.genres:
                by_genre["未分類"].append(product.to_frontend_dict())
            else:
                for genre in product.genres:
                    by_genre[genre].append(product.to_frontend_dict())

        output_path = self.output_dir / "products_by_genre.json"
        payload = {
            "generated_at": datetime.utcnow().isoformat(),
            "genres": {genre: items for genre, items in sorted(by_genre.items())},
        }
        with output_path.open("w", encoding="utf-8") as f:
            json.dump(payload, f, ensure_ascii=False, indent=2)
        logger.info("Exported genre index to %s", output_path)
        return output_path

    def export_index(self, products: list[Product]) -> Path:
        """Next.js ISR 用の軽量インデックス。"""
        index = [
            {
                "id": p.id,
                "slug": p.slug,
                "title": p.title,
                "genres": p.genres,
                "thumbnail_url": p.thumbnail_url,
                "intro_text": p.intro_text,
            }
            for p in products
        ]
        output_path = self.output_dir / "index.json"
        with output_path.open("w", encoding="utf-8") as f:
            json.dump(
                {
                    "generated_at": datetime.utcnow().isoformat(),
                    "count": len(index),
                    "items": index,
                },
            f,
            ensure_ascii=False,
            indent=2,
        )
        return output_path

    def export_slugs(self, products: list[Product]) -> Path:
        slugs = [{"slug": p.slug, "id": p.id} for p in products]
        output_path = self.output_dir / "slugs.json"
        with output_path.open("w", encoding="utf-8") as f:
            json.dump({"count": len(slugs), "slugs": slugs}, f, ensure_ascii=False, indent=2)
        return output_path
