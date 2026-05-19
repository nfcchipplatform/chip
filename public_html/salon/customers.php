<?php
/**
 * PONNU — salon/customers.php
 * サロン顧客一覧 (SALON_ADMIN / SUPER_ADMIN)
 */
declare(strict_types=1);
$bootstrap = require __DIR__ . '/../../app/bootstrap.php';
require_once APP_ROOT . '/views/dashboard_layout.php';

Auth::requireRole(['SALON_ADMIN', 'SUPER_ADMIN']);

$me = Auth::user();
$db = Database::getInstance();

$salonId = (int)($me['salon_id'] ?? 0);
if ($salonId <= 0 && $me['role'] === 'SUPER_ADMIN') {
    $first = $db->fetchOne('SELECT id FROM salons ORDER BY id ASC LIMIT 1');
    $salonId = (int)($first['id'] ?? 0);
}
if ($salonId <= 0) {
    flash_set('error', 'サロンに所属していません。');
    redirect('/dashboard/');
}

$q       = trim((string)($_GET['q'] ?? ''));
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = 'WHERE u.salon_id = ?';
$params = [$salonId];
if ($q !== '') {
    $where .= ' AND (u.email LIKE ? OR u.username LIKE ? OR u.display_name LIKE ?)';
    $like   = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}

$total = (int)($db->fetchOne("SELECT COUNT(*) AS c FROM users u $where", $params)['c'] ?? 0);
$pages = max(1, (int)ceil($total / $perPage));

$rows = $db->fetchAll(
    "SELECT u.id, u.email, u.username, u.display_name, u.created_at, u.last_login_at,
            (SELECT COUNT(*) FROM nfc_chips WHERE user_id = u.id) AS nfc_count,
            p.avatar_url
     FROM users u
     LEFT JOIN profiles p ON p.user_id = u.id
     $where
     ORDER BY u.id DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

dashboard_layout_start('サロン管理 / 顧客');
?>

<nav class="mb-6 flex flex-wrap gap-2 text-sm">
  <a href="/salon/" class="px-3 py-1.5 rounded-lg bg-white border hover:bg-gray-50">概要</a>
  <a href="/salon/customers.php" class="px-3 py-1.5 rounded-lg bg-purple-600 text-white">顧客一覧</a>
  <a href="/salon/settings.php" class="px-3 py-1.5 rounded-lg bg-white border hover:bg-gray-50">設定</a>
</nav>

<h1 class="text-2xl font-bold mb-4">顧客一覧 <span class="text-sm font-normal text-gray-500">(<?= $total ?>件)</span></h1>

<form method="get" class="mb-6 flex gap-2">
  <input type="text" name="q" value="<?= e($q) ?>" placeholder="メール / 名前 で検索" class="flex-1 px-3 py-2 border rounded-lg">
  <button class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">検索</button>
  <?php if ($q !== ''): ?><a href="/salon/customers.php" class="px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200">クリア</a><?php endif; ?>
</form>

<div class="bg-white rounded-2xl shadow-sm border overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 text-left text-gray-600">
      <tr>
        <th class="px-3 py-2"></th>
        <th class="px-3 py-2">名前 / メール</th>
        <th class="px-3 py-2">NFC</th>
        <th class="px-3 py-2">登録日</th>
        <th class="px-3 py-2">最終ログイン</th>
        <th class="px-3 py-2">プロフィール</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr class="border-t hover:bg-gray-50">
        <td class="px-3 py-2">
          <?php if (!empty($r['avatar_url'])): ?>
            <img src="<?= e((string)$r['avatar_url']) ?>" class="w-8 h-8 rounded-full object-cover" alt="">
          <?php else: ?>
            <div class="w-8 h-8 rounded-full bg-gray-200"></div>
          <?php endif; ?>
        </td>
        <td class="px-3 py-2">
          <div class="font-medium"><?= e((string)($r['display_name'] ?: $r['username'] ?: '—')) ?></div>
          <div class="text-xs text-gray-500"><?= e((string)$r['email']) ?></div>
        </td>
        <td class="px-3 py-2"><?= (int)$r['nfc_count'] ?></td>
        <td class="px-3 py-2 text-xs text-gray-500"><?= e((string)$r['created_at']) ?></td>
        <td class="px-3 py-2 text-xs text-gray-500"><?= e((string)($r['last_login_at'] ?? '—')) ?></td>
        <td class="px-3 py-2">
          <?php if (!empty($r['username'])): ?>
            <a href="/u/?username=<?= urlencode((string)$r['username']) ?>" target="_blank" class="text-xs text-purple-600 hover:underline">表示 →</a>
          <?php else: ?>
            <span class="text-xs text-gray-400">—</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
      <tr><td colspan="6" class="px-3 py-8 text-center text-gray-400">該当する顧客がいません</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($pages > 1): ?>
<div class="mt-6 flex justify-center gap-1 flex-wrap">
  <?php for ($p = 1; $p <= $pages; $p++):
    $url = '?' . http_build_query(['q' => $q, 'page' => $p]);
    $cls = $p === $page ? 'bg-purple-600 text-white' : 'bg-white border hover:bg-gray-50';
  ?>
    <a href="<?= e($url) ?>" class="px-3 py-1.5 rounded-lg text-sm <?= $cls ?>"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php dashboard_layout_end(); ?>
