<?php
require_once 'lib/auth.php';
require_once 'lib/db.php';
require_login();
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['unit_no'])) {
    if (!csrf_verify()) { header('Location: units.php'); exit; }
    $stmt = $pdo->prepare('INSERT INTO units (unit_no, monthly_rent_aed) VALUES (?,?)');
    $stmt->execute([$_POST['unit_no'], $_POST['monthly_rent_aed']]);
    header('Location: units.php'); exit;
}
$units = $pdo->query('SELECT * FROM units ORDER BY unit_no')->fetchAll();
$csrf  = csrf_token();
?>
<!doctype html><html><head><meta charset='utf-8'><title>Units</title>
<script src="https://cdn.tailwindcss.com"></script>
</head><body class='bg-gray-50'>
<?php require 'lib/nav.php'; ?>
<div class='max-w-4xl mx-auto px-4'>
<div class='bg-white p-4 rounded shadow mb-4'>
<h3 class='text-lg font-semibold mb-2'>Units</h3>
<form method='post' class='flex gap-2'>
  <input type='hidden' name='csrf_token' value='<?= htmlspecialchars($csrf) ?>'>
  <input name='unit_no' class='border p-2 rounded flex-1' placeholder='Unit No'>
  <input name='monthly_rent_aed' class='border p-2 rounded w-40' placeholder='Monthly Rent AED'>
  <button class='bg-red-600 text-white px-4 rounded'>Add</button>
</form>
</div>
<table class='w-full bg-white rounded shadow overflow-hidden'>
<thead class='bg-gray-100'><tr><th class='p-2 text-left'>Unit</th><th class='p-2 text-left'>Rent (AED)</th></tr></thead>
<tbody>
<?php foreach ($units as $u): ?>
<tr class='border-t'><td class='p-2'><?= htmlspecialchars($u['unit_no']) ?></td><td class='p-2'><?= number_format($u['monthly_rent_aed'], 2) ?></td></tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</body></html>
