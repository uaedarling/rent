<?php
require_once 'lib/auth.php';
require_once 'lib/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

$token = trim($_GET['token'] ?? '');
$pdo   = db();
$error = '';
$success = false;

function get_valid_reset(PDO $pdo, string $token): ?array {
    if ($token === '') return null;
    $stmt = $pdo->prepare(
        'SELECT pr.*, u.id AS uid FROM password_resets pr
         JOIN users u ON u.id = pr.user_id
         WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!isset($_POST['rp_csrf']) || empty($_SESSION['rp_csrf'])
        || !hash_equals($_SESSION['rp_csrf'], $_POST['rp_csrf'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $post_token = trim($_POST['token'] ?? '');
        $reset = get_valid_reset($pdo, $post_token);
        if (!$reset) {
            $error = 'This reset link is invalid or has expired. Please request a new one.';
        } else {
            $pass  = $_POST['password'] ?? '';
            $pass2 = $_POST['password2'] ?? '';
            if (strlen($pass) < 8) {
                $error = 'Password must be at least 8 characters.';
            } elseif ($pass !== $pass2) {
                $error = 'Passwords do not match.';
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                    ->execute([$hash, $reset['uid']]);
                $pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = ?')
                    ->execute([$post_token]);
                unset($_SESSION['rp_csrf']);
                header('Location: ' . APP_BASE_URL . 'index.php?msg=password_reset');
                exit;
            }
            // Keep token in scope for form re-render on error
            $token = $post_token;
        }
    }
    if ($error) {
        $token = trim($_POST['token'] ?? $token);
    }
}

// Validate token for GET display
$reset = null;
if ($token !== '') {
    $reset = get_valid_reset($pdo, $token);
}

// Generate form CSRF for the reset form
if ($reset && empty($_SESSION['rp_csrf'])) {
    $_SESSION['rp_csrf'] = bin2hex(random_bytes(32));
}
$rp_csrf = $_SESSION['rp_csrf'] ?? '';
?>
<!doctype html><html><head><meta charset="utf-8"><title>Reset Password</title>
<script src="https://cdn.tailwindcss.com"></script>
</head><body class="bg-gray-50 p-6">
<div class="max-w-md mx-auto bg-white p-6 rounded shadow">
  <h3 class="text-xl font-semibold mb-4">Reset Password</h3>
  <?php if (!empty($error)): ?>
    <div class="mb-3 text-red-600"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if (!$reset && empty($error)): ?>
    <div class="mb-4 text-red-600">This reset link is invalid or has expired.</div>
    <p class="text-sm"><a href="forgot_password.php" class="text-red-600 hover:underline">Request a new reset link</a></p>
  <?php elseif (!$reset && !empty($error)): ?>
    <p class="text-sm"><a href="forgot_password.php" class="text-red-600 hover:underline">Request a new reset link</a></p>
  <?php else: ?>
    <p class="text-sm text-gray-600 mb-4">Enter your new password below.</p>
    <form method="post" class="space-y-3">
      <input type="hidden" name="rp_csrf" value="<?= htmlspecialchars($rp_csrf) ?>">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <div>
        <label class="text-sm">New password <span class="text-gray-400">(min 8 characters)</span></label>
        <input class="mt-1 block w-full border rounded p-2" type="password" name="password" minlength="8" required autocomplete="new-password">
      </div>
      <div>
        <label class="text-sm">Confirm new password</label>
        <input class="mt-1 block w-full border rounded p-2" type="password" name="password2" minlength="8" required autocomplete="new-password">
      </div>
      <div><button class="w-full bg-red-600 text-white py-2 rounded">Set New Password</button></div>
    </form>
  <?php endif; ?>
</div>
</body></html>
