<?php
/**
 * PONNU — Mail.php
 * SMTP直接接続によるメール送信（外部ライブラリ不要）
 */
declare(strict_types=1);

class Mail
{
    /**
     * テキストメールを送信する
     */
    public static function send(string $to, string $subject, string $body): bool
    {
        $host   = defined('MAIL_SMTP_HOST')   ? MAIL_SMTP_HOST   : '';
        $port   = defined('MAIL_SMTP_PORT')   ? MAIL_SMTP_PORT   : 587;
        $user   = defined('MAIL_SMTP_USER')   ? MAIL_SMTP_USER   : '';
        $pass   = defined('MAIL_SMTP_PASS')   ? MAIL_SMTP_PASS   : '';
        $secure = defined('MAIL_SMTP_SECURE') ? MAIL_SMTP_SECURE : '';
        $from   = defined('MAIL_FROM')        ? MAIL_FROM        : 'noreply@example.com';
        $name   = defined('MAIL_FROM_NAME')   ? MAIL_FROM_NAME   : 'PONNU';

        // SMTP設定がなければ mail() にフォールバック
        if ($host === '' || $user === '') {
            return self::sendViaMail($to, $subject, $body);
        }

        try {
            return self::sendViaSmtp($host, $port, $user, $pass, $secure, $from, $name, $to, $subject, $body);
        } catch (Throwable $e) {
            error_log('[Mail] SMTP送信失敗: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * SMTP直接接続で送信
     */
    private static function sendViaSmtp(
        string $host, int $port, string $user, string $pass, string $secure,
        string $from, string $fromName, string $to, string $subject, string $body
    ): bool {
        $prefix = ($secure === 'ssl') ? 'ssl://' : '';
        $timeout = 30;

        $sock = @fsockopen($prefix . $host, $port, $errno, $errstr, $timeout);
        if (!$sock) {
            throw new RuntimeException("SMTP接続失敗: $errstr ($errno)");
        }

        stream_set_timeout($sock, $timeout);

        // 応答読み取り
        $response = self::readResponse($sock);
        if (!str_starts_with($response, '220')) {
            throw new RuntimeException("SMTP応答エラー: $response");
        }

        // EHLO
        self::smtpCommand($sock, 'EHLO ' . gethostname(), '250');

        // STARTTLS (非SSLポートの場合)
        if ($secure === 'tls' || ($secure === '' && $port === 587)) {
            self::smtpCommand($sock, 'STARTTLS', '220');
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
                throw new RuntimeException("STARTTLS失敗");
            }
            self::smtpCommand($sock, 'EHLO ' . gethostname(), '250');
        }

        // AUTH LOGIN
        self::smtpCommand($sock, 'AUTH LOGIN', '334');
        self::smtpCommand($sock, base64_encode($user), '334');
        self::smtpCommand($sock, base64_encode($pass), '235');

        // MAIL FROM
        self::smtpCommand($sock, "MAIL FROM:<$from>", '250');

        // RCPT TO
        self::smtpCommand($sock, "RCPT TO:<$to>", '250');

        // DATA
        self::smtpCommand($sock, 'DATA', '354');

        // ヘッダ + 本文
        $encodedName    = mb_encode_mimeheader($fromName, 'UTF-8', 'B');
        $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8', 'B');
        $date = date('r');

        $message  = "Date: $date\r\n";
        $message .= "From: $encodedName <$from>\r\n";
        $message .= "To: $to\r\n";
        $message .= "Subject: $encodedSubject\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "\r\n";
        $message .= chunk_split(base64_encode($body));
        $message .= "\r\n.\r\n";

        fwrite($sock, $message);
        $response = self::readResponse($sock);
        if (!str_starts_with($response, '250')) {
            throw new RuntimeException("DATA送信後エラー: $response");
        }

        // QUIT
        fwrite($sock, "QUIT\r\n");
        fclose($sock);

        return true;
    }

    /**
     * SMTPコマンド送信 + 応答確認
     */
    private static function smtpCommand($sock, string $command, string $expectedCode): string
    {
        fwrite($sock, $command . "\r\n");
        $response = self::readResponse($sock);
        if (!str_starts_with($response, $expectedCode)) {
            throw new RuntimeException("SMTP '$command' → 予期しない応答: $response");
        }
        return $response;
    }

    /**
     * 応答を読み取り（複数行対応）
     */
    private static function readResponse($sock): string
    {
        $response = '';
        while (true) {
            $line = fgets($sock, 512);
            if ($line === false) break;
            $response .= $line;
            // 4文字目が空白なら最終行
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return trim($response);
    }

    /**
     * mail() フォールバック
     */
    private static function sendViaMail(string $to, string $subject, string $body): bool
    {
        $from = defined('MAIL_FROM') ? MAIL_FROM : 'noreply@example.com';
        $name = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'PONNU';

        $encodedName    = mb_encode_mimeheader($name, 'UTF-8', 'B');
        $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8', 'B');

        $headers  = "From: {$encodedName} <{$from}>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: base64\r\n";

        return mail($to, $encodedSubject, chunk_split(base64_encode($body)), $headers);
    }

    /**
     * パスワードリセットメールを送信する
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
