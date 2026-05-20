<?php
/**
 * PONNU — auth/register.php
 * 新規登録: メール + パスワード + ユーザー名 + (オプション) salon_code
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
require_once APP_ROOT . '/views/layout.php';

if (Auth::check()) {
    redirect('/dashboard/');
}

$errors       = [];
$formEmail    = '';
$formUsername = '';
// GET 時に ?salon_code=XXX があれば初期値として反映 (POST時は後段で上書き)
$formSalonCode = ($_SERVER['REQUEST_METHOD'] === 'POST') ? '' : trim((string)($_GET['salon_code'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();

    $formEmail     = input('email');
    $password      = input('password');
    $passwordConf  = input('password_confirmation');
    $formUsername  = input('username');
    $formSalonCode = input('salon_code');
    $db            = Database::getInstance();
    $ip            = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $rateKey       = 'register:' . $ip;

    // レート制限
    $rate = $db->fetchOne(
        'SELECT attempts, blocked_until FROM rate_limits WHERE `key` = ?',
        [$rateKey]
    );
    if ($rate && $rate['blocked_until'] && strtotime($rate['blocked_until']) > time()) {
        $errors[] = '登録試行が多すぎます。しばらく待ってから再度お試しください。';
    } else {
        // バリデーション
        if (!filter_var($formEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'メールアドレスの形式が正しくありません。';
        }
        if (strlen($password) < 8) {
            $errors[] = 'パスワードは8文字以上で入力してください。';
        }
        if ($password !== $passwordConf) {
            $errors[] = 'パスワードが一致しません。';
        }
        if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $formUsername)) {
            $errors[] = 'ユーザー名は3〜30文字の英数字・アンダースコアで入力してください。';
        }

        if (empty($errors)) {
            // メール重複チェック
            if ($db->fetchOne('SELECT id FROM users WHERE email = ? LIMIT 1', [$formEmail])) {
                $errors[] = 'このメールアドレスはすでに登録されています。';
            }
            // ユーザー名重複チェック
            if ($db->fetchOne('SELECT id FROM users WHERE username = ? LIMIT 1', [$formUsername])) {
                $errors[] = 'このユーザー名はすでに使用されています。';
            }
        }

        if (empty($errors)) {
            // サロンコード検証
            $salonId = null;
            if ($formSalonCode !== '') {
                $salon = $db->fetchOne(
                    'SELECT id FROM salons WHERE salon_code = ? LIMIT 1',
                    [$formSalonCode]
                );
                if ($salon) {
                    $salonId = (int)$salon['id'];
                } else {
                    $errors[] = '招待コードが正しくありません。';
                }
            }
        }

        if (empty($errors)) {
            $hash = Auth::hashPassword($password);

            $db->beginTransaction();
            try {
                $db->execute(
                    'INSERT INTO users (email, password_hash, display_name, username, salon_id)
                     VALUES (?, ?, ?, ?, ?)',
                    [$formEmail, $hash, $formUsername, $formUsername, $salonId]
                );
                $userId = (int)$db->lastInsertId();

                // profiles レコード作成 (1:1)
                $db->execute(
                    'INSERT INTO profiles (user_id) VALUES (?)',
                    [$userId]
                );

                $db->commit();

                // レート制限カウント
                $db->execute(
                    'INSERT INTO rate_limits (`key`, attempts, window_start)
                     VALUES (?, 1, NOW())
                     ON DUPLICATE KEY UPDATE
                         attempts     = attempts + 1,
                         window_start = IF(window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR), NOW(), window_start)',
                    [$rateKey]
                );

                Auth::login($userId);
                // next パラメータが内部パスならそこへ、なければダッシュボードへ
                $next = (string)($_GET['next'] ?? $_POST['next'] ?? '/dashboard/');
                if (!str_starts_with($next, '/') || str_starts_with($next, '//')) {
                    $next = '/dashboard/';
                }
                redirect($next);
            } catch (PDOException $e) {
                $db->rollback();
                error_log('[register] DB error: ' . $e->getMessage());
                $errors[] = '登録中にエラーが発生しました。しばらく後でお試しください。';
            }
        }
    }
}

$pageTitle = '新規登録';
?>
<!DOCTYPE html>
<html lang="ja">
<head><?php layout_head($pageTitle); ?></head>
<body class="bg-gray-50 min-h-screen font-sans">
<?php layout_header(); ?>

<main class="flex items-center justify-center min-h-[calc(100vh-3.5rem)] px-4 py-12">
    <div class="w-full max-w-md">

        <div class="text-center mb-8">
            <?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . ASSET_LOGO)): ?>
                <img src="<?= e(ASSET_LOGO) ?>" alt="PONNU" class="h-12 mx-auto mb-2">
            <?php else: ?>
                <div class="text-3xl font-bold text-indigo-600 mb-2">PONNU</div>
            <?php endif; ?>
            <p class="text-gray-500 text-sm">アカウントを作成してNFCカードを使い始めよう</p>
        </div>

        <?php layout_flash(); ?>

        <?php if ($errors): ?>
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-4">
                <?php foreach ($errors as $err): ?>
                    <p class="text-sm text-red-700"><?= e($err) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
            <form method="post" action="/auth/register.php<?= isset($_GET['next']) ? '?next=' . urlencode((string)$_GET['next']) : '' ?>" novalidate>
                <?php csrf_field(); ?>
                <?php if (isset($_GET['next'])): ?>
                  <input type="hidden" name="next" value="<?= e((string)$_GET['next']) ?>">
                <?php endif; ?>

                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                        メールアドレス <span class="text-red-500">*</span>
                    </label>
                    <input type="email" id="email" name="email"
                           value="<?= e($formEmail) ?>"
                           required autocomplete="email"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                </div>

                <div class="mb-4">
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">
                        ユーザー名 <span class="text-red-500">*</span>
                    </label>
                    <div class="flex items-center">
                        <span class="px-3 py-2.5 bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg text-gray-500 text-sm">@</span>
                        <input type="text" id="username" name="username"
                               value="<?= e($formUsername) ?>"
                               required autocomplete="username"
                               pattern="[a-zA-Z0-9_]{3,30}"
                               placeholder="your_name"
                               class="flex-1 px-4 py-2.5 border border-gray-300 rounded-r-lg
                                      focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                    </div>
                    <p class="text-xs text-gray-400 mt-1">3〜30文字、英数字とアンダースコアのみ</p>
                </div>

                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                        パスワード <span class="text-red-500">*</span>
                    </label>
                    <input type="password" id="password" name="password"
                           required autocomplete="new-password" minlength="8"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                    <p class="text-xs text-gray-400 mt-1">8文字以上</p>
                </div>

                <div class="mb-4">
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                        パスワード（確認） <span class="text-red-500">*</span>
                    </label>
                    <input type="password" id="password_confirmation" name="password_confirmation"
                           required autocomplete="new-password"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                </div>

                <div class="mb-6">
                    <label for="salon_code" class="block text-sm font-medium text-gray-700 mb-1">
                        サロン招待コード <span class="text-gray-400 text-xs">（任意）</span>
                    </label>
                    <input type="text" id="salon_code" name="salon_code"
                           value="<?= e($formSalonCode) ?>"
                           autocomplete="off"
                           placeholder="サロンから発行された招待コード"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                </div>

                <button type="submit"
                        class="w-full py-3 bg-indigo-600 text-white font-semibold rounded-lg
                               hover:bg-indigo-700 active:bg-indigo-800 transition-colors">
                    アカウント作成
                </button>
            </form>
        </div>

        <p class="text-center text-sm text-gray-500 mt-6">
            すでにアカウントをお持ちの方は
            <a href="/auth/login.php" class="text-indigo-600 font-medium hover:underline">ログイン</a>
        </p>
    </div>
</main>

<?php layout_footer(); ?>
</body>
</html>
