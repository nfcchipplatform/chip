# Affiliate Media System

ASP（FANZA / DUGA）商品データの収集・AI 紹介文生成・メディアサイト公開・SNS 自動投稿を行うアフィリエイト運用自動化システム。

## システム全体構成

```
affiliate-media/
├── README.md                    # 本ファイル（全体概要）
├── backend/                     # Phase 1: Python データ処理
│   ├── config/                  # 設定
│   ├── data/
│   │   ├── raw/                 # ASP から DL した CSV 配置
│   │   ├── processed/           # JSON 出力（Next.js が読み込む）
│   │   ├── checkpoints/         # OpenAI 生成チェックポイント
│   │   └── samples/             # テスト用サンプル CSV
│   ├── scripts/
│   │   └── run_pipeline.py      # パイプライン CLI
│   ├── src/
│   │   ├── parsers/             # FANZA / DUGA CSV パーサー
│   │   ├── cleaners/            # pandas クレンジング
│   │   ├── generators/          # OpenAI 紹介文生成
│   │   ├── exporters/           # JSON エクスポート
│   │   ├── models/              # Pydantic データモデル
│   │   └── utils/               # リトライ等ユーティリティ
│   ├── requirements.txt
│   └── .env.example
├── frontend/                    # Phase 2: Next.js（未実装）
│   └── (Phase 2 で追加)
└── automation/                  # Phase 3: SNS Bot（未実装）
    └── (Phase 3 で追加)
```

## Phase 1: データ収集とコンテンツ自動生成

### セットアップ

```bash
cd affiliate-media/backend
python -m venv .venv
source .venv/bin/activate   # Windows: .venv\Scripts\activate
pip install -r requirements.txt
cp .env.example .env
# .env に OPENAI_API_KEY を設定
```

### 実行方法

**FANZA CSV（紹介文生成なし・テスト）**

```bash
python scripts/run_pipeline.py \
  --source fanza \
  --input data/samples/fanza_sample.csv \
  --skip-generation
```

**FANZA CSV（OpenAI 紹介文生成あり）**

```bash
python scripts/run_pipeline.py \
  --source fanza \
  --input data/raw/fanza_products.csv \
  --chunk-size 10000
```

**DUGA CSV**

```bash
python scripts/run_pipeline.py \
  --source duga \
  --input data/raw/duga_products.csv
```

### CLI オプション

| オプション | 説明 |
|-----------|------|
| `--source` | `fanza` または `duga` |
| `--input` | 入力 CSV パス |
| `--output-dir` | JSON 出力先（省略時: `data/processed`） |
| `--limit` | 処理件数上限（テスト用） |
| `--chunk-size` | 大容量 CSV チャンクサイズ（デフォルト: 10000） |
| `--skip-generation` | OpenAI 紹介文生成をスキップ |
| `--no-checkpoint` | チェックポイント無効化 |

### 出力 JSON

| ファイル | 用途 |
|---------|------|
| `products.json` | 全商品データ（Phase 2 が読み込む） |
| `products_by_genre.json` | ジャンル別インデックス |
| `index.json` | 一覧ページ用軽量インデックス |
| `slugs.json` | 動的ルーティング用 slug 一覧 |

### 商品データスキーマ

```json
{
  "id": "fanza-abc12345",
  "source": "fanza",
  "title": "作品タイトル",
  "actresses": ["女優A", "女優B"],
  "genres": ["ドラマ", "人妻"],
  "thumbnail_url": "https://...",
  "affiliate_url": "https://...",
  "price": 1980,
  "release_date": "2025-01-15",
  "description": "ASP 提供の説明文",
  "intro_text": "OpenAI 生成の紹介文（200文字程度）",
  "slug": "fanza-abc12345-sample-title-001",
  "updated_at": "2025-06-30T12:00:00"
}
```

## 非機能要件

- **レートリミット**: OpenAI API 呼び出しに指数バックオフ + RPM スロットリング
- **大容量対応**: pandas チャンク読み込み（数十万件 CSV 対応）
- **中断再開**: チェックポイント JSONL による紹介文生成の再開
- **モジュール分割**: parsers / cleaners / generators / exporters

## 開発フェーズ

| Phase | 内容 | 状態 |
|-------|------|------|
| Phase 1 | Python データ処理 | **実装済み（本 PR）** |
| Phase 2 | Next.js メディアサイト | 承認後に着手 |
| Phase 3 | X API 自動投稿 Bot | Phase 2 後 |

## 注意事項

- ASP の CSV カラム名はバージョンにより異なる場合があります。`src/parsers/fanza.py` / `duga.py` の `COLUMN_ALIASES` を実際の CSV に合わせて調整してください。
- OpenAI API 利用料が発生します。本番前に `--limit` で小さく試してください。
- アフィリエイトリンク・コンテンツは各 ASP の利用規約を遵守してください。
