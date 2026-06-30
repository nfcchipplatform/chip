# Phase 3: X 自動投稿 Bot

Phase 1 の商品 JSON から作品を選定し、X（Twitter）へ自動投稿。Discord Webhook で稼働状況・エラーを通知。

## セットアップ

```bash
cd affiliate-media/automation
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env
# .env を編集（X API / Discord Webhook）
```

## 必要な API

| サービス | 用途 | 取得先 |
|---------|------|--------|
| X API | ツイート投稿 | https://developer.x.com/ |
| Discord Webhook | 通知 | Discord サーバー設定 → 連携サービス → Webhook |
| Bitly（任意） | URL 短縮 | https://bitly.com/ |

### X API 設定手順

1. [X Developer Portal](https://developer.x.com/) でプロジェクト/App を作成
2. **OAuth 1.0a** の User authentication を有効化（Read and Write）
3. API Key / Secret、Access Token / Secret を `.env` に設定

> Free プランでは投稿数に制限があります。プランは X Developer Portal で確認してください。

## 使い方

### ドライラン（投稿なし・テスト）

```bash
python scripts/run_bot.py --dry-run
```

### 1 回投稿

```bash
python scripts/run_bot.py
```

### 選定モード

```bash
# ランダム（デフォルト）
python scripts/run_bot.py --mode random

# 新作（直近 30 日以内）
python scripts/run_bot.py --mode new

# 高単価（2000 円以上）
python scripts/run_bot.py --mode high_price
```

### スケジュール常駐（3 時間ごと）

```bash
python scripts/run_scheduler.py --interval 180 --run-on-start
```

### cron で運用（推奨）

```cron
# 3 時間ごとに 1 投稿
0 */3 * * * cd /path/to/affiliate-media/automation && .venv/bin/python scripts/run_bot.py >> data/logs/cron.log 2>&1
```

## 投稿文の構成

```
【おすすめ】作品タイトル
出演: 女優名
¥1,980
紹介文（intro_text または description）…
https://短縮アフィリエイトURL
https://自サイト/products/slug（SITE_URL 設定時）
#ジャンル #FANZA
```

## モジュール構成

```
automation/
├── config/settings.py       # 環境変数
├── scripts/
│   ├── run_bot.py           # 1 回実行 CLI
│   └── run_scheduler.py     # 常駐スケジューラ
└── src/
    ├── bot/runner.py        # メイン実行ロジック
    ├── selectors/           # 作品選定（random / new / high_price）
    ├── builders/            # 投稿文組み立て
    ├── posters/             # X API 投稿
    ├── notifiers/           # Discord 通知
    ├── history/             # 重複投稿防止
    └── utils/               # URL 短縮・リトライ
```

## 環境変数

| 変数 | 説明 |
|------|------|
| `POST_MODE` | `random` / `new` / `high_price` |
| `POST_INTERVAL_MINUTES` | スケジューラの投稿間隔 |
| `REPOST_COOLDOWN_DAYS` | 同一作品の再投稿禁止期間 |
| `DISCORD_WEBHOOK_URL` | Discord 通知先 |
| `SITE_URL` | 自サイト URL（任意） |
| `BITLY_ACCESS_TOKEN` | URL 短縮（任意） |

## 非機能要件

- X API: `wait_on_rate_limit=True` + 手動リトライ（429 / 5xx）
- Discord / Bitly: 指数バックオフリトライ
- 投稿履歴 JSON で同一作品の重複投稿を防止
