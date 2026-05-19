<?php
/**
 * PONNU — salon/index.php
 * サロン管理ダッシュボード (SALON_ADMIN / SUPER_ADMIN)
 */
declare(strict_types=1);
$bootstrap = require __DIR__ . '/../../app/bootstrap.php';
require_once APP_ROOT . '/views/dashboard_layout.php';

Auth::requireRole(['SALON_ADMIN', 'SUPER_ADMIN']);

$me = Auth::user();
$db = Database::getInstance();

$salonId = (int)($me['salon_id'] ?? 0);
if ($salonId <= 0 && $me['role'] !== 'SUPER_ADMIN') {
    flash_set('error', 'サロンに所属していません。管理者にお問い合わせください。');
    redirect('/dashboard/');
}

// SUPER_ADMIN で salon_id 未設定なら、最初のサロンを表示
if ($salonId <= 0) {
    $first = $db->fetchOne('SELECT id FROM salons ORDER BY id ASC LIMIT 1');
    $salonId = (int)($first['id'] ?? 0);
}

$salon = $salonId > 0 ? $db->fetchOne('SELECT * FROM salons WHERE id = ?', [$salonId]) : null;
if (!$salon) {
    dashboard_layout_start('サロン管理');
    echo '<div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-6">サロンがまだ登録されていません。</div>';
    dashboard_layout_end();
    exit;
}

$stats = [
    'customers'      => (int)($db->fetchOne('SELECT COUNT(*) AS c FROM users WHERE salon_id = ?', [$salonId])['c'] ?? 0),
    'recent_signups' => (int)($db->fetchOne(
        'SELECT COUNT(*) AS c FROM users WHERE salon_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
        [$salonId]
    )['c'] ?? 0),
];
$recentUsers = $db->fetchAll(
    'SELECT id, email, username, display_name, created_at FROM users WHERE salon_id = ? ORDER BY id DESC LIMIT 10',
    [$salonId]
);

$registerUrl = APP_URL . '/auth/register.php?salon_code=' . urlencode((string)$salon['salon_code']);

dashboard_layout_start('サロン管理 / 概要');
?>

<nav class="mb-6 flex flex-wrap gap-2 text-sm">
  <a href="/salon/" class="px-3 py-1.5 rounded-lg bg-purple-600 text-white">概要</a>
  <a href="/salon/customers.php" class="px-3 py-1.5 rounded-lg bg-white border hover:bg-gray-50">顧客一覧</a>
  <a href="/salon/settings.php" class="px-3 py-1.5 rounded-lg bg-white border hover:bg-gray-50">設定</a>
</nav>

<h1 class="text-2xl font-bold mb-2"><?= e((string)$salon['name']) ?></h1>
<p class="text-sm text-gray-500 mb-6">サロンコード: <code class="bg-gray-100 px-2 py-0.5 rounded"><?= e((string)$salon['salon_code']) ?></code></p>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
  <div class="bg-white rounded-2xl shadow-sm p-5 border">
    <p class="text-xs text-gray-500">顧客数</p>
    <p class="text-3xl font-bold text-purple-600 mt-1"><?= $stats['customers'] ?></p>
  </div>
  <div class="bg-white rounded-2xl shadow-sm p-5 border">
    <p class="text-xs text-gray-500">直近30日の新規登録</p>
    <p class="text-3xl font-bold text-emerald-600 mt-1">+<?= $stats['recent_signups'] ?></p>
  </div>
</div>

<!-- 招待リンク -->
<section class="bg-white rounded-2xl shadow-sm border p-6 mb-6">
  <h2 class="text-lg font-semibold mb-3">顧客向け招待リンク</h2>
  <p class="text-sm text-gray-600 mb-3">このリンクから登録した方は自動的に当サロンに紐付きます。</p>
  <div class="flex gap-2">
    <input type="text" readonly value="<?= e($registerUrl) ?>" id="invite-url" class="flex-1 px-3 py-2 border rounded-lg font-mono text-xs bg-gray-50">
    <button onclick="navigator.clipboard.writeText(document.getElementById('invite-url').value);this.textContent='コピー済';" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">コピー</button>
  </div>
</section>

<!-- 最近の顧客 -->
<section class="bg-white rounded-2xl shadow-sm border p-6">
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-lg font-semibold">最近の顧客</h2>
    <a href="/salon/customers.php" class="text-sm text-purple-600 hover:underline">すべて見る →</a>
  </div>
  <table class="w-full text-sm">
    <thead class="text-left text-gray-500 border-b">
      <tr><th class="py-2 pr-3">名前</th><th class="pr-3">メール</th><th>登録日</th></tr>
    </thead>
    <tbody>
      <?php foreach ($recentUsers as $u): ?>
      <tr class="border-b last:border-0">
        <td class="py-2 pr-3"><?= e((string)($u['display_name'] ?: $u['username'] ?: '—')) ?></td>
        <td class="pr-3 text-gray-600"><?= e((string)$u['email']) ?></td>
        <td class="text-xs text-gray-500"><?= e((string)$u['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$recentUsers): ?>
      <tr><td colspan="3" class="py-6 text-center text-gray-400">まだ顧客がいません</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>

<?php dashboard_layout_end(); ?>
