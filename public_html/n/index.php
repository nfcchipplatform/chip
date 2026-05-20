<?php
/**
 * PONNU — n/index.php
 * NFCランディングページ
 * URL: /n/?t={token}
 *
 * 3パターン:
 *  1. 未紐付き → アクティベーション画面（ログイン誘導 / ログイン済みならカードリンク確認）
 *  2. 紐付き済み → 公開プロフィールへ 302
 *  3. direct_link_enabled=1 → 3秒カウントダウン + スキップボタン → direct_link_url へ
 */

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$token = isset($_GET['t']) ? trim($_GET['t']) : '';

// トークン形式チェック（hex64文字）
if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
    http_response_code(400);
    renderError('無効なリンク', 'QRコード/NFCタグが正しく読み取れませんでした。もう一度お試しください。');
    exit;
}

$db      = Database::getInstance();
$ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$limitKey = 'token:' . $token;

// レート制限チェック
$rate = $db->fetchOne(
    'SELECT attempts, blocked_until FROM rate_limits WHERE `key` = ?',
    [$limitKey]
);
if ($rate && $rate['blocked_until'] && strtotime($rate['blocked_until']) > time()) {
    logNfcAccess($db, null, $token, $ip, 'rate_limited');
    http_response_code(429);
    renderError('アクセス制限中', 'しばらく経ってからもう一度お試しください。');
    exit;
}

// NFCチップ検索（user_idなしも含む）
$chip = $db->fetchOne(
    'SELECT id, user_id, status, label FROM nfc_chips WHERE token = ? LIMIT 1',
    [$token]
);

// レート制限カウンタ更新
updateNfcRateLimit($db, $limitKey);

// トークン無効
if (!$chip) {
    logNfcAccess($db, null, $token, $ip, 'invalid_token');
    http_response_code(404);
    renderError('リンクが見つかりません', 'このNFCタグは登録されていないか、無効化されています。');
    exit;
}

// 無効化済み
if ($chip['status'] !== 'active') {
    logNfcAccess($db, $chip['id'], $token, $ip, 'revoked');
    http_response_code(410);
    renderError('このカードは無効化されています', '新しいカードに差し替えられた可能性があります。');
    exit;
}

// ===== パターン1: 未紐付き =====
if (!$chip['user_id']) {
    logNfcAccess($db, $chip['id'], $token, $ip, 'success');

    $currentUser = Auth::user();

    // ログイン済み → このカードを自分に紐付け確認画面
    if ($currentUser) {
        // POST でアクティベーション実行
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate'])) {
            Csrf::verify();
            $db->execute(
                'UPDATE nfc_chips SET user_id = ? WHERE id = ? AND user_id IS NULL',
                [$currentUser['id'], $chip['id']]
            );
            flash_set('success', 'NFCカードを登録しました！');
            redirect('/dashboard/');
        }
        renderActivateConfirm($chip, $currentUser, $token);
    } else {
        // 未ログイン → ログイン誘導
        renderLoginPrompt($token);
    }
    exit;
}

// ===== ユーザー情報取得 =====
$user = $db->fetchOne(
    'SELECT u.id, u.username, u.display_name, u.status, u.direct_link_enabled, u.direct_link_url,
            p.is_public
     FROM users u
     JOIN profiles p ON p.user_id = u.id
     WHERE u.id = ?
     LIMIT 1',
    [$chip['user_id']]
);

if (!$user || $user['status'] !== 'active' || !$user['is_public']) {
    logNfcAccess($db, $chip['id'], $token, $ip, 'invalid_token');
    http_response_code(404);
    renderError('プロフィールが非公開です', 'このユーザーのプロフィールは現在非公開に設定されています。');
    exit;
}

// アクセスログ記録・最終アクセス更新
logNfcAccess($db, $chip['id'], $token, $ip, 'success');
$db->execute('UPDATE nfc_chips SET last_accessed_at = NOW() WHERE id = ?', [$chip['id']]);

// ===== パターン3: direct_link =====
if ($user['direct_link_enabled'] && !empty($user['direct_link_url'])) {
    $directUrl = filter_var($user['direct_link_url'], FILTER_VALIDATE_URL) ? $user['direct_link_url'] : '/';
    renderInterstitial($directUrl, $user);
    exit;
}

// ===== パターン2: 通常プロフィールへ =====
redirect('/u/?username=' . urlencode($user['username']));
exit;

// ---------------------------------------------------------------------------
// ヘルパー関数
// ---------------------------------------------------------------------------

function logNfcAccess(Database $db, ?int $chipId, string $token, string $ip, string $result): void
{
    $db->execute(
        'INSERT INTO access_logs (chip_id, token_attempted, ip_address, user_agent, referer, result)
         VALUES (?, ?, ?, ?, ?, ?)',
        [
            $chipId,
            $token,
            $ip,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512),
            substr($_SERVER['HTTP_REFERER']    ?? '', 0, 512),
            $result,
        ]
    );
}

function updateNfcRateLimit(Database $db, string $key): void
{
    $db->execute(
        'INSERT INTO rate_limits (`key`, attempts, window_start)
         VALUES (?, 1, NOW())
         ON DUPLICATE KEY UPDATE
             attempts     = IF(window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR), 1, attempts + 1),
             window_start = IF(window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR), NOW(), window_start),
             blocked_until = IF(attempts >= 20, DATE_ADD(NOW(), INTERVAL 30 MINUTE), blocked_until)',
        [$key]
    );
}

/** パターン3: インタースティシャル (3秒カウントダウン) */
function renderInterstitial(string $destUrl, array $user): void
{
    ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PONNU — リダイレクト中...</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">
<div class="text-center max-w-sm">
    <div class="w-20 h-20 flex items-center justify-center mx-auto mb-4">
        <img src="<?= e(ASSET_LOGO) ?>" alt="PONNU" class="w-full h-full object-contain">
    </div>
    <h1 class="text-lg font-bold text-gray-900 mb-1">
        <?= e($user['display_name'] ?: $user['username']) ?> のページへ移動します
    </h1>
    <p class="text-gray-500 text-sm mb-6">
        <span id="countdown">3</span> 秒後に自動的に移動します
    </p>
    <a id="skip-btn"
       href="<?= e($destUrl) ?>"
       class="inline-block px-8 py-2.5 bg-indigo-600 text-white rounded-full font-semibold text-sm
              hover:bg-indigo-700 transition-colors">
        今すぐ移動する
    </a>
    <p class="mt-4 text-xs text-gray-400">
        移動先: <span class="break-all"><?= e($destUrl) ?></span>
    </p>
</div>
<script>
let count = 3;
const el  = document.getElementById('countdown');
const timer = setInterval(() => {
    count--;
    el.textContent = count;
    if (count <= 0) {
        clearInterval(timer);
        window.location.href = <?= json_encode($destUrl) ?>;
    }
}, 1000);
</script>
</body>
</html>
    <?php
}

/** パターン1a: ログイン済み → アクティベーション確認 */
function renderActivateConfirm(array $chip, array $user, string $token): void
{
    ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PONNU — NFCカードの登録</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">
<div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 max-w-sm w-full text-center">

    <div class="w-16 h-16 bg-indigo-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/>
        </svg>
    </div>

    <h1 class="text-xl font-bold text-gray-900 mb-2">NFCカードを登録</h1>
    <p class="text-sm text-gray-600 mb-6">
        このNFCカードを <strong><?= e($user['display_name'] ?: $user['username']) ?></strong>
        (@<?= e($user['username']) ?>) のアカウントに紐付けますか？
    </p>

    <?php if (!empty($chip['label'])): ?>
        <p class="text-xs text-gray-400 mb-4">カード名: <?= e($chip['label']) ?></p>
    <?php endif; ?>

    <form method="post" action="/n/?t=<?= urlencode($token) ?>">
        <?php Csrf::field(); ?>
        <input type="hidden" name="activate" value="1">
        <button type="submit"
                class="w-full py-3 bg-indigo-600 text-white font-semibold rounded-xl
                       hover:bg-indigo-700 transition-colors mb-3">
            このカードを登録する
        </button>
    </form>
    <a href="/dashboard/"
       class="block text-sm text-gray-500 hover:text-gray-700">キャンセル</a>
</div>
</body>
</html>
    <?php
}

/** パターン1b: 未ログイン → ログイン誘導 */
function renderLoginPrompt(string $token): void
{
    ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PONNU — NFCカードのアクティベーション</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">
<div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 max-w-sm w-full text-center">

    <div class="w-16 h-16 bg-indigo-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
        </svg>
    </div>

    <h1 class="text-xl font-bold text-gray-900 mb-2">NFCカードを使い始めよう</h1>
    <p class="text-sm text-gray-600 mb-6">
        このNFCカードを有効化するには、PONNUにログインまたは新規登録が必要です。
    </p>

    <a href="/auth/login.php?next=<?= urlencode('/n/?t=' . $token) ?>"
       class="block w-full py-3 bg-indigo-600 text-white font-semibold rounded-xl
              hover:bg-indigo-700 transition-colors mb-3 text-sm">
        ログインして登録する
    </a>
    <a href="/auth/register.php?next=<?= urlencode('/n/?t=' . $token) ?>"
       class="block w-full py-3 border border-indigo-600 text-indigo-600 font-semibold rounded-xl
              hover:bg-indigo-50 transition-colors text-sm">
        新規登録する
    </a>
</div>
</body>
</html>
    <?php
}

/** エラー表示 */
function renderError(string $title, string $msg): void
{
    ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> — PONNU</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">
    <div class="text-center max-w-md">
        <p class="text-5xl mb-4">🤚</p>
        <h1 class="text-2xl font-bold text-gray-900 mb-3"><?= e($title) ?></h1>
        <p class="text-gray-600 mb-6"><?= e($msg) ?></p>
        <a href="/" class="text-indigo-600 hover:underline">トップへ戻る</a>
    </div>
</body>
</html>
    <?php
}
