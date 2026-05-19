<?php
/**
 * PONNU — dashboard/index.php
 * ダッシュボード: NFCカード一覧、フォロワー数、フォロー中数、プロフィール閲覧数
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
Auth::requireLogin('/auth/login.php');
require_once APP_ROOT . '/views/dashboard_layout.php';

$user = Auth::user();
$db   = Database::getInstance();

// ===== 統計情報取得 =====

// NFCカード一覧
$nfcChips = $db->fetchAll(
    'SELECT id, label, status, issued_at, last_accessed_at
     FROM nfc_chips WHERE user_id = ? ORDER BY issued_at DESC',
    [$user['id']]
);

// フォロワー数
$followerCount = (int)($db->fetchOne(
    'SELECT COUNT(*) AS cnt FROM follows WHERE following_id = ?',
    [$user['id']]
)['cnt'] ?? 0);

// フォロー中数
$followingCount = (int)($db->fetchOne(
    'SELECT COUNT(*) AS cnt FROM follows WHERE follower_id = ?',
    [$user['id']]
)['cnt'] ?? 0);

// プロフィール閲覧数 (過去30日)
$viewCount = (int)($db->fetchOne(
    'SELECT COUNT(*) AS cnt FROM profile_views
     WHERE viewed_user_id = ? AND viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
    [$user['id']]
)['cnt'] ?? 0);

// プロフィール
$profile = $db->fetchOne(
    'SELECT avatar_url, title, bio FROM profiles WHERE user_id = ? LIMIT 1',
    [$user['id']]
);

dashboard_layout_start('ダッシュボード');
?>

<!-- ウェルカムバナー -->
<div class="mb-8">
    <h1 class="text-2xl font-bold text-gray-900">
        おかえりなさい、<?= e($user['display_name'] ?: $user['username'] ?: 'ユーザー') ?>さん
    </h1>
    <p class="text-gray-500 text-sm mt-1">PONNUダッシュボードへようこそ</p>
</div>

<!-- 統計カード -->
<div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-8">
    <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm">
        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">NFCカード</p>
        <p class="text-3xl font-bold text-indigo-600 mt-1"><?= count($nfcChips) ?></p>
        <p class="text-xs text-gray-400 mt-1">枚</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm">
        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">フォロワー</p>
        <p class="text-3xl font-bold text-pink-600 mt-1"><?= $followerCount ?></p>
        <p class="text-xs text-gray-400 mt-1">人</p>
    </div>
    <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm col-span-2 sm:col-span-1">
        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">フォロー中</p>
        <p class="text-3xl font-bold text-teal-600 mt-1"><?= $followingCount ?></p>
        <p class="text-xs text-gray-400 mt-1">人</p>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
    <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm">
        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">プロフィール閲覧数（30日）</p>
        <p class="text-3xl font-bold text-amber-600 mt-1"><?= $viewCount ?></p>
        <p class="text-xs text-gray-400 mt-1">回</p>
    </div>
    <!-- プロフィール表示リンク -->
    <?php if (!empty($user['username'])): ?>
    <div class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-2xl border border-indigo-100 p-5">
        <p class="text-xs text-indigo-500 font-medium mb-2">あなたのプロフィールURL</p>
        <p class="text-sm font-mono text-gray-700 truncate mb-3">
            /u/?username=<?= e($user['username']) ?>
        </p>
        <a href="/u/?username=<?= urlencode($user['username']) ?>"
           target="_blank"
           class="inline-flex items-center gap-1 text-xs text-indigo-600 font-medium hover:underline">
            表示する
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
            </svg>
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- NFCカード一覧 -->
<section class="mb-8">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold text-gray-900">NFCカード</h2>
        <span class="text-xs text-gray-400">Step 4以降でカード管理機能を追加予定</span>
    </div>

    <?php if (empty($nfcChips)): ?>
        <div class="bg-white rounded-2xl border border-dashed border-gray-200 p-10 text-center">
            <!-- Hamsa Hand プレースホルダ -->
            <?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . ASSET_HAND_OPEN)): ?>
                <img src="<?= e(ASSET_HAND_OPEN) ?>" alt="" class="w-20 h-20 mx-auto mb-4 opacity-40">
            <?php else: ?>
                <div class="w-20 h-20 mx-auto mb-4 flex items-center justify-center">
                    <!-- SVGプレースホルダ: 手のひら -->
                    <svg viewBox="0 0 80 80" class="w-20 h-20 text-gray-300" fill="currentColor">
                        <ellipse cx="40" cy="70" rx="20" ry="6" opacity="0.3"/>
                        <rect x="36" y="18" width="8" height="34" rx="4"/>
                        <rect x="26" y="22" width="8" height="30" rx="4"/>
                        <rect x="16" y="28" width="8" height="26" rx="4"/>
                        <rect x="46" y="22" width="8" height="30" rx="4"/>
                        <rect x="56" y="30" width="7" height="22" rx="3.5"/>
                        <rect x="25" y="48" width="30" height="18" rx="4"/>
                    </svg>
                </div>
            <?php endif; ?>
            <p class="text-gray-500 text-sm">NFCカードがまだ登録されていません</p>
            <p class="text-gray-400 text-xs mt-1">管理者にNFCカードの発行を依頼してください</p>
        </div>
    <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($nfcChips as $chip): ?>
                <div class="bg-white rounded-xl border border-gray-100 p-4 flex items-center gap-4 shadow-sm">
                    <!-- カードアイコン -->
                    <?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . ASSET_NFC_CARD)): ?>
                        <img src="<?= e(ASSET_NFC_CARD) ?>" alt="" class="w-10 h-10 object-contain opacity-70">
                    <?php else: ?>
                        <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/>
                            </svg>
                        </div>
                    <?php endif; ?>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-900 text-sm">
                            <?= e($chip['label'] ?: 'NFCカード #' . $chip['id']) ?>
                        </p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            発行: <?= e(date('Y/m/d', strtotime($chip['issued_at']))) ?>
                            <?php if ($chip['last_accessed_at']): ?>
                                ・最終読み取り: <?= e(date('Y/m/d', strtotime($chip['last_accessed_at']))) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <span class="text-xs px-2 py-1 rounded-full font-medium
                                <?= $chip['status'] === 'active'
                                    ? 'bg-green-100 text-green-700'
                                    : 'bg-red-100 text-red-600' ?>">
                        <?= e($chip['status'] === 'active' ? '有効' : '無効') ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- クイックリンク -->
<section>
    <h2 class="text-lg font-bold text-gray-900 mb-4">クイックアクション</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <a href="/dashboard/profile.php"
           class="flex items-center gap-3 bg-white rounded-xl border border-gray-100 p-4 shadow-sm
                  hover:border-indigo-300 hover:shadow-md transition group">
            <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0
                        group-hover:bg-indigo-200 transition">
                <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </div>
            <div>
                <p class="font-medium text-gray-900 text-sm">プロフィール編集</p>
                <p class="text-xs text-gray-400">名前・写真・SNSリンクを更新</p>
            </div>
        </a>

        <a href="/dashboard/follows.php"
           class="flex items-center gap-3 bg-white rounded-xl border border-gray-100 p-4 shadow-sm
                  hover:border-pink-300 hover:shadow-md transition group">
            <div class="w-10 h-10 bg-pink-100 rounded-lg flex items-center justify-center flex-shrink-0
                        group-hover:bg-pink-200 transition">
                <svg class="w-5 h-5 text-pink-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <div>
                <p class="font-medium text-gray-900 text-sm">フォロー管理</p>
                <p class="text-xs text-gray-400">フォロー中・フォロワー一覧</p>
            </div>
        </a>
    </div>
</section>

<?php dashboard_layout_end(); ?>
