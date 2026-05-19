# PONNU セットアップガイド

NFCチップを読み取ると、ユーザーのデジタルプロフィール/コンテンツに遷移する
プラットフォーム「PONNU」の素のPHP実装です。

## 技術スタック

| レイヤ        | 技術                              |
|--------------|-----------------------------------|
| バックエンド  | PHP 8.x (素のPHP、composer 不使用) |
| フロントエンド| Vanilla JS + Tailwind CSS CDN     |
| データベース  | MySQL 5.7 (utf8mb4)               |
| ホスティング  | お名前.com RS (Apache + .htaccess) |

---

## ディレクトリ構成

```
ponnu/
  public_html/          ← FTP でサーバーの public_html にアップ
    index.php           ランディング / ダッシュボードリダイレクト
    .htaccess           HTTPS強制 + セキュリティヘッダ
    assets/
      css/app.css       カスタムスタイル
      js/app.js         Vanilla JS ユーティリティ
      img/              画像アセット
    uploads/            ユーザーアップロード画像（PHP実行禁止）
    n/index.php         NFCランディング (/n/?t=TOKEN)
    api/                APIエンドポイント群（Step 3以降）
  app/
    config.example.php  設定テンプレート（要コピー）
    config.php          実際の設定（.gitignore済み）
    bootstrap.php       セッション・CSRF初期化
    Database.php        PDO ラッパー（シングルトン）
    Auth.php            認証（bcrypt cost=12, DBセッション）
    Csrf.php            CSRFトークン管理
    helpers.php         e(), redirect(), json_response() 等
    views/
      layout.php        共通レイアウト（Tailwind + Noto Sans JP）
      partials/         パーシャルビュー
  db/
    schema.sql          完全なDBスキーマ（12テーブル）
    README.md           スキーマ設計書
    migrations/
      001_init.sql      初期マイグレーション（IF NOT EXISTS版）
```

---

## デプロイ手順

### 1. DB インポート

```bash
# phpMyAdmin の SQL タブに貼り付けて実行
# または mysql コマンドで実行
mysql -u <user> -p <dbname> < db/schema.sql
```

**phpMyAdmin を使う場合:**
1. phpMyAdmin にログイン
2. 対象データベースを選択
3. **SQL タブ** を開く
4. `db/schema.sql` の内容を貼り付けて実行
5. 12テーブルが作成されたことを確認

### 2. config.php 作成

```bash
cp app/config.example.php app/config.php
```

`app/config.php` を編集して以下を設定:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('APP_URL',  'https://your-domain.com');
define('APP_ENV',  'production');
define('APP_DEBUG', false);

// 32バイトのランダム文字列
// php -r "echo bin2hex(random_bytes(32));"
define('APP_SECRET', 'xxxxxxxx...');
```

> **重要**: `config.php` は `.gitignore` に含まれています。Git にコミットしないでください。

### 3. FTP アップロード

お名前.com コントロールパネルでFTPアカウントを作成後:

```bash
# 例: FTP クライアント（FileZilla 等）で接続
# アップロード先: /public_html/  (サーバーの公開ディレクトリ)
```

**アップロードするディレクトリ・ファイル:**
- `public_html/` の中身 → サーバーの `public_html/` に
- `app/` フォルダごと → サーバーの `public_html/` の**一つ上** または安全な場所に
- `db/` は**アップロード不要**（ローカル管理のみ）

**ディレクトリ配置例:**
```
サーバー/
  public_html/       ← Web公開ディレクトリ（public_html/ の中身をアップ）
  app/               ← Webから直接アクセス不可な場所
```

> `app/` を `public_html/` の外に配置する場合は `bootstrap.php` のパスを調整してください。

### 4. Let's Encrypt (SSL 証明書)

お名前.com の場合、コントロールパネルから無料SSL（Let's Encrypt）を申請できます:

1. コントロールパネル → **サーバー設定** → **SSL設定**
2. 対象ドメインを選択して **Let's Encrypt** を申請
3. 証明書が発行されたら `.htaccess` の HSTS ヘッダのコメントを外す:
   ```apache
   Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
   ```

### 5. 動作確認チェックリスト

- [ ] `https://your-domain.com/` にアクセスしてランディングページが表示される
- [ ] `http://` → `https://` のリダイレクトが動作する
- [ ] `https://your-domain.com/n/?t=invalidtoken` にアクセスして 400 エラーが表示される
- [ ] ブラウザの開発ツールで `X-Frame-Options: DENY` ヘッダを確認

---

## 開発環境セットアップ（ローカル）

### 必要なもの
- PHP 8.1+
- MySQL 5.7+ または MariaDB 10.5+

### 起動方法

```bash
# MySQL にデータベースを作成
mysql -u root -p -e "CREATE DATABASE ponnu_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p ponnu_dev < db/schema.sql

# 設定ファイルを作成
cp app/config.example.php app/config.php
# config.php を編集して DB 接続情報を設定

# PHP 内蔵サーバーで起動
php -S localhost:8080 -t public_html/
```

アクセス: http://localhost:8080/

---

## セキュリティ設定

| 項目 | 設定 |
|-----|------|
| HTTPS 強制 | `.htaccess` RewriteRule |
| セキュリティヘッダ | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` |
| CSRF 対策 | `Csrf` クラス（トークン検証） |
| パスワード | bcrypt cost=12 (`Auth::hashPassword`) |
| セッション | DBセッション、`session_regenerate_id` |
| アップロード | `uploads/.htaccess` で PHP 実行禁止 |
| レート制限 | `rate_limits` テーブルで管理 |

---

## 今後のステップ

| ステップ | 内容 |
|---------|------|
| Step 3 | ダッシュボード / プロフィール編集 / NFCランディング本体 |
| Step 4 | コンテンツ管理（リンク/画像/テキスト）|
| Step 5 | サロン管理 / 管理者画面 |
| Step 6 | フォロー / お気に入り機能 |
