<?php
/**
 * PONNU — Csrf.php
 * CSRFトークン生成・検証
 *
 * 使用方法:
 *   // ビューで出力
 *   Csrf::field();         // <input type="hidden" ...>
 *   Csrf::meta();          // <meta name="csrf-token" ...>
 *
 *   // 検証 (POST ハンドラの先頭で)
 *   Csrf::verify();        // 失敗時 403 + exit
 */

declare(strict_types=1);

class Csrf
{
    private const SESSION_KEY = '_csrf_token';
    private const FIELD_NAME  = '_csrf';
    private const HEADER_NAME = 'X-CSRF-Token';

    /**
     * セッションにトークンが無ければ生成する（bootstrap.php から呼ばれる）
     */
    public static function init(): void
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
    }

    /**
     * 現在のトークンを返す
     */
    public static function token(): string
    {
        return $_SESSION[self::SESSION_KEY] ?? '';
    }

    /**
     * hidden フィールドを出力
     */
    public static function field(): void
    {
        echo '<input type="hidden" name="' . self::FIELD_NAME . '" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * meta タグを出力（JS からの AJAX 用）
     */
    public static function meta(): void
    {
        echo '<meta name="csrf-token" content="'
            . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * トークンを検証する
     * 失敗時は 403 を返して終了
     *
     * @param  bool $die  false にすると die しないで bool を返す（テスト用）
     * @return bool
     */
    public static function verify(bool $die = true): bool
    {
        $submitted = '';

        // POST フィールドを優先、次に HTTP ヘッダを確認
        if (!empty($_POST[self::FIELD_NAME])) {
            $submitted = $_POST[self::FIELD_NAME];
        } elseif (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $submitted = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }

        $expected = self::token();
        $valid    = $expected !== '' && hash_equals($expected, $submitted);

        if (!$valid) {
            if ($die) {
                http_response_code(403);
                echo json_encode(['error' => 'CSRF token mismatch']);
                exit;
            }
            return false;
        }

        return true;
    }
}
