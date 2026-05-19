<?php
/**
 * PONNU — config.example.php
 * このファイルをコピーして config.php に名前を変更し、実際の値を設定してください。
 *
 * cp app/config.example.php app/config.php
 * ※ config.php は .gitignore に含まれているのでコミットされません
 */

// =============================================================================
// データベース設定
// =============================================================================
define('DB_HOST',    'localhost');         // お名前.com では通常 localhost
define('DB_PORT',    3306);
define('DB_NAME',    'your_database_name');
define('DB_USER',    'your_db_username');
define('DB_PASS',    'your_db_password');
define('DB_CHARSET', 'utf8mb4');

// =============================================================================
// アプリケーション設定
// =============================================================================
define('APP_NAME', 'PONNU');
define('APP_URL',  'https://your-domain.com');  // 末尾スラッシュなし
define('APP_ENV',  'production');               // 'development' or 'production'
define('APP_DEBUG', false);                     // 本番は false

// =============================================================================
// セキュリティ設定
// =============================================================================
// セッション暗号化キー（32文字以上のランダム文字列）
// php -r "echo bin2hex(random_bytes(32));" で生成
define('APP_SECRET', 'change-this-to-a-random-32-byte-hex-string');

// セッション有効期間（秒）デフォルト2時間
define('SESSION_LIFETIME', 7200);

// パスワードリセットトークン有効期間（秒）デフォルト1時間
define('RESET_TOKEN_LIFETIME', 3600);

// =============================================================================
// ファイルアップロード設定
// =============================================================================
define('UPLOAD_DIR', __DIR__ . '/../public_html/uploads/');
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024);  // 5MB
define('UPLOAD_ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// =============================================================================
// タイムゾーン
// =============================================================================
date_default_timezone_set('Asia/Tokyo');

// =============================================================================
// メール設定
// =============================================================================
define('MAIL_FROM',       'noreply@your-domain.com');
define('MAIL_FROM_NAME',  'PONNU');
define('MAIL_REPLY_TO',   'support@your-domain.com');

// =============================================================================
// アセット画像パス定数 (public_html/assets/img/ 以下)
// ※ 素材を配置したらこのパスにファイルを置くだけでOK
// 実際の配置場所は public_html/assets/img/ 配下の各サブディレクトリ
// =============================================================================
define('ASSET_BASE',           '/assets/img/');

// ロゴ: public_html/assets/img/logo.png に配置する
define('ASSET_LOGO',           '/assets/img/logo.png');

// デフォルトアバター: public_html/assets/img/default-avatar.png に配置する
define('ASSET_DEFAULT_AVATAR', '/assets/img/default-avatar.png');

// Hamsa Hand 画像: public_html/assets/img/hand/ ディレクトリに配置済み
define('ASSET_HAND_OPEN',      '/assets/img/hand/handopen.png');
define('ASSET_HAND_CLOSE',     '/assets/img/hand/handclose.png');
define('ASSET_HAND_GOO',       '/assets/img/hand/handgoo.png');
define('ASSET_BLANK_NAIL',     '/assets/img/hand/blanknail.png');
define('ASSET_BLANK_NAIL_BK',  '/assets/img/hand/blanknailbk.png');

// NFCカード画像: public_html/assets/img/nfc-card.png に配置する
define('ASSET_NFC_CARD',       '/assets/img/nfc-card.png');

// Soul Canvas 画像ディレクトリ (001.jpg〜050.jpg): public_html/assets/img/soul/ 配置済み
define('ASSET_SOUL_DIR',       '/assets/img/soul/');
