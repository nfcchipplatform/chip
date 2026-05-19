<?php
/**
 * PONNU — admin/users.php
 * ユーザー一覧・検索・操作 (SUPER_ADMIN)
 */
declare(strict_types=1);
$bootstrap = require __DIR__ . '/../../app/bootstrap.php';
require_once APP_ROOT . '/views/dashboard_layout.php';

Auth::requireRole('SUPER_ADMIN');

$db = Database::getInstance();
$me = Auth::user();

// =============================
// POST アクション
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $action  = input('action');
    $userId  = (int)input('user_id');

    if ($userId <= 0) {
        flash_set('error', '不正なユーザーIDです。');
        redirect('/admin/users.php');
    }

    try {
        if ($action === 'update_role') {
            $newRole = input('role');
            if (!in_array($newRole, ['USER', 'SALON_ADMIN', 'SUPER_ADMIN'], true)) {
                flash_set('error', '不正な役割です。');
            } elseif ($userId === (int)$me['id'] && $newRole !== 'SUPER_ADMIN') {
                flash_set('error', '自分自身のSUPER_ADMIN権限は降格できません。');
            } else {
                $db->execute('UPDATE users SET role = ? WHERE id = ?', [$newRole, $userId]);
                flash_set('success', '役割を更新しました。');
            }
        } elseif ($action === 'update_salon') {
            $salonId = input('salon_id') === '' ? null : (int)input('salon_id');
            $db->execute('UPDATE users SET salon_id = ? WHERE id = ?', [$salonId, $userId]);
            flash_set('success', '所属サロンを更新しました。');
        } elseif ($action === 'delete') {
            if ($userId === (int)$me['id']) {
                flash_set('error', '自分自身は削除できません。');
            } else {
                $db->execute('DELETE FROM users WHERE id = ?', [$userId]);
                flash_set('success', 'ユーザーを削除しました。');
            }
        }
    } catch (Throwable $e) {
        error_log('[admin/users] ' . $e->getMessage());
        flash_set('error', '操作に失敗しました: ' . $e->getMessage());
    }
    redirect('/admin/users.php?' . http_build_query(['q' => input('q', '', 'GET'), 'page' => input('page', '1', 'GET')]));
}

// =============================
// 検索 + ページネーション
// =============================
$q       = trim((string)($_GET['q'] ?? ''));
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = '';
$params = [];
if ($q !== '') {
    $where = 'WHERE (u.email LIKE ? OR u.username LIKE ? OR u.display_name LIKE ?)';
    $like  = '%' . $q . '%';
    $params = [$like, $like, $like];
}

$total = (int)($db->fetchOne("SELECT COUNT(*) AS c FROM users u $where", $params)['c'] ?? 0);
$pages = max(1, (int)ceil($total / $perPage));

$rows = $db->fetchAll(
    "SELECT u.id, u.email, u.username, u.display_name, u.role, u.salon_id, u.status, u.created_at,
            s.name AS salon_name,
            (SELECT COUNT(*) FROM nfc_chips WHERE user_id = u.id) AS nfc_count
     FROM users u
     LEFT JOIN salons s ON s.id = u.salon_id
     $where
     ORDER BY u.id DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

$salons = $db->fetchAll('SELECT id, name FROM salons ORDER BY name');

dashboard_layout_start('管理画面 / ユーザー');
?>

<nav class="mb-6 flex flex-wrap gap-2 text-sm">
  <a href="/admin/" class="px-3 py-1.5 rounded-lg bg-white border hover:bg-gray-50">概要</a>
  <a href="/admin/users.php" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white">ユーザー</a>
  <a href="/admin/nfc.php" class="px-3 py-1.5 rounded-lg bg-white border hover:bg-gray-50">NFC</a>
</nav>

<h1 class="text-2xl font-bold mb-4">ユーザー管理 <span class="text-sm font-normal text-gray-500">(<?= $total ?>件)</span></h1>

<form method="get" class="mb-6 flex gap-2">
  <input type="text" name="q" value="<?= e($q) ?>" placeholder="メール / ユーザー名 / 表示名で検索" class="flex-1 px-3 py-2 border rounded-lg">
  <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">検索</button>
  <?php if ($q !== ''): ?><a href="/admin/users.php" class="px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200">クリア</a><?php endif; ?>
</form>

<div class="bg-white rounded-2xl shadow-sm border overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 text-left text-gray-600">
      <tr>
        <th class="px-3 py-2">ID</th>
        <th class="px-3 py-2">メール / 名前</th>
        <th class="px-3 py-2">役割</th>
        <th class="px-3 py-2">サロン</th>
        <th class="px-3 py-2">NFC</th>
        <th class="px-3 py-2">登録日</th>
        <th class="px-3 py-2">操作</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r):
        $isMe = ((int)$r['id'] === (int)$me['id']);
      ?>
      <tr class="border-t hover:bg-gray-50 align-top">
        <td class="px-3 py-3 text-gray-400"><?= (int)$r['id'] ?><?php if ($isMe): ?> <span class="text-xs text-indigo-600">(自分)</span><?php endif; ?></td>
        <td class="px-3 py-3">
          <a href="/admin/user_edit.php?id=<?= (int)$r['id'] ?>" class="text-indigo-600 hover:underline font-medium"><?= e((string)$r['email']) ?></a>
          <div class="text-xs text-gray-500"><?= e((string)($r['display_name'] ?? '')) ?> @<?= e((string)($r['username'] ?? '')) ?></div>
        </td>
        <td class="px-3 py-3">
          <form method="post" class="flex gap-1">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="update_role">
            <input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="q" value="<?= e($q) ?>">
            <input type="hidden" name="page" value="<?= $page ?>">
            <select name="role" onchange="this.form.submit()" class="text-xs border rounded px-2 py-1">
              <?php foreach (['USER','SALON_ADMIN','SUPER_ADMIN'] as $opt): ?>
                <option value="<?= $opt ?>" <?= $r['role'] === $opt ? 'selected' : '' ?>><?= $opt ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </td>
        <td class="px-3 py-3">
          <form method="post" class="flex gap-1">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="update_salon">
            <input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="q" value="<?= e($q) ?>">
            <input type="hidden" name="page" value="<?= $page ?>">
            <select name="salon_id" onchange="this.form.submit()" class="text-xs border rounded px-2 py-1">
              <option value="">— 未所属 —</option>
              <?php foreach ($salons as $s): ?>
                <option value="<?= (int)$s['id'] ?>" <?= (int)$r['salon_id'] === (int)$s['id'] ? 'selected' : '' ?>><?= e((string)$s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </td>
        <td class="px-3 py-3 text-gray-600"><?= (int)$r['nfc_count'] ?></td>
        <td class="px-3 py-3 text-xs text-gray-500"><?= e((string)$r['created_at']) ?></td>
        <td class="px-3 py-3">
          <div class="flex gap-1">
            <a href="/admin/user_edit.php?id=<?= (int)$r['id'] ?>" class="text-xs px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">編集</a>
            <?php if (!$isMe): ?>
            <form method="post" onsubmit="return confirm('本当に削除しますか? この操作は取り消せません。');" class="inline">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
              <button class="text-xs px-2 py-1 bg-red-50 text-red-700 hover:bg-red-100 rounded">削除</button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
      <tr><td colspan="7" class="px-3 py-8 text-center text-gray-400">該当ユーザーがいません</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($pages > 1): ?>
<div class="mt-6 flex justify-center gap-1">
  <?php for ($p = 1; $p <= $pages; $p++):
    $url = '?' . http_build_query(['q' => $q, 'page' => $p]);
    $cls = $p === $page ? 'bg-indigo-600 text-white' : 'bg-white border hover:bg-gray-50';
  ?>
    <a href="<?= e($url) ?>" class="px-3 py-1.5 rounded-lg text-sm <?= $cls ?>"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php dashboard_layout_end(); ?>
