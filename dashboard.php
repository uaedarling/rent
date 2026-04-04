<?php
require_once 'lib/auth.php';
require_once 'lib/db.php';
require_login();

$pdo = db();
$s   = get_settings();
$tz  = $s['timezone'] ?? 'Asia/Dubai';
date_default_timezone_set($tz);

$counts = [];
$counts['units']    = $pdo->query('SELECT COUNT(*) FROM units')->fetchColumn();
$counts['tenants']  = $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
$counts['payments'] = $pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn();

$month_start = date('Y-m-01');
$month_end   = date('Y-m-t');
$revenue_this_month = $pdo->prepare('SELECT COALESCE(SUM(amount_aed),0) FROM payments WHERE paid_at BETWEEN ? AND ?');
$revenue_this_month->execute([$month_start, $month_end]);
$monthly_revenue = (float)$revenue_this_month->fetchColumn();

// Count tenants who haven't fully paid this month
$this_period = date('Y-m');
$pending_stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM tenants t
     LEFT JOIN units u ON u.id = t.unit_id
     LEFT JOIN (
         SELECT tenant_id, SUM(amount_aed) AS paid
         FROM payments WHERE period_ym = ?
         GROUP BY tenant_id
     ) p ON p.tenant_id = t.id
     WHERE u.status = \'active\'
     AND COALESCE(p.paid, 0) < COALESCE(u.monthly_rent_aed, 0)'
);
$pending_stmt->execute([$this_period]);
$pending_count = (int)$pending_stmt->fetchColumn();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
<?php require 'lib/nav.php'; ?>
<div class="max-w-4xl mx-auto px-4">
  <h3 class="text-2xl font-semibold mb-6">Dashboard</h3>

  <!-- Stats -->
  <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
    <div class="bg-white p-4 rounded shadow text-center">
      <div class="text-sm text-gray-500">Units</div>
      <div class="text-2xl font-bold"><?= $counts['units'] ?></div>
    </div>
    <div class="bg-white p-4 rounded shadow text-center">
      <div class="text-sm text-gray-500">Tenants</div>
      <div class="text-2xl font-bold"><?= $counts['tenants'] ?></div>
    </div>
    <div class="bg-white p-4 rounded shadow text-center">
      <div class="text-sm text-gray-500">Payments</div>
      <div class="text-2xl font-bold"><?= $counts['payments'] ?></div>
    </div>
    <div class="bg-white p-4 rounded shadow text-center">
      <div class="text-sm text-gray-500">Revenue This Month</div>
      <div class="text-xl font-bold text-green-600">AED <?= number_format($monthly_revenue, 2) ?></div>
    </div>
    <div class="bg-white p-4 rounded shadow text-center <?= $pending_count > 0 ? 'border-l-4 border-red-500' : '' ?>">
      <div class="text-sm text-gray-500">Pending This Month</div>
      <div class="text-2xl font-bold <?= $pending_count > 0 ? 'text-red-600' : 'text-green-600' ?>">
        <?= $pending_count ?>
      </div>
      <a href="<?= htmlspecialchars(APP_BASE_URL) ?>pending.php" class="text-xs text-blue-600 hover:underline">View Pending →</a>
    </div>
  </div>

  <!-- Quick links -->
  <div class="bg-white p-4 rounded shadow">
    <h4 class="text-sm font-medium text-gray-500 mb-3">Quick Links</h4>
    <div class="flex flex-wrap gap-3">
      <?php $b = htmlspecialchars(APP_BASE_URL); ?>
      <a href="<?= $b ?>units.php"    class="bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded text-sm">Units</a>
      <a href="<?= $b ?>tenants.php"  class="bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded text-sm">Tenants</a>
      <a href="<?= $b ?>payments.php" class="bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded text-sm">Payments</a>
      <a href="<?= $b ?>pending.php"  class="bg-red-100 hover:bg-red-200 text-red-800 px-4 py-2 rounded text-sm">Pending</a>
      <a href="<?= $b ?>import.php"   class="bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded text-sm">Import</a>
      <a href="<?= $b ?>settings.php" class="bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded text-sm">Settings</a>
    </div>
  </div>
</div>
</body>
</html>
