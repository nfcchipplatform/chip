<?php
/**
 * PONNU — auth/login.php
 * ログイン画面: メール + パスワード、CSRF、レート制限
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
require_once APP_ROOT . '/views/layout.php';

// ログイン済みならダッシュボードへ
if (Auth::check()) {
    redirect('/dashboard/');
}

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();

    $email    = input('email');
    $password = input('password');
    $db       = Database::getInstance();
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $rateKey  = 'login:' . $ip;

    // レート制限チェック
    $rate = $db->fetchOne(
        'SELECT attempts, blocked_until FROM rate_limits WHERE `key` = ?',
        [$rateKey]
    );
    if ($rate && $rate['blocked_until'] && strtotime($rate['blocked_until']) > time()) {
        $errors[] = 'ログイン試行が多すぎます。しばらく待ってから再度お試しください。';
    } else {
        // バリデーション
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'メールアドレスの形式が正しくありません。';
        }
        if (strlen($password) < 1) {
            $errors[] = 'パスワードを入力してください。';
        }

        if (empty($errors)) {
            $user = $db->fetchOne(
                'SELECT id, email, password_hash, status FROM users WHERE email = ? LIMIT 1',
                [$email]
            );

            if ($user && $user['status'] === 'active' && Auth::verifyPassword($password, $user['password_hash'])) {
                // ログイン成功 → レート制限リセット
                $db->execute('DELETE FROM rate_limits WHERE `key` = ?', [$rateKey]);
                Auth::login((int)$user['id']);
                $next = isset($_GET['next']) ? $_GET['next'] : '/dashboard/';
                // オープンリダイレクト防止
                if (!str_starts_with($next, '/')) {
                    $next = '/dashboard/';
                }
                redirect($next);
            } else {
                // 失敗 → レート制限カウント
                $db->execute(
                    'INSERT INTO rate_limits (`key`, attempts, window_start)
                     VALUES (?, 1, NOW())
                     ON DUPLICATE KEY UPDATE
                         attempts     = IF(window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR), 1, attempts + 1),
                         window_start = IF(window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR), NOW(), window_start),
                         blocked_until = IF(attempts >= 10, DATE_ADD(NOW(), INTERVAL 30 MINUTE), blocked_until)',
                    [$rateKey]
                );
                $errors[] = 'メールアドレスまたはパスワードが正しくありません。';
            }
        }
    }
}

$pageTitle = 'ログイン';
?>
<!DOCTYPE html>
<html lang="ja">
<head><?php layout_head($pageTitle); ?></head>
<body class="bg-gray-50 min-h-screen font-sans">
<?php layout_header(); ?>

<main class="flex items-center justify-center min-h-[calc(100vh-3.5rem)] px-4 py-12">
    <div class="w-full max-w-md">

        <!-- ロゴ -->
        <div class="text-center mb-8">
            <?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . ASSET_LOGO)): ?>
                <img src="<?= e(ASSET_LOGO) ?>" alt="PONNU" class="h-12 mx-auto mb-2">
            <?php else: ?>
                <div class="text-3xl font-bold text-indigo-600 mb-2">PONNU</div>
            <?php endif; ?>
            <p class="text-gray-500 text-sm">ログインしてプロフィールを管理</p>
        </div>

        <!-- フラッシュ -->
        <?php layout_flash(); ?>

        <!-- エラー表示 -->
        <?php if ($errors): ?>
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-4">
                <?php foreach ($errors as $err): ?>
                    <p class="text-sm text-red-700"><?= e($err) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- フォーム -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
            <form method="post" action="/auth/login.php" novalidate>
                <?php csrf_field(); ?>

                <div class="mb-5">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                        メールアドレス
                    </label>
                    <input type="email" id="email" name="email"
                           value="<?= e($email) ?>"
                           required autocomplete="email"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-gray-900
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent
                                  transition">
                </div>

                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                        パスワード
                    </label>
                    <input type="password" id="password" name="password"
                           required autocomplete="current-password"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 text-gray-900
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent
                                  transition">
                    <div class="mt-1 text-right">
                        <a href="/auth/forgot.php" class="text-xs text-indigo-600 hover:underline">
                            パスワードを忘れた方
                        </a>
                    </div>
                </div>

                <button type="submit"
                        class="w-full py-3 bg-indigo-600 text-white font-semibold rounded-lg
                               hover:bg-indigo-700 active:bg-indigo-800 transition-colors">
                    ログイン
                </button>
            </form>
        </div>

        <p class="text-center text-sm text-gray-500 mt-6">
            アカウントをお持ちでない方は
            <a href="/auth/register.php" class="text-indigo-600 font-medium hover:underline">新規登録</a>
        </p>
    </div>
</main>

<?php layout_footer(); ?>
</body>
</html>
