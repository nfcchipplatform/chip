<?php
/**
 * PONNU — views/layout.php
 * Tailwind CDN + Noto Sans JP + CSRF meta + レスポンシブヘッダ
 *
 * 使用方法:
 *   $pageTitle = 'ページタイトル';
 *   require APP_ROOT . '/views/layout.php';
 *   // ページ本文
 *
 * 提供する関数:
 *   layout_head($title)    <head> 内容を出力（<head> タグなし）
 *   layout_header()        ナビゲーションヘッダを出力
 *   layout_footer()        フッターを出力
 *   layout_flash()         フラッシュメッセージを出力
 */

declare(strict_types=1);

if (!function_exists('layout_head')) {

    /**
     * <head> 内のメタ・スタイルシートを出力
     * ※ 呼び出し元で <head>...</head> タグで囲む
     */
    function layout_head(string $pageTitle = 'PONNU'): void
    {
        $appName = defined('APP_NAME') ? APP_NAME : 'PONNU';
        $title   = $pageTitle !== $appName ? e($pageTitle) . ' — ' . e($appName) : e($appName);
        $base    = defined('APP_URL') ? APP_URL : '';
        ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <?php Csrf::meta(); ?>
    <title><?= $title ?></title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Noto Sans JP"', 'sans-serif'],
                    },
                    colors: {
                        ponnu: {
                            primary: '#6366f1',
                            accent:  '#ec4899',
                        },
                    },
                },
            },
        };
    </script>

    <!-- Noto Sans JP (Google Fonts) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap"
          rel="stylesheet">

    <!-- カスタム CSS -->
    <link rel="stylesheet" href="<?= $base ?>/assets/css/app.css">
        <?php
    }

    /**
     * ページ上部のナビゲーションヘッダを出力
     */
    function layout_header(): void
    {
        $user    = Auth::user();
        $appName = defined('APP_NAME') ? APP_NAME : 'PONNU';
        $base    = defined('APP_URL') ? APP_URL : '';
        ?>
<header class="bg-white border-b border-gray-200 sticky top-0 z-40">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 flex items-center justify-between h-14">

        <!-- ロゴ -->
        <a href="<?= $base ?>/" class="font-bold text-xl text-indigo-600 tracking-tight">
            <?= e($appName) ?>
        </a>

        <!-- デスクトップナビ -->
        <nav class="hidden sm:flex items-center gap-4 text-sm font-medium">
            <?php if ($user): ?>
                <a href="<?= $base ?>/dashboard/"
                   class="text-gray-700 hover:text-indigo-600 transition-colors">
                    ダッシュボード
                </a>
                <a href="<?= $base ?>/dashboard/profile.php"
                   class="text-gray-700 hover:text-indigo-600 transition-colors">
                    プロフィール
                </a>
                <form action="<?= $base ?>/auth/logout.php" method="post" class="inline">
                    <?php csrf_field(); ?>
                    <button type="submit"
                            class="text-gray-500 hover:text-red-600 transition-colors">
                        ログアウト
                    </button>
                </form>
            <?php else: ?>
                <a href="<?= $base ?>/auth/login.php"
                   class="text-gray-700 hover:text-indigo-600 transition-colors">
                    ログイン
                </a>
                <a href="<?= $base ?>/auth/register.php"
                   class="btn-primary text-sm px-4 py-2">
                    新規登録
                </a>
            <?php endif; ?>
        </nav>

        <!-- モバイルハンバーガー -->
        <button id="menu-toggle"
                aria-controls="mobile-nav"
                aria-expanded="false"
                class="sm:hidden p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
    </div>

    <!-- モバイルドロワー -->
    <nav id="mobile-nav"
         class="hidden sm:hidden border-t border-gray-100 bg-white px-4 py-3 space-y-2 text-sm">
        <?php if ($user): ?>
            <a href="<?= $base ?>/dashboard/"
               class="block py-2 text-gray-700 hover:text-indigo-600">
                ダッシュボード
            </a>
            <a href="<?= $base ?>/dashboard/profile.php"
               class="block py-2 text-gray-700 hover:text-indigo-600">
                プロフィール
            </a>
            <form action="<?= $base ?>/auth/logout.php" method="post">
                <?php csrf_field(); ?>
                <button type="submit"
                        class="w-full text-left py-2 text-gray-500 hover:text-red-600">
                    ログアウト
                </button>
            </form>
        <?php else: ?>
            <a href="<?= $base ?>/auth/login.php"
               class="block py-2 text-gray-700 hover:text-indigo-600">
                ログイン
            </a>
            <a href="<?= $base ?>/auth/register.php"
               class="block py-2 text-indigo-600 font-semibold">
                新規登録
            </a>
        <?php endif; ?>
    </nav>
</header>
        <?php
    }

    /**
     * フラッシュメッセージを出力（あれば）
     */
    function layout_flash(): void
    {
        $types = ['success', 'error', 'info'];
        foreach ($types as $type) {
            $msg = flash_get($type);
            if ($msg === null) continue;

            $classes = match ($type) {
                'success' => 'alert alert-success',
                'error'   => 'alert alert-error',
                default   => 'alert alert-info',
            };
            echo '<div class="' . $classes . '" data-flash-auto-hide>' . e($msg) . '</div>';
        }
    }

    /**
     * フッターを出力
     */
    function layout_footer(): void
    {
        $year    = date('Y');
        $appName = defined('APP_NAME') ? APP_NAME : 'PONNU';
        $base    = defined('APP_URL') ? APP_URL : '';
        ?>
<footer class="border-t border-gray-200 bg-white mt-auto py-8 text-center text-sm text-gray-400">
    <p>&copy; <?= $year ?> <?= e($appName) ?>. All rights reserved.</p>
</footer>

<!-- アプリケーション JS -->
<script src="<?= $base ?>/assets/js/app.js"></script>
        <?php
    }
}
