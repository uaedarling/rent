<?php
require_once '../lib/auth.php';
require_once '../lib/db.php';
require_admin();
$pdo = db();
$errors = [];
$success = '';

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!csrf_verify()) {
        $errors[] = 'Invalid request.';
    } elseif ((int)$_POST['delete_id'] === (int)$_SESSION['user_id']) {
        $errors[] = 'You cannot delete your own account.';
    } else {
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([(int)$_POST['delete_id']]);
        $success = 'User deleted.';
    }
}

// Handle add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['new_email'])) {
    if (!csrf_verify()) {
        $errors[] = 'Invalid request.';
    } else {
        $new_name  = trim($_POST['new_full_name'] ?? '');
        $new_email = trim($_POST['new_email']     ?? '');
        $new_pass  = $_POST['new_password']       ?? '';
        $new_role  = in_array($_POST['new_role'] ?? '', ['admin', 'manager']) ? $_POST['new_role'] : 'manager';

        if ($new_name === '')                                 $errors[] = 'Full name is required.';
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL))  $errors[] = 'Valid email is required.';
        if (strlen($new_pass) < 8)                           $errors[] = 'Password must be at least 8 characters.';

        if (empty($errors)) {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            try {
                $pdo->prepare('INSERT INTO users (full_name, email, password, role) VALUES (?,?,?,?)')->execute([$new_name, $new_email, $hash, $new_role]);
                $success = 'User added successfully.';
            } catch (PDOException $e) {
                $errors[] = 'Email already exists.';
            }
        }
    }
}

$users = $pdo->query('SELECT id, full_name, email, role, created_at FROM users ORDER BY created_at DESC')->fetchAll();
$csrf  = csrf_token();
?>
<!doctype html><html><head><meta charset='utf-8'><title>User Management</title>
<script src="https://cdn.tailwindcss.com"></script>
</head><body class='bg-gray-50'>
<?php require '../lib/nav.php'; ?>
<div class='max-w-4xl mx-auto px-4'>

<?php if (!empty($success)): ?>
<div class='mb-4 bg-green-50 border border-green-300 text-green-700 rounded p-3 text-sm'><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
<div class='mb-4 bg-red-50 border border-red-300 text-red-700 rounded p-3 text-sm'>
  <ul class='list-disc list-inside'><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<!-- Add user form -->
<div class='bg-white p-4 rounded shadow mb-4'>
  <h3 class='text-lg font-semibold mb-2'>Add User</h3>
  <form method='post' class='grid grid-cols-2 gap-2'>
    <input type='hidden' name='csrf_token' value='<?= htmlspecialchars($csrf) ?>'>
    <input name='new_full_name' class='border p-2 rounded' placeholder='Full name' required>
    <input name='new_email' type='email' class='border p-2 rounded' placeholder='Email' required>
    <input name='new_password' type='password' class='border p-2 rounded' placeholder='Password (min 8 chars)' required>
    <select name='new_role' class='border p-2 rounded'>
      <option value='manager'>Manager</option>
      <option value='admin'>Admin</option>
    </select>
    <button class='bg-red-600 text-white px-4 py-2 rounded col-span-2'>Add User</button>
  </form>
</div>

<!-- Users table -->
<div class='bg-white p-4 rounded shadow'>
  <h3 class='text-lg font-semibold mb-2'>Users</h3>
  <table class='w-full text-sm'>
    <thead class='bg-gray-100'><tr><th class='p-2 text-left'>Name</th><th class='p-2 text-left'>Email</th><th class='p-2 text-left'>Role</th><th class='p-2 text-left'>Created</th><th class='p-2'></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
    <tr class='border-t'>
      <td class='p-2'><?= htmlspecialchars($u['full_name']) ?></td>
      <td class='p-2'><?= htmlspecialchars($u['email']) ?></td>
      <td class='p-2 capitalize'><?= htmlspecialchars($u['role']) ?></td>
      <td class='p-2'><?= htmlspecialchars($u['created_at']) ?></td>
      <td class='p-2'>
        <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
        <form method='post' onsubmit="return confirm('Delete this user?')">
          <input type='hidden' name='csrf_token' value='<?= htmlspecialchars($csrf) ?>'>
          <input type='hidden' name='delete_id' value='<?= intval($u['id']) ?>'>
          <button class='text-red-600 hover:underline text-xs'>Delete</button>
        </form>
        <?php else: ?>
        <span class='text-gray-400 text-xs'>(you)</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
</div>
</body></html>
