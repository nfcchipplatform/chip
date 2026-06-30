#!/usr/bin/env python3
"""Tipsアフィリエイト向け 記事リサーチ＆特典ツールアイデア自動化メインスクリプト。"""

from __future__ import annotations

import sys
from typing import Any

from rich import box
from rich.align import Align
from rich.console import Console
from rich.panel import Panel
from rich.table import Table
from rich.text import Text

from idea_generator import generate_bonus_idea
from target_analyzer import analyze_targets, format_score_reason
from tips_scraper import scrape_trending_articles

console = Console()
AFFILIATE_ACCOUNT = "Nfcchipplatform"


def _format_likes(likes: int | None) -> str:
    if likes is None:
        return "-"
    return str(likes)


def _format_price(price: str | None) -> str:
    return price or "無料/不明"


def _build_articles_table(articles: list[dict[str, Any]], used_mock: bool) -> Table:
    table = Table(
        title="抽出記事一覧",
        box=box.ROUNDED,
        header_style="bold cyan",
        border_style="bright_blue",
        show_lines=True,
    )
    table.add_column("#", style="dim", width=3)
    table.add_column("タイトル", style="white", max_width=52)
    table.add_column("価格", style="green", width=10)
    table.add_column("いいね", style="magenta", width=6, justify="right")
    table.add_column("KW", style="yellow", width=8)

    for index, article in enumerate(articles, start=1):
        table.add_row(
            str(index),
            article.get("title", ""),
            _format_price(article.get("price")),
            _format_likes(article.get("likes")),
            article.get("keyword", "-"),
        )

    if used_mock:
        table.caption = "[yellow]※ ライブ取得に失敗したため、モックデータを表示しています[/yellow]"

    return table


def _print_banner() -> None:
    banner = Text()
    banner.append("TIPS AFFILIATE AUTOMATOR\n", style="bold bright_green")
    banner.append(f"Account: {AFFILIATE_ACCOUNT}", style="bold cyan")
    console.print(
        Panel(
            Align.center(banner),
            border_style="bright_green",
            box=box.DOUBLE_EDGE,
            padding=(1, 2),
        )
    )


def _print_target_result(target: dict[str, Any], idea: dict[str, str]) -> None:
    reason = format_score_reason(target)

    target_panel = Panel(
        f"[bold white]{target.get('title', '')}[/bold white]\n\n"
        f"[cyan]URL:[/cyan] {target.get('url', '')}\n"
        f"[cyan]スコア:[/cyan] [bold yellow]{target.get('score', 0)}[/bold yellow]\n"
        f"[cyan]選定理由:[/cyan] {reason}",
        title="[bold bright_yellow]★ 最適ターゲット記事 ★[/bold bright_yellow]",
        border_style="bright_yellow",
        box=box.HEAVY,
    )

    idea_panel = Panel(
        f"[bold bright_green]{idea.get('idea', '')}[/bold bright_green]\n\n"
        f"[cyan]推奨スタック:[/cyan] {idea.get('tech_stack', '')}\n"
        f"[cyan]開発難易度:[/cyan] {idea.get('difficulty', '')}\n"
        f"[cyan]開発担当:[/cyan] [bold magenta]{idea.get('developer', AFFILIATE_ACCOUNT)}[/bold magenta]",
        title="[bold bright_green]★ 特典ツールアイデア ★[/bold bright_green]",
        border_style="bright_green",
        box=box.HEAVY,
    )

    console.print()
    console.print(target_panel)
    console.print()
    console.print(idea_panel)


def run() -> int:
    """メイン処理を実行し、終了コードを返す。"""
    _print_banner()

    with console.status(
        "[bold cyan]Tipsから最新のAI/副業系トレンド記事を抽出中...[/bold cyan]",
        spinner="dots",
    ):
        articles, used_mock = scrape_trending_articles()

    console.print()
    console.print(_build_articles_table(articles, used_mock))
    console.print()

    with console.status(
        "[bold yellow]最適なターゲットを分析中...[/bold yellow]",
        spinner="line",
    ):
        analysis = analyze_targets(articles)
        top_target = analysis["top_target"]
        idea = generate_bonus_idea(top_target.get("title", ""))

    _print_target_result(top_target, idea)

    console.print()
    console.print(
        Panel(
            "[dim]完了 — Cursorで特典ツールの実装を開始できます。[/dim]",
            border_style="dim",
            box=box.SIMPLE,
        )
    )

    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(run())
    except KeyboardInterrupt:
        console.print("\n[red]中断されました。[/red]")
        raise SystemExit(130)
    except Exception as exc:  # noqa: BLE001
        console.print(f"[bold red]エラー:[/bold red] {exc}")
        raise SystemExit(1)
