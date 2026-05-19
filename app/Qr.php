<?php
/**
 * PONNU — Qr.php
 * QRコード生成ヘルパー (phpqrcode ラッパー)
 */
declare(strict_types=1);

class Qr
{
    /**
     * 指定文字列のQRコードPNGバイナリを返す
     */
    public static function png(string $text, int $size = 256): string
    {
        require_once __DIR__ . '/lib/phpqrcode/qrlib.php';
        $pixelPerCell = max(2, intdiv($size, 40));
        ob_start();
        QRcode::png($text, null, 'L', $pixelPerCell, 2);
        return (string)ob_get_clean();
    }

    /**
     * 指定文字列の data URI 形式 (data:image/png;base64,...) を返す
     */
    public static function dataUri(string $text, int $size = 256): string
    {
        return 'data:image/png;base64,' . base64_encode(self::png($text, $size));
    }
}
