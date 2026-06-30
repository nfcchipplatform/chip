"""ASP CSV パーサー基底クラス。"""

from __future__ import annotations

from abc import ABC, abstractmethod
from pathlib import Path

import pandas as pd

from src.models.product import Product


class BaseParser(ABC):
    """ASP ごとの CSV パーサーインターフェース。"""

    source_name: str

    @abstractmethod
    def get_column_mapping(self) -> dict[str, str]:
        """ASP カラム名 -> 内部カラム名のマッピング。"""

    @abstractmethod
    def parse_row(self, row: pd.Series) -> Product | None:
        """1行を Product に変換。無効行は None。"""

    def read_csv(self, file_path: Path, *, chunksize: int | None = None) -> pd.DataFrame | pd.io.parsers.TextFileReader:
        """エンコーディング自動判定付き CSV 読み込み。"""
        import chardet

        raw_bytes = file_path.read_bytes()[:100_000]
        detected = chardet.detect(raw_bytes)
        encoding = detected.get("encoding") or "utf-8"

        read_kwargs: dict = {
            "filepath_or_buffer": file_path,
            "encoding": encoding,
            "dtype": str,
            "keep_default_na": False,
            "low_memory": False,
        }
        if chunksize:
            read_kwargs["chunksize"] = chunksize

        return pd.read_csv(**read_kwargs)

    def parse_file(self, file_path: Path, *, limit: int | None = None) -> list[Product]:
        """CSV ファイル全体をパース。"""
        df = self.read_csv(file_path)
        if isinstance(df, pd.io.parsers.TextFileReader):
            frames = [chunk for chunk in df]
            df = pd.concat(frames, ignore_index=True)

        products: list[Product] = []
        for _, row in df.iterrows():
            product = self.parse_row(row)
            if product:
                products.append(product)
            if limit and len(products) >= limit:
                break
        return products

    def parse_file_chunked(
        self, file_path: Path, *, chunksize: int = 10_000, limit: int | None = None
    ) -> list[Product]:
        """大容量 CSV をチャンク単位でパース（数十万件対応）。"""
        reader = self.read_csv(file_path, chunksize=chunksize)
        if not isinstance(reader, pd.io.parsers.TextFileReader):
            return self.parse_file(file_path, limit=limit)

        products: list[Product] = []
        for chunk in reader:
            for _, row in chunk.iterrows():
                product = self.parse_row(row)
                if product:
                    products.append(product)
                if limit and len(products) >= limit:
                    return products
        return products
