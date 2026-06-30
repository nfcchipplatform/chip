"""Phase 1 出力 JSON から商品を読み込む。"""

from __future__ import annotations

import json
import logging
from pathlib import Path

from src.models.product import Product, ProductsPayload

logger = logging.getLogger(__name__)


def load_products(json_path: Path) -> list[Product]:
    path = json_path.resolve()
    if not path.exists():
        raise FileNotFoundError(f"Products JSON not found: {path}")

    with path.open(encoding="utf-8") as f:
        raw = json.load(f)

    payload = ProductsPayload(**raw)
    logger.info("Loaded %d products from %s", len(payload.products), path)
    return payload.products
