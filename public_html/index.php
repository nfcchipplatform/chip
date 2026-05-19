<?php
/**
 * PONNU — ランディングページ / ルートエントリポイント
 * 未ログイン: ランディングページを表示
 * ログイン済み: /dashboard/ へリダイレクト
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

// ログイン済みならダッシュボードへ
if (Auth::check()) {
    redirect('/dashboard/');
}

$pageTitle = 'PONNU — NFCデジタル名刺';
require_once APP_ROOT . '/views/layout.php';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <?php layout_head($pageTitle); ?>
</head>
<body class="bg-gray-50 min-h-screen font-sans">
    <?php layout_header(); ?>

    <main class="flex flex-col items-center justify-center min-h-[80vh] px-4 py-16 text-center">
        <h1 class="text-4xl font-bold text-gray-900 mb-4">PONNU</h1>
        <p class="text-xl text-gray-600 mb-8">NFCチップでつながる、デジタルプロフィール</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="/auth/login.php"
               class="px-8 py-3 bg-indigo-600 text-white rounded-lg font-semibold
                      hover:bg-indigo-700 transition-colors">
                ログイン
            </a>
            <a href="/auth/register.php"
               class="px-8 py-3 border-2 border-indigo-600 text-indigo-600 rounded-lg font-semibold
                      hover:bg-indigo-50 transition-colors">
                新規登録
            </a>
        </div>
    </main>

    <?php layout_footer(); ?>
</body>
</html>
