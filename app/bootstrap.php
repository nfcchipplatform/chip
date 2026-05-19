<?php
/**
 * PONNU — bootstrap.php
 * すべてのPHPエントリポイントから最初に require するファイル。
 * - オートロード（クラスファイル）
 * - 設定読み込み
 * - セッション初期化
 * - CSRF 初期化
 * - エラー設定
 */

declare(strict_types=1);

// =============================================================================
// パス定数
// =============================================================================
define('APP_ROOT',  __DIR__);
define('BASE_ROOT', dirname(__DIR__));

// =============================================================================
// 設定読み込み
// =============================================================================
$configFile = APP_ROOT . '/config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    die('config.php が見つかりません。config.example.php をコピーして設定してください。');
}
require_once $configFile;

// =============================================================================
// エラー設定
// =============================================================================
if (defined('APP_DEBUG') && APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('log_errors', '1');
    ini_set('error_log', BASE_ROOT . '/logs/php_error.log');
}

// =============================================================================
// クラスオートロード (composer 不使用)
// =============================================================================
spl_autoload_register(function (string $class): void {
    $file = APP_ROOT . '/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// =============================================================================
// ヘルパー関数の読み込み
// =============================================================================
require_once APP_ROOT . '/helpers.php';

// =============================================================================
// セッション設定 (DBセッションの場合は session_set_save_handler を使用)
// =============================================================================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Lax');

    // HTTPS 環境では cookie_secure を有効にする
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', '1');
    }

    session_name('PONNU_SID');
    session_start();
}

// =============================================================================
// CSRF 初期化
// =============================================================================
Csrf::init();

// =============================================================================
// ASSET_* 定数フォールバック (config.php に定義がない場合の安全策)
// =============================================================================
defined('ASSET_BASE')            || define('ASSET_BASE',           '/assets/img/');
defined('ASSET_LOGO')            || define('ASSET_LOGO',           '/assets/img/logo.gif');
defined('ASSET_DEFAULT_AVATAR')  || define('ASSET_DEFAULT_AVATAR', '/assets/img/default-avatar.png');
defined('ASSET_HAND_OPEN')       || define('ASSET_HAND_OPEN',      '/assets/img/hand/handopen.png');
defined('ASSET_HAND_CLOSE')      || define('ASSET_HAND_CLOSE',     '/assets/img/hand/handclose.png');
defined('ASSET_HAND_GOO')        || define('ASSET_HAND_GOO',       '/assets/img/hand/handgoo.png');
defined('ASSET_BLANK_NAIL')      || define('ASSET_BLANK_NAIL',     '/assets/img/hand/blanknail.png');
defined('ASSET_BLANK_NAIL_BK')   || define('ASSET_BLANK_NAIL_BK',  '/assets/img/hand/blanknailbk.png');
defined('ASSET_NFC_CARD')        || define('ASSET_NFC_CARD',       '/assets/img/nfc-card.png');
defined('ASSET_SOUL_DIR')        || define('ASSET_SOUL_DIR',       '/assets/img/soul/');
defined('MAIL_FROM')             || define('MAIL_FROM',            'noreply@example.com');
defined('MAIL_FROM_NAME')        || define('MAIL_FROM_NAME',       'PONNU');
defined('MAIL_REPLY_TO')         || define('MAIL_REPLY_TO',        'noreply@example.com');
