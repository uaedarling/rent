<?php
require_once 'lib/auth.php';
require_once 'lib/db.php';
require_login();

$pdo = db();
$s   = get_settings();
$tz  = $s['timezone'] ?? 'Asia/Dubai';
date_default_timezone_set($tz);

// Determine the three periods
$periods = [
    'last'  => date('Y-m', strtotime('-1 month')),
    'this'  => date('Y-m'),
    'next'  => date('Y-m', strtotime('+1 month')),
];

$activeTab = $_GET['tab'] ?? 'this';
if (!array_key_exists($activeTab, $periods)) {
    $activeTab = 'this';
}

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $period = $periods[$activeTab];
    $rows = getPendingRows($pdo, $period);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="pending_' . $period . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Tenant Name', 'Unit', 'Monthly Rent (AED)', 'Paid (AED)', 'Balance Due (AED)', 'Due Day', 'Status']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['full_name'],
            $r['unit_no'],
            number_format($r['monthly_rent_aed'], 2),
            number_format($r['paid'], 2),
            number_format($r['balance'], 2),
            $r['due_day'],
            $r['status'],
        ]);
    }
    fclose($out);
    exit;
}

function getPendingRows(PDO $pdo, string $period): array {
    $stmt = $pdo->prepare(
        'SELECT t.id, t.full_name, t.due_day, u.unit_no, u.monthly_rent_aed,
                COALESCE(SUM(p.amount_aed), 0) AS paid
         FROM tenants t
         LEFT JOIN units u ON u.id = t.unit_id
         LEFT JOIN payments p ON p.tenant_id = t.id AND p.period_ym = ?
         WHERE u.status = \'active\'
         GROUP BY t.id'
    );
    $stmt->execute([$period]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['balance'] = max(0, (float)$r['monthly_rent_aed'] - (float)$r['paid']);
        $paid   = (float)$r['paid'];
        $rent   = (float)$r['monthly_rent_aed'];
        if ($paid <= 0) {
            $r['status'] = 'UNPAID';
        } elseif ($paid < $rent) {
            $r['status'] = 'PARTIAL';
        } else {
            $r['status'] = 'PAID';
        }
    }
    unset($r);

    return $rows;
}

$currentRows = getPendingRows($pdo, $periods[$activeTab]);

$totalExpected  = array_sum(array_column($currentRows, 'monthly_rent_aed'));
$totalCollected = array_sum(array_column($currentRows, 'paid'));
$outstanding    = array_sum(array_column($currentRows, 'balance'));

$tabLabels = [
    'last' => 'Last Month (' . $periods['last'] . ')',
    'this' => 'This Month (' . $periods['this'] . ')',
    'next' => 'Next Month (' . $periods['next'] . ')',
];

$csrf = csrf_token();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Pending Payments</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
<?php require 'lib/nav.php'; ?>

<div class="max-w-6xl mx-auto px-4 py-6">
  <div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-semibold">Pending Payments</h2>
    <a href="pending.php?tab=<?= htmlspecialchars($activeTab) ?>&export=csv"
       class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 text-sm">
      ⬇ Export CSV
    </a>
  </div>

  <!-- Summary cards -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white p-4 rounded shadow text-center">
      <div class="text-sm text-gray-500">Total Expected</div>
      <div class="text-2xl font-bold text-gray-800">AED <?= number_format($totalExpected, 2) ?></div>
    </div>
    <div class="bg-white p-4 rounded shadow text-center">
      <div class="text-sm text-gray-500">Total Collected</div>
      <div class="text-2xl font-bold text-green-600">AED <?= number_format($totalCollected, 2) ?></div>
    </div>
    <div class="bg-white p-4 rounded shadow text-center">
      <div class="text-sm text-gray-500">Outstanding</div>
      <div class="text-2xl font-bold text-red-600">AED <?= number_format($outstanding, 2) ?></div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="flex gap-2 mb-4">
    <?php foreach ($tabLabels as $key => $label): ?>
    <a href="pending.php?tab=<?= $key ?>"
       class="px-4 py-2 rounded text-sm font-medium <?= $activeTab === $key
         ? 'bg-red-700 text-white'
         : 'bg-white text-gray-700 hover:bg-gray-100 border' ?>">
      <?= htmlspecialchars($label) ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Table -->
  <div class="bg-white rounded shadow overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-gray-100">
        <tr>
          <th class="p-3 text-left">Tenant Name</th>
          <th class="p-3 text-left">Unit</th>
          <th class="p-3 text-right">Monthly Rent (AED)</th>
          <th class="p-3 text-right">Paid (AED)</th>
          <th class="p-3 text-right">Balance Due (AED)</th>
          <th class="p-3 text-center">Due Day</th>
          <th class="p-3 text-center">Status</th>
          <th class="p-3 text-center">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($currentRows)): ?>
        <tr><td colspan="8" class="p-4 text-center text-gray-500">No active tenants found.</td></tr>
        <?php endif; ?>
        <?php foreach ($currentRows as $r):
          $badgeClass = match($r['status']) {
              'PAID'    => 'bg-green-100 text-green-800',
              'PARTIAL' => 'bg-yellow-100 text-yellow-800',
              default   => 'bg-red-100 text-red-800',
          };
        ?>
        <tr class="border-t hover:bg-gray-50">
          <td class="p-3"><?= htmlspecialchars($r['full_name']) ?></td>
          <td class="p-3"><?= htmlspecialchars($r['unit_no'] ?? '—') ?></td>
          <td class="p-3 text-right"><?= number_format((float)$r['monthly_rent_aed'], 2) ?></td>
          <td class="p-3 text-right"><?= number_format((float)$r['paid'], 2) ?></td>
          <td class="p-3 text-right"><?= number_format($r['balance'], 2) ?></td>
          <td class="p-3 text-center"><?= intval($r['due_day']) ?></td>
          <td class="p-3 text-center">
            <span class="px-2 py-1 rounded text-xs font-semibold <?= $badgeClass ?>">
              <?= htmlspecialchars($r['status']) ?>
            </span>
          </td>
          <td class="p-3 text-center">
            <?php if ($r['status'] !== 'PAID'): ?>
            <a href="payments.php?tenant_id=<?= intval($r['id']) ?>&period=<?= htmlspecialchars($periods[$activeTab]) ?>"
               class="bg-red-600 text-white text-xs px-3 py-1 rounded hover:bg-red-700">
              Record Payment
            </a>
            <?php else: ?>
            <span class="text-gray-400 text-xs">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
