"""処理済みデータの JSON 出力。"""

from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path

from config.settings import Settings
from src.models import ProcessedCatalog, ProductRecord
from src.utils.logger import get_logger

logger = get_logger(__name__)


def _ensure_dir(path: Path) -> None:
    path.mkdir(parents=True, exist_ok=True)


def export_catalog(
    products: list[ProductRecord],
    settings: Settings,
    *,
    output_filename: str | None = None,
) -> Path:
    """
    フロントエンド用カタログ JSON を出力。

    Returns:
        出力ファイルの Path
    """
    _ensure_dir(settings.processed_json_dir)

    timestamp = datetime.now(timezone.utc).strftime("%Y%m%d_%H%M%S")
    filename = output_filename or f"catalog_{settings.asp_source}_{timestamp}.json"
    output_path = settings.processed_json_dir / filename

    catalog = ProcessedCatalog(
        source=settings.asp_source,
        generated_at=datetime.now(timezone.utc).isoformat(),
        total_count=len(products),
        products=products,
    )

    with output_path.open("w", encoding="utf-8") as f:
        json.dump(catalog.to_dict(), f, ensure_ascii=False, indent=2)

    logger.info("Exported %d products to %s", len(products), output_path)

    # フロントエンドが固定パスで参照できるよう latest シンボリックリンク的コピー
    latest_path = settings.processed_json_dir / f"catalog_{settings.asp_source}_latest.json"
    with latest_path.open("w", encoding="utf-8") as f:
        json.dump(catalog.to_dict(), f, ensure_ascii=False, indent=2)
    logger.info("Updated latest catalog: %s", latest_path)

    return output_path


def export_by_genre(
    products: list[ProductRecord],
    settings: Settings,
) -> dict[str, Path]:
    """ジャンル別に分割 JSON を出力（Phase 2 の ISR 用）。"""
    genre_dir = settings.processed_json_dir / "genres"
    _ensure_dir(genre_dir)

    genre_map: dict[str, list[ProductRecord]] = {}
    for product in products:
        genres = product.genre or ["未分類"]
        for genre in genres:
            genre_map.setdefault(genre, []).append(product)

    exported: dict[str, Path] = {}
    for genre, items in genre_map.items():
        safe_name = genre.replace("/", "_").replace("\\", "_")
        path = genre_dir / f"{safe_name}.json"
        payload = {
            "genre": genre,
            "total_count": len(items),
            "products": [p.to_frontend_dict() for p in items],
        }
        with path.open("w", encoding="utf-8") as f:
            json.dump(payload, f, ensure_ascii=False, indent=2)
        exported[genre] = path

    logger.info("Exported %d genre files to %s", len(exported), genre_dir)
    return exported
