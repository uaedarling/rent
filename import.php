<?php
require_once 'lib/auth.php';
require_once 'lib/db.php';
require_login();

$pdo = db();
$s   = get_settings();
$tz  = $s['timezone'] ?? 'Asia/Dubai';
date_default_timezone_set($tz);

// Download sample CSV
if (isset($_GET['action']) && $_GET['action'] === 'sample') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="payment_import_sample.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['unit_no', 'tenant_name', 'period_ym', 'amount_aed', 'paid_at', 'method']);
    $sampleRows = [
        ['A101', 'Ahmed Al Mansoori', '2020-01', '4500.00', '2020-01-05', 'Bank Transfer'],
        ['A101', 'Ahmed Al Mansoori', '2020-02', '4500.00', '2020-02-03', 'Bank Transfer'],
        ['A101', 'Ahmed Al Mansoori', '2020-03', '4500.00', '2020-03-04', 'Bank Transfer'],
        ['B202', 'Sara Mohammed',     '2020-01', '3800.00', '2020-01-02', 'Cash'],
        ['B202', 'Sara Mohammed',     '2020-02', '3800.00', '2020-02-01', 'Cash'],
        ['B202', 'Sara Mohammed',     '2020-03', '3800.00', '2020-03-02', 'Cheque'],
        ['C303', 'Khalid Hassan',     '2024-01', '5200.00', '2024-01-10', 'Bank Transfer'],
        ['C303', 'Khalid Hassan',     '2025-01', '5200.00', '2025-01-08', 'Bank Transfer'],
        ['A101', 'Ahmed Al Mansoori', '2026-01', '4800.00', '2026-01-05', 'Bank Transfer'],
        ['B202', 'Sara Mohammed',     '2026-01', '4000.00', '', 'Cash'],
    ];
    foreach ($sampleRows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

$results  = [];
$imported = 0;
$skipped  = 0;
$errors   = 0;
$message  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $message = 'CSRF verification failed.';
    } elseif (empty($_FILES['csvfile']['tmp_name'])) {
        $message = 'No file uploaded.';
    } else {
        $tmpPath  = $_FILES['csvfile']['tmp_name'];
        $origName = $_FILES['csvfile']['name'] ?? '';

        // Validate extension
        if (!preg_match('/\.csv$/i', $origName)) {
            $message = 'Only .csv files are accepted.';
        } else {
            $handle = fopen($tmpPath, 'r');
            $rowNum = 0;

            // Skip header row
            fgetcsv($handle);

            while (($cols = fgetcsv($handle)) !== false) {
                $rowNum++;
                if (count($cols) < 4) {
                    $results[] = ['row' => $rowNum, 'unit' => '', 'tenant' => '', 'period' => '', 'amount' => '', 'status' => 'skipped', 'reason' => 'Not enough columns'];
                    $skipped++;
                    continue;
                }

                [$unit_no, $tenant_name, $period_ym, $amount_aed] = array_map('trim', $cols);
                $paid_at_raw = trim($cols[4] ?? '');
                $method      = trim($cols[5] ?? 'Unknown');

                // Validate period_ym
                if (!preg_match('/^\d{4}-\d{2}$/', $period_ym)) {
                    $results[] = ['row' => $rowNum, 'unit' => $unit_no, 'tenant' => $tenant_name, 'period' => $period_ym, 'amount' => $amount_aed, 'status' => 'error', 'reason' => 'Invalid period format (need YYYY-MM)'];
                    $errors++;
                    continue;
                }

                // Validate amount
                if (!is_numeric($amount_aed) || (float)$amount_aed <= 0) {
                    $results[] = ['row' => $rowNum, 'unit' => $unit_no, 'tenant' => $tenant_name, 'period' => $period_ym, 'amount' => $amount_aed, 'status' => 'error', 'reason' => 'Invalid amount'];
                    $errors++;
                    continue;
                }
                $amount = (float)$amount_aed;

                // Validate / default paid_at
                if ($paid_at_raw === '') {
                    $paid_at = date('Y-m-d');
                } else {
                    $d = date_create_from_format('Y-m-d', $paid_at_raw);
                    if ($d === false || $d->format('Y-m-d') !== $paid_at_raw) {
                        $results[] = ['row' => $rowNum, 'unit' => $unit_no, 'tenant' => $tenant_name, 'period' => $period_ym, 'amount' => $amount_aed, 'status' => 'error', 'reason' => 'Invalid paid_at date (need YYYY-MM-DD)'];
                        $errors++;
                        continue;
                    }
                    $paid_at = $paid_at_raw;
                }

                // Find unit
                $unitStmt = $pdo->prepare('SELECT id FROM units WHERE unit_no = ? LIMIT 1');
                $unitStmt->execute([$unit_no]);
                $unit = $unitStmt->fetch();
                if (!$unit) {
                    $results[] = ['row' => $rowNum, 'unit' => $unit_no, 'tenant' => $tenant_name, 'period' => $period_ym, 'amount' => $amount_aed, 'status' => 'skipped', 'reason' => 'Unit not found: ' . $unit_no];
                    $skipped++;
                    continue;
                }

                // Find tenant by unit_id
                $tenantStmt = $pdo->prepare('SELECT id, full_name FROM tenants WHERE unit_id = ? LIMIT 1');
                $tenantStmt->execute([$unit['id']]);
                $tenant = $tenantStmt->fetch();
                if (!$tenant) {
                    $results[] = ['row' => $rowNum, 'unit' => $unit_no, 'tenant' => $tenant_name, 'period' => $period_ym, 'amount' => $amount_aed, 'status' => 'skipped', 'reason' => 'No tenant found for unit ' . $unit_no];
                    $skipped++;
                    continue;
                }

                // Warn if CSV tenant name doesn't match DB tenant name
                $nameNote = '';
                if ($tenant_name !== '' && strcasecmp(trim($tenant_name), trim($tenant['full_name'])) !== 0) {
                    $nameNote = ' (name mismatch: CSV "' . $tenant_name . '" vs DB "' . $tenant['full_name'] . '")';
                }

                // Check for duplicate
                $dupStmt = $pdo->prepare(
                    'SELECT id FROM payments WHERE tenant_id = ? AND period_ym = ? AND amount_aed = ? LIMIT 1'
                );
                $dupStmt->execute([$tenant['id'], $period_ym, $amount]);
                if ($dupStmt->fetch()) {
                    $results[] = ['row' => $rowNum, 'unit' => $unit_no, 'tenant' => $tenant['full_name'], 'period' => $period_ym, 'amount' => $amount_aed, 'status' => 'skipped', 'reason' => 'Duplicate payment'];
                    $skipped++;
                    continue;
                }

                // Insert
                $ins = $pdo->prepare(
                    'INSERT INTO payments (tenant_id, period_ym, amount_aed, paid_at, method) VALUES (?,?,?,?,?)'
                );
                $ins->execute([$tenant['id'], $period_ym, $amount, $paid_at, $method]);
                $results[] = ['row' => $rowNum, 'unit' => $unit_no, 'tenant' => $tenant['full_name'], 'period' => $period_ym, 'amount' => $amount_aed, 'status' => 'imported', 'reason' => ltrim($nameNote)];
                $imported++;
            }
            fclose($handle);
            $message = "Import complete: {$imported} imported, {$skipped} skipped, {$errors} errors.";
        }
    }
}

$csrf = csrf_token();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Import Payments</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
<?php require 'lib/nav.php'; ?>

<div class="max-w-6xl mx-auto px-4 py-6">
  <h2 class="text-2xl font-semibold mb-6">Bulk Import Payments</h2>

  <?php if ($message): ?>
  <div class="mb-4 p-4 rounded <?= str_contains($message, 'complete') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
    <?= htmlspecialchars($message) ?>
  </div>
  <?php endif; ?>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <!-- Left panel: download sample -->
    <div class="bg-white p-6 rounded shadow">
      <h3 class="text-lg font-semibold mb-2">1. Download Sample CSV</h3>
      <p class="text-sm text-gray-600 mb-4">
        Download the sample CSV template, fill in your payment data, then upload it below.
        Columns: <code class="bg-gray-100 px-1 rounded">unit_no, tenant_name, period_ym, amount_aed, paid_at, method</code>
      </p>
      <a href="import.php?action=sample"
         class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
        ⬇ Download Sample CSV
      </a>
      <div class="mt-4 text-xs text-gray-500">
        <strong>Notes:</strong>
        <ul class="list-disc ml-4 mt-1 space-y-1">
          <li><code>period_ym</code> must be <code>YYYY-MM</code> (e.g. 2025-09)</li>
          <li><code>paid_at</code> must be <code>YYYY-MM-DD</code> or left blank (uses today)</li>
          <li><code>amount_aed</code> must be a positive number</li>
          <li>Duplicate entries (same tenant + period + amount) are skipped</li>
          <li>Tenant is matched by unit number</li>
        </ul>
      </div>
    </div>

    <!-- Right panel: upload -->
    <div class="bg-white p-6 rounded shadow">
      <h3 class="text-lg font-semibold mb-2">2. Upload CSV File</h3>
      <form method="post" enctype="multipart/form-data"
            onsubmit="return validateCsv(this)">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Select CSV File</label>
          <input type="file" name="csvfile" id="csvfile" accept=".csv"
                 class="border rounded p-2 w-full text-sm">
          <p class="text-xs text-gray-500 mt-1">Only .csv files are accepted.</p>
        </div>
        <button type="submit"
                class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 w-full">
          Upload &amp; Import
        </button>
      </form>
    </div>
  </div>

  <!-- Results table -->
  <?php if (!empty($results)): ?>
  <div class="bg-white rounded shadow overflow-hidden">
    <div class="p-4 border-b">
      <h3 class="font-semibold">Import Results</h3>
      <div class="flex gap-4 mt-1 text-sm">
        <span class="text-green-700 font-medium">✓ <?= $imported ?> imported</span>
        <span class="text-yellow-700 font-medium">↷ <?= $skipped ?> skipped</span>
        <span class="text-red-700 font-medium">✗ <?= $errors ?> errors</span>
      </div>
    </div>
    <table class="w-full text-sm">
      <thead class="bg-gray-100">
        <tr>
          <th class="p-2 text-left">Row</th>
          <th class="p-2 text-left">Unit</th>
          <th class="p-2 text-left">Tenant</th>
          <th class="p-2 text-left">Period</th>
          <th class="p-2 text-right">Amount (AED)</th>
          <th class="p-2 text-center">Status</th>
          <th class="p-2 text-left">Notes</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($results as $r):
          $badge = match($r['status']) {
              'imported' => 'bg-green-100 text-green-800',
              'skipped'  => 'bg-yellow-100 text-yellow-800',
              default    => 'bg-red-100 text-red-800',
          };
        ?>
        <tr class="border-t hover:bg-gray-50">
          <td class="p-2"><?= intval($r['row']) ?></td>
          <td class="p-2"><?= htmlspecialchars($r['unit']) ?></td>
          <td class="p-2"><?= htmlspecialchars($r['tenant']) ?></td>
          <td class="p-2"><?= htmlspecialchars($r['period']) ?></td>
          <td class="p-2 text-right"><?= htmlspecialchars($r['amount']) ?></td>
          <td class="p-2 text-center">
            <span class="px-2 py-1 rounded text-xs font-semibold <?= $badge ?>">
              <?= htmlspecialchars($r['status']) ?>
            </span>
          </td>
          <td class="p-2 text-gray-500 text-xs"><?= htmlspecialchars($r['reason']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
function validateCsv(form) {
    var input = document.getElementById('csvfile');
    if (!input.value) {
        alert('Please select a CSV file to upload.');
        return false;
    }
    if (!input.value.toLowerCase().endsWith('.csv')) {
        alert('Only .csv files are accepted. Please select a .csv file.');
        return false;
    }
    return true;
}
</script>
</body>
</html>
