<?php
require_once 'lib/db.php';
$token = $_GET['token'] ?? '';
if (!$token) { http_response_code(400); echo 'Invalid token'; exit; }
$pdo = db();
$stmt = $pdo->prepare('SELECT t.*, u.unit_no, u.monthly_rent_aed FROM tenants t LEFT JOIN units u ON u.id=t.unit_id WHERE t.ledger_token=? LIMIT 1');
$stmt->execute([$token]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$tenant) { http_response_code(404); echo 'Ledger not found'; exit; }
$stmt = $pdo->prepare('SELECT period_ym, amount_aed, paid_at, method FROM payments WHERE tenant_id=? ORDER BY period_ym ASC');
$stmt->execute([$tenant['id']]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html><head><meta charset='utf-8'><title>Ledger</title>
<script src="https://cdn.tailwindcss.com"></script>
</head><body class='bg-gray-50 p-6'>
<div class='max-w-4xl mx-auto'>
<div class='bg-white p-4 rounded shadow'>
<h3 class='text-lg font-semibold mb-2'>Ledger for <?=htmlspecialchars($tenant['full_name'])?> (Unit <?=htmlspecialchars($tenant['unit_no'])?>)</h3>
<table class='w-full text-sm'>
<thead class='bg-gray-100'><tr><th class='p-2'>Period</th><th class='p-2'>Paid (AED)</th><th class='p-2'>Date</th><th class='p-2'>Method</th></tr></thead>
<tbody>
<?php foreach($payments as $r): ?>
<tr class='border-t'><td class='p-2'><?=htmlspecialchars($r['period_ym'])?></td><td class='p-2'><?=number_format($r['amount_aed'],2)?></td><td class='p-2'><?=htmlspecialchars($r['paid_at'])?></td><td class='p-2'><?=htmlspecialchars($r['method'])?></td></tr>
<?php endforeach; ?>
</tbody></table>
<p class='mt-4'><a class='inline-block bg-gray-200 px-3 py-2 rounded' onclick='window.print()'>Print</a></p>
</div>
</div>
</body></html>