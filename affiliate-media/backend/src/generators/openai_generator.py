"""OpenAI API による紹介文自動生成。"""

from __future__ import annotations

import json
import logging
import time
from pathlib import Path

from openai import OpenAI

from config.settings import Settings, get_settings
from src.models.product import Product
from src.utils.retry import with_exponential_backoff

logger = logging.getLogger(__name__)

SYSTEM_PROMPT = """\
あなたはアダルト動画メディアサイトのライターです。
商品情報をもとに、SEO を意識しつつ読者の興味を引く紹介文を日本語で作成してください。

ルール:
- 200文字前後（180〜220文字）
- 煽りすぎず、自然で読みやすい文体
- 女優名・ジャンル・作品の魅力を具体的に含める
- 誇大表現や虚偽の内容は避ける
- 紹介文のみを出力（前置き・説明不要）
"""


class IntroTextGenerator:
    """OpenAI API で商品紹介文を生成。"""

    def __init__(self, settings: Settings | None = None):
        self.settings = settings or get_settings()
        self.client = OpenAI(api_key=self.settings.openai_api_key)
        self._last_request_at: float = 0.0
        self._min_interval = 60.0 / max(self.settings.openai_rpm, 1)
        self._call_api = with_exponential_backoff(
            max_attempts=self.settings.retry_max_attempts,
            base_delay=self.settings.retry_base_delay,
            max_delay=self.settings.retry_max_delay,
        )(self._call_api_impl)

    def _build_user_prompt(self, product: Product) -> str:
        actresses = "、".join(product.actresses) if product.actresses else "不明"
        genres = "、".join(product.genres) if product.genres else "不明"
        price = f"{product.price:,}円" if product.price else "不明"

        parts = [
            f"タイトル: {product.title}",
            f"女優: {actresses}",
            f"ジャンル: {genres}",
            f"価格: {price}",
        ]
        if product.description:
            parts.append(f"商品説明: {product.description[:300]}")
        return "\n".join(parts)

    def _throttle(self) -> None:
        elapsed = time.monotonic() - self._last_request_at
        if elapsed < self._min_interval:
            time.sleep(self._min_interval - elapsed)
        self._last_request_at = time.monotonic()

    def _call_api_impl(self, product: Product) -> str:
        self._throttle()

        response = self.client.chat.completions.create(
            model=self.settings.openai_model,
            messages=[
                {"role": "system", "content": SYSTEM_PROMPT},
                {"role": "user", "content": self._build_user_prompt(product)},
            ],
            max_tokens=self.settings.openai_max_tokens,
            temperature=self.settings.openai_temperature,
        )
        content = response.choices[0].message.content or ""
        return content.strip()

    def generate(self, product: Product) -> str:
        if not self.settings.openai_api_key:
            raise ValueError("OPENAI_API_KEY が設定されていません")

        text = self._call_api(product)
        # 200文字前後にトリム（超過時）
        if len(text) > 250:
            text = text[:220].rstrip() + "…"
        return text

    def generate_batch(
        self,
        products: list[Product],
        *,
        checkpoint_path: Path | None = None,
        skip_existing: bool = True,
    ) -> list[Product]:
        """バッチ生成。チェックポイントで中断再開可能。"""
        completed: dict[str, str] = {}

        if checkpoint_path and checkpoint_path.exists():
            with checkpoint_path.open(encoding="utf-8") as f:
                for line in f:
                    record = json.loads(line)
                    completed[record["id"]] = record["intro_text"]
            logger.info("Loaded %d checkpoint entries", len(completed))

        results: list[Product] = []
        for product in products:
            if skip_existing and product.intro_text:
                results.append(product)
                continue

            if skip_existing and product.id in completed:
                results.append(product.model_copy(update={"intro_text": completed[product.id]}))
                continue

            try:
                intro_text = self.generate(product)
                updated = product.model_copy(update={"intro_text": intro_text})
                results.append(updated)

                if checkpoint_path:
                    checkpoint_path.parent.mkdir(parents=True, exist_ok=True)
                    with checkpoint_path.open("a", encoding="utf-8") as f:
                        f.write(
                            json.dumps({"id": product.id, "intro_text": intro_text}, ensure_ascii=False)
                            + "\n"
                        )
            except Exception as exc:
                logger.error("Failed to generate intro for %s: %s", product.id, exc)
                results.append(product)

        return results
