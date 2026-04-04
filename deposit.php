<?php
require_once 'lib/auth.php';
require_once 'lib/db.php';
require_login();

define('DEPOSIT_MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5 MB

$pdo = db();

$payment_id = intval($_GET['payment_id'] ?? 0);
if ($payment_id <= 0) {
    header('Location: ' . APP_BASE_URL . 'payments.php'); exit;
}

// Fetch payment + tenant + unit info
$stmt = $pdo->prepare(
    'SELECT p.*, t.full_name, t.phone, u.unit_no
     FROM payments p
     JOIN tenants t ON t.id = p.tenant_id
     JOIN units u ON u.id = t.unit_id
     WHERE p.id = ? LIMIT 1'
);
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();

if (!$payment) {
    header('Location: ' . APP_BASE_URL . 'payments.php'); exit;
}

// Check for existing deposit
$dep_stmt = $pdo->prepare('SELECT d.*, u.full_name AS depositor_name FROM deposits d LEFT JOIN users u ON u.id = d.deposited_by WHERE d.payment_id = ? LIMIT 1');
$dep_stmt->execute([$payment_id]);
$deposit = $dep_stmt->fetch();

$error   = '';
$success = '';

if (isset($_GET['success'])) {
    $success = 'Deposit recorded successfully.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$deposit) {
    if (!csrf_verify()) {
        $error = 'Invalid request. Please try again.';
    } else {
        $deposited_at = trim($_POST['deposited_at'] ?? '');
        $bank_name    = trim($_POST['bank_name']    ?? '');
        $deposit_ref  = trim($_POST['deposit_ref']  ?? '');
        $notes        = trim($_POST['notes']         ?? '');

        if (!$deposited_at || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deposited_at)) {
            $error = 'Deposit date is required.';
        }

        $slip_filename = null;
        if (!$error) {
            if (isset($_FILES['slip_file']) && $_FILES['slip_file']['error'] === UPLOAD_ERR_OK) {
                $file          = $_FILES['slip_file'];
                $max_size      = DEPOSIT_MAX_UPLOAD_BYTES;
                $allowed_exts  = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
                $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];

                if ($file['size'] > $max_size) {
                    $error = 'File size must not exceed ' . (DEPOSIT_MAX_UPLOAD_BYTES / (1024 * 1024)) . ' MB.';
                } else {
                    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $mime = mime_content_type($file['tmp_name']);
                    if (!in_array($ext, $allowed_exts, true) || !in_array($mime, $allowed_mimes, true)) {
                        $error = 'Invalid file type. Only JPG, PNG, GIF, and PDF are allowed.';
                    } else {
                        $upload_dir = __DIR__ . '/uploads/deposits/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        $slip_filename = 'deposit_' . $payment_id . '_' . time() . '.' . $ext;
                        if (!move_uploaded_file($file['tmp_name'], $upload_dir . $slip_filename)) {
                            $error         = 'Failed to save the uploaded file. Check directory permissions.';
                            $slip_filename = null;
                        }
                    }
                }
            } elseif (isset($_FILES['slip_file']) && $_FILES['slip_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $error = 'File upload error (code ' . intval($_FILES['slip_file']['error']) . ').';
            }
        }

        if (!$error) {
            $ins = $pdo->prepare(
                'INSERT INTO deposits (payment_id, deposited_by, deposited_at, bank_name, deposit_ref, notes, slip_filename)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([$payment_id, $_SESSION['user_id'], $deposited_at, $bank_name, $deposit_ref, $notes, $slip_filename]);
            header('Location: ' . APP_BASE_URL . 'deposit.php?payment_id=' . $payment_id . '&success=1');
            exit;
        }
    }
}

$csrf       = csrf_token();
$period_fmt = date('F Y', strtotime($payment['period_ym'] . '-01'));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Deposit — <?= htmlspecialchars($payment['unit_no']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
<?php require 'lib/nav.php'; ?>
<div class="max-w-2xl mx-auto px-4 pb-10">

  <div class="mb-4">
    <a href="<?= htmlspecialchars(APP_BASE_URL) ?>payments.php" class="text-blue-600 hover:underline text-sm">← Back to Payments</a>
  </div>

  <!-- Payment Context -->
  <div class="bg-white rounded shadow p-4 mb-4">
    <h2 class="text-lg font-bold mb-3">Payment Details</h2>
    <div class="grid grid-cols-2 gap-2 text-sm">
      <div><span class="text-gray-500">Tenant:</span> <strong><?= htmlspecialchars($payment['full_name']) ?></strong></div>
      <div><span class="text-gray-500">Unit:</span> <strong><?= htmlspecialchars($payment['unit_no']) ?></strong></div>
      <div><span class="text-gray-500">Period:</span> <?= htmlspecialchars($period_fmt) ?></div>
      <div><span class="text-gray-500">Amount:</span> AED <?= number_format((float)$payment['amount_aed'], 2) ?></div>
      <div><span class="text-gray-500">Paid At:</span> <?= htmlspecialchars($payment['paid_at']) ?></div>
      <div><span class="text-gray-500">Method:</span> <?= htmlspecialchars($payment['method']) ?></div>
    </div>
  </div>

  <?php if ($success): ?>
  <div class="mb-4 bg-green-50 border border-green-300 text-green-700 rounded p-3"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="mb-4 bg-red-50 border border-red-300 text-red-700 rounded p-3"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($deposit): ?>
  <!-- Deposit Details (read-only) -->
  <div class="bg-white rounded shadow p-4">
    <h3 class="text-lg font-bold mb-3 text-green-700">✅ Deposit Recorded</h3>
    <div class="grid grid-cols-2 gap-3 text-sm mb-4">
      <div><span class="text-gray-500">Deposited On:</span> <strong><?= htmlspecialchars($deposit['deposited_at']) ?></strong></div>
      <div><span class="text-gray-500">Bank Name:</span> <?= htmlspecialchars($deposit['bank_name'] ?? '—') ?></div>
      <div><span class="text-gray-500">Reference No.:</span> <?= htmlspecialchars($deposit['deposit_ref'] ?? '—') ?></div>
      <div><span class="text-gray-500">Deposited By:</span> <?= htmlspecialchars($deposit['depositor_name'] ?? '—') ?></div>
      <div class="col-span-2"><span class="text-gray-500">Recorded At:</span> <?= htmlspecialchars($deposit['created_at']) ?></div>
    </div>
    <?php if (!empty($deposit['notes'])): ?>
    <div class="text-sm mb-4">
      <span class="text-gray-500">Notes:</span>
      <p class="mt-1"><?= htmlspecialchars($deposit['notes']) ?></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($deposit['slip_filename'])): ?>
      <?php
        $ext      = strtolower(pathinfo($deposit['slip_filename'], PATHINFO_EXTENSION));
        $slip_url = htmlspecialchars(APP_BASE_URL . 'uploads/deposits/' . $deposit['slip_filename']);
      ?>
      <div class="mt-2">
        <h4 class="text-sm font-semibold text-gray-700 mb-2">Deposit Slip:</h4>
        <?php if ($ext === 'pdf'): ?>
        <a href="<?= $slip_url ?>" target="_blank" class="inline-flex items-center gap-1 text-blue-600 hover:underline">
          📄 View PDF Deposit Slip
        </a>
        <?php else: ?>
        <a href="<?= $slip_url ?>" target="_blank">
          <img src="<?= $slip_url ?>" alt="Deposit Slip" class="max-w-full rounded border" style="max-height:400px">
        </a>
        <?php endif; ?>
      </div>
    <?php else: ?>
    <p class="text-sm text-gray-400 italic">No slip image was uploaded.</p>
    <?php endif; ?>
  </div>

  <?php else: ?>
  <!-- Deposit Form -->
  <div class="bg-white rounded shadow p-4">
    <h3 class="text-lg font-bold mb-3">Record Bank Deposit</h3>
    <form method="post" enctype="multipart/form-data" class="space-y-4">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="payment_id" value="<?= intval($payment_id) ?>">

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Deposit Date <span class="text-red-500">*</span></label>
          <input type="date" name="deposited_at" value="<?= htmlspecialchars(date('Y-m-d')) ?>"
            class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" required>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Bank Name</label>
          <input type="text" name="bank_name" value="<?= htmlspecialchars($_POST['bank_name'] ?? '') ?>"
            class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500"
            placeholder="e.g. Emirates NBD">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Deposit Reference / Slip No.</label>
          <input type="text" name="deposit_ref" value="<?= htmlspecialchars($_POST['deposit_ref'] ?? '') ?>"
            class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500"
            placeholder="Bank reference number">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Deposit Slip <span class="text-gray-400 font-normal">(image or PDF, max 5 MB)</span></label>
          <input type="file" name="slip_file" accept="image/*,application/pdf"
            class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Notes</label>
        <textarea name="notes" rows="3"
          class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500"
          placeholder="Any additional notes about this deposit"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
      </div>

      <div>
        <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded hover:bg-red-700">
          💾 Save Deposit Record
        </button>
      </div>
    </form>
  </div>
  <?php endif; ?>

</div>
</body>
</html>
