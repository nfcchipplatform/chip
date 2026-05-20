<?php
/**
 * PONNU — dashboard/nails.php
 * ネイル管理: 自分の指へのネイル貼り付け / ID検索 / アップロード
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
Auth::requireLogin('/auth/login.php');
require_once APP_ROOT . '/views/dashboard_layout.php';

$user = Auth::user();
$db   = Database::getInstance();

// =============================
// POST処理
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $action = input('action');

    try {
        if ($action === 'upload') {
            // ネイル画像アップロード
            if (!isset($_FILES['nail_image']) || $_FILES['nail_image']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('画像がアップロードされていません。');
            }
            $f = $_FILES['nail_image'];
            if ($f['size'] > 5 * 1024 * 1024) throw new RuntimeException('5MB以下の画像を選択してください。');
            $info = @getimagesize($f['tmp_name']);
            if (!$info) throw new RuntimeException('画像ファイルではありません。');
            $ext = match ($info[2]) {
                IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png',
                IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp', default => null,
            };
            if (!$ext) throw new RuntimeException('対応していない画像形式です。');

            $dir = UPLOAD_DIR . 'nails/';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $filename = 'nail_' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (!move_uploaded_file($f['tmp_name'], $dir . $filename)) throw new RuntimeException('ファイル保存失敗。');

            $imageUrl = '/uploads/nails/' . $filename;
            $nailCode = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $designName = mb_substr(input('design_name'), 0, 100);
            $salonId = ($user['salon_id'] ?? null) ?: null;

            $db->execute(
                'INSERT INTO nails (nail_code, image_url, design_name, salon_id, creator_user_id) VALUES (?, ?, ?, ?, ?)',
                [$nailCode, $imageUrl, $designName, $salonId, $user['id']]
            );
            flash_set('success', 'ネイルを登録しました！コード: ' . $nailCode);

        } elseif ($action === 'apply') {
            // 自分の指にネイルを貼る
            $nailId = (int)input('nail_id');
            $fingerIndex = (int)input('finger_index');
            if ($fingerIndex < 0 || $fingerIndex > 4) throw new RuntimeException('指の指定が不正です。');
            $nail = $db->fetchOne('SELECT id FROM nails WHERE id = ?', [$nailId]);
            if (!$nail) throw new RuntimeException('ネイルが見つかりません。');

            $db->execute(
                'INSERT INTO user_nails (user_id, finger_index, nail_id) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE nail_id = VALUES(nail_id), applied_at = NOW()',
                [$user['id'], $fingerIndex, $nailId]
            );
            // used_count更新
            $db->execute('UPDATE nails SET used_count = used_count + 1 WHERE id = ?', [$nailId]);
            flash_set('success', '指にネイルをセットしました！');

        } elseif ($action === 'remove') {
            $fingerIndex = (int)input('finger_index');
            $db->execute('DELETE FROM user_nails WHERE user_id = ? AND finger_index = ?', [$user['id'], $fingerIndex]);
            flash_set('success', 'ネイルを外しました。');

        } elseif ($action === 'search') {
            // nail_codeで検索 → リダイレクトで結果表示
            $code = strtoupper(trim(input('nail_code')));
            redirect('/dashboard/nails.php?search=' . urlencode($code));
        }
    } catch (Throwable $e) {
        flash_set('error', $e->getMessage());
    }
    redirect('/dashboard/nails.php');
}

// =============================
// データ取得
// =============================
// 自分の指に貼ったネイル
$myNails = $db->fetchAll(
    'SELECT un.finger_index, un.nail_id, n.nail_code, n.image_url, n.design_name, n.salon_id, s.name AS salon_name
     FROM user_nails un
     JOIN nails n ON n.id = un.nail_id
     LEFT JOIN salons s ON s.id = n.salon_id
     WHERE un.user_id = ?
     ORDER BY un.finger_index',
    [$user['id']]
);
$nailsByFinger = [];
foreach ($myNails as $n) $nailsByFinger[(int)$n['finger_index']] = $n;

// 自分が登録したネイル
$myCreatedNails = $db->fetchAll(
    'SELECT id, nail_code, image_url, design_name, used_count, like_count, created_at
     FROM nails WHERE creator_user_id = ? ORDER BY id DESC LIMIT 20',
    [$user['id']]
);

// 検索結果
$searchResult = null;
if (isset($_GET['search']) && $_GET['search'] !== '') {
    $code = strtoupper(trim((string)$_GET['search']));
    $searchResult = $db->fetchOne(
        'SELECT n.*, s.name AS salon_name, u.display_name AS creator_name, u.username AS creator_username
         FROM nails n
         LEFT JOIN salons s ON s.id = n.salon_id
         LEFT JOIN users u ON u.id = n.creator_user_id
         WHERE n.nail_code = ? AND n.is_public = 1',
        [$code]
    );
}

$fingerNames = ['親指', '人差指', '中指', '薬指', '小指'];

dashboard_layout_start('ネイル管理');
?>

<nav class="mb-6 flex flex-wrap gap-2 text-sm">
    <a href="/dashboard/" class="px-3 py-1.5 rounded-lg bg-white border hover:bg-gray-50">ホーム</a>
    <a href="/dashboard/nails.php" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white">ネイル管理</a>
</nav>

<h1 class="text-2xl font-bold mb-6">ネイル管理</h1>

<!-- ===== 手のアニメーション + ネイル表示 ===== -->
<section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
    <h2 class="text-lg font-semibold mb-4 text-center">あなたの手</h2>

    <div id="hand-container" class="relative mx-auto" style="width:280px; height:360px; user-select:none;">
        <img id="hand-open" src="/assets/img/hand/handopen.png" alt="パー" class="absolute inset-0 w-full h-full object-contain transition-opacity duration-500 opacity-100">
        <img id="hand-close" src="/assets/img/hand/handclose.png" alt="閉じ" class="absolute inset-0 w-full h-full object-contain transition-opacity duration-500 opacity-0">
        <img id="hand-goo" src="/assets/img/hand/handgoo.png" alt="グー" class="absolute inset-0 w-full h-full object-contain transition-opacity duration-500 opacity-0">

        <!-- ネイルチップ重ね合わせ (グー状態の爪位置に配置) -->
        <?php
        // handgoo.png上の爪座標 (%, 画像サイズ比で指定)
        $nailPositions = [
            0 => ['top' => '47%', 'left' => '62%', 'w' => '38px', 'h' => '50px', 'rotate' => '-25deg'],  // 親指
            1 => ['top' => '24%', 'left' => '6%',  'w' => '32px', 'h' => '44px', 'rotate' => '10deg'],   // 人差指
            2 => ['top' => '22%', 'left' => '24%', 'w' => '34px', 'h' => '46px', 'rotate' => '5deg'],    // 中指
            3 => ['top' => '24%', 'left' => '42%', 'w' => '32px', 'h' => '44px', 'rotate' => '0deg'],    // 薬指
            4 => ['top' => '30%', 'left' => '56%', 'w' => '28px', 'h' => '38px', 'rotate' => '-5deg'],   // 小指
        ];
        foreach ($nailPositions as $i => $pos):
            $nail = $nailsByFinger[$i] ?? null;
        ?>
        <div class="nail-chip absolute opacity-0 transition-opacity duration-300 rounded-[50%_50%_45%_45%] overflow-hidden border border-gray-200 shadow-sm"
             style="top:<?= $pos['top'] ?>; left:<?= $pos['left'] ?>; width:<?= $pos['w'] ?>; height:<?= $pos['h'] ?>; transform:rotate(<?= $pos['rotate'] ?>);"
             data-finger="<?= $i ?>">
            <?php if ($nail): ?>
                <img src="<?= e($nail['image_url']) ?>" alt="<?= e($nail['design_name']) ?>" class="w-full h-full object-cover">
            <?php else: ?>
                <div class="w-full h-full bg-pink-50 flex items-center justify-center">
                    <span class="text-[8px] text-gray-300">+</span>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <p class="text-center text-xs text-gray-400 mt-3">タップ/クリックで爪が見えます</p>
</section>

<!-- ===== 指ごとのネイル設定 ===== -->
<section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
    <h2 class="text-lg font-semibold mb-4">指のネイル設定</h2>
    <div class="grid grid-cols-5 gap-2">
        <?php for ($i = 0; $i < 5; $i++):
            $nail = $nailsByFinger[$i] ?? null;
        ?>
        <div class="text-center">
            <p class="text-xs text-gray-500 mb-1"><?= $fingerNames[$i] ?></p>
            <div class="w-12 h-16 mx-auto rounded-[50%_50%_45%_45%] overflow-hidden border-2 <?= $nail ? 'border-indigo-300' : 'border-dashed border-gray-300' ?> mb-1">
                <?php if ($nail): ?>
                    <img src="<?= e($nail['image_url']) ?>" class="w-full h-full object-cover" alt="">
                <?php else: ?>
                    <div class="w-full h-full bg-gray-50 flex items-center justify-center">
                        <span class="text-gray-300 text-lg">+</span>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($nail): ?>
                <p class="text-[10px] text-gray-500 truncate"><?= e($nail['nail_code']) ?></p>
                <form method="post" class="mt-1">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="finger_index" value="<?= $i ?>">
                    <button class="text-[10px] text-red-500 hover:underline">外す</button>
                </form>
            <?php else: ?>
                <p class="text-[10px] text-gray-400">未設定</p>
            <?php endif; ?>
        </div>
        <?php endfor; ?>
    </div>
</section>

<!-- ===== ネイルID検索 ===== -->
<section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
    <h2 class="text-lg font-semibold mb-4">ネイルIDで検索</h2>
    <form method="post" class="flex gap-2 mb-4">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="search">
        <input type="text" name="nail_code" placeholder="例: NL8A2F4C" maxlength="8"
               value="<?= e((string)($_GET['search'] ?? '')) ?>"
               class="flex-1 px-3 py-2 border rounded-lg uppercase font-mono">
        <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">検索</button>
    </form>

    <?php if (isset($_GET['search'])): ?>
        <?php if ($searchResult): ?>
        <div class="border rounded-xl p-4 flex gap-4 items-center">
            <div class="w-16 h-20 rounded-[50%_50%_45%_45%] overflow-hidden border border-gray-200 flex-shrink-0">
                <img src="<?= e($searchResult['image_url']) ?>" class="w-full h-full object-cover" alt="">
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-medium text-sm"><?= e($searchResult['design_name'] ?: 'ネイル') ?></p>
                <p class="text-xs text-gray-500 font-mono"><?= e($searchResult['nail_code']) ?></p>
                <?php if ($searchResult['salon_name']): ?>
                    <p class="text-xs text-purple-600">🏠 <?= e($searchResult['salon_name']) ?></p>
                <?php endif; ?>
                <p class="text-xs text-gray-400">by @<?= e($searchResult['creator_username'] ?? '') ?> ・使用 <?= (int)$searchResult['used_count'] ?>人</p>
            </div>
            <div>
                <form method="post" class="flex flex-col gap-1">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="apply">
                    <input type="hidden" name="nail_id" value="<?= (int)$searchResult['id'] ?>">
                    <select name="finger_index" class="text-xs border rounded px-2 py-1">
                        <?php for ($i = 0; $i < 5; $i++): ?>
                            <option value="<?= $i ?>"><?= $fingerNames[$i] ?></option>
                        <?php endfor; ?>
                    </select>
                    <button class="text-xs px-3 py-1.5 bg-emerald-600 text-white rounded hover:bg-emerald-700">貼る</button>
                </form>
            </div>
        </div>
        <?php else: ?>
            <p class="text-sm text-gray-500">コード「<?= e((string)$_GET['search']) ?>」のネイルは見つかりませんでした。</p>
        <?php endif; ?>
    <?php endif; ?>
</section>

<!-- ===== ネイル新規登録 ===== -->
<section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
    <h2 class="text-lg font-semibold mb-4">ネイルを登録する</h2>
    <form method="post" enctype="multipart/form-data" class="space-y-3">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="upload">
        <div>
            <label class="block text-sm text-gray-600 mb-1">デザイン名</label>
            <input type="text" name="design_name" maxlength="100" placeholder="例: 春のフラワーアート" class="w-full px-3 py-2 border rounded-lg">
        </div>
        <div>
            <label class="block text-sm text-gray-600 mb-1">ネイルチップ画像</label>
            <input type="file" name="nail_image" accept="image/*" required class="w-full">
            <p class="text-xs text-gray-400 mt-1">JPEG/PNG/GIF/WebP (5MBまで)</p>
        </div>
        <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">登録する</button>
    </form>
</section>

<!-- ===== 自分が登録したネイル一覧 ===== -->
<?php if ($myCreatedNails): ?>
<section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
    <h2 class="text-lg font-semibold mb-4">あなたが登録したネイル</h2>
    <div class="grid grid-cols-3 sm:grid-cols-5 gap-3">
        <?php foreach ($myCreatedNails as $nail): ?>
        <div class="text-center">
            <div class="w-14 h-18 mx-auto rounded-[50%_50%_45%_45%] overflow-hidden border border-gray-200">
                <img src="<?= e($nail['image_url']) ?>" class="w-full h-full object-cover" alt="">
            </div>
            <p class="text-[10px] text-gray-600 mt-1 truncate"><?= e($nail['design_name'] ?: 'ネイル') ?></p>
            <p class="text-[10px] text-indigo-500 font-mono"><?= e($nail['nail_code']) ?></p>
            <p class="text-[9px] text-gray-400">使用 <?= (int)$nail['used_count'] ?>人</p>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- 手のアニメーション JS -->
<script>
(function() {
    const container = document.getElementById('hand-container');
    const imgOpen  = document.getElementById('hand-open');
    const imgClose = document.getElementById('hand-close');
    const imgGoo   = document.getElementById('hand-goo');
    const nailChips = document.querySelectorAll('.nail-chip');

    let phase = 'open'; // open -> close -> (user press = goo)
    let loopTimer = null;

    // 初回: パー → 1.5秒後に閉じ
    setTimeout(() => {
        imgOpen.style.opacity = '0';
        imgClose.style.opacity = '1';
        phase = 'close';
        showNails(false); // 閉じ状態ではネイル非表示（爪は見えるが位置がグーとずれるため）
    }, 1500);

    // タップ/クリック中 = グー
    function goGoo() {
        if (phase === 'open') return;
        imgClose.style.opacity = '0';
        imgGoo.style.opacity = '1';
        phase = 'goo';
        showNails(true);
    }

    function goClose() {
        imgGoo.style.opacity = '0';
        imgClose.style.opacity = '1';
        phase = 'close';
        showNails(false);
    }

    function showNails(show) {
        nailChips.forEach(el => {
            el.style.opacity = show ? '1' : '0';
        });
    }

    // Mouse
    container.addEventListener('mousedown', goGoo);
    container.addEventListener('mouseup', goClose);
    container.addEventListener('mouseleave', () => { if (phase === 'goo') goClose(); });

    // Touch
    container.addEventListener('touchstart', (e) => { e.preventDefault(); goGoo(); });
    container.addEventListener('touchend', goClose);
    container.addEventListener('touchcancel', goClose);
})();
</script>

<?php dashboard_layout_end(); ?>
