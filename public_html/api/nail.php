<?php
/**
 * PONNU — api/nail.php
 * ネイル操作 API
 * JSON {action: 'apply'|'remove'|'like'|'unlike', nail_id?: int, finger_index?: int}
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

// AJAX POST のみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

// CSRF 検証 (Header: X-CSRF-Token)
if (!Csrf::verify(false)) {
    json_response(['success' => false, 'message' => 'CSRF token mismatch'], 403);
}

// 認証チェック
$currentUser = Auth::user();
if (!$currentUser) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 401);
}

// リクエストボディ解析
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? '';
$db = Database::getInstance();

try {
    switch ($action) {
        case 'apply':
            $nailId = (int)($input['nail_id'] ?? 0);
            $fingerIndex = (int)($input['finger_index'] ?? -1);
            if ($nailId <= 0 || $fingerIndex < 0 || $fingerIndex > 4) {
                throw new Exception('パラメータが不正です。');
            }
            // ネイル存在確認
            $nail = $db->fetchOne('SELECT id FROM nails WHERE id = ?', [$nailId]);
            if (!$nail) throw new Exception('ネイルが見つかりません。');

            $db->execute(
                'INSERT INTO user_nails (user_id, finger_index, nail_id) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE nail_id = VALUES(nail_id), applied_at = NOW()',
                [$currentUser['id'], $fingerIndex, $nailId]
            );
            $db->execute('UPDATE nails SET used_count = used_count + 1 WHERE id = ?', [$nailId]);
            json_response(['success' => true, 'message' => 'ネイルを指にセットしました。']);
            break;

        case 'remove':
            $fingerIndex = (int)($input['finger_index'] ?? -1);
            if ($fingerIndex < 0 || $fingerIndex > 4) {
                throw new Exception('パラメータが不正です。');
            }
            $db->execute('DELETE FROM user_nails WHERE user_id = ? AND finger_index = ?', [$currentUser['id'], $fingerIndex]);
            json_response(['success' => true, 'message' => 'ネイルを外しました。']);
            break;

        case 'like':
            $nailId = (int)($input['nail_id'] ?? 0);
            if ($nailId <= 0) throw new Exception('パラメータが不正です。');
            $db->execute('INSERT IGNORE INTO nail_likes (user_id, nail_id) VALUES (?, ?)', [$currentUser['id'], $nailId]);
            $db->execute('UPDATE nails SET like_count = (SELECT COUNT(*) FROM nail_likes WHERE nail_id = ?) WHERE id = ?', [$nailId, $nailId]);
            json_response(['success' => true, 'message' => 'お気に入りに追加しました。']);
            break;

        case 'unlike':
            $nailId = (int)($input['nail_id'] ?? 0);
            if ($nailId <= 0) throw new Exception('パラメータが不正です。');
            $db->execute('DELETE FROM nail_likes WHERE user_id = ? AND nail_id = ?', [$currentUser['id'], $nailId]);
            $db->execute('UPDATE nails SET like_count = (SELECT COUNT(*) FROM nail_likes WHERE nail_id = ?) WHERE id = ?', [$nailId, $nailId]);
            json_response(['success' => true, 'message' => 'お気に入りから削除しました。']);
            break;

        default:
            throw new Exception('アクションが不明です。');
    }
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 400);
}
