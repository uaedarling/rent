<?php
require_once 'lib/auth.php';
require_once 'lib/db.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: payments.php'); exit; }

// Allow access via signed token (for tenants opening link from WhatsApp)
$token_provided = isset($_GET['token']);
if ($token_provided) {
    $expected = hash_hmac('sha256', $id, DB_PASS);
    if (!hash_equals($expected, (string)$_GET['token'])) {
        http_response_code(403);
        echo '<p>Invalid or expired receipt link.</p>';
        exit;
    }
} else {
    require_login();
}

$pdo  = db();
$stmt = $pdo->prepare('SELECT p.*, t.full_name, t.email, u.unit_no FROM payments p JOIN tenants t ON t.id=p.tenant_id JOIN units u ON u.id=t.unit_id WHERE p.id=?');
$stmt->execute([$id]);
$pay = $stmt->fetch();
if (!$pay) { header('Location: payments.php'); exit; }

$settings   = get_settings();
$company    = htmlspecialchars($settings['company_name'] ?? 'Rent Manager');
$receipt_no = '#' . str_pad($pay['id'], 6, '0', STR_PAD_LEFT);
$period_fmt = date('F Y', strtotime($pay['period_ym'] . '-01'));
?>
<!doctype html><html><head><meta charset='utf-8'>
<title>Receipt <?= htmlspecialchars($receipt_no) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  @media print {
    .no-print { display: none !important; }
    body { background: #fff; }
  }
</style>
</head><body class='bg-gray-100 p-6'>

<div class='no-print mb-4 flex gap-3'>
  <a href='payments.php' class='text-sm text-blue-600 hover:underline'>← Back to Payments</a>
  <button onclick='window.print()' class='bg-red-600 text-white text-sm px-4 py-1 rounded'>🖨 Print Receipt</button>
</div>

<div class='max-w-xl mx-auto bg-white rounded-lg shadow overflow-hidden'>
  <!-- Header -->
  <div class='bg-red-700 text-white px-8 py-6'>
    <h1 class='text-2xl font-bold'><?= $company ?></h1>
    <p class='text-sm opacity-80 mt-1'>Payment Receipt</p>
  </div>

  <!-- Body -->
  <div class='px-8 py-6'>
    <div class='flex justify-between text-sm text-gray-500 mb-4'>
      <span>Receipt No. <strong class='text-gray-800'><?= htmlspecialchars($receipt_no) ?></strong></span>
      <span>Issue Date: <strong class='text-gray-800'><?= date('d M Y') ?></strong></span>
    </div>

    <table class='w-full text-sm border-collapse'>
      <tr class='border-b'><td class='py-2 text-gray-500 w-1/2'>Tenant</td><td class='py-2 font-semibold'><?= htmlspecialchars($pay['full_name']) ?></td></tr>
      <tr class='border-b'><td class='py-2 text-gray-500'>Unit</td><td class='py-2 font-semibold'><?= htmlspecialchars($pay['unit_no']) ?></td></tr>
      <tr class='border-b'><td class='py-2 text-gray-500'>Period</td><td class='py-2 font-semibold'><?= htmlspecialchars($period_fmt) ?></td></tr>
      <tr class='border-b'><td class='py-2 text-gray-500'>Amount Paid</td><td class='py-2 font-semibold text-lg'>AED <?= number_format((float)$pay['amount_aed'], 2) ?></td></tr>
      <tr class='border-b'><td class='py-2 text-gray-500'>Payment Date</td><td class='py-2 font-semibold'><?= htmlspecialchars($pay['paid_at']) ?></td></tr>
      <tr><td class='py-2 text-gray-500'>Payment Method</td><td class='py-2 font-semibold'><?= htmlspecialchars($pay['method']) ?></td></tr>
    </table>

    <!-- PAID stamp -->
    <div class='mt-6'>
      <span class='inline-block border-4 border-green-600 text-green-600 font-bold text-2xl px-6 py-2 rounded tracking-widest' style='transform:rotate(-5deg);display:inline-block'>PAID</span>
    </div>
  </div>

  <!-- Footer -->
  <div class='bg-gray-50 px-8 py-4 text-xs text-gray-400 text-center'>
    This is an automatically generated receipt. Thank you for your payment.
  </div>
</div>

</body></html>
