# Affiliate Media System

ASP（FANZA / DUGA）商品データの収集・AI 紹介文生成・メディアサイト公開・SNS 自動投稿を行うアフィリエイト運用自動化システム。

## システム全体構成

```
affiliate-media/
├── README.md                    # 本ファイル（全体概要）
├── docs/
│   └── affiliate-setup.md       # ASP アカウント登録ガイド
├── backend/                     # Phase 1: Python データ処理
│   ├── config/                  # 設定
│   ├── data/
│   │   ├── raw/                 # ASP から DL した CSV 配置
│   │   ├── processed/           # JSON 出力（Next.js が読み込む）
│   │   ├── checkpoints/         # OpenAI 生成チェックポイント
│   │   └── samples/             # テスト用サンプル CSV
│   ├── scripts/
│   │   └── run_pipeline.py      # パイプライン CLI
│   └── src/                     # parsers / cleaners / generators / exporters
├── frontend/                    # Phase 2: Next.js メディアサイト
│   ├── src/app/                 # App Router（SSG/ISR）
│   ├── src/components/          # UI コンポーネント
│   ├── src/lib/                 # データ読み込み・Schema.org
│   └── public/data/             # 商品 JSON（sync-data で更新）
└── automation/                  # Phase 3: X 自動投稿 Bot
    ├── scripts/run_bot.py       # 1 回実行 CLI
    ├── scripts/run_scheduler.py # 常駐スケジューラ
    └── src/                     # selectors / posters / notifiers
```

## 開発フェーズ

| Phase | 内容 | 状態 |
|-------|------|------|
| Phase 1 | Python データ処理 | 実装済み |
| Phase 2 | Next.js メディアサイト | 実装済み |
| Phase 3 | X API 自動投稿 Bot | **実装済み（本 PR）** |

## FANZA アカウントについて

| 用途 | 必要？ |
|------|--------|
| 開発・テスト（Phase 1〜3） | **不要** — サンプルデータで動作 |
| 本番運用・収益化 | **必要** — [DMM アフィリエイト](https://affiliate.dmm.com/) への登録 |

一般の FANZA 視聴アカウントとは別です。登録手順は [docs/affiliate-setup.md](docs/affiliate-setup.md) を参照してください。

---

## Phase 1: データ収集とコンテンツ自動生成

```bash
cd affiliate-media/backend
python3 -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env

python scripts/run_pipeline.py \
  --source fanza \
  --input data/samples/fanza_sample.csv \
  --skip-generation
```

---

## Phase 2: Next.js メディアサイト

### セットアップ

```bash
cd affiliate-media/frontend
npm install
cp .env.example .env.local
npm run sync-data   # backend の JSON を public/data/ にコピー
npm run dev         # http://localhost:3000
```

### 本番ビルド

```bash
npm run sync-data
npm run build
npm start
```

### 実装内容

- **App Router + ISR** — `revalidate: 3600`（1 時間ごとに再生成）
- **作品詳細ページ** — `/products/[slug]`
- **ジャンル一覧** — `/genres` / `/genres/[genre]`
- **モバイル CVR 最適化** — 固定 CTA バー、オレンジグラデーションボタン
- **SEO** — 動的 `sitemap.xml`、`robots.txt`、Schema.org（Product / BreadcrumbList / WebSite）
- **OGP / Twitter Card** — 作品ページごとにメタデータ生成

### 環境変数

| 変数 | 説明 |
|------|------|
| `NEXT_PUBLIC_SITE_URL` | 本番 URL（sitemap / canonical） |
| `NEXT_PUBLIC_SITE_NAME` | サイト名 |
| `PRODUCTS_DATA_DIR` | JSON 読み込みパス（省略可） |

---

## Phase 3: X 自動投稿 Bot

```bash
cd affiliate-media/automation
python3 -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env

# ドライラン（X API 不要）
python scripts/run_bot.py --dry-run

# 本番投稿（X API 認証設定後）
python scripts/run_bot.py

# 常駐スケジューラ（3 時間ごと）
python scripts/run_scheduler.py --interval 180
```

詳細: [automation/README.md](automation/README.md)

---

## FANZA アカウントについて

- ASP の CSV カラム名はバージョンにより異なる場合があります。
- OpenAI API 利用料が発生します。
- アフィリエイトリンク・コンテンツは各 ASP の利用規約を遵守してください。
