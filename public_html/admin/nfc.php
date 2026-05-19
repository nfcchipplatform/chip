<?php
/**
 * PONNU — admin/nfc.php
 * NFCチップ管理 (SUPER_ADMIN)
 */
declare(strict_types=1);
$bootstrap = require __DIR__ . '/../../app/bootstrap.php';

Auth::requireRole('SUPER_ADMIN');

$db = Database::getInstance();

// =============================
// CSV ダウンロード
// =============================
if (isset($_GET['download_csv'])) {
    $idsParam = (string)($_GET['ids'] ?? '');
    $ids = array_filter(array_map('intval', explode(',', $idsParam)));
    if (!$ids) {
        header('HTTP/1.1 400 Bad Request');
        exit('ids パラメータが必要です');
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $rows = $db->fetchAll("SELECT id, token, issued_at FROM nfc_chips WHERE id IN ($placeholders)", $ids);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="nfc_chips_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM (Excel互換)
    fputcsv($out, ['id', 'token', 'url', 'issued_at']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],
            $r['token'],
            APP_URL . '/n/?t=' . $r['token'],
            $r['issued_at'],
        ]);
    }
    fclose($out);
    exit;
}

// =============================
// QR表示モード
// =============================
$qrId = isset($_GET['qr']) ? (int)$_GET['qr'] : 0;
$qrChip = null;
if ($qrId > 0) {
    $qrChip = $db->fetchOne('SELECT id, token FROM nfc_chips WHERE id = ?', [$qrId]);
}

// =============================
// POST 処理
// =============================
$issuedTokens = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $action = input('action');

    try {
        if ($action === 'issue') {
            $count = max(1, min(100, (int)input('count')));
            $memo  = mb_substr(input('memo'), 0, 500);
            for ($i = 0; $i < $count; $i++) {
                $token = bin2hex(random_bytes(32));
                $db->execute(
                    'INSERT INTO nfc_chips (token, status, memo, issued_at) VALUES (?, ?, ?, NOW())',
                    [$token, 'active', $memo]
                );
                $issuedTokens[] = ['id' => (int)Database::getInstance()->lastInsertId(), 'token' => $token];
            }
            flash_set('success', $count . '件のNFCチップを発行しました。');
        } elseif ($action === 'unlink') {
            $nfcId = (int)input('nfc_id');
            $db->execute('UPDATE nfc_chips SET user_id = NULL WHERE id = ?', [$nfcId]);
            flash_set('success', 'チップを解除しました。');
            redirect('/admin/nfc.php');
        } elseif ($action === 'delete') {
            $nfcId = (int)input('nfc_id');
            $db->execute('DELETE FROM nfc_chips WHERE id = ?', [$nfcId]);
            flash_set('success', 'チップを削除しました。');
            redirect('/admin/nfc.php');
        } elseif ($action === 'revoke') {
            $nfcId = (int)input('nfc_id');
            $db->execute('UPDATE nfc_chips SET status = ?, revoked_at = NOW() WHERE id = ?', ['revoked', $nfcId]);
            flash_set('success', 'チップを無効化しました。');
            redirect('/admin/nfc.php');
        }
    } catch (Throwable $e) {
        error_log('[admin/nfc] ' . $e->getMessage());
        flash_set('error', '操作に失敗しました: ' . $e->getMessage());
    }
}

// =============================
// 一覧 + ページング
// =============================
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page - 1) * $perPage;
$total   = (int)($db->fetchOne('SELECT COUNT(*) AS c FROM nfc_chips')['c'] ?? 0);
$pages   = max(1, (int)ceil($total / $perPage));

$chips = $db->fetchAll(
    "SELECT n.*, u.email AS user_email, u.username
     FROM nfc_chips n
     LEFT JOIN users u ON u.id = n.user_id
     ORDER BY n.id DESC
     LIMIT $perPage OFFSET $offset"
);

require_once APP_ROOT . '/views/dashboard_layout.php';
dashboard_layout_start('管理画面 / NFC');
?>

<nav class="mb-6 flex flex-wrap gap-2 text-sm">
  <a href="/admin/" class="px-3 py-1.5 rounded-lg bg-white border hover:bg-gray-50">概要</a>
  <a href="/admin/users.php" class="px-3 py-1.5 rounded-lg bg-white border hover:bg-gray-50">ユーザー</a>
  <a href="/admin/nfc.php" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white">NFC</a>
</nav>

<h1 class="text-2xl font-bold mb-4">NFCチップ管理 <span class="text-sm font-normal text-gray-500">(<?= $total ?>件)</span></h1>

<?php if ($qrChip): ?>
<?php require_once APP_ROOT . '/Qr.php'; $qrUrl = APP_URL . '/n/?t=' . $qrChip['token']; ?>
<section class="bg-white rounded-2xl shadow-sm border p-6 mb-6">
  <h2 class="text-lg font-semibold mb-4">QRコード (チップ #<?= (int)$qrChip['id'] ?>)</h2>
  <div class="flex flex-col items-center gap-3">
    <img src="<?= e(Qr::dataUri($qrUrl, 320)) ?>" alt="QR" class="border rounded">
    <code class="text-xs text-gray-500 break-all max-w-md text-center"><?= e($qrUrl) ?></code>
    <a href="/admin/nfc.php" class="text-sm text-indigo-600 hover:underline">← 一覧に戻る</a>
  </div>
</section>
<?php endif; ?>

<?php if ($issuedTokens): ?>
<section class="bg-emerald-50 border border-emerald-200 rounded-2xl p-6 mb-6">
  <h2 class="font-semibold mb-3 text-emerald-900">発行されたチップ (<?= count($issuedTokens) ?>件)</h2>
  <div class="space-y-1 max-h-64 overflow-y-auto">
    <?php foreach ($issuedTokens as $t): ?>
      <div class="text-xs font-mono break-all">#<?= $t['id'] ?> → <?= e(APP_URL . '/n/?t=' . $t['token']) ?></div>
    <?php endforeach; ?>
  </div>
  <div class="mt-4 flex gap-2 flex-wrap">
    <a href="/admin/nfc.php?download_csv=1&ids=<?= implode(',', array_column($issuedTokens, 'id')) ?>"
       class="px-3 py-1.5 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700">CSVダウンロード</a>
    <a href="/admin/nfc_print.php?ids=<?= implode(',', array_column($issuedTokens, 'id')) ?>"
       class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700" target="_blank">A4印刷用ページを開く</a>
  </div>
</section>
<?php endif; ?>

<!-- 新規発行フォーム -->
<section class="bg-white rounded-2xl shadow-sm border p-6 mb-6">
  <h2 class="text-lg font-semibold mb-4">新規発行</h2>
  <form method="post" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="issue">
    <div>
      <label class="block text-sm text-gray-600 mb-1">発行枚数 (1-100)</label>
      <input type="number" name="count" min="1" max="100" value="1" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <div class="sm:col-span-1">
      <label class="block text-sm text-gray-600 mb-1">メモ (任意)</label>
      <input type="text" name="memo" maxlength="500" placeholder="例: 2026年5月ロット" class="w-full px-3 py-2 border rounded-lg">
    </div>
    <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">発行する</button>
  </form>
</section>

<!-- 一覧 -->
<div class="bg-white rounded-2xl shadow-sm border overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 text-left text-gray-600">
      <tr>
        <th class="px-3 py-2">ID</th>
        <th class="px-3 py-2">トークン</th>
        <th class="px-3 py-2">所有者</th>
        <th class="px-3 py-2">状態</th>
        <th class="px-3 py-2">発行日</th>
        <th class="px-3 py-2">最終アクセス</th>
        <th class="px-3 py-2">操作</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($chips as $c): ?>
      <tr class="border-t hover:bg-gray-50">
        <td class="px-3 py-2 text-gray-400"><?= (int)$c['id'] ?></td>
        <td class="px-3 py-2 font-mono text-xs"><?= e(substr((string)$c['token'], 0, 16)) ?>…</td>
        <td class="px-3 py-2">
          <?php if ($c['user_email']): ?>
            <a href="/admin/user_edit.php?id=<?= (int)$c['user_id'] ?>" class="text-indigo-600 hover:underline"><?= e((string)$c['user_email']) ?></a>
          <?php else: ?>
            <span class="text-gray-400">未割当</span>
          <?php endif; ?>
        </td>
        <td class="px-3 py-2">
          <span class="text-xs px-2 py-0.5 rounded <?= $c['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>"><?= e((string)$c['status']) ?></span>
        </td>
        <td class="px-3 py-2 text-xs text-gray-500"><?= e((string)$c['issued_at']) ?></td>
        <td class="px-3 py-2 text-xs text-gray-500"><?= e((string)($c['last_accessed_at'] ?? '—')) ?></td>
        <td class="px-3 py-2">
          <div class="flex gap-1 flex-wrap">
            <a href="/admin/nfc.php?qr=<?= (int)$c['id'] ?>" class="text-xs px-2 py-1 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded">QR</a>
            <a href="/admin/nfc_print.php?ids=<?= (int)$c['id'] ?>" target="_blank" class="text-xs px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded">印刷</a>
            <?php if ($c['user_id']): ?>
            <form method="post" onsubmit="return confirm('紐付け解除しますか?');" class="inline">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="unlink">
              <input type="hidden" name="nfc_id" value="<?= (int)$c['id'] ?>">
              <button class="text-xs px-2 py-1 bg-amber-50 text-amber-700 hover:bg-amber-100 rounded">解除</button>
            </form>
            <?php endif; ?>
            <?php if ($c['status'] !== 'revoked'): ?>
            <form method="post" onsubmit="return confirm('無効化しますか?');" class="inline">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="revoke">
              <input type="hidden" name="nfc_id" value="<?= (int)$c['id'] ?>">
              <button class="text-xs px-2 py-1 bg-orange-50 text-orange-700 hover:bg-orange-100 rounded">無効化</button>
            </form>
            <?php endif; ?>
            <form method="post" onsubmit="return confirm('完全に削除しますか? 取り消せません。');" class="inline">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="nfc_id" value="<?= (int)$c['id'] ?>">
              <button class="text-xs px-2 py-1 bg-red-50 text-red-700 hover:bg-red-100 rounded">削除</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$chips): ?>
      <tr><td colspan="7" class="px-3 py-8 text-center text-gray-400">NFCチップがまだ発行されていません</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($pages > 1): ?>
<div class="mt-6 flex justify-center gap-1 flex-wrap">
  <?php for ($p = 1; $p <= $pages; $p++):
    $cls = $p === $page ? 'bg-indigo-600 text-white' : 'bg-white border hover:bg-gray-50';
  ?>
    <a href="?page=<?= $p ?>" class="px-3 py-1.5 rounded-lg text-sm <?= $cls ?>"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php dashboard_layout_end(); ?>
