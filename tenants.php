<?php
require_once 'lib/auth.php';
require_once 'lib/db.php';
require_login();
$pdo = db();
$errors = [];
$form = ['full_name' => '', 'email' => '', 'phone' => '', 'unit_id' => '', 'due_day' => '1', 'grace_days' => '3'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $form['full_name']  = trim($_POST['full_name']  ?? '');
        $form['email']      = trim($_POST['email']      ?? '');
        $form['phone']      = trim($_POST['phone']      ?? '');
        $form['unit_id']    = trim($_POST['unit_id']    ?? '');
        $form['due_day']    = trim($_POST['due_day']    ?? '');
        $form['grace_days'] = trim($_POST['grace_days'] ?? '');

        if ($form['full_name'] === '') $errors[] = 'Full name is required.';
        if ($form['email'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
        if ($form['phone'] === '') $errors[] = 'Phone is required.';
        if (!ctype_digit($form['unit_id']) || (int)$form['unit_id'] <= 0) $errors[] = 'Please select a valid unit.';
        if (!ctype_digit($form['due_day']) || (int)$form['due_day'] < 1 || (int)$form['due_day'] > 28) $errors[] = 'Due day must be between 1 and 28.';
        if (!ctype_digit($form['grace_days']) || (int)$form['grace_days'] < 0) $errors[] = 'Grace days must be 0 or more.';

        if (empty($errors)) {
            $token = bin2hex(random_bytes(16));
            $stmt = $pdo->prepare('INSERT INTO tenants (full_name, email, phone, unit_id, due_day, grace_days, ledger_token) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$form['full_name'], $form['email'], $form['phone'], (int)$form['unit_id'], (int)$form['due_day'], (int)$form['grace_days'], $token]);
            header('Location: tenants.php'); exit;
        }
    }
}

$units   = $pdo->query('SELECT * FROM units')->fetchAll();
$tenants = $pdo->query('SELECT t.*, u.unit_no FROM tenants t LEFT JOIN units u ON u.id=t.unit_id ORDER BY t.full_name')->fetchAll();
$csrf    = csrf_token();
?>
<!doctype html><html><head><meta charset='utf-8'><title>Tenants</title>
<script src="https://cdn.tailwindcss.com"></script>
</head><body class='bg-gray-50'>
<?php require 'lib/nav.php'; ?>
<div class='max-w-4xl mx-auto px-4'>
<div class='bg-white p-4 rounded shadow mb-4'>
<h3 class='text-lg font-semibold mb-2'>Add Tenant</h3>
<?php if (!empty($errors)): ?>
<div class='mb-3 bg-red-50 border border-red-300 text-red-700 rounded p-3 text-sm'>
  <ul class='list-disc list-inside'>
    <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>
<form method='post' class='grid grid-cols-2 gap-2'>
  <input type='hidden' name='csrf_token' value='<?= htmlspecialchars($csrf) ?>'>
  <input name='full_name' class='border p-2 rounded' placeholder='Full name' value='<?= htmlspecialchars($form['full_name']) ?>'>
  <input name='email' class='border p-2 rounded' placeholder='Email' type='email' value='<?= htmlspecialchars($form['email']) ?>'>
  <input name='phone' class='border p-2 rounded' placeholder='Phone (E.164 e.g. 9715...)' value='<?= htmlspecialchars($form['phone']) ?>'>
  <select name='unit_id' class='border p-2 rounded'>
    <option value=''>-- Unit --</option>
    <?php foreach ($units as $u): ?>
    <option value='<?= $u['id'] ?>' <?= $form['unit_id'] == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['unit_no']) ?></option>
    <?php endforeach; ?>
  </select>
  <input name='due_day' class='border p-2 rounded' placeholder='Due day (1-28)' value='<?= htmlspecialchars($form['due_day']) ?>'>
  <input name='grace_days' class='border p-2 rounded' placeholder='Grace days' value='<?= htmlspecialchars($form['grace_days']) ?>'>
  <button class='bg-red-600 text-white px-4 py-2 rounded col-span-2'>Add tenant</button>
</form>
</div>

<div class='bg-white p-4 rounded shadow'>
<table class='w-full text-sm'>
<thead class='bg-gray-100'><tr><th class='p-2 text-left'>Name</th><th class='p-2 text-left'>Unit</th><th class='p-2 text-left'>Phone</th><th class='p-2 text-left'>Ledger Link</th></tr></thead>
<tbody>
<?php foreach ($tenants as $t): ?>
<tr class='border-t'>
  <td class='p-2'><?= htmlspecialchars($t['full_name']) ?></td>
  <td class='p-2'><?= htmlspecialchars($t['unit_no'] ?? '') ?></td>
  <td class='p-2'><?= htmlspecialchars($t['phone']) ?></td>
  <td class='p-2'><?php if ($t['ledger_token']):
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = preg_replace('/[^a-zA-Z0-9.\-:]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $link = $scheme . '://' . $host . '/ledger.php?token=' . rawurlencode($t['ledger_token']);
    echo "<a class='text-blue-600 underline' href='" . htmlspecialchars($link) . "' target='_blank'>View</a>";
  endif; ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
</div>
</body></html>
