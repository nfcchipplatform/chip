<?php
/**
 * PONNU — helpers.php
 * グローバルヘルパー関数
 *
 * - e()              HTMLエスケープ
 * - redirect()       リダイレクト
 * - json_response()  JSON レスポンスを送信して終了
 * - flash_set()      フラッシュメッセージ設定
 * - flash_get()      フラッシュメッセージ取得（取得後に削除）
 * - csrf_field()     CSRF hidden フィールド出力のショートハンド
 * - asset()          assets URL を生成
 * - base_url()       ベースURL を生成
 */

declare(strict_types=1);

// =============================================================================
// HTML エスケープ
// =============================================================================

/**
 * HTML 特殊文字をエスケープして返す
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// =============================================================================
// リダイレクト
// =============================================================================

/**
 * 指定URLにリダイレクトして終了
 *
 * @param string $url     リダイレクト先URL（絶対 or 相対）
 * @param int    $status  HTTPステータスコード (デフォルト 302)
 */
function redirect(string $url, int $status = 302): never
{
    // 外部URLへのオープンリダイレクト防止
    if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
        // 相対パスはそのまま使用
    } elseif (defined('APP_URL') && !str_starts_with($url, APP_URL)) {
        // 外部URLは許可しない → トップへ
        $url = '/';
    }

    http_response_code($status);
    header('Location: ' . $url);
    exit;
}

// =============================================================================
// JSON レスポンス
// =============================================================================

/**
 * JSONレスポンスを送信して終了
 *
 * @param mixed $data        レスポンスデータ
 * @param int   $statusCode  HTTPステータスコード
 */
function json_response(mixed $data, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

// =============================================================================
// フラッシュメッセージ
// =============================================================================

/**
 * フラッシュメッセージをセッションに保存
 *
 * @param string $type    'success' | 'error' | 'info'
 * @param string $message
 */
function flash_set(string $type, string $message): void
{
    $_SESSION['_flash'][$type] = $message;
}

/**
 * フラッシュメッセージを取得してセッションから削除
 *
 * @param  string $type
 * @return string|null
 */
function flash_get(string $type): ?string
{
    $msg = $_SESSION['_flash'][$type] ?? null;
    unset($_SESSION['_flash'][$type]);
    return $msg;
}

/**
 * すべてのフラッシュメッセージを取得してセッションから削除
 *
 * @return array<string, string>
 */
function flash_all(): array
{
    $all = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $all;
}

// =============================================================================
// アセット URL
// =============================================================================

/**
 * assets/ 以下のURLを生成
 *
 * @param  string $path  例: 'css/app.css'
 * @return string
 */
function asset(string $path): string
{
    $base = defined('APP_URL') ? APP_URL : '';
    return $base . '/assets/' . ltrim($path, '/');
}

/**
 * ベースURL を生成
 *
 * @param  string $path  例: '/dashboard/'
 * @return string
 */
function base_url(string $path = '/'): string
{
    $base = defined('APP_URL') ? APP_URL : '';
    return $base . '/' . ltrim($path, '/');
}

// =============================================================================
// CSRF ショートハンド
// =============================================================================

/**
 * CSRF hidden フィールドを出力
 */
function csrf_field(): void
{
    Csrf::field();
}

// =============================================================================
// 入力値サニタイズ
// =============================================================================

/**
 * POST/GET の値を取得して trim。存在しない場合は $default を返す
 *
 * @param  string $key
 * @param  string $default
 * @param  string $method  'POST' | 'GET'
 * @return string
 */
function input(string $key, string $default = '', string $method = 'POST'): string
{
    $source = strtoupper($method) === 'GET' ? $_GET : $_POST;
    return isset($source[$key]) ? trim((string)$source[$key]) : $default;
}

// =============================================================================
// アセットヘルパー
// =============================================================================

/**
 * アセット画像が実際に存在するか確認する
 * DOCUMENT_ROOT 基準でファイルシステムのパスを組み立てる
 *
 * @param  string $assetConst  定数値 例: ASSET_LOGO
 * @return bool
 */
function asset_exists(string $assetConst): bool
{
    if ($assetConst === '') return false;
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
    $path    = $docRoot . '/' . ltrim($assetConst, '/');
    return file_exists($path);
}

/**
 * アセット画像タグを出力する。ファイルが存在しない場合は SVG プレースホルダを返す
 *
 * @param  string $assetConst   定数値
 * @param  string $alt          alt テキスト
 * @param  string $class        CSS クラス
 * @param  string $fallbackSvg  ファイル未配置時の SVG 文字列 (空の場合は何も出力しない)
 * @return string
 */
function asset_img(string $assetConst, string $alt = '', string $class = '', string $fallbackSvg = ''): string
{
    if (asset_exists($assetConst)) {
        return '<img src="' . e($assetConst) . '" alt="' . e($alt) . '" class="' . e($class) . '">';
    }
    return $fallbackSvg;
}
