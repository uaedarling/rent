<?php
require_once 'lib/db.php';
require_once 'lib/functions.php';
session_start();
if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$pdo = db();
$tenants = $pdo->query('SELECT t.*, u.unit_no, u.monthly_rent_aed FROM tenants t LEFT JOIN units u ON u.id=t.unit_id')->fetchAll(PDO::FETCH_ASSOC);
if ($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['tenant_id'])) {
  $tenant_id = intval($_POST['tenant_id']);
  $period = trim($_POST['period_ym']);
  $amount = floatval($_POST['amount_aed']);
  $paid_at = $_POST['paid_at'] ?: date('Y-m-d');
  $method = $_POST['method'];
  $stmt = $pdo->prepare('INSERT INTO payments (tenant_id, period_ym, amount_aed, paid_at, method) VALUES (?,?,?,?,?)');
  $stmt->execute([$tenant_id, $period, $amount, $paid_at, $method]);
  $payment_id = $pdo->lastInsertId();
  // send receipt email
  send_receipt_email($tenant_id, $payment_id);
  header('Location: payments.php'); exit;
}
$payments = $pdo->query('SELECT p.*, t.full_name, u.unit_no FROM payments p JOIN tenants t ON t.id=p.tenant_id JOIN units u ON u.id=t.unit_id ORDER BY p.created_at DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html><head><meta charset='utf-8'><title>Payments</title>
<script src="https://cdn.tailwindcss.com"></script>
</head><body class='bg-gray-50 p-6'>
<div class='max-w-4xl mx-auto'>
<div class='bg-white p-4 rounded shadow mb-4'>
<h3 class='text-lg font-semibold mb-2'>Record Payment</h3>
<form method='post' class='grid grid-cols-3 gap-2'>
<select name='tenant_id' class='border p-2 rounded col-span-1'><?php foreach($tenants as $t) echo "<option value='{$t['id']}'>{$t['full_name']} - {$t['unit_no']}</option>"; ?></select>
<input name='period_ym' class='border p-2 rounded col-span-1' placeholder='Period (YYYY-MM) e.g. 2025-09'>
<input name='amount_aed' class='border p-2 rounded col-span-1' placeholder='Amount AED'>
<input name='paid_at' class='border p-2 rounded' placeholder='Paid at (YYYY-MM-DD)'>
<input name='method' class='border p-2 rounded' placeholder='Method e.g. Bank transfer'>
<button class='bg-red-600 text-white px-4 py-2 rounded col-span-3'>Record Payment & Send Receipt</button>
</form>
</div>

<div class='bg-white p-4 rounded shadow'>
<table class='w-full text-sm'>
<thead class='bg-gray-100'><tr><th class='p-2'>Date</th><th class='p-2'>Tenant</th><th class='p-2'>Unit</th><th class='p-2'>Period</th><th class='p-2'>Amount</th></tr></thead>
<tbody>
<?php foreach($payments as $p): ?>
<tr class='border-t'><td class='p-2'><?=htmlspecialchars($p['paid_at'])?></td><td class='p-2'><?=htmlspecialchars($p['full_name'])?></td><td class='p-2'><?=htmlspecialchars($p['unit_no'])?></td><td class='p-2'><?=htmlspecialchars($p['period_ym'])?></td><td class='p-2'><?=number_format($p['amount_aed'],2)?></td></tr>
<?php endforeach; ?>
</tbody></table>
</div>
</div>
</body></html>