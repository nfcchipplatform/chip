<?php
/**
 * PONNU — u/index.php
 * 公開プロフィールページ
 * URL: /u/?username=xxx  または .htaccess で /u/{username} → /u/?username={username}
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
require_once APP_ROOT . '/views/layout.php';

$db       = Database::getInstance();
$username = isset($_GET['username']) ? trim($_GET['username']) : '';

// ユーザー検索
if ($username === '' || !preg_match('/^[a-zA-Z0-9_]{1,50}$/', $username)) {
    http_response_code(404);
    renderNotFound();
    exit;
}

$targetUser = $db->fetchOne(
    'SELECT u.id, u.display_name, u.username, u.role, u.status
     FROM users u
     WHERE u.username = ? AND u.status = ?
     LIMIT 1',
    [$username, 'active']
);

if (!$targetUser) {
    http_response_code(404);
    renderNotFound();
    exit;
}

$targetProfile = $db->fetchOne(
    'SELECT bio, avatar_url, twitter_handle, instagram_handle, website_url, title, is_public
     FROM profiles WHERE user_id = ? LIMIT 1',
    [$targetUser['id']]
);

if (!$targetProfile || !$targetProfile['is_public']) {
    http_response_code(404);
    renderNotFound();
    exit;
}

// プロフィール閲覧数を記録（IP/UA を sha256 でハッシュ化）
$ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ua       = $_SERVER['HTTP_USER_AGENT'] ?? '';
$salt     = defined('APP_SECRET') ? APP_SECRET : 'ponnu_salt';
$ipHash   = hash('sha256', $ip . $salt);
$uaHash   = hash('sha256', $ua . $salt);

$currentUser   = Auth::user();
$viewerUserId  = $currentUser ? $currentUser['id'] : null;

// 自分のプロフィールは記録しない
if ($viewerUserId !== $targetUser['id']) {
    $db->execute(
        'INSERT INTO profile_views (viewed_user_id, viewer_user_id, ip_hash, ua_hash)
         VALUES (?, ?, ?, ?)',
        [$targetUser['id'], $viewerUserId, $ipHash, $uaHash]
    );
}

// フォロワー数・フォロー中数
$followerCount  = (int)($db->fetchOne('SELECT COUNT(*) AS cnt FROM follows WHERE following_id = ?', [$targetUser['id']])['cnt'] ?? 0);
$followingCount = (int)($db->fetchOne('SELECT COUNT(*) AS cnt FROM follows WHERE follower_id = ?',  [$targetUser['id']])['cnt'] ?? 0);

// 自分がフォローしているか？
$isFollowing = false;
if ($currentUser && $currentUser['id'] !== $targetUser['id']) {
    $isFollowing = (bool)$db->fetchOne(
        'SELECT id FROM follows WHERE follower_id = ? AND following_id = ? LIMIT 1',
        [$currentUser['id'], $targetUser['id']]
    );
}

// Top5 お気に入り（スロット0〜4）
$favorites = $db->fetchAll(
    'SELECT f.slot_index, u.id AS uid, u.display_name, u.username, p.avatar_url
     FROM favorites f
     LEFT JOIN users u   ON u.id = f.selected_user_id AND u.status = ?
     LEFT JOIN profiles p ON p.user_id = u.id
     WHERE f.owner_user_id = ?
     ORDER BY f.slot_index ASC',
    ['active', $targetUser['id']]
);
$favBySlot = [];
foreach ($favorites as $fav) {
    $favBySlot[(int)$fav['slot_index']] = $fav;
}

// 対象ユーザーのネイルチップ情報取得
$targetNails = $db->fetchAll(
    'SELECT un.finger_index, un.nail_id, n.nail_code, n.image_url, n.design_name, n.salon_id, s.name AS salon_name, n.like_count
     FROM user_nails un
     JOIN nails n ON n.id = un.nail_id
     LEFT JOIN salons s ON s.id = n.salon_id
     WHERE un.user_id = ?
     ORDER BY un.finger_index',
    [$targetUser['id']]
);
$nailsByFinger = [];
foreach ($targetNails as $n) {
    // 自分が「いいね」しているか
    $n['is_liked'] = false;
    if ($currentUser) {
        $n['is_liked'] = (bool)$db->fetchOne(
            'SELECT id FROM nail_likes WHERE user_id = ? AND nail_id = ? LIMIT 1',
            [$currentUser['id'], $n['nail_id']]
        );
    }
    $nailsByFinger[(int)$n['finger_index']] = $n;
}

$avatarUrl = $targetProfile['avatar_url'] ?? null;
$profileUrl = APP_URL . '/u/?username=' . urlencode($targetUser['username']);
$pageTitle = e($targetUser['display_name'] ?: $targetUser['username']) . ' のプロフィール';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <?php layout_head($pageTitle); ?>
    <meta name="robots" content="noindex, nofollow">
</head>
<body class="bg-gray-50 min-h-screen font-sans">
<?php layout_header(); ?>

<main class="max-w-lg mx-auto px-4 py-10">

    <!-- ===== プロフィールカード ===== -->
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 text-center mb-6">

        <!-- アバター -->
        <div class="relative inline-block mb-4">
            <?php if ($avatarUrl && asset_exists($avatarUrl)): ?>
                <img src="<?= e($avatarUrl) ?>" alt="<?= e($targetUser['display_name'] ?: $targetUser['username']) ?>"
                     class="w-24 h-24 rounded-full object-cover mx-auto border-2 border-gray-200">
            <?php elseif (asset_exists(ASSET_DEFAULT_AVATAR)): ?>
                <img src="<?= e(ASSET_DEFAULT_AVATAR) ?>" alt=""
                     class="w-24 h-24 rounded-full object-cover mx-auto border-2 border-gray-200">
            <?php else: ?>
                <div class="w-24 h-24 rounded-full bg-gradient-to-br from-indigo-400 to-purple-500
                            flex items-center justify-center mx-auto border-2 border-gray-200">
                    <span class="text-3xl font-bold text-white">
                        <?= mb_strtoupper(mb_substr($targetUser['display_name'] ?: $targetUser['username'], 0, 1)) ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <!-- 名前・肩書き -->
        <h1 class="text-xl font-bold text-gray-900">
            <?= e($targetUser['display_name'] ?: $targetUser['username']) ?>
        </h1>
        <?php if (!empty($targetProfile['title'])): ?>
            <p class="text-sm text-indigo-600 font-medium mt-1"><?= e($targetProfile['title']) ?></p>
        <?php endif; ?>
        <p class="text-xs text-gray-400 mt-0.5">@<?= e($targetUser['username']) ?></p>

        <!-- フォロワー数 -->
        <div class="flex items-center justify-center gap-8 mt-4 mb-4">
            <div>
                <p class="font-bold text-gray-900"><?= $followerCount ?></p>
                <p class="text-xs text-gray-400">フォロワー</p>
            </div>
            <div class="w-px h-8 bg-gray-200"></div>
            <div>
                <p class="font-bold text-gray-900"><?= $followingCount ?></p>
                <p class="text-xs text-gray-400">フォロー中</p>
            </div>
        </div>

        <!-- 自己紹介 -->
        <?php if (!empty($targetProfile['bio'])): ?>
            <p class="text-sm text-gray-600 leading-relaxed mb-4"><?= nl2br(e($targetProfile['bio'])) ?></p>
        <?php endif; ?>

        <!-- フォローボタン (ログイン済みかつ他人) -->
        <?php if ($currentUser && $currentUser['id'] !== $targetUser['id']): ?>
            <button
                id="follow-btn"
                data-target="<?= (int)$targetUser['id'] ?>"
                data-following="<?= $isFollowing ? '1' : '0' ?>"
                onclick="toggleFollow(this)"
                class="<?= $isFollowing
                    ? 'bg-gray-100 text-gray-700 hover:bg-red-50 hover:text-red-600 border border-gray-300'
                    : 'bg-indigo-600 text-white hover:bg-indigo-700' ?>
                       px-8 py-2.5 rounded-full font-semibold text-sm transition-colors mb-4">
                <?= $isFollowing ? 'フォロー中' : 'フォローする' ?>
            </button>
        <?php elseif (!$currentUser): ?>
            <a href="/auth/login.php"
               class="inline-block px-8 py-2.5 bg-indigo-600 text-white rounded-full font-semibold text-sm
                      hover:bg-indigo-700 transition-colors mb-4">
                フォローする
            </a>
        <?php endif; ?>

        <!-- SNS・ウェブサイトリンク -->
        <div class="flex items-center justify-center gap-3 flex-wrap">
            <?php if (!empty($targetProfile['website_url'])): ?>
                <a href="<?= e($targetProfile['website_url']) ?>" target="_blank" rel="noopener noreferrer"
                   class="flex items-center gap-1.5 px-4 py-2 rounded-full bg-gray-100 text-gray-700 text-sm font-medium hover:bg-gray-200 transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/>
                    </svg>
                    Web
                </a>
            <?php endif; ?>
            <?php if (!empty($targetProfile['twitter_handle'])): ?>
                <a href="https://twitter.com/<?= urlencode($targetProfile['twitter_handle']) ?>"
                   target="_blank" rel="noopener noreferrer"
                   class="flex items-center gap-1.5 px-4 py-2 rounded-full bg-gray-100 text-gray-700 text-sm font-medium hover:bg-gray-200 transition">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                    </svg>
                    X
                </a>
            <?php endif; ?>
            <?php if (!empty($targetProfile['instagram_handle'])): ?>
                <a href="https://instagram.com/<?= urlencode($targetProfile['instagram_handle']) ?>"
                   target="_blank" rel="noopener noreferrer"
                   class="flex items-center gap-1.5 px-4 py-2 rounded-full bg-gray-100 text-gray-700 text-sm font-medium hover:bg-gray-200 transition">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
                    </svg>
                    Instagram
                </a>
            <?php endif; ?>
        </div>

        <!-- 連絡先を保存 (VCard) -->
        <div class="mt-5 pt-4 border-t border-gray-100">
            <a href="/u/vcard.php?username=<?= urlencode($targetUser['username']) ?>"
               class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                連絡先を保存
            </a>
        </div>
    </div>

    <!-- ===== QRコード (このプロフィールのURL) ===== -->
    <?php require_once APP_ROOT . '/Qr.php'; ?>
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 mb-6 text-center">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">QR コード</h2>
        <img src="<?= e(Qr::dataUri($profileUrl, 200)) ?>" alt="QR" class="mx-auto w-36 h-36">
        <p class="text-xs text-gray-400 mt-3">このQRコードを読み取ると、このプロフィールにアクセスできます</p>
    </div>

    <!-- ===== Hamsa Hand (Nail Display) ===== -->
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 mb-6">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide text-center mb-6">
            Nails
        </h2>

        <!-- 手のひらアニメーション + 5スロット -->
        <div id="hand-container" class="relative mx-auto mb-4 cursor-pointer" style="width:240px; height:310px; user-select:none; -webkit-tap-highlight-color:transparent;">
            <img id="hand-open" src="<?= e(ASSET_HAND_OPEN) ?>" alt="Hand Open"
                 class="absolute inset-0 w-full h-full object-contain transition-opacity duration-500 opacity-100">
            <img id="hand-close" src="<?= e(ASSET_HAND_CLOSE) ?>" alt="Hand Close"
                 class="absolute inset-0 w-full h-full object-contain transition-opacity duration-500 opacity-0">
            <img id="hand-goo" src="<?= e(ASSET_HAND_GOO) ?>" alt="Hand Goo"
                 class="absolute inset-0 w-full h-full object-contain transition-opacity duration-500 opacity-0">

            <!-- ネイルチップ重ね合わせ -->
            <?php
            // handgoo.png上の爪座標
            $nailPositions = [
                0 => ['top' => '51.5%', 'left' => '58.4%', 'w' => '24px', 'h' => '32px', 'rotate' => '-30deg'], // 親指
                1 => ['top' => '38.8%', 'left' => '10.8%', 'w' => '23px', 'h' => '31px', 'rotate' => '15deg'], // 人差指
                2 => ['top' => '35.4%', 'left' => '26.8%', 'w' => '24px', 'h' => '32px', 'rotate' => '8deg'], // 中指
                3 => ['top' => '36.8%', 'left' => '42.8%', 'w' => '24px', 'h' => '32px', 'rotate' => '0deg'], // 薬指
                4 => ['top' => '40%', 'left' => '55.6%', 'w' => '21px', 'h' => '28px', 'rotate' => '-8deg'], // 小指
            ];
            foreach ($nailPositions as $i => $pos):
                $nail = $nailsByFinger[$i] ?? null;
            ?>
            <div class="nail-chip absolute opacity-0 transition-opacity duration-300 rounded-[50%_50%_45%_45%] overflow-hidden border border-gray-200 shadow-sm"
                 style="top:<?= $pos['top'] ?>; left:<?= $pos['left'] ?>; width:<?= $pos['w'] ?>; height:<?= $pos['h'] ?>; transform:rotate(<?= $pos['rotate'] ?>);"
                 data-nail="<?= e(json_encode($nail)) ?>"
                 onclick="showNailModal(this, event)">
                <?php if ($nail): ?>
                    <img src="<?= e($nail['image_url']) ?>" alt="" class="w-full h-full object-cover">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="flex justify-center gap-2 flex-wrap">
            <?php for ($i = 0; $i < 5; $i++):
                $nail = $nailsByFinger[$i] ?? null;
            ?>
                <div class="flex flex-col items-center gap-1">
                    <div class="w-12 h-16 rounded-[50%_50%_45%_45%] overflow-hidden border-2 <?= $nail ? 'border-indigo-300' : 'border-dashed border-gray-300' ?> flex-shrink-0 cursor-pointer"
                         onclick="showNailModal(this, event)" data-nail="<?= e(json_encode($nail)) ?>">
                        <?php if ($nail): ?>
                            <img src="<?= e($nail['image_url']) ?>" class="w-full h-full object-cover" alt="">
                        <?php else: ?>
                            <div class="w-full h-full bg-gray-50 flex items-center justify-center">
                                <span class="text-gray-300 text-lg">+</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
        <p class="text-center text-xs text-gray-400 mt-4">タップ/長押しで爪が見えます</p>
    </div>

    <!-- ネイル詳細モーダル -->
    <div id="nail-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 opacity-0 pointer-events-none transition-opacity duration-300">
        <div class="bg-white rounded-3xl w-full max-w-sm overflow-hidden shadow-2xl transform scale-95 transition-transform duration-300">
            <div class="relative h-64 bg-gray-100">
                <img id="modal-nail-img" src="" class="w-full h-full object-cover">
                <button onclick="hideNailModal()" class="absolute top-4 right-4 w-8 h-8 rounded-full bg-black/30 text-white flex items-center justify-center hover:bg-black/50 transition">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 id="modal-design-name" class="text-lg font-bold text-gray-900"></h3>
                        <p id="modal-nail-code" class="text-sm font-mono text-indigo-600"></p>
                    </div>
                    <button id="modal-like-btn" onclick="toggleLike()" class="flex flex-col items-center gap-0.5">
                        <svg id="modal-like-icon" class="w-7 h-7 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                        <span id="modal-like-count" class="text-[10px] text-gray-400">0</span>
                    </button>
                </div>
                
                <p id="modal-salon" class="text-sm text-purple-600 mb-6 hidden">
                    <svg class="w-4 h-4 inline mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    <span id="modal-salon-name"></span>
                </p>

                <?php if ($currentUser): ?>
                    <div class="space-y-3">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">自分の指に貼る</p>
                        <div class="grid grid-cols-5 gap-2">
                            <?php 
                            $fingerNamesShort = ['親', '人', '中', '薬', '小'];
                            foreach ($fingerNamesShort as $idx => $name): ?>
                                <button onclick="applyNail(<?= $idx ?>)" class="flex flex-col items-center gap-1">
                                    <div class="w-10 h-10 rounded-full border-2 border-indigo-100 bg-indigo-50 flex items-center justify-center text-indigo-600 font-bold text-xs hover:bg-indigo-600 hover:text-white transition">
                                        <?= $name ?>
                                    </div>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="/auth/login.php" class="block w-full py-3 bg-indigo-600 text-white text-center rounded-2xl font-bold hover:bg-indigo-700 transition">
                        ログインしてこのネイルを貼る
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

</main>

<!-- スクリプト -->
<script>
let currentNail = null;

// --- 手のアニメーション ---
(function() {
    const container = document.getElementById('hand-container');
    const imgOpen  = document.getElementById('hand-open');
    const imgClose = document.getElementById('hand-close');
    const imgGoo   = document.getElementById('hand-goo');
    const nailChips = document.querySelectorAll('.nail-chip');

    let phase = 'open';

    setTimeout(() => {
        imgOpen.style.opacity = '0';
        imgClose.style.opacity = '1';
        phase = 'close';
    }, 500);

    function goGoo() {
        if (phase === 'open') return;
        imgClose.style.opacity = '0';
        imgGoo.style.opacity = '1';
        phase = 'goo';
        nailChips.forEach(el => el.style.opacity = '1');
    }

    function goClose() {
        imgGoo.style.opacity = '0';
        imgClose.style.opacity = '1';
        phase = 'close';
        nailChips.forEach(el => el.style.opacity = '0');
    }

    container.addEventListener('mousedown', goGoo);
    container.addEventListener('mouseup', goClose);
    container.addEventListener('mouseleave', () => { if (phase === 'goo') goClose(); });
    container.addEventListener('touchstart', (e) => { e.preventDefault(); goGoo(); });
    container.addEventListener('touchend', goClose);
    container.addEventListener('touchcancel', goClose);
})();

// --- モーダル & API ---
function showNailModal(el, event) {
    if (event) event.stopPropagation();
    const data = el.dataset.nail ? JSON.parse(el.dataset.nail) : null;
    if (!data) return;

    currentNail = data;
    document.getElementById('modal-nail-img').src = data.image_url;
    document.getElementById('modal-design-name').textContent = data.design_name || 'ネイルデザイン';
    document.getElementById('modal-nail-code').textContent = data.nail_code;
    document.getElementById('modal-like-count').textContent = data.like_count || 0;
    
    const salonEl = document.getElementById('modal-salon');
    if (data.salon_name) {
        salonEl.classList.remove('hidden');
        document.getElementById('modal-salon-name').textContent = data.salon_name;
    } else {
        salonEl.classList.add('hidden');
    }

    updateLikeUI(data.is_liked);

    const modal = document.getElementById('nail-modal');
    modal.classList.remove('opacity-0', 'pointer-events-none');
    modal.querySelector('div').classList.remove('scale-95');
}

function hideNailModal() {
    const modal = document.getElementById('nail-modal');
    modal.classList.add('opacity-0', 'pointer-events-none');
    modal.querySelector('div').classList.add('scale-95');
}

function updateLikeUI(isLiked) {
    const icon = document.getElementById('modal-like-icon');
    if (isLiked) {
        icon.classList.add('text-red-500', 'fill-current');
        icon.classList.remove('text-gray-400');
    } else {
        icon.classList.remove('text-red-500', 'fill-current');
        icon.classList.add('text-gray-400');
    }
}

async function apiCall(action, params = {}) {
    try {
        const res = await fetch('/api/nail.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ action, ...params }),
        });
        return await res.json();
    } catch (err) {
        console.error(err);
        return { success: false, message: '通信エラーが発生しました。' };
    }
}

async function toggleLike() {
    if (!currentNail) return;
    const action = currentNail.is_liked ? 'unlike' : 'like';
    const data = await apiCall(action, { nail_id: parseInt(currentNail.nail_id) });
    
    if (data.success) {
        currentNail.is_liked = !currentNail.is_liked;
        const countEl = document.getElementById('modal-like-count');
        let count = parseInt(countEl.textContent);
        count = currentNail.is_liked ? count + 1 : count - 1;
        countEl.textContent = count;
        updateLikeUI(currentNail.is_liked);
    } else {
        alert(data.message);
    }
}

async function applyNail(fingerIndex) {
    if (!currentNail) return;
    const data = await apiCall('apply', {
        nail_id: parseInt(currentNail.nail_id),
        finger_index: fingerIndex
    });
    
    if (data.success) {
        alert('自分の指に貼りました！');
        hideNailModal();
    } else {
        alert(data.message);
    }
}

async function toggleFollow(btn) {
    const targetId  = btn.dataset.target;
    const following = btn.dataset.following === '1';
    const action    = following ? 'unfollow' : 'follow';

    btn.disabled = true;
    try {
        const res = await fetch('/api/follow.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ action, target_user_id: parseInt(targetId) }),
        });
        const data = await res.json();
        if (data.success) {
            const nowFollowing = data.following;
            btn.dataset.following = nowFollowing ? '1' : '0';
            btn.textContent = nowFollowing ? 'フォロー中' : 'フォローする';
            if (nowFollowing) {
                btn.className = 'bg-gray-100 text-gray-700 hover:bg-red-50 hover:text-red-600 border border-gray-300 px-8 py-2.5 rounded-full font-semibold text-sm transition-colors mb-4';
            } else {
                btn.className = 'bg-indigo-600 text-white hover:bg-indigo-700 px-8 py-2.5 rounded-full font-semibold text-sm transition-colors mb-4';
            }
        }
    } catch (err) {
        console.error(err);
    } finally {
        btn.disabled = false;
    }
}
</script>

<?php layout_footer(); ?>
</body>
</html>

<?php
function renderNotFound(): void
{
    global $pageTitle;
    require_once APP_ROOT . '/views/layout.php';
    $pageTitle = '404 Not Found';
    ?>
<!DOCTYPE html>
<html lang="ja">
<head><?php layout_head('ページが見つかりません'); ?></head>
<body class="bg-gray-50 min-h-screen font-sans">
<?php layout_header(); ?>
<main class="flex items-center justify-center min-h-[60vh] text-center px-4">
    <div>
        <p class="text-6xl font-bold text-gray-200 mb-4">404</p>
        <h1 class="text-xl font-bold text-gray-900 mb-2">ページが見つかりません</h1>
        <p class="text-gray-500 text-sm mb-6">このユーザーは存在しないか、プロフィールが非公開です。</p>
        <a href="/" class="text-indigo-600 hover:underline">トップへ戻る</a>
    </div>
</main>
<?php layout_footer(); ?>
</body>
</html>
    <?php
}
