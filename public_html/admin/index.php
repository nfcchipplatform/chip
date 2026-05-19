<?php
/**
 * PONNU — admin/index.php
 * 管理ダッシュボード概要 (SUPER_ADMIN)
 */
declare(strict_types=1);
$bootstrap = require __DIR__ . '/../../app/bootstrap.php';
require_once APP_ROOT . '/views/dashboard_layout.php';

Auth::requireRole('SUPER_ADMIN');

$db = Database::getInstance();

// 統計
$stats = [
    'users'        => (int)($db->fetchOne('SELECT COUNT(*) AS c FROM users')['c'] ?? 0),
    'salons'       => (int)($db->fetchOne('SELECT COUNT(*) AS c FROM salons')['c'] ?? 0),
    'nfc_total'    => (int)($db->fetchOne('SELECT COUNT(*) AS c FROM nfc_chips')['c'] ?? 0),
    'nfc_linked'   => (int)($db->fetchOne('SELECT COUNT(*) AS c FROM nfc_chips WHERE user_id IS NOT NULL')['c'] ?? 0),
    'users_recent' => (int)($db->fetchOne(
        'SELECT COUNT(*) AS c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
    )['c'] ?? 0),
];

$recentUsers = $db->fetchAll(
    'SELECT id, email, username, display_name, role, created_at
     FROM users ORDER BY id DESC LIMIT 10'
);
$recentChips = $db->fetchAll(
    'SELECT n.id, n.token, n.user_id, n.status, n.issued_at, u.email AS user_email
     FROM nfc_chips n LEFT JOIN users u ON u.id = n.user_id
     ORDER BY n.id DESC LIMIT 10'
);

dashboard_layout_start('管理画面 / 概要');
?>

<!-- 管理ナビ -->
<nav class="mb-6 flex flex-wrap gap-2 text-sm">
  <a href="/admin/" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white">概要</a>
  <a href="/admin/users.php" class="px-3 py-1.5 rounded-lg bg-white border hover:bg-gray-50">ユーザー</a>
  <a href="/admin/nfc.php" class="px-3 py-1.5 rounded-lg bg-white border hover:bg-gray-50">NFC</a>
</nav>

<h1 class="text-2xl font-bold mb-6">管理ダッシュボード</h1>

<!-- 統計カード -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
  <div class="bg-white rounded-2xl shadow-sm p-5 border">
    <p class="text-xs text-gray-500">総ユーザー数</p>
    <p class="text-3xl font-bold text-indigo-600 mt-1"><?= $stats['users'] ?></p>
    <p class="text-xs text-gray-400 mt-1">直近7日: +<?= $stats['users_recent'] ?></p>
  </div>
  <div class="bg-white rounded-2xl shadow-sm p-5 border">
    <p class="text-xs text-gray-500">サロン数</p>
    <p class="text-3xl font-bold text-purple-600 mt-1"><?= $stats['salons'] ?></p>
  </div>
  <div class="bg-white rounded-2xl shadow-sm p-5 border">
    <p class="text-xs text-gray-500">NFCチップ総数</p>
    <p class="text-3xl font-bold text-emerald-600 mt-1"><?= $stats['nfc_total'] ?></p>
  </div>
  <div class="bg-white rounded-2xl shadow-sm p-5 border">
    <p class="text-xs text-gray-500">NFC紐付け済</p>
    <p class="text-3xl font-bold text-amber-600 mt-1"><?= $stats['nfc_linked'] ?></p>
    <p class="text-xs text-gray-400 mt-1">未割当: <?= $stats['nfc_total'] - $stats['nfc_linked'] ?></p>
  </div>
</div>

<!-- 最近のユーザー -->
<section class="bg-white rounded-2xl shadow-sm p-6 border mb-6">
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-lg font-semibold">最近のユーザー</h2>
    <a href="/admin/users.php" class="text-sm text-indigo-600 hover:underline">すべて見る →</a>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-left text-gray-500 border-b">
        <tr><th class="py-2 pr-3">ID</th><th class="pr-3">メール</th><th class="pr-3">ユーザー名</th><th class="pr-3">役割</th><th>登録日</th></tr>
      </thead>
      <tbody>
        <?php foreach ($recentUsers as $u): ?>
        <tr class="border-b last:border-b-0 hover:bg-gray-50">
          <td class="py-2 pr-3 text-gray-400"><?= (int)$u['id'] ?></td>
          <td class="pr-3"><a href="/admin/user_edit.php?id=<?= (int)$u['id'] ?>" class="text-indigo-600 hover:underline"><?= e((string)$u['email']) ?></a></td>
          <td class="pr-3 text-gray-600">@<?= e((string)($u['username'] ?? '')) ?></td>
          <td class="pr-3">
            <span class="inline-block px-2 py-0.5 rounded text-xs <?= $u['role'] === 'SUPER_ADMIN' ? 'bg-red-100 text-red-700' : ($u['role'] === 'SALON_ADMIN' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-600') ?>"><?= e((string)$u['role']) ?></span>
          </td>
          <td class="text-gray-500 text-xs"><?= e((string)$u['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- 最近のNFC -->
<section class="bg-white rounded-2xl shadow-sm p-6 border">
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-lg font-semibold">最近のNFCチップ</h2>
    <a href="/admin/nfc.php" class="text-sm text-indigo-600 hover:underline">すべて見る →</a>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-left text-gray-500 border-b">
        <tr><th class="py-2 pr-3">ID</th><th class="pr-3">トークン</th><th class="pr-3">所有者</th><th class="pr-3">状態</th><th>発行日</th></tr>
      </thead>
      <tbody>
        <?php foreach ($recentChips as $c): ?>
        <tr class="border-b last:border-b-0 hover:bg-gray-50">
          <td class="py-2 pr-3 text-gray-400"><?= (int)$c['id'] ?></td>
          <td class="pr-3 font-mono text-xs"><?= e(substr((string)$c['token'], 0, 16)) ?>…</td>
          <td class="pr-3 text-gray-600"><?= $c['user_email'] ? e((string)$c['user_email']) : '<span class="text-gray-400">未割当</span>' ?></td>
          <td class="pr-3"><span class="text-xs px-2 py-0.5 rounded bg-gray-100"><?= e((string)$c['status']) ?></span></td>
          <td class="text-gray-500 text-xs"><?= e((string)$c['issued_at']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$recentChips): ?>
        <tr><td colspan="5" class="py-6 text-center text-gray-400">まだNFCチップが発行されていません</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php dashboard_layout_end(); ?>
