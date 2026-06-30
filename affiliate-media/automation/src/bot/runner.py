"""Bot 実行ロジック。"""

from __future__ import annotations

import logging

from config.settings import Settings, get_settings
from src.builders.post_builder import build_post_text
from src.history.post_history import PostHistory
from src.loaders.product_loader import load_products
from src.notifiers.discord_notifier import DiscordNotifier
from src.posters.x_poster import XPoster
from src.selectors.product_selector import ProductSelector
from src.utils.url_shortener import UrlShortener

logger = logging.getLogger(__name__)


def run_once(*, dry_run: bool = False, mode: str | None = None, settings: Settings | None = None) -> bool:
    settings = settings or get_settings()
    notifier = DiscordNotifier(settings)
    json_path = settings.resolve_path(settings.products_json_path)

    try:
        products = load_products(json_path)
    except FileNotFoundError as exc:
        logger.error("%s", exc)
        notifier.notify_error(message="商品 JSON が見つかりません", detail=str(exc))
        return False

    history = PostHistory(
        settings.resolve_path(settings.history_file),
        cooldown_days=settings.repost_cooldown_days,
    )

    selector = ProductSelector(
        mode=mode or settings.post_mode,
        new_release_days=settings.new_release_days,
        min_price=settings.min_price,
        history=history,
    )

    product = selector.select(products)
    if not product:
        msg = f"投稿対象なし (mode={selector.mode}, history={history.posted_count}件)"
        logger.warning(msg)
        notifier.notify_info(message=msg)
        return False

    shortener = UrlShortener(settings)
    short_url = shortener.shorten(product.affiliate_url)

    tweet_text = build_post_text(
        product,
        short_url=short_url,
        site_url=settings.site_url,
        max_hashtags=settings.max_hashtags,
    )

    logger.info("Tweet text (%d chars):\n%s", len(tweet_text), tweet_text)

    if dry_run:
        print("--- DRY RUN ---")
        print(tweet_text)
        print("--- END ---")
        notifier.notify_info(message=f"[DRY RUN] {product.title}\n{tweet_text[:500]}")
        return True

    poster = XPoster(settings)
    if not poster.configured:
        msg = "X API 認証情報が未設定です"
        logger.error(msg)
        notifier.notify_error(message=msg)
        return False

    try:
        tweet_id = poster.post(tweet_text)
        history.mark_posted(product.id)
        notifier.notify_success(
            product_id=product.id,
            title=product.title,
            tweet_text=tweet_text,
            tweet_id=tweet_id,
        )
        return True
    except Exception as exc:
        logger.exception("Post failed")
        notifier.notify_error(message="X 投稿に失敗しました", detail=str(exc))
        return False
