<?php
// Auto-detect the base URL of the application (handles any subdirectory install).
// auth.php lives in lib/, so dirname(__DIR__) is the app root.
if (!defined('APP_BASE_URL')) {
    $__scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $__host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $__doc    = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $__app    = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
    $__path   = ($__doc !== '' && strncmp($__app, $__doc, strlen($__doc)) === 0)
        ? substr($__app, strlen($__doc))
        : '';
    define('APP_BASE_URL', $__scheme . '://' . $__host . rtrim($__path, '/') . '/');
    unset($__scheme, $__host, $__doc, $__app, $__path);
}

function require_login() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . APP_BASE_URL . 'index.php'); exit;
    }
}

function require_admin() {
    require_login();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: ' . APP_BASE_URL . 'dashboard.php?error=forbidden'); exit;
    }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_verify(): bool {
    return isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf_token']);
}
