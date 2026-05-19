<?php
/**
 * PONNU — salon/settings.php
 * サロン設定編集 (SALON_ADMIN / SUPER_ADMIN)
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

// =============================
// POST 処理
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $action = input('action');

    try {
        if ($action === 'save') {
            $data = [
                input('name'),
                input('location'),
                input('map_url') !== '' ? input('map_url') : null,
                input('website_url') !== '' ? input('website_url') : null,
                preg_match('/^#[0-9A-Fa-f]{6}$/', input('primary_color')) ? input('primary_color') : null,
                preg_match('/^#[0-9A-Fa-f]{6}$/', input('accent_color')) ? input('accent_color') : null,
                $salonId,
            ];
            $db->execute(
                'UPDATE salons SET name = ?, location = ?, map_url = ?, website_url = ?, primary_color = ?, accent_color = ? WHERE id = ?',
                $data
            );
            flash_set('success', 'サロン設定を更新しました。');
        } elseif ($action === 'upload_logo') {
            if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('ファイルがアップロードされていません。');
            }
            $f = $_FILES['logo'];
            if ($f['size'] > 5 * 1024 * 1024) {
                throw new RuntimeException('ファイルサイズが大きすぎます (5MB以下)。');
            }
            $info = @getimagesize($f['tmp_name']);
            if (!$info) {
                throw new RuntimeException('画像ファイルではありません。');
            }
            $ext = match ($info[2]) {
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG  => 'png',
                IMAGETYPE_GIF  => 'gif',
                IMAGETYPE_WEBP => 'webp',
                default => null,
            };
            if (!$ext) {
                throw new RuntimeException('対応していない画像形式です。');
            }
            $dir = UPLOAD_DIR . 'salon_logos/';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $filename = 'salon_' . $salonId . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = $dir . $filename;
            if (!move_uploaded_file($f['tmp_name'], $dest)) {
                throw new RuntimeException('ファイル保存に失敗しました。');
            }
            $logoUrl = '/uploads/salon_logos/' . $filename;
            $db->execute('UPDATE salons SET logo_url = ? WHERE id = ?', [$logoUrl, $salonId]);
            flash_set('success', 'ロゴをアップロードしました。');
        }
    } catch (Throwable $e) {
        error_log('[salon/settings] ' . $e->getMessage());
        flash_set('error', '操作に失敗しました: ' . $e->getMessage());
    }
    redirect('/salon/settings.php');
}

$salon = $db->fetchOne('SELECT * FROM salons WHERE id = ?', [$salonId]);
if (!$salon) {
    flash_set('error', 'サロンが見つかりません。');
    redirect('/dashboard/');
}

dashboard_layout_start('サロン管理 / 設定');
?>

<nav class="mb-6 flex flex-wrap gap-2 text-sm">
  <a href="/salon/" class="px-3 py-1.5 rounded-lg bg-white border hover:bg-gray-50">概要</a>
  <a href="/salon/customers.php" class="px-3 py-1.5 rounded-lg bg-white border hover:bg-gray-50">顧客一覧</a>
  <a href="/salon/settings.php" class="px-3 py-1.5 rounded-lg bg-purple-600 text-white">設定</a>
</nav>

<h1 class="text-2xl font-bold mb-6">サロン設定</h1>

<!-- 基本情報 -->
<section class="bg-white rounded-2xl shadow-sm border p-6 mb-6">
  <h2 class="text-lg font-semibold mb-4">基本情報</h2>
  <form method="post" class="space-y-4">
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="save">

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">サロン名 <span class="text-red-500">*</span></label>
      <input type="text" name="name" value="<?= e((string)$salon['name']) ?>" required class="w-full px-3 py-2 border rounded-lg">
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">サロンコード (変更不可)</label>
      <input type="text" value="<?= e((string)$salon['salon_code']) ?>" disabled class="w-full px-3 py-2 border rounded-lg bg-gray-50 text-gray-500 font-mono">
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">所在地</label>
      <input type="text" name="location" value="<?= e((string)($salon['location'] ?? '')) ?>" class="w-full px-3 py-2 border rounded-lg" placeholder="東京都渋谷区...">
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Google マップURL</label>
      <input type="url" name="map_url" value="<?= e((string)($salon['map_url'] ?? '')) ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">公式サイトURL</label>
      <input type="url" name="website_url" value="<?= e((string)($salon['website_url'] ?? '')) ?>" class="w-full px-3 py-2 border rounded-lg">
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">プライマリカラー</label>
        <input type="color" name="primary_color" value="<?= e((string)($salon['primary_color'] ?? '#6366f1')) ?>" class="w-full h-10 border rounded-lg">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">アクセントカラー</label>
        <input type="color" name="accent_color" value="<?= e((string)($salon['accent_color'] ?? '#a855f7')) ?>" class="w-full h-10 border rounded-lg">
      </div>
    </div>

    <button class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">保存</button>
  </form>
</section>

<!-- ロゴアップロード -->
<section class="bg-white rounded-2xl shadow-sm border p-6">
  <h2 class="text-lg font-semibold mb-4">サロンロゴ</h2>

  <?php if (!empty($salon['logo_url'])): ?>
  <div class="mb-4">
    <img src="<?= e((string)$salon['logo_url']) ?>" alt="logo" class="max-h-32 border rounded">
  </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="space-y-3">
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="upload_logo">
    <input type="file" name="logo" accept="image/*" required class="w-full">
    <p class="text-xs text-gray-500">JPEG / PNG / GIF / WebP (5MBまで)</p>
    <button class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">アップロード</button>
  </form>
</section>

<?php dashboard_layout_end(); ?>
