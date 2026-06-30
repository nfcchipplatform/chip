"""X 投稿文の組み立て。"""

from __future__ import annotations

import re

from src.models.product import Product

TWEET_MAX_LENGTH = 280


def _genre_to_hashtag(genre: str) -> str:
    tag = re.sub(r"[^\w\u3040-\u309F\u30A0-\u30FF\u4E00-\u9FFF]", "", genre)
    return f"#{tag}" if tag else ""


def build_hashtags(product: Product, *, max_tags: int = 3) -> list[str]:
    tags: list[str] = []
    for genre in product.genres:
        tag = _genre_to_hashtag(genre)
        if tag and tag not in tags:
            tags.append(tag)
        if len(tags) >= max_tags:
            break

    source_tag = "#FANZA" if product.source == "fanza" else "#DUGA"
    if source_tag not in tags:
        tags.append(source_tag)

    return tags[: max_tags + 1]


def build_post_text(
    product: Product,
    *,
    short_url: str,
    site_url: str = "",
    max_hashtags: int = 3,
) -> str:
    hashtags = build_hashtags(product, max_tags=max_hashtags)
    hashtag_str = " ".join(hashtags)

    summary = product.intro_text or product.description or ""
    if summary:
        summary = summary.replace("\n", " ").strip()

    actress_str = ""
    if product.actresses:
        actress_str = f"出演: {product.actresses[0]}"
        if len(product.actresses) > 1:
            actress_str += " 他"

    price_str = f"¥{product.price:,}" if product.price else ""

    # 自サイトリンク（任意）
    detail_url = ""
    if site_url:
        detail_url = f"{site_url.rstrip('/')}/products/{product.slug}"

    lines = [f"【おすすめ】{product.title}"]
    if actress_str:
        lines.append(actress_str)
    if price_str:
        lines.append(price_str)

    # 固定パーツ（URL + ハッシュタグ）の長さを確保してサマリーをトリム
    footer_parts = [short_url]
    if detail_url:
        footer_parts.append(detail_url)
    footer_parts.append(hashtag_str)
    footer = "\n".join(footer_parts)

    header = "\n".join(lines)
    available_for_summary = TWEET_MAX_LENGTH - len(header) - len(footer) - 2  # newlines

    if summary and available_for_summary > 20:
        if len(summary) > available_for_summary:
            summary = summary[: available_for_summary - 1].rstrip() + "…"
        lines.append(summary)

    lines.append(footer)
    text = "\n".join(lines)

    if len(text) > TWEET_MAX_LENGTH:
        # 最終フォールバック: タイトルを短縮
        max_title = TWEET_MAX_LENGTH - len(footer) - 10
        lines[0] = f"【おすすめ】{product.title[:max_title]}…"
        text = "\n".join([lines[0], footer])

    return text[:TWEET_MAX_LENGTH]
