<?php
/**
 * Emergency admin password reset tool.
 * To enable: create a file named 'admin_reset.enable' in the app root directory.
 * DELETE that file immediately after using this tool.
 */

$root = __DIR__;
$enable_file = $root . '/admin_reset.enable';

if (!file_exists($enable_file)) {
    echo '<!doctype html><html><body style="font-family:sans-serif;padding:40px;max-width:600px;margin:auto">
    <h2>Tool Disabled</h2>
    <p>This tool is disabled. Create a file named <code>admin_reset.enable</code> in the app root to enable it.</p>
    </body></html>';
    exit;
}

require_once $root . '/lib/db.php';

$error   = '';
$success = '';
$show_warning = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if ($email === '' || $password === '' || $confirm === '') {
        $error = 'All fields are required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id, role FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && $user['role'] === 'admin') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                ->execute([$hash, $user['id']]);
            $success = true;
            $show_warning = true;
        } else {
            // Do not reveal whether email exists or role mismatch
            $error = 'No admin account was found for that email, or access is not allowed.';
        }
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Emergency Admin Reset</title>
<style>
  body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 40px; }
  .card { background: #fff; max-width: 500px; margin: 0 auto; padding: 32px; border-radius: 8px;
          box-shadow: 0 2px 8px rgba(0,0,0,.15); }
  h2 { margin-top: 0; color: #b91c1c; }
  label { display: block; margin-top: 14px; font-size: 14px; font-weight: bold; }
  input[type=email], input[type=password] {
    width: 100%; padding: 8px 10px; margin-top: 4px; border: 1px solid #ccc;
    border-radius: 4px; box-sizing: border-box; font-size: 14px;
  }
  button { margin-top: 18px; background: #b91c1c; color: #fff; padding: 10px 28px;
           border: none; border-radius: 4px; font-size: 15px; cursor: pointer; }
  .error { color: #b91c1c; background: #fef2f2; border: 1px solid #fca5a5;
           padding: 10px 14px; border-radius: 4px; margin-bottom: 14px; }
  .success { color: #166534; background: #f0fdf4; border: 1px solid #86efac;
             padding: 10px 14px; border-radius: 4px; margin-bottom: 14px; }
  .warning { color: #92400e; background: #fffbeb; border: 2px solid #f59e0b;
             padding: 14px 18px; border-radius: 4px; margin-top: 18px; font-size: 14px; }
  .warning strong { display: block; margin-bottom: 6px; font-size: 15px; }
</style>
</head><body>
<div class="card">
  <h2>⚡ Emergency Admin Reset</h2>
  <p style="font-size:13px;color:#555">Use this tool only when you are completely locked out and cannot log in.</p>

  <?php if (!empty($error)): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="success">✅ Admin password has been updated successfully. <a href="/index.php">Go to login</a></div>
  <?php else: ?>
    <form method="post">
      <label>Admin email address</label>
      <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
      <label>New password <small style="font-weight:normal;color:#666">(min 8 characters)</small></label>
      <input type="password" name="password" required minlength="8">
      <label>Confirm new password</label>
      <input type="password" name="confirm" required minlength="8">
      <button type="submit">Reset Admin Password</button>
    </form>
  <?php endif; ?>

  <?php if ($show_warning): ?>
  <div class="warning">
    <strong>⚠️ IMPORTANT: Security Action Required</strong>
    Delete the file <code>admin_reset.enable</code> from your server immediately after using this tool.
    Leaving it in place allows anyone to reset the admin password without authentication.
  </div>
  <?php endif; ?>
</div>
</body></html>
