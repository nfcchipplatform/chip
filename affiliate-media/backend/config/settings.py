"""アプリケーション設定（環境変数ベース）。"""

from pathlib import Path

from pydantic import Field
from pydantic_settings import BaseSettings, SettingsConfigDict

BACKEND_ROOT = Path(__file__).resolve().parent.parent


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=BACKEND_ROOT / ".env",
        env_file_encoding="utf-8",
        extra="ignore",
    )

    openai_api_key: str = Field(default="", alias="OPENAI_API_KEY")
    openai_model: str = Field(default="gpt-4o-mini", alias="OPENAI_MODEL")
    openai_max_tokens: int = Field(default=512, alias="OPENAI_MAX_TOKENS")
    openai_temperature: float = Field(default=0.7, alias="OPENAI_TEMPERATURE")

    batch_size: int = Field(default=50, alias="BATCH_SIZE")
    max_workers: int = Field(default=4, alias="MAX_WORKERS")
    generation_limit: int = Field(default=0, alias="GENERATION_LIMIT")

    raw_data_dir: Path = Field(default=BACKEND_ROOT / "data" / "raw", alias="RAW_DATA_DIR")
    processed_data_dir: Path = Field(
        default=BACKEND_ROOT / "data" / "processed", alias="PROCESSED_DATA_DIR"
    )
    checkpoint_dir: Path = Field(
        default=BACKEND_ROOT / "data" / "checkpoints", alias="CHECKPOINT_DIR"
    )

    openai_rpm: int = Field(default=500, alias="OPENAI_RPM")
    retry_max_attempts: int = Field(default=5, alias="RETRY_MAX_ATTEMPTS")
    retry_base_delay: float = Field(default=1.0, alias="RETRY_BASE_DELAY")
    retry_max_delay: float = Field(default=60.0, alias="RETRY_MAX_DELAY")

    def resolve_path(self, path: Path) -> Path:
        if path.is_absolute():
            return path
        return BACKEND_ROOT / path


def get_settings() -> Settings:
    return Settings()
