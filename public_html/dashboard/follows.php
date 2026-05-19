<?php
/**
 * PONNU — dashboard/follows.php
 * フォロー中 / フォロワー 一覧 タブ切替
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
Auth::requireLogin('/auth/login.php');
require_once APP_ROOT . '/views/dashboard_layout.php';

$user = Auth::user();
$db   = Database::getInstance();

$tab = (isset($_GET['tab']) && $_GET['tab'] === 'followers') ? 'followers' : 'following';

// フォロー中一覧
$following = $db->fetchAll(
    'SELECT u.id, u.display_name, u.username, p.avatar_url, p.title, f.created_at
     FROM follows f
     JOIN users    u ON u.id = f.following_id AND u.status = ?
     JOIN profiles p ON p.user_id = u.id
     WHERE f.follower_id = ?
     ORDER BY f.created_at DESC',
    ['active', $user['id']]
);

// フォロワー一覧
$followers = $db->fetchAll(
    'SELECT u.id, u.display_name, u.username, p.avatar_url, p.title, f.created_at
     FROM follows f
     JOIN users    u ON u.id = f.follower_id AND u.status = ?
     JOIN profiles p ON p.user_id = u.id
     WHERE f.following_id = ?
     ORDER BY f.created_at DESC',
    ['active', $user['id']]
);

// 自分がフォローしているユーザーIDのセット（フォロワー一覧でのフォローバックボタン用）
$followingIds = array_column($following, 'id');

dashboard_layout_start('フォロー管理');
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">フォロー管理</h1>
</div>

<!-- タブ -->
<div class="flex border-b border-gray-200 mb-6">
    <a href="/dashboard/follows.php?tab=following"
       class="px-6 py-3 text-sm font-medium border-b-2 transition
              <?= $tab === 'following'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
        フォロー中
        <span class="ml-1.5 bg-gray-100 text-gray-500 text-xs px-1.5 py-0.5 rounded-full">
            <?= count($following) ?>
        </span>
    </a>
    <a href="/dashboard/follows.php?tab=followers"
       class="px-6 py-3 text-sm font-medium border-b-2 transition
              <?= $tab === 'followers'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
        フォロワー
        <span class="ml-1.5 bg-gray-100 text-gray-500 text-xs px-1.5 py-0.5 rounded-full">
            <?= count($followers) ?>
        </span>
    </a>
</div>

<?php if ($tab === 'following'): ?>
    <!-- ===== フォロー中 ===== -->
    <?php if (empty($following)): ?>
        <div class="text-center py-16 text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <p class="text-sm">まだフォローしているユーザーはいません</p>
        </div>
    <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($following as $u): ?>
                <div class="bg-white rounded-xl border border-gray-100 p-4 flex items-center gap-4 shadow-sm">
                    <!-- アバター -->
                    <a href="/u/?username=<?= urlencode($u['username']) ?>">
                        <?php if ($u['avatar_url'] && asset_exists($u['avatar_url'])): ?>
                            <img src="<?= e($u['avatar_url']) ?>" alt=""
                                 class="w-12 h-12 rounded-full object-cover flex-shrink-0">
                        <?php else: ?>
                            <div class="w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                <span class="font-bold text-indigo-500">
                                    <?= mb_strtoupper(mb_substr($u['display_name'] ?: $u['username'], 0, 1)) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </a>
                    <!-- 情報 -->
                    <div class="flex-1 min-w-0">
                        <a href="/u/?username=<?= urlencode($u['username']) ?>"
                           class="font-semibold text-gray-900 text-sm hover:text-indigo-600 transition truncate block">
                            <?= e($u['display_name'] ?: $u['username']) ?>
                        </a>
                        <p class="text-xs text-gray-400">@<?= e($u['username']) ?></p>
                        <?php if (!empty($u['title'])): ?>
                            <p class="text-xs text-gray-500 truncate"><?= e($u['title']) ?></p>
                        <?php endif; ?>
                    </div>
                    <!-- アンフォローボタン -->
                    <button
                        data-user-id="<?= (int)$u['id'] ?>"
                        data-action="unfollow"
                        onclick="doFollowAction(this)"
                        class="flex-shrink-0 px-4 py-1.5 border border-gray-300 text-gray-600 text-xs font-medium
                               rounded-full hover:bg-red-50 hover:text-red-600 hover:border-red-300 transition">
                        フォロー中
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <!-- ===== フォロワー ===== -->
    <?php if (empty($followers)): ?>
        <div class="text-center py-16 text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <p class="text-sm">まだフォロワーはいません</p>
        </div>
    <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($followers as $u): ?>
                <?php $alreadyFollowing = in_array($u['id'], $followingIds, false); ?>
                <div class="bg-white rounded-xl border border-gray-100 p-4 flex items-center gap-4 shadow-sm">
                    <a href="/u/?username=<?= urlencode($u['username']) ?>">
                        <?php if ($u['avatar_url'] && asset_exists($u['avatar_url'])): ?>
                            <img src="<?= e($u['avatar_url']) ?>" alt=""
                                 class="w-12 h-12 rounded-full object-cover flex-shrink-0">
                        <?php else: ?>
                            <div class="w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                <span class="font-bold text-indigo-500">
                                    <?= mb_strtoupper(mb_substr($u['display_name'] ?: $u['username'], 0, 1)) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </a>
                    <div class="flex-1 min-w-0">
                        <a href="/u/?username=<?= urlencode($u['username']) ?>"
                           class="font-semibold text-gray-900 text-sm hover:text-indigo-600 transition truncate block">
                            <?= e($u['display_name'] ?: $u['username']) ?>
                        </a>
                        <p class="text-xs text-gray-400">@<?= e($u['username']) ?></p>
                        <?php if (!empty($u['title'])): ?>
                            <p class="text-xs text-gray-500 truncate"><?= e($u['title']) ?></p>
                        <?php endif; ?>
                    </div>
                    <!-- フォロー/フォロー中ボタン -->
                    <button
                        data-user-id="<?= (int)$u['id'] ?>"
                        data-action="<?= $alreadyFollowing ? 'unfollow' : 'follow' ?>"
                        onclick="doFollowAction(this)"
                        class="flex-shrink-0 px-4 py-1.5 text-xs font-medium rounded-full transition
                               <?= $alreadyFollowing
                                   ? 'border border-gray-300 text-gray-600 hover:bg-red-50 hover:text-red-600 hover:border-red-300'
                                   : 'bg-indigo-600 text-white hover:bg-indigo-700' ?>">
                        <?= $alreadyFollowing ? 'フォロー中' : 'フォローバック' ?>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
async function doFollowAction(btn) {
    const userId = parseInt(btn.dataset.userId);
    const action = btn.dataset.action;
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
            body: JSON.stringify({ action, target_user_id: userId }),
        });
        const data = await res.json();
        if (data.success) {
            // ページリロードで状態を反映（シンプルアプローチ）
            window.location.reload();
        }
    } catch (err) {
        console.error(err);
        btn.disabled = false;
    }
}
</script>

<?php dashboard_layout_end(); ?>
