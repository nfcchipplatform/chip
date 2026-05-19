<?php
/**
 * PONNU — api/follow.php
 * POST {action:'follow'|'unfollow', target_user_id: int}
 * レスポンス: JSON {success: bool, following: bool, follower_count: int}
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

// AJAX のみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

// CSRF 検証
Csrf::verify(false) || json_response(['error' => 'CSRF token mismatch'], 403);

// ログイン必須
$currentUser = Auth::user();
if (!$currentUser) {
    json_response(['error' => 'Unauthorized'], 401);
}

// リクエストボディ解析
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    // フォームエンコードにもフォールバック
    $body = $_POST;
}

$action       = isset($body['action']) ? trim((string)$body['action']) : '';
$targetUserId = isset($body['target_user_id']) ? (int)$body['target_user_id'] : 0;

if (!in_array($action, ['follow', 'unfollow'], true) || $targetUserId <= 0) {
    json_response(['error' => 'Invalid parameters'], 400);
}

// 自己フォロー禁止
if ($targetUserId === $currentUser['id']) {
    json_response(['error' => 'Cannot follow yourself'], 400);
}

$db = Database::getInstance();

// 対象ユーザーの存在確認
$targetUser = $db->fetchOne(
    'SELECT id FROM users WHERE id = ? AND status = ? LIMIT 1',
    [$targetUserId, 'active']
);
if (!$targetUser) {
    json_response(['error' => 'User not found'], 404);
}

if ($action === 'follow') {
    // 重複無視して INSERT
    try {
        $db->execute(
            'INSERT IGNORE INTO follows (follower_id, following_id) VALUES (?, ?)',
            [$currentUser['id'], $targetUserId]
        );
    } catch (PDOException $e) {
        error_log('[api/follow] ' . $e->getMessage());
        json_response(['error' => 'DB error'], 500);
    }
    $following = true;
} else {
    $db->execute(
        'DELETE FROM follows WHERE follower_id = ? AND following_id = ?',
        [$currentUser['id'], $targetUserId]
    );
    $following = false;
}

// フォロワー数
$followerCount = (int)($db->fetchOne(
    'SELECT COUNT(*) AS cnt FROM follows WHERE following_id = ?',
    [$targetUserId]
)['cnt'] ?? 0);

json_response([
    'success'        => true,
    'following'      => $following,
    'follower_count' => $followerCount,
]);
