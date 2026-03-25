<?php
require_once 'lib/auth.php';
require_once 'lib/db.php';
require_once 'lib/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

// Generate a simple form token for CSRF on this public page
if (empty($_SESSION['fp_csrf'])) {
    $_SESSION['fp_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['fp_csrf'];

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['fp_csrf']) || !hash_equals($_SESSION['fp_csrf'], $_POST['fp_csrf'])) {
        $msg = 'Invalid request. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        if ($email !== '') {
            $pdo = db();
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600);

                // Invalidate any existing unused tokens for this user
                $pdo->prepare('UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0')
                    ->execute([$user['id']]);

                $pdo->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)')
                    ->execute([$user['id'], $token, $expires]);

                $host = preg_replace('/[^a-zA-Z0-9.\-:]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
                $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $reset_link = $scheme . '://' . $host . '/reset_password.php?token=' . urlencode($token);

                $settings = get_settings();
                send_password_reset_email($email, $reset_link, $settings);
            }
        }
        // Always show the same message to prevent user enumeration
        $msg = 'success';
        // Regenerate CSRF after successful submission
        $_SESSION['fp_csrf'] = bin2hex(random_bytes(32));
        $csrf = $_SESSION['fp_csrf'];
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Forgot Password</title>
<script src="https://cdn.tailwindcss.com"></script>
</head><body class="bg-gray-50 p-6">
<div class="max-w-md mx-auto bg-white p-6 rounded shadow">
  <h3 class="text-xl font-semibold mb-4">Forgot Password</h3>
  <?php if ($msg === 'success'): ?>
    <div class="mb-4 bg-green-50 border border-green-300 text-green-700 rounded p-3">
      If that email exists, a reset link has been sent.
    </div>
    <p class="text-sm text-gray-600"><a href="/index.php" class="text-red-600 hover:underline">Back to login</a></p>
  <?php else: ?>
    <?php if (!empty($msg)): ?>
      <div class="mb-3 text-red-600"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <p class="text-sm text-gray-600 mb-4">Enter your email address and we'll send you a link to reset your password.</p>
    <form method="post" class="space-y-3">
      <input type="hidden" name="fp_csrf" value="<?= htmlspecialchars($csrf) ?>">
      <div>
        <label class="text-sm">Email address</label>
        <input class="mt-1 block w-full border rounded p-2" type="email" name="email" required autocomplete="email">
      </div>
      <div><button class="w-full bg-red-600 text-white py-2 rounded">Send Reset Link</button></div>
      <div class="text-center text-sm"><a href="/index.php" class="text-red-600 hover:underline">Back to login</a></div>
    </form>
  <?php endif; ?>
</div>
</body></html>
