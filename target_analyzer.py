"""取得した記事群から、特典ツール開発に最適なターゲット記事を選定する。"""

from __future__ import annotations

import re
from typing import Any

# ホットキーワード: トレンド・需要が高いテーマ
HOT_KEYWORDS: dict[str, int] = {
    "自動化": 12,
    "AI": 10,
    "ChatGPT": 8,
    "SNS": 7,
    "運用": 7,
    "稼ぐ": 8,
    "副業": 9,
    "ツール": 8,
    "マネタイズ": 7,
    "効率化": 6,
    "量産": 7,
    "テンプレ": 5,
    "Python": 6,
    "GAS": 6,
    "スプレッドシート": 5,
}

# 手作業・反復作業キーワード: 特典ツール化しやすいノウハウ
MANUAL_WORK_KEYWORDS: dict[str, int] = {
    "手作業": 10,
    "コピペ": 9,
    "手動": 8,
    "毎日": 5,
    "定型文": 6,
    "テンプレ": 5,
    "収集": 7,
    "まとめる": 6,
    "一括": 7,
    "転記": 8,
    "入力": 6,
    "繰り返し": 7,
    "面倒": 5,
    "時短": 6,
    "ワークフロー": 6,
    "手順": 4,
    "チェックリスト": 4,
}

ENGAGEMENT_WEIGHTS = {
    "likes_high": 5,   # 100いいね以上
    "likes_mid": 3,    # 30いいね以上
    "likes_low": 1,    # 1いいね以上
}


def _count_keyword_hits(title: str, keywords: dict[str, int]) -> tuple[int, list[str]]:
    lowered = title.lower()
    score = 0
    matched: list[str] = []

    for keyword, weight in keywords.items():
        if keyword.lower() in lowered:
            score += weight
            matched.append(keyword)

    return score, matched


def _engagement_bonus(article: dict[str, Any]) -> int:
    likes = article.get("likes")
    if likes is None:
        return 0
    if likes >= 100:
        return ENGAGEMENT_WEIGHTS["likes_high"]
    if likes >= 30:
        return ENGAGEMENT_WEIGHTS["likes_mid"]
    if likes >= 1:
        return ENGAGEMENT_WEIGHTS["likes_low"]
    return 0


def score_article(article: dict[str, Any]) -> dict[str, Any]:
    """記事1件のスコアとマッチ理由を算出する。"""
    title = article.get("title", "")
    hot_score, hot_matches = _count_keyword_hits(title, HOT_KEYWORDS)
    manual_score, manual_matches = _count_keyword_hits(title, MANUAL_WORK_KEYWORDS)
    engagement = _engagement_bonus(article)

    # 手作業系キーワードを重視（特典ツール化のしやすさ）
    total_score = hot_score + (manual_score * 1.5) + engagement

    return {
        **article,
        "score": round(total_score, 1),
        "hot_matches": hot_matches,
        "manual_matches": manual_matches,
        "engagement_bonus": engagement,
    }


def analyze_targets(articles: list[dict[str, Any]]) -> dict[str, Any]:
    """
    記事リストをスコアリングし、最適ターゲット Top 1 を返す。

    Returns:
        {
            "top_target": {...},
            "ranked": [...],  # スコア降順の全記事
        }
    """
    if not articles:
        raise ValueError("分析対象の記事がありません。")

    ranked = sorted(
        (score_article(article) for article in articles),
        key=lambda item: item["score"],
        reverse=True,
    )

    return {
        "top_target": ranked[0],
        "ranked": ranked,
    }


def format_score_reason(target: dict[str, Any]) -> str:
    """スコア根拠を人間が読める文字列に整形する。"""
    parts: list[str] = []

    if target.get("hot_matches"):
        parts.append(f"ホットKW: {', '.join(target['hot_matches'])}")
    if target.get("manual_matches"):
        parts.append(f"手作業系KW: {', '.join(target['manual_matches'])}")
    if target.get("engagement_bonus"):
        parts.append(f"エンゲージメント加点: +{target['engagement_bonus']}")

    return " / ".join(parts) if parts else "総合スコアが最高"
