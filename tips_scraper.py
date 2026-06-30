"""Tips.jp 検索結果・ランキングから記事情報を取得するスクレイパー。"""

from __future__ import annotations

import re
from typing import Any
from urllib.parse import quote

import requests
from bs4 import BeautifulSoup

DEFAULT_KEYWORDS = ("AI", "自動化", "副業", "X運用")
SEARCH_URL = "https://tips.jp/search"
RANKING_URL = "https://tips.jp/search/ranking"
REQUEST_TIMEOUT = 20
USER_AGENT = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
    "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
)

MOCK_ARTICLES: list[dict[str, Any]] = [
    {
        "title": "【完全自動化】X運用を1日10分に圧縮するAIツール運用術",
        "url": "https://tips.jp/u/example/a/x-auto",
        "price": "¥2,980",
        "likes": 128,
        "keyword": "X運用",
        "source": "mock",
    },
    {
        "title": "ChatGPT×副業｜コピペ作業をゼロにする記事量産ワークフロー",
        "url": "https://tips.jp/u/example/a/side-hustle",
        "price": "¥1,480",
        "likes": 95,
        "keyword": "副業",
        "source": "mock",
    },
    {
        "title": "初心者でも月5万！AI自動化で稼ぐスプレッドシート連携ノウハウ",
        "url": "https://tips.jp/u/example/a/ai-money",
        "price": "¥3,280",
        "likes": 210,
        "keyword": "AI",
        "source": "mock",
    },
    {
        "title": "SNS運用の手作業を一括削除｜Pythonで実現する運用自動化テンプレ",
        "url": "https://tips.jp/u/example/a/sns-auto",
        "price": "¥1,980",
        "likes": 67,
        "keyword": "自動化",
        "source": "mock",
    },
    {
        "title": "ブログ記事をAIで量産する前に知るべきSEO見出し設計の全手順",
        "url": "https://tips.jp/u/example/a/blog-seo",
        "price": "¥980",
        "likes": 44,
        "keyword": "AI",
        "source": "mock",
    },
]


def _build_search_url(keyword: str) -> str:
    """検索URLを組み立てる（Tipsは keyword パラメータを使用）。"""
    encoded = quote(keyword)
    return f"{SEARCH_URL}?keyword={encoded}&sort=2&page=1"


def _normalize_url(href: str) -> str:
    if href.startswith("http"):
        return href
    return f"https://tips.jp{href}"


def _extract_price(article: BeautifulSoup) -> str | None:
    price_box = article.select_one("div.price-box div.price")
    if not price_box:
        return None

    text = price_box.get_text(strip=True)
    if not text:
        return None

    digits = re.sub(r"[^\d]", "", text)
    if digits:
        return f"¥{int(digits):,}"
    return text


def _extract_likes(article: BeautifulSoup) -> int | None:
    likes_el = article.select_one("span.clap-count-total")
    if not likes_el:
        return None

    text = likes_el.get_text(strip=True)
    if not text:
        return None

    try:
        return int(re.sub(r"[^\d]", "", text))
    except ValueError:
        return None


def _parse_articles(html: str, keyword: str) -> list[dict[str, Any]]:
    soup = BeautifulSoup(html, "html.parser")
    articles: list[dict[str, Any]] = []

    for article in soup.select("article.d-flex"):
        title_el = article.select_one("h2.list-title")
        link_el = article.select_one("a.stretched-link[href*='/a/']")

        if not title_el or not link_el:
            continue

        title = title_el.get_text(strip=True)
        url = _normalize_url(link_el["href"])

        if not title or "/a/" not in url:
            continue

        articles.append(
            {
                "title": title,
                "url": url,
                "price": _extract_price(article),
                "likes": _extract_likes(article),
                "keyword": keyword,
                "source": "tips.jp",
            }
        )

    return articles


def _fetch_html(url: str, session: requests.Session) -> str:
    response = session.get(url, timeout=REQUEST_TIMEOUT)
    response.raise_for_status()
    return response.text


def _deduplicate_articles(articles: list[dict[str, Any]]) -> list[dict[str, Any]]:
    seen_urls: set[str] = set()
    unique: list[dict[str, Any]] = []

    for article in articles:
        url = article.get("url", "")
        if not url or url in seen_urls:
            continue
        seen_urls.add(url)
        unique.append(article)

    return unique


def get_mock_articles() -> list[dict[str, Any]]:
    """フォールバック用のモックトレンド記事を返す。"""
    return [dict(article) for article in MOCK_ARTICLES]


def scrape_search(keyword: str, session: requests.Session | None = None) -> list[dict[str, Any]]:
    """単一キーワードの検索結果を取得する。"""
    owns_session = session is None
    session = session or requests.Session()
    session.headers.setdefault("User-Agent", USER_AGENT)

    try:
        html = _fetch_html(_build_search_url(keyword), session)
        return _parse_articles(html, keyword)
    except (requests.RequestException, ValueError):
        return []
    finally:
        if owns_session:
            session.close()


def scrape_trending_articles(
    keywords: tuple[str, ...] | list[str] | None = None,
) -> tuple[list[dict[str, Any]], bool]:
    """
    複数キーワードでTipsを検索し、記事リストを返す。

    Returns:
        (articles, used_mock): used_mock が True の場合はモックデータを使用。
    """
    keywords = tuple(keywords or DEFAULT_KEYWORDS)
    collected: list[dict[str, Any]] = []

    with requests.Session() as session:
        session.headers.update({"User-Agent": USER_AGENT})

        for keyword in keywords:
            try:
                html = _fetch_html(_build_search_url(keyword), session)
                collected.extend(_parse_articles(html, keyword))
            except (requests.RequestException, ValueError):
                continue

        if not collected:
            try:
                html = _fetch_html(RANKING_URL, session)
                collected.extend(_parse_articles(html, "ranking"))
            except (requests.RequestException, ValueError):
                collected = []

    articles = _deduplicate_articles(collected)

    if articles:
        return articles, False

    return get_mock_articles(), True
