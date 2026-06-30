"""run_bot CLI entrypoint。"""

from __future__ import annotations

import argparse
import logging
import sys
from pathlib import Path

AUTOMATION_ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(AUTOMATION_ROOT))

from config.settings import get_settings
from src.bot.runner import run_once

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger(__name__)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="X 自動投稿 Bot (Phase 3)")
    parser.add_argument(
        "--mode",
        choices=["random", "new", "high_price"],
        default=None,
        help="作品選定モード（省略時: .env の POST_MODE）",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="実際には投稿せず、投稿文を表示",
    )
    parser.add_argument(
        "--products-json",
        type=Path,
        default=None,
        help="商品 JSON パス",
    )
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    settings = get_settings()
    if args.products_json:
        settings.products_json_path = args.products_json

    success = run_once(dry_run=args.dry_run, mode=args.mode, settings=settings)
    sys.exit(0 if success else 1)


if __name__ == "__main__":
    main()
