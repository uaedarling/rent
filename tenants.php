<?php
require_once 'lib/db.php';
session_start();
if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$pdo = db();
if ($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['full_name'])) {
  $token = bin2hex(random_bytes(16));
  $stmt = $pdo->prepare('INSERT INTO tenants (full_name,email,phone,unit_id,due_day,grace_days,ledger_token) VALUES (?,?,?,?,?,?,?)');
  $stmt->execute([$_POST['full_name'], $_POST['email'], $_POST['phone'], $_POST['unit_id'], $_POST['due_day'], $_POST['grace_days'], $token]);
  header('Location: tenants.php'); exit;
}
$units = $pdo->query('SELECT * FROM units')->fetchAll(PDO::FETCH_ASSOC);
$tenants = $pdo->query('SELECT t.*, u.unit_no FROM tenants t LEFT JOIN units u ON u.id=t.unit_id ORDER BY t.full_name')->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html><head><meta charset='utf-8'><title>Tenants</title>
<script src="https://cdn.tailwindcss.com"></script>
</head><body class='bg-gray-50 p-6'>
<div class='max-w-4xl mx-auto'>
<div class='bg-white p-4 rounded shadow mb-4'>
<h3 class='text-lg font-semibold mb-2'>Add Tenant</h3>
<form method='post' class='grid grid-cols-2 gap-2'>
<input name='full_name' class='border p-2 rounded' placeholder='Full name'>
<input name='email' class='border p-2 rounded' placeholder='Email'>
<input name='phone' class='border p-2 rounded' placeholder='Phone (E.164 e.g. 9715...)'>
<select name='unit_id' class='border p-2 rounded'>
  <option value=''>-- Unit --</option>
  <?php foreach($units as $u) echo "<option value='{$u['id']}'>{$u['unit_no']}</option>"; ?>
</select>
<input name='due_day' class='border p-2 rounded' placeholder='Due day (1-28)' value='1'>
<input name='grace_days' class='border p-2 rounded' placeholder='Grace days' value='3'>
<button class='bg-red-600 text-white px-4 py-2 rounded col-span-2'>Add tenant</button>
</form>
</div>

<div class='bg-white p-4 rounded shadow'>
<table class='w-full text-sm'>
<thead class='bg-gray-100'><tr><th class='p-2 text-left'>Name</th><th class='p-2 text-left'>Unit</th><th class='p-2 text-left'>Phone</th><th class='p-2 text-left'>Ledger Link</th></tr></thead>
<tbody>
<?php foreach($tenants as $t): ?>
<tr class='border-t'><td class='p-2'><?=htmlspecialchars($t['full_name'])?></td><td class='p-2'><?=htmlspecialchars($t['unit_no'])?></td><td class='p-2'><?=htmlspecialchars($t['phone'])?></td>
<td class='p-2'><?php if($t['ledger_token']): $link=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!='off'?'https':'http').'://'.$_SERVER['HTTP_HOST'].'/ledger.php?token='.$t['ledger_token']; echo "<a class='text-blue-600 underline' href='$link' target='_blank'>View</a>"; endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table>
</div>
</div>
</body></html>