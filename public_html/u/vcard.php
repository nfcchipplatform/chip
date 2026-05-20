<?php
/**
 * PONNU — u/vcard.php
 * VCard (.vcf) ダウンロード
 * URL: /u/vcard.php?username=xxx
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

$db       = Database::getInstance();
$username = isset($_GET['username']) ? trim($_GET['username']) : '';

if ($username === '' || !preg_match('/^[a-zA-Z0-9_]{1,50}$/', $username)) {
    http_response_code(404);
    exit('Not Found');
}

$user = $db->fetchOne(
    'SELECT u.id, u.email, u.display_name, u.username
     FROM users u WHERE u.username = ? AND u.status = ? LIMIT 1',
    [$username, 'active']
);
if (!$user) {
    http_response_code(404);
    exit('Not Found');
}

$profile = $db->fetchOne(
    'SELECT bio, avatar_url, twitter_handle, instagram_handle, website_url, title, is_public
     FROM profiles WHERE user_id = ? LIMIT 1',
    [$user['id']]
);
if (!$profile || !$profile['is_public']) {
    http_response_code(404);
    exit('Not Found');
}

// VCard 3.0 構築
$displayName = $user['display_name'] ?: $user['username'];
$profileUrl  = APP_URL . '/u/?username=' . urlencode($user['username']);

$lines = [
    'BEGIN:VCARD',
    'VERSION:3.0',
    'FN:' . vcardEscape($displayName),
    'N:;' . vcardEscape($displayName) . ';;;',
];

if (!empty($profile['title'])) {
    $lines[] = 'TITLE:' . vcardEscape($profile['title']);
}
if (!empty($user['email'])) {
    $lines[] = 'EMAIL;TYPE=INTERNET:' . vcardEscape($user['email']);
}
if (!empty($profile['website_url'])) {
    $lines[] = 'URL:' . vcardEscape($profile['website_url']);
}
$lines[] = 'URL;TYPE=PROFILE:' . vcardEscape($profileUrl);

if (!empty($profile['twitter_handle'])) {
    $lines[] = 'X-SOCIALPROFILE;TYPE=twitter:https://twitter.com/' . vcardEscape($profile['twitter_handle']);
}
if (!empty($profile['instagram_handle'])) {
    $lines[] = 'X-SOCIALPROFILE;TYPE=instagram:https://instagram.com/' . vcardEscape($profile['instagram_handle']);
}
if (!empty($profile['bio'])) {
    $lines[] = 'NOTE:' . vcardEscape($profile['bio']);
}

$lines[] = 'END:VCARD';

$vcard = implode("\r\n", $lines) . "\r\n";

// ファイル名（日本語を避ける）
$filename = $user['username'] . '.vcf';

header('Content-Type: text/vcard; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($vcard));
header('Cache-Control: no-cache, no-store');

echo $vcard;
exit;

/**
 * VCard用エスケープ（改行・カンマ・セミコロン・バックスラッシュ）
 */
function vcardEscape(string $text): string
{
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace(',', '\\,', $text);
    $text = str_replace(';', '\\;', $text);
    $text = str_replace("\n", '\\n', $text);
    $text = str_replace("\r", '', $text);
    return $text;
}
