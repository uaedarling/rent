<?php
require_once 'lib/auth.php';
require_once 'lib/db.php';
require_login();
$pdo = db();
$counts = [];
$counts['units']    = $pdo->query('SELECT COUNT(*) FROM units')->fetchColumn();
$counts['tenants']  = $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
$counts['payments'] = $pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn();
$month_start = date('Y-m-01');
$month_end   = date('Y-m-t');
$revenue_this_month = $pdo->prepare('SELECT COALESCE(SUM(amount_aed),0) FROM payments WHERE paid_at BETWEEN ? AND ?');
$revenue_this_month->execute([$month_start, $month_end]);
$monthly_revenue = (float)$revenue_this_month->fetchColumn();
?>
<!doctype html><html><head><meta charset='utf-8'><title>Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
</head><body class='bg-gray-50'>
<?php require 'lib/nav.php'; ?>
<div class='max-w-4xl mx-auto px-4'>
<h3 class='text-2xl font-semibold mb-6'>Dashboard</h3>
<div class='grid grid-cols-2 md:grid-cols-4 gap-4'>
  <div class='bg-white p-4 rounded shadow'>Units<br><strong class='text-2xl'><?= $counts['units'] ?></strong></div>
  <div class='bg-white p-4 rounded shadow'>Tenants<br><strong class='text-2xl'><?= $counts['tenants'] ?></strong></div>
  <div class='bg-white p-4 rounded shadow'>Payments<br><strong class='text-2xl'><?= $counts['payments'] ?></strong></div>
  <div class='bg-white p-4 rounded shadow'>Revenue This Month<br><strong class='text-2xl'>AED <?= number_format($monthly_revenue, 2) ?></strong></div>
</div>
</div>
</body></html>
