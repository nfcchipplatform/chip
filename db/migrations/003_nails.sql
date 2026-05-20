-- =============================================================================
-- 003_nails.sql
-- Step 5: ネイルデザイン登録 + ユーザー指への貼り付け
--
-- 適用: phpMyAdmin で対象DBを選択 → SQLタブで本ファイルを実行
-- =============================================================================

-- ネイルデザインマスター
CREATE TABLE IF NOT EXISTS nails (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nail_code    CHAR(8) NOT NULL UNIQUE COMMENT '公開用ユニークコード (検索・交換用)',
    image_url    VARCHAR(512) NOT NULL COMMENT 'ネイルチップ画像パス',
    design_name  VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'デザイン名',
    description  VARCHAR(500) NOT NULL DEFAULT '' COMMENT '説明・カラー等',
    salon_id     INT UNSIGNED NULL COMMENT '施術サロンID (NULL=個人登録)',
    creator_user_id INT UNSIGNED NOT NULL COMMENT '登録者ユーザーID',
    finger_type  ENUM('thumb','index','middle','ring','pinky','any') NOT NULL DEFAULT 'any' COMMENT '推奨指 (any=どの指でもOK)',
    is_public    TINYINT(1) NOT NULL DEFAULT 1 COMMENT '公開フラグ (0=自分のみ)',
    like_count   INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'いいね数',
    used_count   INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '貼り付けられた回数',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_nails_salon (salon_id),
    INDEX idx_nails_creator (creator_user_id),
    INDEX idx_nails_public (is_public, created_at),

    CONSTRAINT fk_nails_salon
        FOREIGN KEY (salon_id) REFERENCES salons (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_nails_creator
        FOREIGN KEY (creator_user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ネイルデザインマスター (シール交換の「シール」に相当)';

-- ユーザーの指に貼り付けたネイル
CREATE TABLE IF NOT EXISTS user_nails (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL COMMENT 'ユーザーID',
    finger_index TINYINT UNSIGNED NOT NULL COMMENT '0=親指, 1=人差指, 2=中指, 3=薬指, 4=小指',
    nail_id      INT UNSIGNED NOT NULL COMMENT '貼り付けたネイルID',
    applied_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '貼り付け日時',

    UNIQUE KEY uk_user_finger (user_id, finger_index),

    CONSTRAINT fk_user_nails_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_user_nails_nail
        FOREIGN KEY (nail_id) REFERENCES nails (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ユーザーの指にセットしたネイル (5本まで)';

-- ネイルお気に入り（いいね）
CREATE TABLE IF NOT EXISTS nail_likes (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nail_id      INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_nail_like (nail_id, user_id),

    CONSTRAINT fk_nail_likes_nail
        FOREIGN KEY (nail_id) REFERENCES nails (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_nail_likes_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ネイルへのいいね';

-- ネイル画像アップロード用ディレクトリ
-- /uploads/nails/ に保存される想定
