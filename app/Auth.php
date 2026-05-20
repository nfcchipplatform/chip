<?php
/**
 * PONNU — Auth.php
 * 認証クラス
 *
 * - password_hash / verify (bcrypt cost=12)
 * - sessions テーブルへのDB保存
 * - login / logout / check / requireLogin / requireRole
 */

declare(strict_types=1);

class Auth
{
    private const BCRYPT_COST = 12;
    private const SESSION_KEY = 'auth_user_id';
    private const TOKEN_KEY   = 'auth_token';

    // =========================================================================
    // パスワードハッシュ
    // =========================================================================

    /**
     * パスワードをbcryptでハッシュ化
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
    }

    /**
     * パスワードの検証
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    // =========================================================================
    // ログイン / ログアウト
    // =========================================================================

    /**
     * ログイン処理
     * - DB の sessions テーブルにセッションを保存
     * - $_SESSION にユーザーIDとトークンを格納
     *
     * @param  int    $userId
     * @param  bool   $remember 将来の「ログイン維持」機能のプレースホルダ
     * @return bool
     */
    public static function login(int $userId, bool $remember = false): bool
    {
        $db    = Database::getInstance();
        $token = bin2hex(random_bytes(32)); // hex64

        $ip        = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua        = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);
        $expiresAt = date('Y-m-d H:i:s', time() + (defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 7200));

        try {
            $db->execute(
                'INSERT INTO sessions (user_id, session_token, ip_address, user_agent, expires_at)
                 VALUES (?, ?, ?, ?, ?)',
                [$userId, $token, $ip, $ua, $expiresAt]
            );
        } catch (PDOException $e) {
            error_log('[Auth::login] DB error: ' . $e->getMessage());
            return false;
        }

        // セッション固定化攻撃対策：セッションIDを再生成
        session_regenerate_id(true);

        $_SESSION[self::SESSION_KEY] = $userId;
        $_SESSION[self::TOKEN_KEY]   = $token;

        // last_login_at 更新
        $db->execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$userId]);

        return true;
    }

    /**
     * ログアウト処理
     * - DBのセッションレコードを削除
     * - $_SESSION を破棄
     */
    public static function logout(): void
    {
        if (isset($_SESSION[self::TOKEN_KEY])) {
            $db = Database::getInstance();
            $db->execute(
                'DELETE FROM sessions WHERE session_token = ?',
                [$_SESSION[self::TOKEN_KEY]]
            );
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    // =========================================================================
    // セッション検証
    // =========================================================================

    /**
     * ログイン済みか確認（DBセッションと照合）
     */
    public static function check(): bool
    {
        if (empty($_SESSION[self::SESSION_KEY]) || empty($_SESSION[self::TOKEN_KEY])) {
            return false;
        }

        $db  = Database::getInstance();
        $row = $db->fetchOne(
            'SELECT id FROM sessions
             WHERE session_token = ?
               AND user_id = ?
               AND expires_at > NOW()
             LIMIT 1',
            [$_SESSION[self::TOKEN_KEY], $_SESSION[self::SESSION_KEY]]
        );

        if (!$row) {
            // セッションが無効またはタイムアウト
            self::logout();
            return false;
        }

        return true;
    }

    /**
     * ログイン済みユーザーの情報を取得
     * ログインしていない場合は null
     *
     * @return array|null
     */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        $db = Database::getInstance();
        return $db->fetchOne(
            'SELECT id, email, display_name, username, role, salon_id,
                    direct_link_enabled, direct_link_url, status, created_at
             FROM users
             WHERE id = ? AND status = ?
             LIMIT 1',
            [$_SESSION[self::SESSION_KEY], 'active']
        ) ?: null;
    }

    /**
     * ログインしていない場合にログインページへリダイレクト
     */
    public static function requireLogin(string $redirectTo = '/auth/login.php'): void
    {
        if (!self::check()) {
            redirect($redirectTo . '?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
        }
    }

    /**
     * 指定ロール以外はリダイレクト
     *
     * @param  string|string[] $roles  許可するロール ('USER','SALON_ADMIN','SUPER_ADMIN')
     * @param  string          $redirectTo
     */
    public static function requireRole(array|string $roles, string $redirectTo = '/'): void
    {
        self::requireLogin();

        $user = self::user();
        if ($user === null) {
            redirect($redirectTo);
        }

        $allowed = is_array($roles) ? $roles : [$roles];
        if (!in_array($user['role'], $allowed, true)) {
            http_response_code(403);
            redirect($redirectTo);
        }
    }

    /**
     * 現在のユーザーIDを返す（未ログイン時は null）
     */
    public static function id(): ?int
    {
        return isset($_SESSION[self::SESSION_KEY])
            ? (int)$_SESSION[self::SESSION_KEY]
            : null;
    }

    // =========================================================================
    // 期限切れセッション削除（定期クリーンアップ用）
    // =========================================================================

    /**
     * 期限切れセッションをDBから削除
     * cron や定期処理で呼び出すことを推奨
     */
    public static function purgeExpiredSessions(): int
    {
        $db = Database::getInstance();
        return $db->execute('DELETE FROM sessions WHERE expires_at < NOW()');
    }
}
