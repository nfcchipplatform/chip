<?php
/**
 * PONNU — views/partials/alert.php
 * 汎用アラートパーシャル
 *
 * 使用方法:
 *   $alertType = 'success'; // 'success' | 'error' | 'info'
 *   $alertMsg  = 'メッセージ';
 *   require APP_ROOT . '/views/partials/alert.php';
 */

declare(strict_types=1);

if (empty($alertMsg)) {
    return;
}

$alertClass = match ($alertType ?? 'info') {
    'success' => 'alert alert-success',
    'error'   => 'alert alert-error',
    default   => 'alert alert-info',
};
?>
<div class="<?= $alertClass ?>" role="alert">
    <?= e($alertMsg) ?>
</div>
