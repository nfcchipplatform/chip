"""OpenAI API による紹介文の自動生成。"""

from __future__ import annotations

from openai import OpenAI

from config.settings import Settings
from src.models import ProductRecord
from src.utils.logger import get_logger
from src.utils.retry import RateLimiter, exponential_backoff_retry

logger = get_logger(__name__)

SYSTEM_PROMPT = """\
あなたはアダルトコンテンツ紹介メディアのライターです。
商品情報をもとに、読者の興味を引く紹介文を日本語で作成してください。

ルール:
- 200文字程度（{max_chars}文字以内）
- 具体的な魅力（出演者、ジャンル、シチュエーション）を盛り込む
- 誇大表現や虚偽の内容は避ける
- 直接的すぎる表現は控えめに、SEO を意識した自然な文体
- 紹介文のみを出力（前置き・説明不要）
"""


def _build_user_prompt(product: ProductRecord) -> str:
    genres = "、".join(product.genre) if product.genre else "不明"
    price_text = f"{product.price:,}円" if product.price else "価格未設定"
    return (
        f"タイトル: {product.title}\n"
        f"出演: {product.actress or '不明'}\n"
        f"ジャンル: {genres}\n"
        f"価格: {price_text}\n"
        f"発売日: {product.release_date or '不明'}"
    )


class ContentGenerator:
    """商品メタデータから紹介文を生成。"""

    def __init__(self, settings: Settings) -> None:
        if not settings.openai_api_key:
            raise ValueError(
                "OPENAI_API_KEY is not set. Copy .env.example to .env and configure it."
            )
        self.settings = settings
        self.client = OpenAI(api_key=settings.openai_api_key)
        self.rate_limiter = RateLimiter(settings.openai_requests_per_minute)
        self._system_prompt = SYSTEM_PROMPT.format(max_chars=settings.intro_max_chars)

    @exponential_backoff_retry(
        max_retries=5,
        base_delay=1.0,
        max_delay=60.0,
    )
    def _call_openai(self, user_prompt: str) -> str:
        self.rate_limiter.wait()
        response = self.client.chat.completions.create(
            model=self.settings.openai_model,
            messages=[
                {"role": "system", "content": self._system_prompt},
                {"role": "user", "content": user_prompt},
            ],
            max_tokens=300,
            temperature=0.7,
        )
        content = response.choices[0].message.content or ""
        return content.strip()

    def generate_intro(self, product: ProductRecord) -> str:
        prompt = _build_user_prompt(product)
        intro = self._call_openai(prompt)
        if len(intro) > self.settings.intro_max_chars:
            intro = intro[: self.settings.intro_max_chars - 1] + "…"
        return intro

    def enrich_products(
        self,
        products: list[ProductRecord],
        *,
        skip_existing: bool = True,
    ) -> list[ProductRecord]:
        """全商品に intro フィールドを付与。"""
        enriched: list[ProductRecord] = []
        total = len(products)

        for idx, product in enumerate(products, start=1):
            if skip_existing and product.intro:
                enriched.append(product)
                continue

            try:
                intro = self.generate_intro(product)
                enriched.append(product.model_copy(update={"intro": intro}))
                if idx % 10 == 0 or idx == total:
                    logger.info("Generated intro: %d / %d", idx, total)
            except Exception:
                logger.exception("Failed to generate intro for %s", product.product_id)
                enriched.append(product)

        return enriched
