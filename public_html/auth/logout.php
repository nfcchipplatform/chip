<?php
/**
 * PONNU — auth/logout.php
 * POST + CSRF でセッション破棄
 */
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/');
}

Csrf::verify();
Auth::logout();
flash_set('success', 'ログアウトしました。');
redirect('/auth/login.php');
