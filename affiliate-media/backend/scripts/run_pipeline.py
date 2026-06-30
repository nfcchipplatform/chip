"""Phase 1 データ処理パイプライン CLI。"""

from __future__ import annotations

import argparse
import logging
import sys
from pathlib import Path

BACKEND_ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(BACKEND_ROOT))

from config.settings import get_settings
from src.cleaners.product_cleaner import ProductCleaner
from src.exporters.json_exporter import JsonExporter
from src.generators.openai_generator import IntroTextGenerator
from src.parsers.duga import DugaParser
from src.parsers.fanza import FanzaParser

PARSERS = {
    "fanza": FanzaParser,
    "duga": DugaParser,
}

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger(__name__)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="ASP 商品データ処理パイプライン (Phase 1)")
    parser.add_argument(
        "--source",
        choices=["fanza", "duga"],
        required=True,
        help="ASP ソース種別",
    )
    parser.add_argument(
        "--input",
        type=Path,
        required=True,
        help="入力 CSV ファイルパス",
    )
    parser.add_argument(
        "--output-dir",
        type=Path,
        default=None,
        help="JSON 出力ディレクトリ（デフォルト: data/processed）",
    )
    parser.add_argument(
        "--limit",
        type=int,
        default=None,
        help="処理件数上限（テスト用）",
    )
    parser.add_argument(
        "--chunk-size",
        type=int,
        default=10_000,
        help="大容量 CSV のチャンクサイズ",
    )
    parser.add_argument(
        "--skip-generation",
        action="store_true",
        help="OpenAI 紹介文生成をスキップ",
    )
    parser.add_argument(
        "--no-checkpoint",
        action="store_true",
        help="チェックポイント保存を無効化",
    )
    return parser.parse_args()


def run_pipeline(args: argparse.Namespace) -> None:
    settings = get_settings()
    output_dir = args.output_dir or settings.resolve_path(settings.processed_data_dir)
    checkpoint_dir = settings.resolve_path(settings.checkpoint_dir)

    parser_cls = PARSERS[args.source]
    parser = parser_cls()

    logger.info("Parsing CSV: %s (source=%s)", args.input, args.source)
    products = parser.parse_file_chunked(
        args.input,
        chunksize=args.chunk_size,
        limit=args.limit,
    )
    logger.info("Parsed %d raw products", len(products))

    cleaner = ProductCleaner()
    products = cleaner.clean(products)
    logger.info("After cleaning: %d products", len(products))

    if not args.skip_generation:
        gen_limit = settings.generation_limit or args.limit
        if gen_limit:
            products_to_generate = products[:gen_limit]
            rest = products[gen_limit:]
        else:
            products_to_generate = products
            rest = []

        checkpoint_path = None
        if not args.no_checkpoint:
            checkpoint_path = checkpoint_dir / f"{args.source}_intro_checkpoint.jsonl"

        generator = IntroTextGenerator(settings)
        logger.info("Generating intro texts for %d products...", len(products_to_generate))
        products_to_generate = generator.generate_batch(
            products_to_generate,
            checkpoint_path=checkpoint_path,
        )
        products = products_to_generate + rest

    exporter = JsonExporter(output_dir)
    exporter.export_products(products)
    exporter.export_by_genre(products)
    exporter.export_index(products)
    exporter.export_slugs(products)

    logger.info("Pipeline completed. Output: %s", output_dir)


def main() -> None:
    args = parse_args()
    if not args.input.exists():
        logger.error("Input file not found: %s", args.input)
        sys.exit(1)
    run_pipeline(args)


if __name__ == "__main__":
    main()
