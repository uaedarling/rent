<?php
require_once 'lib/auth.php';
require_once 'lib/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

$pdo     = db();
$s       = get_settings();
$tz      = $s['timezone'] ?? 'Asia/Dubai';
date_default_timezone_set($tz);

$token      = trim($_GET['token'] ?? '');
$loggedIn   = !empty($_SESSION['user_id']);
$tenant     = null;
$tenants    = [];
$showPicker = false;

if ($token !== '') {
    // Token provided — look up tenant
    $stmt = $pdo->prepare(
        'SELECT t.*, u.unit_no, u.monthly_rent_aed
         FROM tenants t
         LEFT JOIN units u ON u.id = t.unit_id
         WHERE t.ledger_token = ? LIMIT 1'
    );
    $stmt->execute([$token]);
    $tenant = $stmt->fetch();
    if (!$tenant) {
        http_response_code(404);
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Not Found</title>'
            . '<script src="https://cdn.tailwindcss.com"></script></head>'
            . '<body class="bg-gray-50 flex items-center justify-center min-h-screen">'
            . '<div class="text-center"><h1 class="text-4xl font-bold text-gray-700 mb-2">404</h1>'
            . '<p class="text-gray-500">Ledger not found.</p></div></body></html>';
        exit;
    }
} elseif ($loggedIn) {
    // No token, logged in — show tenant picker or display selected tenant
    $selected_id = intval($_GET['tenant_id'] ?? 0);
    $tenants = $pdo->query(
        'SELECT t.id, t.full_name, u.unit_no
         FROM tenants t
         LEFT JOIN units u ON u.id = t.unit_id
         ORDER BY u.unit_no, t.full_name'
    )->fetchAll();
    if ($selected_id) {
        $stmt = $pdo->prepare(
            'SELECT t.*, u.unit_no, u.monthly_rent_aed
             FROM tenants t
             LEFT JOIN units u ON u.id = t.unit_id
             WHERE t.id = ? LIMIT 1'
        );
        $stmt->execute([$selected_id]);
        $tenant = $stmt->fetch();
    } else {
        $showPicker = true;
    }
} else {
    // No token, not logged in — 404
    http_response_code(404);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Not Found</title>'
        . '<script src="https://cdn.tailwindcss.com"></script></head>'
        . '<body class="bg-gray-50 flex items-center justify-center min-h-screen">'
        . '<div class="text-center"><h1 class="text-4xl font-bold text-gray-700 mb-2">404</h1>'
        . '<p class="text-gray-500">Ledger not found. Please use your personal ledger link.</p>'
        . '<p class="mt-2"><a href="/index.php" class="text-blue-600 hover:underline">Login</a></p>'
        . '</div></body></html>';
    exit;
}

// Load payments for tenant
$payments      = [];
$totalPaid     = 0;
$yearTotalPaid = 0;
$paidThisMonth = 0;
if ($tenant) {
    $stmt = $pdo->prepare(
        'SELECT p.id, p.period_ym, p.amount_aed, p.paid_at, p.method
         FROM payments p
         WHERE p.tenant_id = ?
         ORDER BY p.period_ym ASC'
    );
    $stmt->execute([$tenant['id']]);
    $payments  = $stmt->fetchAll();
    $totalPaid = array_sum(array_column($payments, 'amount_aed'));

    // Year and month totals calculated in a single SQL query
    $sumStmt = $pdo->prepare(
        'SELECT
           COALESCE(SUM(CASE WHEN LEFT(period_ym,4) = ? THEN amount_aed ELSE 0 END), 0) AS year_total,
           COALESCE(SUM(CASE WHEN period_ym = ? THEN amount_aed ELSE 0 END), 0) AS month_total
         FROM payments WHERE tenant_id = ?'
    );
    $sumStmt->execute([date('Y'), date('Y-m'), $tenant['id']]);
    $sums          = $sumStmt->fetch();
    $yearTotalPaid = (float)$sums['year_total'];
    $paidThisMonth = (float)$sums['month_total'];
}
$monthlyRent = (float)($tenant['monthly_rent_aed'] ?? 0);
$outstanding = max(0, $monthlyRent - $paidThisMonth);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Ledger<?= $tenant ? ' — ' . htmlspecialchars($tenant['full_name']) : '' ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @media print {
      .no-print { display: none !important; }
      body { background: white; }
    }
  </style>
</head>
<body class="bg-gray-50">
<?php if ($loggedIn): require 'lib/nav.php'; endif; ?>

<div class="max-w-4xl mx-auto px-4 py-6">

<?php if ($showPicker): ?>
  <div class="bg-white p-6 rounded shadow">
    <h2 class="text-xl font-semibold mb-4">View Tenant Ledger</h2>
    <form method="get" action="/ledger.php" class="flex gap-3 items-end">
      <div class="flex-1">
        <label class="block text-sm font-medium text-gray-700 mb-1">Select Tenant</label>
        <select name="tenant_id" class="border p-2 rounded w-full">
          <option value="">— Choose a tenant —</option>
          <?php foreach ($tenants as $t): ?>
          <option value="<?= intval($t['id']) ?>">
            <?= htmlspecialchars($t['full_name']) ?> — Unit <?= htmlspecialchars($t['unit_no'] ?? 'N/A') ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">View Ledger</button>
    </form>
  </div>

<?php elseif ($tenant): ?>
  <!-- Balance summary cards -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 no-print">
    <div class="bg-white p-4 rounded shadow text-center">
      <div class="text-sm text-gray-500">Monthly Rent</div>
      <div class="text-2xl font-bold text-gray-800">AED <?= number_format($monthlyRent, 2) ?></div>
    </div>
    <div class="bg-white p-4 rounded shadow text-center">
      <div class="text-sm text-gray-500">Total Paid This Year</div>
      <div class="text-2xl font-bold text-green-600">AED <?= number_format($yearTotalPaid, 2) ?></div>
    </div>
    <div class="bg-white p-4 rounded shadow text-center">
      <div class="text-sm text-gray-500">This Month Outstanding</div>
      <div class="text-2xl font-bold <?= $outstanding > 0 ? 'text-red-600' : 'text-green-600' ?>">
        AED <?= number_format($outstanding, 2) ?>
      </div>
    </div>
  </div>

  <div class="bg-white p-6 rounded shadow">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold">
        Ledger for <?= htmlspecialchars($tenant['full_name']) ?>
        (Unit <?= htmlspecialchars($tenant['unit_no'] ?? 'N/A') ?>)
      </h3>
      <div class="flex gap-2 no-print">
        <?php if ($loggedIn): ?>
        <a href="/ledger.php" class="text-sm bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded">← Back</a>
        <?php endif; ?>
        <button onclick="window.print()" class="text-sm bg-blue-600 text-white hover:bg-blue-700 px-3 py-1 rounded">
          🖨 Print Ledger
        </button>
      </div>
    </div>

    <?php if (empty($payments)): ?>
    <p class="text-gray-500 text-sm">No payments recorded yet.</p>
    <?php else: ?>
    <table class="w-full text-sm">
      <thead class="bg-gray-100">
        <tr>
          <th class="p-2 text-left">Period</th>
          <th class="p-2 text-right">Paid (AED)</th>
          <th class="p-2 text-left">Date</th>
          <th class="p-2 text-left">Method</th>
          <?php if ($loggedIn): ?>
          <th class="p-2 text-left no-print">Receipt</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
        <tr class="border-t hover:bg-gray-50">
          <td class="p-2"><?= htmlspecialchars($p['period_ym']) ?></td>
          <td class="p-2 text-right"><?= number_format((float)$p['amount_aed'], 2) ?></td>
          <td class="p-2"><?= htmlspecialchars($p['paid_at']) ?></td>
          <td class="p-2"><?= htmlspecialchars($p['method']) ?></td>
          <?php if ($loggedIn): ?>
          <td class="p-2 no-print">
            <a href="/receipt.php?id=<?= intval($p['id']) ?>" target="_blank"
               class="text-blue-600 hover:underline text-xs">🖨 Receipt</a>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
        <tr class="border-t font-semibold bg-gray-50">
          <td class="p-2">Total Paid</td>
          <td class="p-2 text-right">AED <?= number_format($totalPaid, 2) ?></td>
          <td class="p-2" colspan="<?= $loggedIn ? 3 : 2 ?>"></td>
        </tr>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

<?php endif; ?>
</div>
</body>
</html>
