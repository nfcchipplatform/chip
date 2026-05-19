<?php
/**
 * PONNU — admin/nfc_print.php
 * NFC QR印刷用ページ (A4・2列グリッド)
 */
declare(strict_types=1);
$bootstrap = require __DIR__ . '/../../app/bootstrap.php';
require_once APP_ROOT . '/Qr.php';

Auth::requireRole('SUPER_ADMIN');

$db = Database::getInstance();

$idsParam = (string)($_GET['ids'] ?? '');
$ids = array_filter(array_map('intval', explode(',', $idsParam)));
if (!$ids) {
    http_response_code(400);
    exit('ids パラメータが必要です。例: /admin/nfc_print.php?ids=1,2,3');
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$chips = $db->fetchAll("SELECT id, token FROM nfc_chips WHERE id IN ($placeholders) ORDER BY id ASC", $ids);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>NFC QR 印刷</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  @media print {
    @page { size: A4; margin: 1cm; }
    body { background: white; }
    .no-print { display: none !important; }
    .card { break-inside: avoid; }
  }
  body { font-family: 'Segoe UI', 'Yu Gothic UI', sans-serif; background: #f3f4f6; }
  .grid-cards { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1cm; }
  .card { background: white; border: 1px solid #d1d5db; border-radius: 8px; padding: 1.2cm 0.8cm; text-align: center; }
</style>
</head>
<body class="p-4">

<div class="no-print mb-6 max-w-4xl mx-auto flex items-center justify-between">
  <h1 class="text-xl font-bold">NFC QR 印刷 (<?= count($chips) ?>件)</h1>
  <div class="flex gap-2">
    <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">印刷</button>
    <a href="/admin/nfc.php" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">戻る</a>
  </div>
</div>

<div class="max-w-4xl mx-auto grid-cards">
  <?php foreach ($chips as $c):
    $url = APP_URL . '/n/?t=' . $c['token'];
  ?>
  <div class="card">
    <div class="text-xs text-gray-500 mb-2">PONNU</div>
    <img src="<?= htmlspecialchars(Qr::dataUri($url, 280), ENT_QUOTES) ?>" alt="QR" class="mx-auto w-44 h-44">
    <div class="mt-3 text-xs font-mono text-gray-600">
      <?= htmlspecialchars(substr((string)$c['token'], 0, 8), ENT_QUOTES) ?>
    </div>
    <div class="mt-1 text-[10px] text-gray-400">#<?= (int)$c['id'] ?></div>
  </div>
  <?php endforeach; ?>
</div>

<?php if (!$chips): ?>
<p class="text-center text-gray-500 py-12">該当するチップがありません。</p>
<?php endif; ?>

</body>
</html>
