"""run_scheduler CLI entrypoint。"""

from __future__ import annotations

import argparse
import logging
import sys
import time
from datetime import datetime
from pathlib import Path

import schedule

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
    parser = argparse.ArgumentParser(description="X Bot スケジューラ（常駐）")
    parser.add_argument("--interval", type=int, default=None, help="投稿間隔（分）")
    parser.add_argument("--run-on-start", action="store_true", help="起動直後に 1 回実行")
    parser.add_argument("--dry-run", action="store_true", help="ドライラン")
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    settings = get_settings()
    interval = args.interval or settings.post_interval_minutes

    logger.info("Scheduler started. Interval: %d minutes", interval)

    def job() -> None:
        logger.info("Scheduled job triggered at %s", datetime.now().isoformat())
        run_once(dry_run=args.dry_run, settings=settings)

    schedule.every(interval).minutes.do(job)

    if args.run_on_start:
        job()

    while True:
        schedule.run_pending()
        time.sleep(30)


if __name__ == "__main__":
    main()
