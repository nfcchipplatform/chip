#!/usr/bin/env python3
"""
Phase 1 エントリーポイント: ASP CSV → クレンジング → 紹介文生成 → JSON 出力

Usage:
    cd affiliate-system/backend
    pip install -r requirements.txt
    cp .env.example .env   # OPENAI_API_KEY を設定

    # FANZA CSV を処理（紹介文生成なし・テスト）
    python -m src.main --asp fanza --skip-intro

    # 本番（紹介文生成あり）
    python -m src.main --asp fanza

    # 件数制限（開発用）
    python -m src.main --asp fanza --limit 100 --skip-intro
"""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

# backend/ を PYTHONPATH に追加
BACKEND_ROOT = Path(__file__).resolve().parent.parent
if str(BACKEND_ROOT) not in sys.path:
    sys.path.insert(0, str(BACKEND_ROOT))

from config.settings import Settings, get_settings
from src.content_generator import ContentGenerator
from src.csv_loader import load_csv_files, rename_columns
from src.data_processor import cleanse_dataframe, dataframe_to_records
from src.json_exporter import export_by_genre, export_catalog
from src.utils.logger import get_logger

logger = get_logger(__name__)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="ASP 商品データ処理パイプライン (Phase 1)")
    parser.add_argument(
        "--asp",
        choices=["fanza", "duga"],
        default=None,
        help="ASP ソース（デフォルト: 環境変数 ASP_SOURCE）",
    )
    parser.add_argument(
        "--csv",
        type=Path,
        nargs="+",
        default=None,
        help="処理する CSV ファイルパス（省略時は data/raw/ 内を自動検出）",
    )
    parser.add_argument(
        "--limit",
        type=int,
        default=None,
        help="処理件数上限（0 = 無制限）",
    )
    parser.add_argument(
        "--skip-intro",
        action="store_true",
        help="OpenAI 紹介文生成をスキップ",
    )
    parser.add_argument(
        "--split-genre",
        action="store_true",
        help="ジャンル別 JSON も出力",
    )
    parser.add_argument(
        "--output",
        type=str,
        default=None,
        help="出力ファイル名（省略時はタイムスタンプ付き）",
    )
    return parser.parse_args()


def run_pipeline(settings: Settings, args: argparse.Namespace) -> Path:
    logger.info("=== Phase 1 Pipeline Start (source=%s) ===", settings.asp_source)

    # 1. CSV 読み込み
    df = load_csv_files(settings, csv_paths=args.csv)
    df = rename_columns(df, settings)

    # 2. クレンジング
    df = cleanse_dataframe(df, settings)
    products = dataframe_to_records(df)
    logger.info("Valid products after cleansing: %d", len(products))

    # 3. 紹介文生成
    generate = settings.generate_intro and not args.skip_intro
    if generate:
        generator = ContentGenerator(settings)
        products = generator.enrich_products(products)
    else:
        logger.info("Skipping intro generation")

    # 4. JSON 出力
    output_path = export_catalog(products, settings, output_filename=args.output)

    if args.split_genre:
        export_by_genre(products, settings)

    logger.info("=== Phase 1 Pipeline Complete ===")
    return output_path


def main() -> None:
    args = parse_args()
    settings = get_settings()

    if args.asp:
        settings.asp_source = args.asp
    if args.limit is not None:
        settings.limit = args.limit

    try:
        output_path = run_pipeline(settings, args)
        print(f"\n✅ Output: {output_path}")
    except FileNotFoundError as exc:
        logger.error("%s", exc)
        sys.exit(1)
    except ValueError as exc:
        logger.error("%s", exc)
        sys.exit(1)


if __name__ == "__main__":
    main()
