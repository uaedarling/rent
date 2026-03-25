<?php
require_once 'lib/auth.php';
require_once 'lib/db.php';

require_login();

$pdo  = db();
$uid  = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT id, full_name, email FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$uid]);
$user = $stmt->fetch();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid request. Please try again.';
    } else {
        $current  = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        // Re-fetch full row to verify current password
        $stmt2 = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
        $stmt2->execute([$uid]);
        $row = $stmt2->fetch();

        if (!$row || !password_verify($current, $row['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new_pass) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new_pass !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                ->execute([$hash, $uid]);
            $success = 'Password changed successfully.';
        }
    }
}

$csrf = csrf_token();
?>
<!doctype html><html><head><meta charset="utf-8"><title>My Profile</title>
<script src="https://cdn.tailwindcss.com"></script>
</head><body class="bg-gray-50">
<?php require 'lib/nav.php'; ?>
<div class="max-w-lg mx-auto px-4">
  <h2 class="text-2xl font-bold mb-6">My Profile</h2>

  <div class="bg-white rounded shadow p-6 mb-6">
    <h3 class="text-lg font-semibold mb-4">Account Details</h3>
    <div class="space-y-2 text-sm">
      <div><span class="text-gray-500 w-32 inline-block">Name:</span> <?= htmlspecialchars($user['full_name'] ?? '') ?></div>
      <div><span class="text-gray-500 w-32 inline-block">Email:</span> <?= htmlspecialchars($user['email'] ?? '') ?></div>
    </div>
  </div>

  <div class="bg-white rounded shadow p-6">
    <h3 class="text-lg font-semibold mb-4">Change Password</h3>
    <?php if (!empty($success)): ?>
      <div class="mb-4 bg-green-50 border border-green-300 text-green-700 rounded p-3"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
      <div class="mb-4 text-red-600"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" class="space-y-4">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <div>
        <label class="block text-sm font-medium mb-1">Current password</label>
        <input class="block w-full border rounded p-2" type="password" name="current_password" required autocomplete="current-password">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">New password <span class="text-gray-400 font-normal">(min 8 characters)</span></label>
        <input class="block w-full border rounded p-2" type="password" name="new_password" minlength="8" required autocomplete="new-password">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Confirm new password</label>
        <input class="block w-full border rounded p-2" type="password" name="confirm_password" minlength="8" required autocomplete="new-password">
      </div>
      <div><button class="bg-red-600 text-white px-6 py-2 rounded hover:bg-red-700">Change Password</button></div>
    </form>
  </div>
</div>
</body></html>
