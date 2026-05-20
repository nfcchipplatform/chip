<?php
/**
 * PONNU — dashboard/profile.php
 * プロフィール編集: 表示名/username/title/bio/SNS/website/avatar/direct_link
 */
declare(strict_types=1);

// OPcache無効化（開発中: ファイル更新を即座に反映）
if (function_exists('opcache_invalidate')) {
    @opcache_invalidate(__FILE__, true);
}

require_once __DIR__ . '/../../app/bootstrap.php';
Auth::requireLogin('/auth/login.php');
require_once APP_ROOT . '/views/dashboard_layout.php';

$user = Auth::user();
$db   = Database::getInstance();

// プロフィール取得
$profile = $db->fetchOne(
    'SELECT bio, avatar_url, theme, twitter_handle, instagram_handle, website_url, title
     FROM profiles WHERE user_id = ? LIMIT 1',
    [$user['id']]
);
if (!$profile) {
    // profiles レコードが無ければ作成
    $db->execute('INSERT INTO profiles (user_id) VALUES (?)', [$user['id']]);
    $profile = ['bio' => '', 'avatar_url' => null, 'theme' => 'default',
                'twitter_handle' => '', 'instagram_handle' => '', 'website_url' => '', 'title' => ''];
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();

    $displayName   = input('display_name');
    $username      = input('username');
    $title         = input('title');
    $bio           = input('bio');
    $websiteUrl    = input('website_url');
    $twitterHandle = ltrim(input('twitter_handle'), '@');
    $instaHandle   = ltrim(input('instagram_handle'), '@');
    $directEnabled = isset($_POST['direct_link_enabled']) ? 1 : 0;
    $directUrl     = input('direct_link_url');
    $isPublic      = isset($_POST['is_public']) ? 1 : 0;

    // バリデーション
    if (strlen($displayName) > 100) {
        $errors[] = '表示名は100文字以内で入力してください。';
    }
    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $errors[] = 'ユーザー名は3〜30文字の英数字・アンダースコアで入力してください。';
    }
    if (strlen($bio) > 500) {
        $errors[] = '自己紹介は500文字以内で入力してください。';
    }
    if ($websiteUrl && !filter_var($websiteUrl, FILTER_VALIDATE_URL)) {
        $errors[] = 'WebサイトURLの形式が正しくありません。';
    }
    if ($directEnabled && $directUrl && !filter_var($directUrl, FILTER_VALIDATE_URL)) {
        $errors[] = 'ダイレクトリンクURLの形式が正しくありません。';
    }

    // ユーザー名重複チェック（自分以外）
    if (empty($errors)) {
        $dup = $db->fetchOne(
            'SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1',
            [$username, $user['id']]
        );
        if ($dup) {
            $errors[] = 'このユーザー名はすでに使用されています。';
        }
    }

    // ======= アバターアップロード処理 =======
    $newAvatarUrl = $profile['avatar_url'] ?? null;

    if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['avatar'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'ファイルのアップロードに失敗しました (エラーコード: ' . $file['error'] . ')';
        } elseif ($file['size'] > (defined('UPLOAD_MAX_SIZE') ? UPLOAD_MAX_SIZE : 5242880)) {
            $errors[] = '画像ファイルは5MB以下にしてください。';
        } else {
            // MIME タイプ検証（getimagesize より finfo の方が信頼性が高い）
            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);

            $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($allowedMimes[$mimeType])) {
                $errors[] = '対応している画像形式は JPEG・PNG・WebP です。';
            } else {
                $ext      = $allowedMimes[$mimeType];
                // 推測不能なファイル名 (二重拡張子防止)
                $filename = bin2hex(random_bytes(16)) . '.' . $ext;
                $uploadDir = (defined('UPLOAD_DIR') ? UPLOAD_DIR : BASE_ROOT . '/public_html/uploads/') . 'avatars/';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $destPath = $uploadDir . $filename;

                // GD でリサイズ (512x512)
                $resized = resizeAvatar($file['tmp_name'], $mimeType, 512, 512);
                if ($resized === false) {
                    $errors[] = '画像の処理に失敗しました。';
                } else {
                    $saved = false;
                    if ($mimeType === 'image/jpeg') {
                        $saved = imagejpeg($resized, $destPath, 88);
                    } elseif ($mimeType === 'image/png') {
                        $saved = imagepng($resized, $destPath);
                    } elseif ($mimeType === 'image/webp') {
                        $saved = imagewebp($resized, $destPath, 88);
                    }
                    imagedestroy($resized);

                    if ($saved) {
                        // 旧アバターを削除（デフォルトアバターでない場合）
                        if ($newAvatarUrl && str_starts_with($newAvatarUrl, '/uploads/avatars/')) {
                            $oldPath = BASE_ROOT . '/public_html' . $newAvatarUrl;
                            if (file_exists($oldPath)) {
                                @unlink($oldPath);
                            }
                        }
                        $newAvatarUrl = '/uploads/avatars/' . $filename;
                    } else {
                        $errors[] = '画像の保存に失敗しました。';
                    }
                }
            }
        }
    }

    // ======= DB 更新 =======
    if (empty($errors)) {
        $db->beginTransaction();
        try {
            $db->execute(
                'UPDATE users SET display_name = ?, username = ?, direct_link_enabled = ?, direct_link_url = ?
                 WHERE id = ?',
                [$displayName, $username, $directEnabled, $directUrl ?: null, $user['id']]
            );
            $db->execute(
                'UPDATE profiles SET title = ?, bio = ?, avatar_url = ?, twitter_handle = ?,
                 instagram_handle = ?, website_url = ?, is_public = ?
                 WHERE user_id = ?',
                [$title ?: null, $bio ?: null, $newAvatarUrl, $twitterHandle ?: null,
                 $instaHandle ?: null, $websiteUrl ?: null, $isPublic, $user['id']]
            );
            $db->commit();
            flash_set('success', 'プロフィールを更新しました。');
            redirect('/dashboard/profile.php');
        } catch (PDOException $e) {
            $db->rollback();
            error_log('[profile] DB error: ' . $e->getMessage());
            $errors[] = '保存中にエラーが発生しました。';
        }
    }

    // エラー時は最新値でフォームを再表示するためデータを更新
    $user    = Auth::user();
    $profile = $db->fetchOne('SELECT bio, avatar_url, theme, twitter_handle, instagram_handle, website_url, title, is_public FROM profiles WHERE user_id = ? LIMIT 1', [$user['id']]) ?: $profile;
}

// ========== GD リサイズヘルパー ==========
function resizeAvatar(string $srcPath, string $mime, int $w, int $h): \GdImage|false
{
    $src = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($srcPath),
        'image/png'  => imagecreatefrompng($srcPath),
        'image/webp' => imagecreatefromwebp($srcPath),
        default      => false,
    };
    if (!$src) return false;

    $sw = imagesx($src);
    $sh = imagesy($src);

    // クロップしてから正方形にリサイズ
    $cropSize = min($sw, $sh);
    $cropX    = (int)(($sw - $cropSize) / 2);
    $cropY    = (int)(($sh - $cropSize) / 2);

    $dst = imagecreatetruecolor($w, $h);

    // PNG の透過対応
    if ($mime === 'image/png') {
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $transparent);
    }

    imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, $w, $h, $cropSize, $cropSize);
    imagedestroy($src);
    return $dst;
}

dashboard_layout_start('プロフィール編集');

$avatarUrl = $profile['avatar_url'] ?? null;
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">プロフィール編集</h1>
    <p class="text-gray-500 text-sm mt-1">公開プロフィールに表示される情報を編集します</p>
</div>

<?php if ($errors): ?>
    <div class="mb-6 rounded-xl bg-red-50 border border-red-200 p-4">
        <?php foreach ($errors as $err): ?>
            <p class="text-sm text-red-700"><?= e($err) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="/dashboard/profile.php"
      enctype="multipart/form-data" novalidate>
    <?php csrf_field(); ?>

    <!-- ===== アバター ===== -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
        <h2 class="font-semibold text-gray-900 mb-4">プロフィール画像</h2>
        <div class="flex items-center gap-6">
            <!-- 現在のアバター -->
            <div class="relative flex-shrink-0">
                <?php if ($avatarUrl && asset_exists($avatarUrl)): ?>
                    <img id="avatar-preview" src="<?= e($avatarUrl) ?>" alt="アバター"
                         class="w-24 h-24 rounded-full object-cover border-2 border-gray-200">
                <?php else: ?>
                    <div id="avatar-preview-placeholder"
                         class="w-24 h-24 rounded-full bg-indigo-100 flex items-center justify-center border-2 border-gray-200">
                        <svg class="w-10 h-10 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <img id="avatar-preview" src="" alt="" class="w-24 h-24 rounded-full object-cover border-2 border-gray-200 hidden">
                <?php endif; ?>
            </div>
            <div>
                <label for="avatar" class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    画像を選択
                </label>
                <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp"
                       class="hidden" onchange="previewAvatar(this)">
                <p class="text-xs text-gray-400 mt-2">JPEG・PNG・WebP、5MB以下<br>正方形にクロップして512×512pxで保存されます</p>
            </div>
        </div>
    </div>

    <!-- ===== 基本情報 ===== -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
        <h2 class="font-semibold text-gray-900 mb-4">基本情報</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
            <div>
                <label for="display_name" class="block text-sm font-medium text-gray-700 mb-1">
                    表示名
                </label>
                <input type="text" id="display_name" name="display_name"
                       value="<?= e($user['display_name'] ?? '') ?>"
                       maxlength="100" data-max-length="100"
                       class="w-full px-4 py-2.5 rounded-lg border border-gray-300
                              focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
            </div>
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">
                    ユーザー名 <span class="text-red-500">*</span>
                </label>
                <div class="flex items-center">
                    <span class="px-3 py-2.5 bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg text-gray-500 text-sm">@</span>
                    <input type="text" id="username" name="username"
                           value="<?= e($user['username'] ?? '') ?>"
                           required pattern="[a-zA-Z0-9_]{3,30}"
                           class="flex-1 px-4 py-2.5 border border-gray-300 rounded-r-lg
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                </div>
            </div>
        </div>

        <div class="mb-4">
            <label for="title" class="block text-sm font-medium text-gray-700 mb-1">
                肩書き・職種
            </label>
            <input type="text" id="title" name="title"
                   value="<?= e($profile['title'] ?? '') ?>"
                   maxlength="100"
                   placeholder="例: ネイリスト / フリーランスデザイナー"
                   class="w-full px-4 py-2.5 rounded-lg border border-gray-300
                          focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
        </div>

        <div class="mb-4">
            <label for="bio" class="block text-sm font-medium text-gray-700 mb-1">
                自己紹介
            </label>
            <textarea id="bio" name="bio" rows="4"
                      maxlength="500" data-max-length="500"
                      placeholder="あなた自身について教えてください"
                      class="w-full px-4 py-2.5 rounded-lg border border-gray-300
                             focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent
                             transition resize-y"><?= e($profile['bio'] ?? '') ?></textarea>
        </div>

        <div class="mb-2">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_public" value="1"
                       <?= ($profile['is_public'] ?? 1) ? 'checked' : '' ?>
                       class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="text-sm font-medium text-gray-700">プロフィールを公開する</span>
            </label>
        </div>
    </div>

    <!-- ===== SNS・ウェブサイト ===== -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
        <h2 class="font-semibold text-gray-900 mb-4">SNS・ウェブサイト</h2>

        <div class="mb-4">
            <label for="website_url" class="block text-sm font-medium text-gray-700 mb-1">
                ウェブサイト URL
            </label>
            <input type="url" id="website_url" name="website_url"
                   value="<?= e($profile['website_url'] ?? '') ?>"
                   placeholder="https://your-website.com"
                   class="w-full px-4 py-2.5 rounded-lg border border-gray-300
                          focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="twitter_handle" class="block text-sm font-medium text-gray-700 mb-1">
                    X (Twitter)
                </label>
                <div class="flex items-center">
                    <span class="px-3 py-2.5 bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg text-gray-500 text-sm">@</span>
                    <input type="text" id="twitter_handle" name="twitter_handle"
                           value="<?= e($profile['twitter_handle'] ?? '') ?>"
                           placeholder="username"
                           class="flex-1 px-4 py-2.5 border border-gray-300 rounded-r-lg
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                </div>
            </div>
            <div>
                <label for="instagram_handle" class="block text-sm font-medium text-gray-700 mb-1">
                    Instagram
                </label>
                <div class="flex items-center">
                    <span class="px-3 py-2.5 bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg text-gray-500 text-sm">@</span>
                    <input type="text" id="instagram_handle" name="instagram_handle"
                           value="<?= e($profile['instagram_handle'] ?? '') ?>"
                           placeholder="username"
                           class="flex-1 px-4 py-2.5 border border-gray-300 rounded-r-lg
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                </div>
            </div>
        </div>
    </div>

    <!-- ===== ダイレクトリンク ===== -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
        <h2 class="font-semibold text-gray-900 mb-1">ダイレクトリンク</h2>
        <p class="text-xs text-gray-400 mb-4">有効にすると、ICカード読み取り時にプロフィールを経由せず直接このURLに遷移します</p>

        <label class="flex items-center gap-2 cursor-pointer mb-4">
            <input type="checkbox" name="direct_link_enabled" value="1"
                   id="direct_link_enabled"
                   <?= ($user['direct_link_enabled'] ?? 0) ? 'checked' : '' ?>
                   class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
            <span class="text-sm font-medium text-gray-700">ダイレクトリンクを有効にする</span>
        </label>

        <label for="direct_link_url" class="block text-sm font-medium text-gray-700 mb-1">
            リンク先 URL
        </label>
        <input type="url" id="direct_link_url" name="direct_link_url"
               value="<?= e($user['direct_link_url'] ?? '') ?>"
               placeholder="https://example.com"
               class="w-full px-4 py-2.5 rounded-lg border border-gray-300
                      focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
    </div>

    <!-- ===== 保存ボタン ===== -->
    <div class="flex items-center gap-3">
        <button type="submit"
                class="px-8 py-3 bg-indigo-600 text-white font-semibold rounded-xl
                       hover:bg-indigo-700 active:bg-indigo-800 transition-colors">
            変更を保存
        </button>
        <?php if (!empty($user['username'])): ?>
            <a href="/u/?username=<?= urlencode($user['username']) ?>"
               target="_blank"
               class="px-6 py-3 border border-gray-300 text-gray-600 font-medium rounded-xl
                      hover:bg-gray-50 transition-colors text-sm">
                プロフィール表示
            </a>
        <?php endif; ?>
    </div>
</form>

<script>
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('avatar-preview');
            const placeholder = document.getElementById('avatar-preview-placeholder');
            preview.src = e.target.result;
            preview.classList.remove('hidden');
            if (placeholder) placeholder.classList.add('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php dashboard_layout_end(); ?>
