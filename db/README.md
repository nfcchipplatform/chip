# PONNU データベーススキーマ設計書

## 概要

NFCチップを読み取ると、ユーザーのデジタルプロフィール/コンテンツに遷移する
プラットフォーム「PONNU」のデータベース定義。

- **ホスティング**: お名前.com RS (MySQL 5.7系)
- **文字コード**: utf8mb4 / utf8mb4_unicode_ci
- **ストレージエンジン**: InnoDB (全テーブル)

---

## テーブル一覧（12テーブル）

| テーブル名              | 行数目安  | 用途                                      |
|------------------------|----------|-------------------------------------------|
| salons                 | 〜数百   | サロン（美容室等）マスタ                  |
| users                  | 〜数万   | ユーザーアカウント                        |
| nfc_chips              | 〜数万   | NFCチップとトークン管理                   |
| profiles               | 〜数万   | 公開プロフィール (users と 1:1)           |
| contents               | 〜数十万 | プロフィールコンテンツ                    |
| access_logs            | 〜数百万 | NFCアクセスログ                           |
| sessions               | 〜数万   | DBセッション管理                          |
| rate_limits            | 〜数千   | レート制限（ブルートフォース対策）        |
| favorites              | 〜数万   | お気に入りユーザー（スロット式 最大5件）  |
| follows                | 〜数十万 | ユーザーフォロー関係                      |
| profile_views          | 〜数百万 | プロフィール閲覧ログ                      |
| password_reset_tokens  | 〜数千   | パスワードリセットトークン                |

---

## ER図 (テキスト)

```
salons (1) ──< users (N)                [users.salon_id → salons.id]
users (1) ──< nfc_chips (N)             [nfc_chips.user_id → users.id]
users (1) ── profiles (1)               [profiles.user_id → users.id]
users (1) ──< contents (N)              [contents.user_id → users.id]
users (1) ──< sessions (N)              [sessions.user_id → users.id]
users (1) ──< favorites (N)             [favorites.owner_user_id → users.id]
users (1) ──< follows.follower (N)      [follows.follower_id → users.id]
users (1) ──< follows.following (N)     [follows.following_id → users.id]
users (1) ──< profile_views.viewed (N)  [profile_views.viewed_user_id → users.id]
users (1) ──< password_reset_tokens (N) [password_reset_tokens.user_id → users.id]
nfc_chips (1) ──< access_logs (N)       [access_logs.chip_id → nfc_chips.id, NULL許可]
users (0..1) ── nfc_chips (0..1)        [users.nfc_card_id → nfc_chips.id, 主カード]
```

---

## テーブル詳細

### salons — サロンマスタ

| カラム        | 型                         | 説明                     |
|--------------|---------------------------|--------------------------|
| id           | BIGINT UNSIGNED AUTO_INC  | PK                       |
| name         | VARCHAR(200)              | サロン名                 |
| slug         | VARCHAR(100) UNIQUE       | URLスラッグ              |
| salon_code   | VARCHAR(50) UNIQUE        | 招待等に使うコード       |
| location     | VARCHAR(500) NULL         | 所在地テキスト           |
| map_url      | VARCHAR(2048) NULL        | Google Maps URL          |
| website_url  | VARCHAR(2048) NULL        | 公式サイトURL            |
| primary_color| VARCHAR(7) NULL           | #RRGGBB                  |
| accent_color | VARCHAR(7) NULL           | #RRGGBB                  |
| logo_url     | VARCHAR(2048) NULL        | ロゴ画像URL              |
| created_at   | DATETIME                  | 作成日時                 |
| updated_at   | DATETIME ON UPDATE        | 更新日時                 |

### users — ユーザーアカウント（拡張後）

| カラム               | 型                              | 説明                            |
|---------------------|--------------------------------|---------------------------------|
| id                  | INT UNSIGNED AUTO_INCREMENT    | PK                              |
| email               | VARCHAR(255) UNIQUE            | ログインID                      |
| password_hash       | VARCHAR(255)                   | bcrypt ハッシュ                 |
| display_name        | VARCHAR(100)                   | 表示名                          |
| username            | VARCHAR(50) UNIQUE NULL        | URLスラッグ等                   |
| role                | ENUM(USER/SALON_ADMIN/SUPER_ADMIN) | ロール                      |
| salon_id            | BIGINT UNSIGNED FK NULL        | 所属サロン                      |
| direct_link_enabled | TINYINT(1)                     | ダイレクトリンク有効フラグ      |
| direct_link_url     | VARCHAR(2048) NULL             | ダイレクトリンクURL             |
| nfc_card_id         | BIGINT UNSIGNED FK NULL        | 主NFCカード                     |
| status              | ENUM(active/suspended)         | アカウント状態                  |
| created_at          | DATETIME                       | 作成日時                        |
| updated_at          | DATETIME ON UPDATE             | 更新日時                        |
| last_login_at       | DATETIME NULL                  | 最終ログイン                    |

### nfc_chips — NFCチップとトークン管理

| カラム           | 型                          | 説明                                     |
|-----------------|----------------------------|------------------------------------------|
| id              | INT UNSIGNED AUTO_INCREMENT | PK                                       |
| user_id         | INT UNSIGNED FK             | 所有ユーザー                             |
| token           | CHAR(64) UNIQUE             | NFCトークン (hex64, CSPRNG 32byte→hex)   |
| label           | VARCHAR(100)                | ラベル（例: 名刺用、財布用）             |
| status          | ENUM(active/revoked/lost)   | チップ状態                               |
| issued_at       | DATETIME                    | 発行日時                                 |
| revoked_at      | DATETIME NULL               | 無効化日時                               |
| last_accessed_at| DATETIME NULL               | 最終アクセス日時                         |

**トークン仕様:**
- `bin2hex(random_bytes(32))` で生成 (PHP)
- CHAR(64) = 小文字hex64文字
- チップUID(ハードウェア固有ID)は**使用しない**
- 紛失・無効化時は旧トークンを `revoked` にして新トークンを発行
- 1ユーザーが複数チップを所有可能

### profiles — 公開プロフィール（拡張後）

| カラム            | 型                  | 説明                              |
|------------------|---------------------|-----------------------------------|
| user_id          | INT UNSIGNED PK/FK  | users と 1:1                      |
| bio              | TEXT NULL           | 自己紹介文                        |
| avatar_url       | VARCHAR(2048) NULL  | アバター画像URL（旧512→2048）     |
| theme            | VARCHAR(50)         | CSSテーマ識別子                   |
| social_links     | JSON NULL           | SNSリンク {"platform":"url",...}  |
| is_public        | TINYINT(1)          | 公開フラグ                        |
| twitter_handle   | VARCHAR(50) NULL    | Twitterハンドル (@なし)           |
| instagram_handle | VARCHAR(50) NULL    | Instagramハンドル (@なし)         |
| website_url      | VARCHAR(2048) NULL  | 個人サイトURL                     |
| title            | VARCHAR(100) NULL   | 肩書き・職種                      |
| updated_at       | DATETIME            | 更新日時                          |

### contents — コンテンツ

| カラム       | 型                            | 説明                |
|-------------|------------------------------|---------------------|
| id          | INT UNSIGNED AUTO_INCREMENT   | PK                  |
| user_id     | INT UNSIGNED FK               | 所有ユーザー        |
| type        | ENUM(link/image/text/sns)     | コンテンツ種別      |
| title       | VARCHAR(200)                  | タイトル            |
| body        | TEXT NULL                     | 本文 (type=text)    |
| url         | VARCHAR(512) NULL             | URL                 |
| image_path  | VARCHAR(512) NULL             | 画像パス            |
| sort_order  | INT                           | 表示順（昇順）      |
| is_published| TINYINT(1)                    | 公開フラグ          |
| created_at  | DATETIME                      | 作成日時            |
| updated_at  | DATETIME                      | 更新日時            |

### access_logs — NFCアクセスログ

| カラム           | 型                                           | 説明              |
|-----------------|---------------------------------------------|-------------------|
| id              | BIGINT UNSIGNED AUTO_INCREMENT              | PK                |
| chip_id         | INT UNSIGNED FK NULL                        | NFCチップID       |
| token_attempted | CHAR(64) NULL                               | 試行トークン      |
| ip_address      | VARCHAR(45)                                 | アクセス元IP      |
| user_agent      | VARCHAR(512) NULL                           | User-Agent        |
| referer         | VARCHAR(512) NULL                           | Referer           |
| accessed_at     | DATETIME                                    | アクセス日時      |
| result          | ENUM(success/invalid_token/revoked/rate_limited) | 結果         |

### sessions — セッション管理

| カラム         | 型                          | 説明             |
|---------------|----------------------------|------------------|
| id            | INT UNSIGNED AUTO_INCREMENT | PK               |
| user_id       | INT UNSIGNED FK             | ユーザーID       |
| session_token | CHAR(64) UNIQUE             | セッショントークン|
| ip_address    | VARCHAR(45)                 | 作成時IP         |
| user_agent    | VARCHAR(512) NULL           | User-Agent       |
| created_at    | DATETIME                    | 作成日時         |
| expires_at    | DATETIME                    | 有効期限         |
| last_active_at| DATETIME ON UPDATE          | 最終アクティブ   |

### rate_limits — レート制限

| カラム        | 型              | 説明                          |
|--------------|----------------|-------------------------------|
| key (PK)     | VARCHAR(128)   | "ip:x.x.x.x" or "token:hex"  |
| attempts     | INT UNSIGNED   | 試行回数                      |
| window_start | DATETIME       | ウィンドウ開始                |
| blocked_until| DATETIME NULL  | ブロック解除日時              |

### favorites — お気に入りユーザー

| カラム           | 型                          | 説明                        |
|-----------------|----------------------------|-----------------------------|
| id              | BIGINT UNSIGNED AUTO_INC   | PK                          |
| owner_user_id   | INT UNSIGNED FK             | 登録者ID                    |
| slot_index      | TINYINT UNSIGNED            | スロット番号 0〜4           |
| selected_user_id| INT UNSIGNED FK NULL        | 登録対象ユーザーID          |
| created_at      | DATETIME                    | 作成日時                    |
| updated_at      | DATETIME ON UPDATE          | 更新日時                    |

**制約:** `UNIQUE(owner_user_id, slot_index)` により各スロットは1ユーザーのみ。

### follows — フォロー関係

| カラム       | 型                          | 説明                  |
|-------------|----------------------------|-----------------------|
| id          | BIGINT UNSIGNED AUTO_INC   | PK                    |
| follower_id | INT UNSIGNED FK             | フォローする側         |
| following_id| INT UNSIGNED FK             | フォローされる側       |
| created_at  | DATETIME                    | フォロー日時          |

**制約:** `UNIQUE(follower_id, following_id)` で重複防止。自己フォロー防止はアプリ層で実施。

### profile_views — プロフィール閲覧ログ

| カラム           | 型                          | 説明                           |
|-----------------|----------------------------|--------------------------------|
| id              | BIGINT UNSIGNED AUTO_INC   | PK                             |
| viewed_user_id  | INT UNSIGNED FK             | 閲覧されたユーザー             |
| viewer_user_id  | INT UNSIGNED FK NULL        | 閲覧者（未ログイン時NULL）      |
| viewed_at       | DATETIME                    | 閲覧日時                       |
| ip_hash         | CHAR(64) NULL               | IPのSHA-256ハッシュ            |
| ua_hash         | CHAR(64) NULL               | User-AgentのSHA-256ハッシュ    |

### password_reset_tokens — パスワードリセット

| カラム      | 型                          | 説明                  |
|------------|----------------------------|-----------------------|
| id         | BIGINT UNSIGNED AUTO_INC   | PK                    |
| user_id    | INT UNSIGNED FK             | ユーザーID            |
| token_hash | CHAR(64) UNIQUE             | SHA-256ハッシュ済みトークン |
| expires_at | DATETIME                    | 有効期限（1時間推奨）  |
| used_at    | DATETIME NULL               | 使用日時              |
| created_at | DATETIME                    | 作成日時              |

---

## インデックス一覧

| テーブル               | インデックス名                        | カラム                    | 理由                               |
|-----------------------|--------------------------------------|--------------------------|-------------------------------------|
| salons                | uq_salons_slug (UNIQUE)              | slug                     | URL解決                            |
| salons                | uq_salons_salon_code (UNIQUE)        | salon_code               | 招待コード検索                     |
| users                 | uq_users_email (UNIQUE)              | email                    | ログイン時のメール検索             |
| users                 | uq_users_username (UNIQUE)           | username                 | ユーザー名検索・URL解決            |
| users                 | idx_users_salon_id                   | salon_id                 | サロン所属ユーザー一覧             |
| nfc_chips             | uq_nfc_chips_token (UNIQUE)          | token                    | NFC読み取り時の主要検索キー        |
| nfc_chips             | idx_nfc_chips_user_id                | user_id                  | ユーザー所有チップ一覧             |
| contents              | idx_contents_user_sort (複合)        | user_id, sort_order      | プロフィールページのコンテンツ取得 |
| access_logs           | idx_access_logs_accessed_at          | accessed_at              | 時系列集計・古いログのパージ       |
| access_logs           | idx_access_logs_chip_id              | chip_id                  | チップ別アクセス履歴               |
| access_logs           | idx_access_logs_ip                   | ip_address               | IP別不正アクセス調査               |
| sessions              | uq_sessions_token (UNIQUE)           | session_token            | リクエスト毎の認証                 |
| sessions              | idx_sessions_expires_at              | expires_at               | 期限切れセッション削除             |
| favorites             | uq_favorites_owner_slot (UNIQUE)     | owner_user_id, slot_index | スロット重複防止                  |
| follows               | uq_follows_pair (UNIQUE)             | follower_id, following_id | 重複フォロー防止                  |
| profile_views         | idx_profile_views_user_date (複合)   | viewed_user_id, viewed_at | PV集計クエリ                      |
| password_reset_tokens | uq_password_reset_token_hash (UNIQUE)| token_hash               | トークン検索                       |

---

## 代表クエリ

### NFC読み取り時の認証・プロフィール表示

```sql
-- ① トークン検証とチップ情報取得
SELECT
    c.id        AS chip_id,
    c.user_id,
    c.status    AS chip_status,
    u.status    AS user_status,
    p.is_public
FROM nfc_chips c
JOIN users   u ON u.id = c.user_id
JOIN profiles p ON p.user_id = c.user_id
WHERE c.token = ?          -- CHAR(64) hex トークン
  AND c.status = 'active'
  AND u.status = 'active'
LIMIT 1;

-- ② プロフィールとコンテンツ一括取得
SELECT
    u.display_name, u.username,
    p.bio, p.avatar_url, p.theme, p.social_links,
    p.twitter_handle, p.instagram_handle, p.website_url, p.title,
    co.id, co.type, co.title AS content_title, co.body, co.url, co.image_path, co.sort_order
FROM users u
JOIN profiles p ON p.user_id = u.id
LEFT JOIN contents co
    ON co.user_id = u.id
   AND co.is_published = 1
WHERE u.id = ?
ORDER BY co.sort_order ASC;
```

### フォロー関係

```sql
-- フォロワー数・フォロー数取得
SELECT
    (SELECT COUNT(*) FROM follows WHERE following_id = ?) AS followers_count,
    (SELECT COUNT(*) FROM follows WHERE follower_id  = ?) AS following_count;

-- 自分がフォローしているか確認
SELECT id FROM follows WHERE follower_id = ? AND following_id = ? LIMIT 1;
```

### お気に入りスロット取得

```sql
SELECT f.slot_index, u.id, u.display_name, p.avatar_url
FROM favorites f
LEFT JOIN users u ON u.id = f.selected_user_id
LEFT JOIN profiles p ON p.user_id = f.selected_user_id
WHERE f.owner_user_id = ?
ORDER BY f.slot_index ASC;
```

### プロフィールPV集計（日別）

```sql
SELECT DATE(viewed_at) AS day, COUNT(*) AS views
FROM profile_views
WHERE viewed_user_id = ?
  AND viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(viewed_at)
ORDER BY day ASC;
```

### パスワードリセット検証

```sql
SELECT id, user_id, expires_at, used_at
FROM password_reset_tokens
WHERE token_hash = SHA2(?, 256)   -- アプリ層でハッシュ化して渡すことを推奨
  AND used_at IS NULL
  AND expires_at > NOW()
LIMIT 1;
```

### マイページ管理画面（編集機能）

```sql
-- ユーザーのコンテンツ全件取得（非公開含む）
SELECT id, type, title, sort_order, is_published, updated_at
FROM contents
WHERE user_id = ?
ORDER BY sort_order ASC;

-- コンテンツの表示順一括更新
UPDATE contents
SET sort_order = ?
WHERE id = ? AND user_id = ?;
```

---

## phpMyAdmin での初期セットアップ手順

1. phpMyAdmin にログイン
2. 左メニューから対象データベースを選択
3. **SQL タブ** を開く
4. `db/schema.sql` の全内容を貼り付けて **実行**
5. 12テーブルが作成されたことをテーブル一覧で確認
6. `外部キー` タブで FK が設定されていることを確認

> **注意**: `CREATE DATABASE` は含まない。既存DBに対して実行すること。

---

## 旧 Prisma スキーマとのマッピング（Next.js版 → PHP版）

| 旧 Prisma モデル | 対応テーブル           | 主な変更点                                          |
|----------------|----------------------|-----------------------------------------------------|
| User           | users                | role/salon_id/username/nfc_card_id 等を追加         |
| Salon          | salons               | 新規（旧版では別サービス/外部管理）                  |
| NfcChip        | nfc_chips            | ほぼ同等                                            |
| Profile        | profiles             | twitter_handle/instagram_handle/website_url/title 追加 |
| Content        | contents             | ほぼ同等                                            |
| AccessLog      | access_logs          | ほぼ同等                                            |
| Session        | sessions             | NextAuth → PHP DB セッションへ移行                  |
| Favorite       | favorites            | スロット方式は同等                                  |
| Follow         | follows              | 新規                                                |
| ProfileView    | profile_views        | ip_hash/ua_hash でプライバシー保護                   |
| PasswordReset  | password_reset_tokens| NextAuth 組み込み → 独自実装へ                      |

---

## セキュリティメモ

- `password_hash` は PHP `password_hash($pass, PASSWORD_BCRYPT, ['cost'=>12])` で生成
- NFC トークンは `bin2hex(random_bytes(32))` で生成（PHP 7.0+）
- セッショントークンも同様に CSPRNG で生成
- rate_limits テーブルで IP / トークン単位のブルートフォース対策
- access_logs の `chip_id` は NULL 許可（無効トークンも記録する設計）
- profile_views の IP/UA はハッシュ化して保存（生データ不保存）
- password_reset_tokens のトークンはハッシュ化して保存

---

## 将来の拡張テーブル候補

| テーブル名         | 想定機能                               |
|-------------------|---------------------------------------|
| analytics_daily   | 日別アクセス集計（access_logs の集約） |
| notifications     | ユーザー向け通知                      |
| chip_qr_fallback  | QRコードフォールバック管理            |
