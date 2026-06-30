"""選定記事のタイトルから、購入特典ツールのアイデアを自動生成する。"""

from __future__ import annotations

import re
from typing import Any

IDEA_RULES: list[dict[str, Any]] = [
    {
        "patterns": (r"X\b", r"Twitter", r"ツイート", r"ポスト"),
        "idea": (
            "特典アイデア: 特定キーワードのポストを自動収集し、"
            "スプレッドシートにまとめる Python スクリプト"
        ),
        "tech_stack": "Python + Google Sheets API",
        "difficulty": "Cursor開発: 中",
    },
    {
        "patterns": (r"ブログ", r"記事", r"SEO", r"見出し", r"コンテンツ"),
        "idea": (
            "特典アイデア: SEOキーワードから見出し構成を自動生成する"
            "プロンプト＆スクリプト"
        ),
        "tech_stack": "Python + OpenAI API / プロンプトテンプレ",
        "difficulty": "Cursor開発: 易〜中",
    },
    {
        "patterns": (r"Instagram", r"インスタ", r"Threads", r"SNS", r"運用"),
        "idea": (
            "特典アイデア: 投稿ネタ・ハッシュタグを日次で提案し、"
            "投稿予約CSVを出力する GAS / Python ツール"
        ),
        "tech_stack": "GAS または Python",
        "difficulty": "Cursor開発: 中",
    },
    {
        "patterns": (r"スプレッドシート", r"Excel", r"集計", r"転記", r"コピペ"),
        "idea": (
            "特典アイデア: 複数シート間のデータ転記・集計を"
            "ワンクリック化する GAS マクロ＋操作マニュアル"
        ),
        "tech_stack": "Google Apps Script",
        "difficulty": "Cursor開発: 易",
    },
    {
        "patterns": (r"メール", r"問い合わせ", r"返信", r"テンプレ", r"定型文"),
        "idea": (
            "特典アイデア: 問い合わせ内容を分類し、最適な返信文案を"
            "自動生成する Python / ChatGPT 連携ツール"
        ),
        "tech_stack": "Python + OpenAI API",
        "difficulty": "Cursor開発: 中",
    },
    {
        "patterns": (r"副業", r"稼ぐ", r"マネタイズ", r"収益"),
        "idea": (
            "特典アイデア: 記事の手順をチェックリスト化し、"
            "進捗を記録する Notion / スプレッドシート連携ダッシュボード"
        ),
        "tech_stack": "GAS + スプレッドシート",
        "difficulty": "Cursor開発: 易〜中",
    },
    {
        "patterns": (r"AI", r"ChatGPT", r"自動化", r"量産"),
        "idea": (
            "特典アイデア: 記事のワークフローをステップ実行する"
            "対話型 AI エージェントスクリプト（プロンプトチェーン付き）"
        ),
        "tech_stack": "Python + OpenAI API",
        "difficulty": "Cursor開発: 中",
    },
]

DEFAULT_IDEA = {
    "idea": (
        "特典アイデア: 記事内のノウハウを対話形式で実行できる"
        "専用チャットボットスクリプト"
    ),
    "tech_stack": "Python + OpenAI API",
    "difficulty": "Cursor開発: 中",
}


def _matches_any(title: str, patterns: tuple[str, ...]) -> bool:
    for pattern in patterns:
        if re.search(pattern, title, re.IGNORECASE):
            return True
    return False


def generate_bonus_idea(title: str) -> dict[str, str]:
    """
    記事タイトルから特典ツールのアイデアを生成する。

    Returns:
        {
            "title": 元タイトル,
            "idea": 特典アイデア本文,
            "tech_stack": 推奨技術スタック,
            "difficulty": Cursorでの開発難易度,
            "developer": 開発者アカウント名,
        }
    """
    for rule in IDEA_RULES:
        if _matches_any(title, rule["patterns"]):
            return {
                "title": title,
                "idea": rule["idea"],
                "tech_stack": rule["tech_stack"],
                "difficulty": rule["difficulty"],
                "developer": "Nfcchipplatform",
            }

    return {
        "title": title,
        **DEFAULT_IDEA,
        "developer": "Nfcchipplatform",
    }
