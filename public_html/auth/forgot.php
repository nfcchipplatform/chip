<?php
/**
 * PONNU — auth/forgot.php
 * パスワードリセット申請: メール入力 → トークン発行 → メール送信
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
require_once APP_ROOT . '/views/layout.php';

if (Auth::check()) {
    redirect('/dashboard/');
}

$errors  = [];
$sent    = false;
$formEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();

    $formEmail = input('email');
    $db        = Database::getInstance();
    $ip        = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $rateKey   = 'forgot:' . $ip;

    // レート制限
    $rate = $db->fetchOne(
        'SELECT attempts, blocked_until FROM rate_limits WHERE `key` = ?',
        [$rateKey]
    );
    if ($rate && $rate['blocked_until'] && strtotime($rate['blocked_until']) > time()) {
        $errors[] = '試行回数が多すぎます。しばらく待ってから再度お試しください。';
    } else {
        if (!filter_var($formEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'メールアドレスの形式が正しくありません。';
        }

        if (empty($errors)) {
            $user = $db->fetchOne(
                'SELECT id, email FROM users WHERE email = ? AND status = ? LIMIT 1',
                [$formEmail, 'active']
            );

            // ユーザーが存在しなくても同じ表示にして情報漏えいを防ぐ
            if ($user) {
                $rawToken  = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $rawToken);
                $expiresAt = date('Y-m-d H:i:s', time() + (defined('RESET_TOKEN_LIFETIME') ? RESET_TOKEN_LIFETIME : 3600));

                // 古いトークンを無効化
                $db->execute(
                    'UPDATE password_reset_tokens SET used_at = NOW()
                     WHERE user_id = ? AND used_at IS NULL',
                    [$user['id']]
                );

                $db->execute(
                    'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
                     VALUES (?, ?, ?)',
                    [$user['id'], $tokenHash, $expiresAt]
                );

                Mail::sendPasswordReset($user['email'], $rawToken);
            }

            // レート制限更新
            $db->execute(
                'INSERT INTO rate_limits (`key`, attempts, window_start)
                 VALUES (?, 1, NOW())
                 ON DUPLICATE KEY UPDATE
                     attempts     = IF(window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR), 1, attempts + 1),
                     window_start = IF(window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR), NOW(), window_start),
                     blocked_until = IF(attempts >= 5, DATE_ADD(NOW(), INTERVAL 1 HOUR), blocked_until)',
                [$rateKey]
            );

            $sent = true;
        }
    }
}

$pageTitle = 'パスワードリセット';
?>
<!DOCTYPE html>
<html lang="ja">
<head><?php layout_head($pageTitle); ?></head>
<body class="bg-gray-50 min-h-screen font-sans">
<?php layout_header(); ?>

<main class="flex items-center justify-center min-h-[calc(100vh-3.5rem)] px-4 py-12">
    <div class="w-full max-w-md">

        <div class="text-center mb-8">
            <div class="text-3xl font-bold text-indigo-600 mb-2">PONNU</div>
            <p class="text-gray-500 text-sm">パスワードをリセットします</p>
        </div>

        <?php if ($sent): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-900 mb-2">メールを送信しました</h2>
                <p class="text-gray-600 text-sm mb-6">
                    ご登録のメールアドレスにパスワードリセット用のリンクを送信しました。<br>
                    メールが届かない場合は迷惑メールフォルダをご確認ください。
                </p>
                <a href="/auth/login.php" class="text-indigo-600 hover:underline text-sm">ログインページへ戻る</a>
            </div>
        <?php else: ?>
            <?php if ($errors): ?>
                <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-4">
                    <?php foreach ($errors as $err): ?>
                        <p class="text-sm text-red-700"><?= e($err) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                <p class="text-sm text-gray-600 mb-6">
                    登録済みのメールアドレスを入力すると、パスワードリセット用のメールをお送りします。
                </p>
                <form method="post" action="/auth/forgot.php" novalidate>
                    <?php csrf_field(); ?>
                    <div class="mb-6">
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                            メールアドレス
                        </label>
                        <input type="email" id="email" name="email"
                               value="<?= e($formEmail) ?>"
                               required autocomplete="email"
                               class="w-full px-4 py-2.5 rounded-lg border border-gray-300
                                      focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                    </div>
                    <button type="submit"
                            class="w-full py-3 bg-indigo-600 text-white font-semibold rounded-lg
                                   hover:bg-indigo-700 transition-colors">
                        リセットメールを送信
                    </button>
                </form>
            </div>

            <p class="text-center text-sm text-gray-500 mt-6">
                <a href="/auth/login.php" class="text-indigo-600 hover:underline">ログインへ戻る</a>
            </p>
        <?php endif; ?>
    </div>
</main>

<?php layout_footer(); ?>
</body>
</html>
