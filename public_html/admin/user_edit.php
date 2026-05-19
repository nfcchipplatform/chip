<?php
/**
 * PONNU — admin/user_edit.php
 * ユーザー詳細編集 (SUPER_ADMIN)
 */
declare(strict_types=1);
$bootstrap = require __DIR__ . '/../../app/bootstrap.php';
require_once APP_ROOT . '/views/dashboard_layout.php';

Auth::requireRole('SUPER_ADMIN');

$db = Database::getInstance();
$me = Auth::user();

$id = (int)($_GET['id'] ?? $_POST['user_id'] ?? 0);
if ($id <= 0) {
    flash_set('error', '不正なIDです。');
    redirect('/admin/users.php');
}

// =============================
// POST 処理
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $action = input('action');

    try {
        if ($action === 'save') {
            $email    = input('email');
            $username = input('username');
            $role     = input('role');
            $salonId  = input('salon_id') === '' ? null : (int)input('salon_id');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash_set('error', 'メールアドレスが不正です。');
            } elseif (!in_array($role, ['USER','SALON_ADMIN','SUPER_ADMIN'], true)) {
                flash_set('error', '役割が不正です。');
            } elseif ($id === (int)$me['id'] && $role !== 'SUPER_ADMIN') {
                flash_set('error', '自分自身のSUPER_ADMIN権限は降格できません。');
            } else {
                $db->execute(
                    'UPDATE users SET email = ?, username = ?, role = ?, salon_id = ? WHERE id = ?',
                    [$email, $username !== '' ? $username : null, $role, $salonId, $id]
                );
                flash_set('success', 'ユーザー情報を更新しました。');
            }
        } elseif ($action === 'unlink_nfc') {
            $nfcId = (int)input('nfc_id');
            if ($nfcId > 0) {
                $db->execute('UPDATE nfc_chips SET user_id = NULL WHERE id = ? AND user_id = ?', [$nfcId, $id]);
                flash_set('success', 'NFCチップを解除しました。');
            }
        } elseif ($action === 'reset_password') {
            $newPass = bin2hex(random_bytes(8)); // 16文字
            $hash    = Auth::hashPassword($newPass);
            $db->execute('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $id]);
            flash_set('success', '仮パスワードを発行しました: ' . $newPass . '  ※このメッセージは1度しか表示されません。本人に安全な経路で伝えてください。');
        }
    } catch (Throwable $e) {
        error_log('[admin/user_edit] ' . $e->getMessage());
        flash_set('error', '操作に失敗しました: ' . $e->getMessage());
    }
    redirect('/admin/user_edit.php?id=' . $id);
}

// =============================
// データ取得
// =============================
$user = $db->fetchOne(
    'SELECT u.*, p.bio, p.avatar_url, p.title FROM users u LEFT JOIN profiles p ON p.user_id = u.id WHERE u.id = ?',
    [$id]
);
if (!$user) {
    flash_set('error', '対象ユーザーが見つかりません。');
    redirect('/admin/users.php');
}

$nfcChips = $db->fetchAll('SELECT id, token, status, issued_at, last_accessed_at FROM nfc_chips WHERE user_id = ? ORDER BY id DESC', [$id]);
$salons   = $db->fetchAll('SELECT id, name FROM salons ORDER BY name');

dashboard_layout_start('管理画面 / ユーザー編集');
?>

<nav class="mb-6 flex flex-wrap gap-2 text-sm">
  <a href="/admin/" class="px-3 py-1.5 rounded-lg bg-white border hover:bg-gray-50">概要</a>
  <a href="/admin/users.php" class="px-3 py-1.5 rounded-lg bg-white border hover:bg-gray-50">← ユーザー一覧</a>
</nav>

<h1 class="text-2xl font-bold mb-6">ユーザー編集 #<?= (int)$user['id'] ?></h1>

<!-- 基本情報フォーム -->
<section class="bg-white rounded-2xl shadow-sm border p-6 mb-6">
  <h2 class="text-lg font-semibold mb-4">基本情報</h2>
  <form method="post" class="space-y-4">
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">メールアドレス</label>
      <input type="email" name="email" value="<?= e((string)$user['email']) ?>" required class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">ユーザー名</label>
      <input type="text" name="username" value="<?= e((string)($user['username'] ?? '')) ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">役割</label>
        <select name="role" class="w-full px-3 py-2 border rounded-lg">
          <?php foreach (['USER','SALON_ADMIN','SUPER_ADMIN'] as $opt): ?>
            <option value="<?= $opt ?>" <?= $user['role'] === $opt ? 'selected' : '' ?>><?= $opt ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">所属サロン</label>
        <select name="salon_id" class="w-full px-3 py-2 border rounded-lg">
          <option value="">— 未所属 —</option>
          <?php foreach ($salons as $s): ?>
            <option value="<?= (int)$s['id'] ?>" <?= (int)$user['salon_id'] === (int)$s['id'] ? 'selected' : '' ?>><?= e((string)$s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="flex gap-2">
      <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">保存</button>
      <a href="/admin/users.php" class="px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200">戻る</a>
    </div>
  </form>
</section>

<!-- パスワード強制リセット -->
<section class="bg-white rounded-2xl shadow-sm border p-6 mb-6">
  <h2 class="text-lg font-semibold mb-2">パスワード強制リセット</h2>
  <p class="text-sm text-gray-600 mb-4">仮パスワードを発行し、画面に1回だけ表示します。本人に安全な経路で伝えてください。</p>
  <form method="post" onsubmit="return confirm('本当にパスワードをリセットしますか?');">
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="reset_password">
    <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
    <button class="px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600">仮パスワードを発行</button>
  </form>
</section>

<!-- 紐付けNFC -->
<section class="bg-white rounded-2xl shadow-sm border p-6">
  <h2 class="text-lg font-semibold mb-4">紐付けNFCチップ (<?= count($nfcChips) ?>)</h2>
  <?php if (!$nfcChips): ?>
    <p class="text-sm text-gray-400">紐付けNFCチップはありません。</p>
  <?php else: ?>
    <table class="w-full text-sm">
      <thead class="text-left text-gray-500 border-b">
        <tr><th class="py-2">ID</th><th>トークン</th><th>状態</th><th>発行日</th><th>最終アクセス</th><th>操作</th></tr>
      </thead>
      <tbody>
        <?php foreach ($nfcChips as $c): ?>
        <tr class="border-b last:border-0">
          <td class="py-2 text-gray-400"><?= (int)$c['id'] ?></td>
          <td class="font-mono text-xs"><?= e(substr((string)$c['token'], 0, 16)) ?>…</td>
          <td><span class="text-xs px-2 py-0.5 rounded bg-gray-100"><?= e((string)$c['status']) ?></span></td>
          <td class="text-xs text-gray-500"><?= e((string)$c['issued_at']) ?></td>
          <td class="text-xs text-gray-500"><?= e((string)($c['last_accessed_at'] ?? '—')) ?></td>
          <td>
            <form method="post" onsubmit="return confirm('紐付けを解除しますか?');" class="inline">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="unlink_nfc">
              <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
              <input type="hidden" name="nfc_id" value="<?= (int)$c['id'] ?>">
              <button class="text-xs px-2 py-1 bg-red-50 text-red-700 hover:bg-red-100 rounded">解除</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<?php dashboard_layout_end(); ?>
