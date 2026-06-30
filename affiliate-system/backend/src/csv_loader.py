"""ASP 提供 CSV の読み込み（大容量対応）。"""

from __future__ import annotations

from pathlib import Path

import pandas as pd

from config.settings import Settings
from src.utils.logger import get_logger

logger = get_logger(__name__)

# 数十万件規模を想定した pandas 読み込み設定
READ_CSV_KWARGS: dict = {
    "encoding": "utf-8",
    "dtype": str,
    "low_memory": False,
    "on_bad_lines": "warn",
}


def discover_csv_files(directory: Path) -> list[Path]:
    """指定ディレクトリ内の CSV ファイルを列挙。"""
    if not directory.exists():
        raise FileNotFoundError(f"CSV directory not found: {directory}")
    files = sorted(directory.glob("*.csv"))
    if not files:
        raise FileNotFoundError(f"No CSV files found in: {directory}")
    return files


def load_csv_files(
    settings: Settings,
    *,
    csv_paths: list[Path] | None = None,
) -> pd.DataFrame:
    """
    1 件以上の CSV を結合して DataFrame として返す。
    複数ファイルがある場合は縦方向に concat。
    """
    paths = csv_paths or discover_csv_files(settings.raw_csv_dir)
    frames: list[pd.DataFrame] = []

    for path in paths:
        logger.info("Loading CSV: %s", path.name)
        try:
            df = pd.read_csv(path, **READ_CSV_KWARGS)
        except UnicodeDecodeError:
            logger.warning("UTF-8 failed for %s, retrying with cp932", path.name)
            df = pd.read_csv(path, encoding="cp932", **{k: v for k, v in READ_CSV_KWARGS.items() if k != "encoding"})
        frames.append(df)
        logger.info("  -> %d rows loaded", len(df))

    combined = pd.concat(frames, ignore_index=True)
    logger.info("Total rows after concat: %d", len(combined))
    return combined


def rename_columns(df: pd.DataFrame, settings: Settings) -> pd.DataFrame:
    """ASP 固有カラム名を内部フィールド名にリネーム。"""
    mapping = settings.column_mapping
    available = {src: dst for src, dst in mapping.items() if src in df.columns}
    missing = set(mapping.keys()) - set(available.keys())

    if missing:
        logger.warning("Missing columns for %s: %s", settings.asp_source, sorted(missing))

    renamed = df.rename(columns=available)
    return renamed
