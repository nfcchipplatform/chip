# アフィリエイト自動化システム

ASP（FANZA / DUGA）の商品データを収集し、AI による紹介文生成・メディアサイト公開・SNS 自動投稿までを自動化するシステムです。

## ディレクトリ構成

```
affiliate-system/
├── README.md                    # 本ファイル
├── .gitignore
│
├── backend/                     # Phase 1: Python データ処理
│   ├── requirements.txt
│   ├── .env.example
│   ├── config/
│   │   └── settings.py          # 環境変数・ASP カラムマッピング
│   ├── data/
│   │   ├── raw/                  # ASP から DL した CSV（git 管理外）
│   │   ├── processed/            # 生成 JSON（git 管理外）
│   │   └── sample/               # テスト用サンプル CSV
│   └── src/
│       ├── main.py               # CLI エントリーポイント
│       ├── models.py             # Pydantic データモデル
│       ├── csv_loader.py         # CSV 読み込み（大容量対応）
│       ├── data_processor.py     # クレンジング・正規化
│       ├── content_generator.py  # OpenAI 紹介文生成
│       ├── json_exporter.py      # JSON 出力
│       └── utils/
│           ├── retry.py          # 指数バックオフ + レートリミット
│           └── logger.py
│
├── frontend/                    # Phase 2: Next.js（未実装）
│   └── README.md
│
└── automation/                  # Phase 3: X Bot / GAS（未実装）
    └── README.md
```

## Phase 1: セットアップ手順

### 1. 依存関係のインストール

```bash
cd affiliate-system/backend
python -m venv .venv
source .venv/bin/activate   # Windows: .venv\Scripts\activate
pip install -r requirements.txt
```

### 2. 環境変数の設定

```bash
cp .env.example .env
```

`.env` を編集し、最低限 `OPENAI_API_KEY` を設定してください。

### 3. ASP CSV の配置

FANZA / DUGA の ASP 管理画面からダウンロードした CSV を `data/raw/` に配置します。

| ASP   | 想定ファイル名例     | 設定           |
|-------|----------------------|----------------|
| FANZA | `fanza_items.csv`    | `ASP_SOURCE=fanza` |
| DUGA  | `duga_items.csv`     | `ASP_SOURCE=duga`  |

カラム名は `config/settings.py` の `column_mapping` で ASP ごとに定義しています。  
ASP の CSV フォーマットが異なる場合は、マッピングを調整してください。

### 4. パイプライン実行

```bash
# サンプル CSV で動作確認（紹介文生成スキップ）
python -m src.main \
  --asp fanza \
  --csv data/sample/fanza_sample.csv \
  --skip-intro

# 本番実行（紹介文生成あり）
python -m src.main --asp fanza

# 開発用: 先頭 100 件のみ
python -m src.main --asp fanza --limit 100

# ジャンル別 JSON も出力
python -m src.main --asp fanza --skip-intro --split-genre
```

### 5. 出力形式

`data/processed/catalog_fanza_latest.json` がフロントエンドの入力ファイルです。

```json
{
  "version": "1.0",
  "source": "fanza",
  "generated_at": "2025-06-30T12:00:00+00:00",
  "total_count": 3,
  "products": [
    {
      "product_id": "abc001",
      "title": "サンプル作品タイトル1",
      "actress": "山田花子",
      "genre": ["巨乳", "人妻"],
      "thumbnail_url": "https://example.com/thumb1.jpg",
      "affiliate_link": "https://al.fanza.co.jp/?lurl=sample1b",
      "price": 2180,
      "release_date": "2025-01-20",
      "intro": "（OpenAI 生成の紹介文）",
      "slug": "abc001",
      "updated_at": "2025-06-30T12:00:00Z"
    }
  ]
}
```

## 大容量 CSV の処理について

- `pandas` の `dtype=str` + `low_memory=False` でメモリ効率と型推論エラーを回避
- 複数 CSV ファイルを自動結合
- `product_id` 重複は最新行を採用
- OpenAI API 呼び出しは **指数バックオフ** + **RPM レートリミッター** で Rate Limit に対応

## 次のフェーズ

| Phase | 内容                         | 状態     |
|-------|------------------------------|----------|
| 1     | Python データ処理            | ✅ 本 PR |
| 2     | Next.js メディアサイト       | 承認待ち |
| 3     | X 自動投稿 + Discord 通知    | 承認待ち |

Phase 1 の内容をご確認のうえ、問題なければ Phase 2 へ進みます。
