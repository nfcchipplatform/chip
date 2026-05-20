<?php
/**
 * PONNU — views/dashboard_layout.php
 * ダッシュボード専用レイアウト: サイドバー + メインコンテンツ
 *
 * 使用方法:
 *   require_once APP_ROOT . '/views/dashboard_layout.php';
 *   dashboard_layout_start($pageTitle);  // <html>〜サイドバーまで出力
 *   // ...メインコンテンツ...
 *   dashboard_layout_end();             // </div>〜</html> まで出力
 */

declare(strict_types=1);

if (!function_exists('dashboard_layout_start')) {

    function dashboard_layout_start(string $pageTitle = 'ダッシュボード'): void
    {
        require_once APP_ROOT . '/views/layout.php';

        $user    = Auth::user();
        $base    = defined('APP_URL') ? APP_URL : '';
        $appName = defined('APP_NAME') ? APP_NAME : 'PONNU';

        // プロフィール取得
        $db      = Database::getInstance();
        $profile = $db->fetchOne(
            'SELECT avatar_url, title FROM profiles WHERE user_id = ? LIMIT 1',
            [$user['id']]
        );

        // 現在のURL（サイドバーのアクティブ判定用）
        $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        $avatarUrl = (!empty($profile['avatar_url']))
            ? $profile['avatar_url']
            : (file_exists($_SERVER['DOCUMENT_ROOT'] . ASSET_DEFAULT_AVATAR)
                ? ASSET_DEFAULT_AVATAR
                : null);
        ?>
<!DOCTYPE html>
<html lang="ja">
<head><?php layout_head($pageTitle); ?></head>
<body class="bg-gray-50 min-h-screen font-sans">

<!-- ===== トップナビゲーション ===== -->
<header class="bg-white border-b border-gray-200 sticky top-0 z-40">
    <div class="flex items-center justify-between h-14 px-4 sm:px-6">

        <!-- ハンバーガー(モバイル) + ロゴ -->
        <div class="flex items-center gap-3">
            <button id="sidebar-toggle"
                    class="sm:hidden p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition"
                    aria-label="メニューを開く">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <a href="/" class="font-bold text-xl text-indigo-600 tracking-tight">
                <?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . ASSET_LOGO)): ?>
                    <img src="<?= e(ASSET_LOGO) ?>" alt="<?= e($appName) ?>" class="h-8">
                <?php else: ?>
                    <?= e($appName) ?>
                <?php endif; ?>
            </a>
        </div>

        <!-- 右側: アバター -->
        <div class="flex items-center gap-3">
            <a href="/dashboard/profile.php" class="flex items-center gap-2 group">
                <?php if ($avatarUrl): ?>
                    <img src="<?= e($avatarUrl) ?>" alt="アバター"
                         class="w-8 h-8 rounded-full object-cover ring-2 ring-transparent
                                group-hover:ring-indigo-400 transition">
                <?php else: ?>
                    <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center
                                ring-2 ring-transparent group-hover:ring-indigo-400 transition">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                <?php endif; ?>
                <span class="hidden sm:block text-sm font-medium text-gray-700 group-hover:text-indigo-600 transition">
                    <?= e($user['display_name'] ?: $user['username'] ?: 'プロフィール') ?>
                </span>
            </a>
        </div>
    </div>
</header>

<!-- ===== オーバーレイ(モバイル) ===== -->
<div id="sidebar-overlay"
     class="fixed inset-0 bg-black/40 z-30 hidden sm:hidden"
     onclick="closeSidebar()"></div>

<!-- ===== レイアウトラッパー ===== -->
<div class="flex min-h-[calc(100vh-3.5rem)]">

    <!-- ===== サイドバー ===== -->
    <aside id="sidebar"
           class="fixed sm:sticky top-14 left-0 h-[calc(100vh-3.5rem)] w-64
                  bg-white border-r border-gray-200 overflow-y-auto z-30
                  -translate-x-full sm:translate-x-0 transition-transform duration-300 flex-shrink-0">
        <nav class="p-4 space-y-1">

            <!-- プロフィール概要 -->
            <div class="pb-4 mb-4 border-b border-gray-100">
                <a href="/dashboard/profile.php" class="flex items-center gap-3 group p-2 rounded-xl hover:bg-gray-50 transition">
                    <?php if ($avatarUrl): ?>
                        <img src="<?= e($avatarUrl) ?>" alt="アバター"
                             class="w-12 h-12 rounded-full object-cover">
                    <?php else: ?>
                        <div class="w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                    <?php endif; ?>
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900 text-sm truncate">
                            <?= e($user['display_name'] ?: $user['username'] ?: 'ユーザー') ?>
                        </p>
                        <?php if (!empty($user['username'])): ?>
                            <p class="text-xs text-gray-400 truncate">@<?= e($user['username']) ?></p>
                        <?php endif; ?>
                    </div>
                </a>
            </div>

            <!-- ナビリンク -->
            <?php
            $navItems = [
                ['href' => '/dashboard/',         'label' => 'ホーム',          'icon' => 'home'],
                ['href' => '/dashboard/profile.php','label' => 'プロフィール編集', 'icon' => 'user'],
                ['href' => '/dashboard/nails.php', 'label' => 'ネイル',          'icon' => 'sparkles'],
                ['href' => '/dashboard/follows.php','label' => 'フォロー',        'icon' => 'users'],
                ['href' => '/u/?username=' . urlencode($user['username'] ?? ''), 'label' => 'プロフィール表示', 'icon' => 'eye'],
            ];

            // サロン管理 (SALON_ADMIN 以上)
            if (in_array($user['role'], ['SALON_ADMIN', 'SUPER_ADMIN'])) {
                $navItems[] = ['href' => '/dashboard/salon.php', 'label' => 'サロン管理', 'icon' => 'building'];
            }
            // 管理 (SUPER_ADMIN のみ)
            if ($user['role'] === 'SUPER_ADMIN') {
                $navItems[] = ['href' => '/admin/', 'label' => '管理画面', 'icon' => 'shield'];
            }

            $icons = [
                'home'     => 'M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z',
                'user'     => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
                'users'    => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
                'eye'      => 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z',
                'building' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
                'shield'   => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
            ];
            foreach ($navItems as $item):
                $isActive = str_starts_with($currentPath, parse_url($item['href'], PHP_URL_PATH));
                $activeClass = $isActive ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900';
                $iconPath = $icons[$item['icon']] ?? $icons['home'];
            ?>
                <a href="<?= e($item['href']) ?>"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition <?= $activeClass ?>">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="<?= e($iconPath) ?>"/>
                    </svg>
                    <?= e($item['label']) ?>
                </a>
            <?php endforeach; ?>

            <!-- ログアウト -->
            <div class="pt-4 mt-4 border-t border-gray-100">
                <form method="post" action="/auth/logout.php">
                    <?php csrf_field(); ?>
                    <button type="submit"
                            class="flex w-full items-center gap-3 px-3 py-2.5 rounded-xl text-sm
                                   text-gray-500 hover:bg-red-50 hover:text-red-600 transition">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        ログアウト
                    </button>
                </form>
            </div>
        </nav>
    </aside>

    <!-- ===== メインコンテンツ開始 ===== -->
    <main class="flex-1 overflow-x-hidden">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 py-8">
            <?php layout_flash(); ?>
        <?php
    }

    function dashboard_layout_end(): void
    {
        ?>
        </div><!-- /.max-w-4xl -->
    </main>
</div><!-- /.flex -->

<script>
function openSidebar() {
    document.getElementById('sidebar').classList.remove('-translate-x-full');
    document.getElementById('sidebar-overlay').classList.remove('hidden');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.add('-translate-x-full');
    document.getElementById('sidebar-overlay').classList.add('hidden');
}
document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar.classList.contains('-translate-x-full')) {
        openSidebar();
    } else {
        closeSidebar();
    }
});
</script>
<?php layout_footer(); ?>
</body>
</html>
        <?php
    }
}
