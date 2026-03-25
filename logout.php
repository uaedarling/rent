<?php
require_once 'lib/auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
if (isset($_GET['token']) && hash_equals($_SESSION['csrf'] ?? '', $_GET['token'])) {
    session_unset();
    session_destroy();
}
header('Location: index.php');
exit;
