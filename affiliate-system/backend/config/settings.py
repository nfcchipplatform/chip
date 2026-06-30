"""アプリケーション設定（環境変数 + デフォルト値）。"""

from __future__ import annotations

from pathlib import Path
from typing import Literal

from pydantic import Field
from pydantic_settings import BaseSettings, SettingsConfigDict

BACKEND_ROOT = Path(__file__).resolve().parent.parent


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=BACKEND_ROOT / ".env",
        env_file_encoding="utf-8",
        extra="ignore",
    )

    # OpenAI
    openai_api_key: str = ""
    openai_model: str = "gpt-4o-mini"

    # 処理
    batch_size: int = 50
    max_workers: int = 4
    generate_intro: bool = True
    intro_max_chars: int = 200

    # リトライ
    openai_max_retries: int = 5
    openai_retry_base_delay: float = 1.0
    openai_retry_max_delay: float = 60.0
    openai_requests_per_minute: int = 500

    # パス
    raw_csv_dir: Path = Field(default=BACKEND_ROOT / "data" / "raw")
    processed_json_dir: Path = Field(default=BACKEND_ROOT / "data" / "processed")

    # ASP ソース: fanza | duga
    asp_source: Literal["fanza", "duga"] = "fanza"

    # 処理件数上限（0 = 無制限、テスト用）
    limit: int = 0

    @property
    def column_mapping(self) -> dict[str, str]:
        """ASP ごとの CSV カラム名 → 内部フィールド名のマッピング。"""
        mappings: dict[str, dict[str, str]] = {
            "fanza": {
                "content_id": "product_id",
                "title": "title",
                "actress": "actress",
                "genre": "genre",
                "image_url": "thumbnail_url",
                "affiliate_url": "affiliate_link",
                "price": "price",
                "release_date": "release_date",
            },
            "duga": {
                "product_id": "product_id",
                "title": "title",
                "actress_name": "actress",
                "category": "genre",
                "thumbnail": "thumbnail_url",
                "affiliate_link": "affiliate_link",
                "price": "price",
                "release_date": "release_date",
            },
        }
        return mappings[self.asp_source]


def get_settings() -> Settings:
    return Settings()
