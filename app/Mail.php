<?php
/**
 * PONNU — Mail.php
 * PHP mail() ラッパー。From/Reply-To ヘッダを適切に設定する。
 */

declare(strict_types=1);

class Mail
{
    /**
     * テキストメールを送信する
     *
     * @param  string $to      宛先メールアドレス
     * @param  string $subject 件名
     * @param  string $body    本文（プレーンテキスト）
     * @return bool
     */
    public static function send(string $to, string $subject, string $body): bool
    {
        $fromAddr = defined('MAIL_FROM')      ? MAIL_FROM      : 'noreply@example.com';
        $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'PONNU';
        $replyTo  = defined('MAIL_REPLY_TO')  ? MAIL_REPLY_TO  : $fromAddr;

        $encodedName    = mb_encode_mimeheader($fromName, 'UTF-8', 'B');
        $encodedSubject = mb_encode_mimeheader($subject,  'UTF-8', 'B');

        $headers  = "From: {$encodedName} <{$fromAddr}>\r\n";
        $headers .= "Reply-To: {$replyTo}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: base64\r\n";
        $headers .= "X-Mailer: PHP/" . PHP_VERSION . "\r\n";

        $encodedBody = chunk_split(base64_encode($body));

        return mail($to, $encodedSubject, $encodedBody, $headers);
    }

    /**
     * パスワードリセットメールを送信する
     *
     * @param  string $to       宛先
     * @param  string $token    リセットトークン(生値)
     * @return bool
     */
    public static function sendPasswordReset(string $to, string $token): bool
    {
        $appUrl  = defined('APP_URL')  ? APP_URL  : '';
        $appName = defined('APP_NAME') ? APP_NAME : 'PONNU';

        $resetUrl = $appUrl . '/auth/reset.php?token=' . urlencode($token);

        $subject = '[' . $appName . '] パスワードリセットのご案内';
        $body    = <<<TEXT
{$appName} をご利用いただきありがとうございます。

以下のURLからパスワードをリセットしてください。
このリンクは1時間で無効になります。

{$resetUrl}

このメールに心当たりがない場合は、無視してください。
パスワードは変更されません。

---
{$appName} サポート
TEXT;

        return self::send($to, $subject, $body);
    }
}
