-- =============================================================================
-- 002_admin_step4.sql
-- Step 4 管理画面に必要なスキーマ拡張
--   - nfc_chips.user_id を NULL 許容に変更（未割当チップを発行できるようにする）
--   - nfc_chips に memo カラム追加
--   - FK ON DELETE を SET NULL に変更（ユーザー削除時にチップは未割当として残す）
--
-- 適用: phpMyAdmin で対象DB(本プロジェクトのデータベース)を選択 → SQLタブで本ファイルを実行
-- =============================================================================

-- 既存FKを削除
ALTER TABLE nfc_chips DROP FOREIGN KEY fk_nfc_chips_user;

-- user_id を NULL 許容に
ALTER TABLE nfc_chips MODIFY user_id INT UNSIGNED NULL DEFAULT NULL COMMENT '所有ユーザーID（NULL=未割当）';

-- memo カラム追加（既に存在する場合はエラーになるので、念のため IF NOT EXISTS 風に）
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'nfc_chips'
                      AND COLUMN_NAME = 'memo');
SET @sql := IF(@col_exists = 0,
               'ALTER TABLE nfc_chips ADD COLUMN memo VARCHAR(500) NOT NULL DEFAULT '''' COMMENT ''管理用メモ'' AFTER label',
               'SELECT ''memo column already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FKを再作成 (ON DELETE SET NULL に変更)
ALTER TABLE nfc_chips
  ADD CONSTRAINT fk_nfc_chips_user
  FOREIGN KEY (user_id) REFERENCES users (id)
  ON DELETE SET NULL ON UPDATE CASCADE;
