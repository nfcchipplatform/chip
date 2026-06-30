"""自動化 Bot 設定。"""

from pathlib import Path

from pydantic import Field
from pydantic_settings import BaseSettings, SettingsConfigDict

AUTOMATION_ROOT = Path(__file__).resolve().parent.parent


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=AUTOMATION_ROOT / ".env",
        env_file_encoding="utf-8",
        extra="ignore",
    )

    # X API
    x_api_key: str = Field(default="", alias="X_API_KEY")
    x_api_secret: str = Field(default="", alias="X_API_SECRET")
    x_access_token: str = Field(default="", alias="X_ACCESS_TOKEN")
    x_access_token_secret: str = Field(default="", alias="X_ACCESS_TOKEN_SECRET")

    # Discord
    discord_webhook_url: str = Field(default="", alias="DISCORD_WEBHOOK_URL")
    discord_notify_success: bool = Field(default=True, alias="DISCORD_NOTIFY_SUCCESS")

    # Data paths
    products_json_path: Path = Field(
        default=AUTOMATION_ROOT / "../backend/data/processed/products.json",
        alias="PRODUCTS_JSON_PATH",
    )
    history_file: Path = Field(
        default=AUTOMATION_ROOT / "data/post_history.json",
        alias="HISTORY_FILE",
    )
    log_dir: Path = Field(default=AUTOMATION_ROOT / "data/logs", alias="LOG_DIR")

    # Posting
    post_mode: str = Field(default="random", alias="POST_MODE")
    post_interval_minutes: int = Field(default=180, alias="POST_INTERVAL_MINUTES")
    new_release_days: int = Field(default=30, alias="NEW_RELEASE_DAYS")
    min_price: int = Field(default=2000, alias="MIN_PRICE")
    max_hashtags: int = Field(default=3, alias="MAX_HASHTAGS")
    site_url: str = Field(default="", alias="SITE_URL")
    bitly_access_token: str = Field(default="", alias="BITLY_ACCESS_TOKEN")

    # Retry
    retry_max_attempts: int = Field(default=5, alias="RETRY_MAX_ATTEMPTS")
    retry_base_delay: float = Field(default=2.0, alias="RETRY_BASE_DELAY")
    retry_max_delay: float = Field(default=120.0, alias="RETRY_MAX_DELAY")

    repost_cooldown_days: int = Field(default=30, alias="REPOST_COOLDOWN_DAYS")

    def resolve_path(self, path: Path) -> Path:
        if path.is_absolute():
            return path
        return (AUTOMATION_ROOT / path).resolve()


def get_settings() -> Settings:
    return Settings()
