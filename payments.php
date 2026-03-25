<?php
require_once 'lib/auth.php';
require_once 'lib/db.php';
require_once 'lib/functions.php';
require_login();

$pdo = db();
$s   = get_settings();
$tz  = $s['timezone'] ?? 'Asia/Dubai';
date_default_timezone_set($tz);

$tenants = $pdo->query('SELECT t.*, u.unit_no, u.monthly_rent_aed FROM tenants t LEFT JOIN units u ON u.id=t.unit_id ORDER BY u.unit_no, t.full_name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['tenant_id'])) {
    if (!csrf_verify()) { header('Location: payments.php'); exit; }
    $tenant_id = intval($_POST['tenant_id']);
    $period    = trim($_POST['period_ym']);
    $amount    = floatval($_POST['amount_aed']);
    $paid_at   = $_POST['paid_at'] ?: date('Y-m-d');
    $method    = $_POST['method'];
    $stmt = $pdo->prepare('INSERT INTO payments (tenant_id, period_ym, amount_aed, paid_at, method) VALUES (?,?,?,?,?)');
    $stmt->execute([$tenant_id, $period, $amount, $paid_at, $method]);
    $payment_id = $pdo->lastInsertId();
    send_receipt_email($tenant_id, $payment_id);
    header('Location: payments.php'); exit;
}

// GET pre-fill params from pending.php
$prefill_tenant_id = intval($_GET['tenant_id'] ?? 0);
$prefill_period    = trim($_GET['period'] ?? date('Y-m'));

// Month filter
$filter_month = trim($_GET['month'] ?? '');
// Build last 12 months options
$monthOptions = [];
for ($i = 0; $i < 12; $i++) {
    $monthOptions[] = date('Y-m', strtotime("-{$i} months"));
}

if ($filter_month && preg_match('/^\d{4}-\d{2}$/', $filter_month)) {
    $payments = $pdo->prepare(
        'SELECT p.*, t.full_name, u.unit_no FROM payments p
         JOIN tenants t ON t.id=p.tenant_id
         JOIN units u ON u.id=t.unit_id
         WHERE p.period_ym = ?
         ORDER BY p.created_at DESC LIMIT 500'
    );
    $payments->execute([$filter_month]);
    $payments = $payments->fetchAll();
} else {
    $payments = $pdo->query(
        'SELECT p.*, t.full_name, u.unit_no FROM payments p
         JOIN tenants t ON t.id=p.tenant_id
         JOIN units u ON u.id=t.unit_id
         ORDER BY p.created_at DESC LIMIT 200'
    )->fetchAll();
}

$csrf = csrf_token();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Payments</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
<?php require 'lib/nav.php'; ?>
<div class="max-w-4xl mx-auto px-4">

<div class="bg-white p-4 rounded shadow mb-4">
  <h3 class="text-lg font-semibold mb-2">Record Payment</h3>
  <form method="post" class="grid grid-cols-3 gap-2">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <div class="col-span-1">
      <label class="block text-xs text-gray-500 mb-1">Tenant</label>
      <select name="tenant_id" class="border p-2 rounded w-full">
        <?php foreach ($tenants as $t): ?>
        <option value="<?= intval($t['id']) ?>"
          <?= $prefill_tenant_id === intval($t['id']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($t['full_name']) ?> — <?= htmlspecialchars($t['unit_no'] ?? '') ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-span-1">
      <label class="block text-xs text-gray-500 mb-1">Period</label>
      <input type="month" name="period_ym" class="border p-2 rounded w-full"
             value="<?= htmlspecialchars($prefill_period) ?>">
    </div>
    <div class="col-span-1">
      <label class="block text-xs text-gray-500 mb-1">Amount (AED)</label>
      <input name="amount_aed" type="number" step="0.01" min="0"
             class="border p-2 rounded w-full" placeholder="Amount AED">
    </div>
    <div class="col-span-1">
      <label class="block text-xs text-gray-500 mb-1">Paid At</label>
      <input type="date" name="paid_at" class="border p-2 rounded w-full"
             value="<?= date('Y-m-d') ?>">
    </div>
    <div class="col-span-1">
      <label class="block text-xs text-gray-500 mb-1">Method</label>
      <input name="method" class="border p-2 rounded w-full" placeholder="e.g. Bank Transfer">
    </div>
    <div class="col-span-1 flex items-end">
      <button class="bg-red-600 text-white px-4 py-2 rounded w-full hover:bg-red-700">
        Record Payment &amp; Send Receipt
      </button>
    </div>
  </form>
</div>

<div class="bg-white p-4 rounded shadow">
  <div class="flex items-center justify-between mb-3">
    <h3 class="font-semibold">Payments</h3>
    <form method="get" class="flex items-center gap-2">
      <label class="text-sm text-gray-600">Filter by month:</label>
      <select name="month" onchange="this.form.submit()" class="border p-1 rounded text-sm">
        <option value="">All recent</option>
        <?php foreach ($monthOptions as $mo): ?>
        <option value="<?= htmlspecialchars($mo) ?>"
          <?= $filter_month === $mo ? 'selected' : '' ?>>
          <?= htmlspecialchars($mo) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <table class="w-full text-sm">
    <thead class="bg-gray-100">
      <tr>
        <th class="p-2 text-left">Date</th>
        <th class="p-2 text-left">Tenant</th>
        <th class="p-2 text-left">Unit</th>
        <th class="p-2 text-left">Period</th>
        <th class="p-2 text-right">Amount</th>
        <th class="p-2 text-center">Receipt</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($payments as $p): ?>
      <tr class="border-t hover:bg-gray-50">
        <td class="p-2"><?= htmlspecialchars($p['paid_at']) ?></td>
        <td class="p-2"><?= htmlspecialchars($p['full_name']) ?></td>
        <td class="p-2"><?= htmlspecialchars($p['unit_no']) ?></td>
        <td class="p-2"><?= htmlspecialchars($p['period_ym']) ?></td>
        <td class="p-2 text-right"><?= number_format($p['amount_aed'], 2) ?></td>
        <td class="p-2 text-center">
          <a href="receipt.php?id=<?= intval($p['id']) ?>"
             class="text-blue-600 hover:underline" target="_blank">🖨 Receipt</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($payments)): ?>
      <tr><td colspan="6" class="p-4 text-center text-gray-500">No payments found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</div>
</body>
</html>
