-- =============================================================================
-- Migration: 001_init.sql
-- 説明: PONNU データベース初期スキーマ（schema.sql の差分管理アーカイブ）
-- 作成日: 2026-05-18
-- 対象環境: お名前.com RS (MySQL 5.7系)
-- =============================================================================
-- 実行方法:
--   mysql -u <user> -p <dbname> < db/migrations/001_init.sql
--   または phpMyAdmin の SQL タブに貼り付けて実行
--
-- ロールバック: 001_rollback.sql（別途作成）で DROP TABLE を実行
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

-- -----------------------------------------------------------------------------
-- 1. salons
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS salons (
    id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT COMMENT 'サロンID',
    name          VARCHAR(200)     NOT NULL                COMMENT 'サロン名',
    slug          VARCHAR(100)     NOT NULL                COMMENT 'URLスラッグ（英数字・ハイフン）',
    salon_code    VARCHAR(50)      NOT NULL                COMMENT 'サロン固有コード（招待等に使用）',
    location      VARCHAR(500)         NULL DEFAULT NULL   COMMENT '所在地テキスト',
    map_url       VARCHAR(2048)        NULL DEFAULT NULL   COMMENT 'Google Maps等のURL',
    website_url   VARCHAR(2048)        NULL DEFAULT NULL   COMMENT 'サロン公式サイトURL',
    primary_color VARCHAR(7)           NULL DEFAULT NULL   COMMENT 'プライマリカラー (#RRGGBB)',
    accent_color  VARCHAR(7)           NULL DEFAULT NULL   COMMENT 'アクセントカラー (#RRGGBB)',
    logo_url      VARCHAR(2048)        NULL DEFAULT NULL   COMMENT 'ロゴ画像URL',
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP  COMMENT '作成日時',
    updated_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                            ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    PRIMARY KEY (id),
    UNIQUE KEY uq_salons_slug (slug),
    UNIQUE KEY uq_salons_salon_code (salon_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='サロン（美容室等）マスタ';

-- -----------------------------------------------------------------------------
-- 2. users
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id                   INT UNSIGNED     NOT NULL AUTO_INCREMENT COMMENT 'ユーザーID',
    email                VARCHAR(255)     NOT NULL                COMMENT 'メールアドレス（ログインID）',
    password_hash        VARCHAR(255)     NOT NULL                COMMENT 'bcryptハッシュ (password_hash)',
    display_name         VARCHAR(100)     NOT NULL DEFAULT ''     COMMENT '表示名',
    username             VARCHAR(50)          NULL DEFAULT NULL   COMMENT 'ユーザー名（URLスラッグ等）',
    role                 ENUM('USER','SALON_ADMIN','SUPER_ADMIN')
                                          NOT NULL DEFAULT 'USER' COMMENT 'ユーザーロール',
    salon_id             BIGINT UNSIGNED      NULL DEFAULT NULL   COMMENT '所属サロンID',
    direct_link_enabled  TINYINT(1)       NOT NULL DEFAULT 0      COMMENT 'ダイレクトリンク有効フラグ',
    direct_link_url      VARCHAR(2048)        NULL DEFAULT NULL   COMMENT 'ダイレクトリンクURL',
    nfc_card_id          INT UNSIGNED         NULL DEFAULT NULL   COMMENT 'NFCカードID（nfc_chips.id 参照）',
    status               ENUM('active','suspended') NOT NULL DEFAULT 'active'
                                                               COMMENT 'アカウント状態',
    created_at           DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP  COMMENT '作成日時',
    updated_at           DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                  ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    last_login_at        DATETIME             NULL DEFAULT NULL   COMMENT '最終ログイン日時',
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_username (username),
    KEY idx_users_salon_id (salon_id),
    KEY idx_users_nfc_card_id (nfc_card_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ユーザーアカウント';

-- -----------------------------------------------------------------------------
-- 3. nfc_chips
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS nfc_chips (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT  COMMENT 'チップID',
    user_id          INT UNSIGNED NOT NULL                  COMMENT '所有ユーザーID',
    token            CHAR(64)     NOT NULL                  COMMENT 'NFC埋め込みトークン(hex64, CSPRNG)',
    label            VARCHAR(100) NOT NULL DEFAULT ''       COMMENT 'チップ識別ラベル',
    status           ENUM('active','revoked','lost') NOT NULL DEFAULT 'active'
                                                            COMMENT 'チップ状態',
    issued_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP  COMMENT '発行日時',
    revoked_at       DATETIME         NULL DEFAULT NULL    COMMENT '無効化日時',
    last_accessed_at DATETIME         NULL DEFAULT NULL    COMMENT '最終アクセス日時',
    PRIMARY KEY (id),
    UNIQUE KEY uq_nfc_chips_token (token),
    KEY idx_nfc_chips_user_id (user_id),
    CONSTRAINT fk_nfc_chips_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='NFCチップとトークン管理';

-- -----------------------------------------------------------------------------
-- 4. profiles
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS profiles (
    user_id           INT UNSIGNED NOT NULL                  COMMENT 'ユーザーID (PK/FK)',
    bio               TEXT             NULL DEFAULT NULL     COMMENT '自己紹介文',
    avatar_url        VARCHAR(2048)    NULL DEFAULT NULL     COMMENT 'アバター画像URL',
    theme             VARCHAR(50)  NOT NULL DEFAULT 'default' COMMENT 'テーマ識別子',
    social_links      JSON             NULL DEFAULT NULL     COMMENT 'SNSリンク {"platform":"url",...}',
    is_public         TINYINT(1)   NOT NULL DEFAULT 1        COMMENT '公開フラグ',
    twitter_handle    VARCHAR(50)      NULL DEFAULT NULL     COMMENT 'Twitterハンドル (@なし)',
    instagram_handle  VARCHAR(50)      NULL DEFAULT NULL     COMMENT 'Instagramハンドル (@なし)',
    website_url       VARCHAR(2048)    NULL DEFAULT NULL     COMMENT '個人サイトURL',
    title             VARCHAR(100)     NULL DEFAULT NULL     COMMENT '肩書き・職種',
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                            ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    PRIMARY KEY (user_id),
    CONSTRAINT fk_profiles_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ユーザー公開プロフィール（users と 1:1）';

-- -----------------------------------------------------------------------------
-- 5. contents
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contents (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT   COMMENT 'コンテンツID',
    user_id      INT UNSIGNED NOT NULL                   COMMENT '所有ユーザーID',
    type         ENUM('link','image','text','sns') NOT NULL COMMENT 'コンテンツ種別',
    title        VARCHAR(200) NOT NULL DEFAULT ''        COMMENT 'タイトル',
    body         TEXT             NULL DEFAULT NULL      COMMENT 'テキスト本文',
    url          VARCHAR(512)     NULL DEFAULT NULL      COMMENT 'リンクURL',
    image_path   VARCHAR(512)     NULL DEFAULT NULL      COMMENT '画像ファイルパス',
    sort_order   INT          NOT NULL DEFAULT 0         COMMENT '表示順（昇順）',
    is_published TINYINT(1)   NOT NULL DEFAULT 1         COMMENT '公開フラグ',
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP  COMMENT '作成日時',
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    PRIMARY KEY (id),
    KEY idx_contents_user_sort (user_id, sort_order),
    CONSTRAINT fk_contents_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='プロフィールコンテンツ';

-- -----------------------------------------------------------------------------
-- 6. access_logs
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS access_logs (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ログID',
    chip_id        INT UNSIGNED        NULL DEFAULT NULL   COMMENT 'NFCチップID',
    token_attempted CHAR(64)           NULL DEFAULT NULL   COMMENT '試行されたトークン',
    ip_address     VARCHAR(45)     NOT NULL                COMMENT 'アクセス元IP',
    user_agent     VARCHAR(512)        NULL DEFAULT NULL   COMMENT 'User-Agent',
    referer        VARCHAR(512)        NULL DEFAULT NULL   COMMENT 'Referer',
    accessed_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'アクセス日時',
    result         ENUM('success','invalid_token','revoked','rate_limited')
                                   NOT NULL                COMMENT 'アクセス結果',
    PRIMARY KEY (id),
    KEY idx_access_logs_accessed_at (accessed_at),
    KEY idx_access_logs_chip_id (chip_id),
    KEY idx_access_logs_ip (ip_address),
    CONSTRAINT fk_access_logs_chip
        FOREIGN KEY (chip_id) REFERENCES nfc_chips (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='NFCチップ読み取りアクセスログ';

-- -----------------------------------------------------------------------------
-- 7. sessions
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sessions (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT  COMMENT 'セッション内部ID',
    user_id        INT UNSIGNED NOT NULL                  COMMENT 'ユーザーID',
    session_token  CHAR(64)     NOT NULL                  COMMENT 'セッショントークン(hex64)',
    ip_address     VARCHAR(45)  NOT NULL                  COMMENT '作成時IP',
    user_agent     VARCHAR(512)     NULL DEFAULT NULL     COMMENT 'User-Agent',
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    expires_at     DATETIME     NOT NULL                  COMMENT '有効期限',
    last_active_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                          ON UPDATE CURRENT_TIMESTAMP COMMENT '最終アクティブ日時',
    PRIMARY KEY (id),
    UNIQUE KEY uq_sessions_token (session_token),
    KEY idx_sessions_user_id (user_id),
    KEY idx_sessions_expires_at (expires_at),
    CONSTRAINT fk_sessions_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ログインセッション管理';

-- -----------------------------------------------------------------------------
-- 8. rate_limits
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rate_limits (
    `key`          VARCHAR(128) NOT NULL                  COMMENT '制限キー',
    attempts       INT UNSIGNED NOT NULL DEFAULT 1        COMMENT '試行回数',
    window_start   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'ウィンドウ開始日時',
    blocked_until  DATETIME         NULL DEFAULT NULL     COMMENT 'ブロック解除日時',
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='レート制限テーブル';

-- -----------------------------------------------------------------------------
-- 9. favorites
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS favorites (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'お気に入りID',
    owner_user_id    INT UNSIGNED    NOT NULL                COMMENT 'お気に入り登録者ID',
    slot_index       TINYINT UNSIGNED NOT NULL               COMMENT 'スロット番号 (0〜4)',
    selected_user_id INT UNSIGNED        NULL DEFAULT NULL   COMMENT '登録対象ユーザーID',
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                              ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    PRIMARY KEY (id),
    UNIQUE KEY uq_favorites_owner_slot (owner_user_id, slot_index),
    KEY idx_favorites_selected (selected_user_id),
    CONSTRAINT fk_favorites_owner
        FOREIGN KEY (owner_user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_favorites_selected
        FOREIGN KEY (selected_user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='お気に入りユーザー（スロット式 最大5件）';

-- -----------------------------------------------------------------------------
-- 10. follows
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS follows (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'フォローID',
    follower_id  INT UNSIGNED    NOT NULL                COMMENT 'フォローする側ユーザーID',
    following_id INT UNSIGNED    NOT NULL                COMMENT 'フォローされる側ユーザーID',
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'フォロー日時',
    PRIMARY KEY (id),
    UNIQUE KEY uq_follows_pair (follower_id, following_id),
    KEY idx_follows_following (following_id),
    KEY idx_follows_follower (follower_id),
    CONSTRAINT fk_follows_follower
        FOREIGN KEY (follower_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_follows_following
        FOREIGN KEY (following_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ユーザーフォロー関係';

-- -----------------------------------------------------------------------------
-- 11. profile_views
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS profile_views (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '閲覧ログID',
    viewed_user_id INT UNSIGNED    NOT NULL                COMMENT '閲覧されたユーザーID',
    viewer_user_id INT UNSIGNED        NULL DEFAULT NULL   COMMENT '閲覧者ユーザーID',
    viewed_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '閲覧日時',
    ip_hash        CHAR(64)            NULL DEFAULT NULL   COMMENT 'IPのSHA-256ハッシュ',
    ua_hash        CHAR(64)            NULL DEFAULT NULL   COMMENT 'UAのSHA-256ハッシュ',
    PRIMARY KEY (id),
    KEY idx_profile_views_user_date (viewed_user_id, viewed_at),
    KEY idx_profile_views_viewer (viewer_user_id),
    CONSTRAINT fk_profile_views_viewed
        FOREIGN KEY (viewed_user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_profile_views_viewer
        FOREIGN KEY (viewer_user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='プロフィール閲覧ログ';

-- -----------------------------------------------------------------------------
-- 12. password_reset_tokens
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'トークンID',
    user_id    INT UNSIGNED    NOT NULL                COMMENT 'ユーザーID',
    token_hash CHAR(64)        NOT NULL                COMMENT 'SHA-256ハッシュ化トークン',
    expires_at DATETIME        NOT NULL                COMMENT '有効期限',
    used_at    DATETIME            NULL DEFAULT NULL   COMMENT '使用日時',
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_reset_token_hash (token_hash),
    KEY idx_password_reset_user (user_id),
    KEY idx_password_reset_expires (expires_at),
    CONSTRAINT fk_password_reset_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='パスワードリセットトークン管理';

-- -----------------------------------------------------------------------------
-- 遅延FK
-- -----------------------------------------------------------------------------
ALTER TABLE users
    ADD CONSTRAINT fk_users_salon
        FOREIGN KEY (salon_id) REFERENCES salons (id)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE users
    ADD CONSTRAINT fk_users_nfc_card
        FOREIGN KEY (nfc_card_id) REFERENCES nfc_chips (id)
        ON DELETE SET NULL ON UPDATE CASCADE;

SET foreign_key_checks = 1;

-- =============================================================================
-- END OF MIGRATION 001_init.sql
-- =============================================================================
