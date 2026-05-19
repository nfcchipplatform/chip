<?php
/**
 * PONNU — auth/reset.php
 * パスワードリセット: ?token=xxx → 新パスワード設定
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
require_once APP_ROOT . '/views/layout.php';

if (Auth::check()) {
    redirect('/dashboard/');
}

$errors   = [];
$success  = false;
$rawToken = isset($_GET['token']) ? trim($_GET['token']) : '';

// トークン形式チェック
if (!preg_match('/^[0-9a-f]{64}$/', $rawToken)) {
    $rawToken = '';
}

$tokenHash = $rawToken ? hash('sha256', $rawToken) : '';
$db        = Database::getInstance();
$tokenRow  = null;

if ($tokenHash) {
    $tokenRow = $db->fetchOne(
        'SELECT t.id, t.user_id, t.expires_at, t.used_at, u.email
         FROM password_reset_tokens t
         JOIN users u ON u.id = t.user_id
         WHERE t.token_hash = ? LIMIT 1',
        [$tokenHash]
    );
}

$tokenValid = $tokenRow
    && $tokenRow['used_at'] === null
    && strtotime($tokenRow['expires_at']) > time();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();

    if (!$tokenValid) {
        $errors[] = 'このリンクは無効または期限切れです。もう一度パスワードリセットを申請してください。';
    } else {
        $password     = input('password');
        $passwordConf = input('password_confirmation');

        if (strlen($password) < 8) {
            $errors[] = 'パスワードは8文字以上で入力してください。';
        }
        if ($password !== $passwordConf) {
            $errors[] = 'パスワードが一致しません。';
        }

        if (empty($errors)) {
            $hash = Auth::hashPassword($password);

            $db->beginTransaction();
            try {
                $db->execute(
                    'UPDATE users SET password_hash = ? WHERE id = ?',
                    [$hash, $tokenRow['user_id']]
                );
                // トークンを使用済みにする
                $db->execute(
                    'UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?',
                    [$tokenRow['id']]
                );
                // 既存のセッションを全削除してセキュリティ確保
                $db->execute(
                    'DELETE FROM sessions WHERE user_id = ?',
                    [$tokenRow['user_id']]
                );
                $db->commit();
                $success = true;
            } catch (PDOException $e) {
                $db->rollback();
                error_log('[reset] DB error: ' . $e->getMessage());
                $errors[] = 'エラーが発生しました。しばらく後でお試しください。';
            }
        }
    }
}

$pageTitle = 'パスワードの再設定';
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
            <p class="text-gray-500 text-sm">新しいパスワードを設定してください</p>
        </div>

        <?php if ($success): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-900 mb-2">パスワードを変更しました</h2>
                <p class="text-gray-600 text-sm mb-6">新しいパスワードでログインしてください。</p>
                <a href="/auth/login.php"
                   class="inline-block px-6 py-2.5 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors">
                    ログインページへ
                </a>
            </div>
        <?php elseif (!$tokenValid && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-900 mb-2">リンクが無効です</h2>
                <p class="text-gray-600 text-sm mb-6">
                    このリンクは無効または期限切れです。<br>
                    もう一度パスワードリセットを申請してください。
                </p>
                <a href="/auth/forgot.php"
                   class="text-indigo-600 hover:underline text-sm">パスワードリセット申請へ</a>
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
                <form method="post"
                      action="/auth/reset.php?token=<?= urlencode($rawToken) ?>"
                      novalidate>
                    <?php csrf_field(); ?>

                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                            新しいパスワード <span class="text-red-500">*</span>
                        </label>
                        <input type="password" id="password" name="password"
                               required autocomplete="new-password" minlength="8"
                               class="w-full px-4 py-2.5 rounded-lg border border-gray-300
                                      focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                        <p class="text-xs text-gray-400 mt-1">8文字以上</p>
                    </div>

                    <div class="mb-6">
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                            パスワード（確認） <span class="text-red-500">*</span>
                        </label>
                        <input type="password" id="password_confirmation" name="password_confirmation"
                               required autocomplete="new-password"
                               class="w-full px-4 py-2.5 rounded-lg border border-gray-300
                                      focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                    </div>

                    <button type="submit"
                            class="w-full py-3 bg-indigo-600 text-white font-semibold rounded-lg
                                   hover:bg-indigo-700 transition-colors">
                        パスワードを変更する
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php layout_footer(); ?>
</body>
</html>
