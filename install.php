<?php
/**
 * Rent Manager AED — Web Installer
 * Visit this file once to set up the application, then DELETE it from the server.
 */

// ── Guard: already installed ─────────────────────────────────────────────────
if (file_exists(__DIR__ . '/installed.lock')) {
    http_response_code(403);
?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Already Installed</title>
<script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-6">
<div class="bg-white rounded-xl shadow p-8 max-w-md w-full text-center">
  <div class="text-4xl mb-4">🔒</div>
  <h1 class="text-xl font-bold text-gray-800 mb-2">Already Installed</h1>
  <p class="text-gray-600">The application is already installed. Delete <code class="bg-gray-100 px-1 rounded">installed.lock</code> to run the installer again.</p>
</div>
</body></html>
<?php
    exit;
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function run_sql_file(PDO $pdo, string $path): void {
    $sql = file_get_contents($path);
    // Split on semicolons; skip empty/comment-only statements
    $statements = preg_split('/;\s*\n/', $sql);
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') continue;
        // Skip lines that are purely SQL comments
        if (preg_match('/^(--[^\n]*\n?)+$/', $stmt)) continue;
        $pdo->exec($stmt);
    }
}

function write_config(string $host, string $name, string $user, string $pass, string $tz): bool {
    $content  = "<?php\n";
    $content .= "define('DB_HOST', " . var_export($host, true) . ");\n";
    $content .= "define('DB_NAME', " . var_export($name, true) . ");\n";
    $content .= "define('DB_USER', " . var_export($user, true) . ");\n";
    $content .= "define('DB_PASS', " . var_export($pass, true) . ");\n";
    $content .= "date_default_timezone_set(" . var_export($tz, true) . ");\n";
    $content .= "define('CURRENCY', 'AED');\n";
    return file_put_contents(__DIR__ . '/config.php', $content) !== false;
}

// ── Requirements ──────────────────────────────────────────────────────────────
$checks = [
    'php'      => ['label' => 'PHP >= 7.4',              'ok' => version_compare(PHP_VERSION, '7.4.0', '>=')],
    'pdo'      => ['label' => 'PDO extension',            'ok' => extension_loaded('pdo')],
    'pdo_mysql'=> ['label' => 'PDO MySQL driver',         'ok' => extension_loaded('pdo_mysql')],
    'writable' => ['label' => 'Directory is writable',    'ok' => is_writable(__DIR__)],
    'schema'   => ['label' => 'schema.sql found',         'ok' => file_exists(__DIR__ . '/schema.sql')],
];
$all_ok = array_reduce($checks, fn($carry, $c) => $carry && $c['ok'], true);

// ── Step routing ──────────────────────────────────────────────────────────────
$step  = (int)($_POST['step'] ?? 1);
$error = '';

if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── Process installation ──────────────────────────────────────────────────
    $db_host      = trim($_POST['db_host']      ?? '');
    $db_name      = trim($_POST['db_name']      ?? '');
    $db_user      = trim($_POST['db_user']      ?? '');
    $db_pass      = $_POST['db_pass']           ?? '';
    $admin_email  = trim($_POST['admin_email']  ?? '');
    $admin_pass   = $_POST['admin_pass']        ?? '';
    $admin_pass2  = $_POST['admin_pass2']       ?? '';
    $company_name = trim($_POST['company_name'] ?? '');
    $timezone     = trim($_POST['timezone']     ?? 'Asia/Dubai');
    $smtp_user    = trim($_POST['smtp_user']    ?? '');
    $smtp_pass    = $_POST['smtp_pass']         ?? '';

    // Validate
    if (!$db_host || !$db_name || !$db_user) {
        $error = 'Database host, name, and user are required.';
    } elseif (!$admin_email || !filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'A valid admin email address is required.';
    } elseif (strlen($admin_pass) < 8) {
        $error = 'Admin password must be at least 8 characters.';
    } elseif ($admin_pass !== $admin_pass2) {
        $error = 'Admin passwords do not match.';
    } elseif (!$company_name) {
        $error = 'Company name is required.';
    }

    if (!$error) {
        try {
            // Connect to DB
            $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
            $pdo = new PDO($dsn, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE      => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // Run schema
            run_sql_file($pdo, __DIR__ . '/schema.sql');

            // Run migrations if they exist
            $migrations = [
                __DIR__ . '/migrations/001_add_password_resets.sql',
                __DIR__ . '/migrations/002_add_whatsapp_template.sql',
                __DIR__ . '/migrations/003_add_deposits_table.sql',
            ];
            foreach ($migrations as $migration) {
                if (file_exists($migration)) {
                    run_sql_file($pdo, $migration);
                }
            }

            // Create uploads/deposits directory
            $uploads_dir = __DIR__ . '/uploads/deposits';
            if (!is_dir($uploads_dir)) {
                mkdir($uploads_dir, 0755, true);
            }

            // Create admin user
            $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)');
            $stmt->execute(['Administrator', $admin_email, $hash, 'admin']);

            // Insert / update settings row
            $tz_safe = in_array($timezone, DateTimeZone::listIdentifiers()) ? $timezone : 'Asia/Dubai';
            $smtp_host_val = $smtp_user ? 'smtp.gmail.com' : '';
            $smtp_port_val = $smtp_user ? 587 : 587;
            $stmt2 = $pdo->prepare('INSERT INTO settings
                (id, company_name, manager_name, manager_email, manager_whatsapp, whatsapp_phone_id, whatsapp_token,
                 smtp_host, smtp_port, smtp_user, smtp_pass, from_email, from_name, timezone)
                VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                  company_name = VALUES(company_name),
                  manager_email = VALUES(manager_email),
                  smtp_host = VALUES(smtp_host),
                  smtp_port = VALUES(smtp_port),
                  smtp_user = VALUES(smtp_user),
                  smtp_pass = VALUES(smtp_pass),
                  from_email = VALUES(from_email),
                  from_name = VALUES(from_name),
                  timezone = VALUES(timezone)');
            $stmt2->execute([
                $company_name, 'Administrator', $admin_email, '', '', '', '',
                $smtp_host_val, $smtp_port_val, $smtp_user, $smtp_pass,
                $smtp_user ?: $admin_email, $company_name, $tz_safe,
            ]);

            // Write config.php
            if (!write_config($db_host, $db_name, $db_user, $db_pass, $tz_safe)) {
                throw new RuntimeException('Could not write config.php — check directory permissions.');
            }

            // Write installed.lock
            file_put_contents(__DIR__ . '/installed.lock', 'Installed at ' . date('c') . "\n");

            // ── Success screen ────────────────────────────────────────────────
            $login_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                . '://' . htmlspecialchars($_SERVER['HTTP_HOST'])
                . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/index.php';
?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Installation Complete — Rent Manager AED</title>
<script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-6">
<div class="bg-white rounded-xl shadow-lg p-8 max-w-lg w-full">
  <div class="text-5xl text-center mb-4">✅</div>
  <h1 class="text-2xl font-bold text-green-700 text-center mb-2">Installation Complete!</h1>
  <p class="text-gray-500 text-center mb-6">Rent Manager AED has been installed successfully.</p>
  <div class="bg-gray-50 rounded-lg p-4 mb-6 space-y-1 text-sm text-gray-700">
    <p>✔ Database tables created</p>
    <p>✔ Admin account created (<strong><?= htmlspecialchars($admin_email) ?></strong>)</p>
    <p>✔ <code>config.php</code> written</p>
    <p>✔ <code>installed.lock</code> created</p>
    <p>✔ <code>uploads/deposits/</code> directory created</p>
    <?php if ($smtp_user): ?><p>✔ SMTP configured (<?= htmlspecialchars($smtp_user) ?>)</p><?php endif; ?>
  </div>
  <a href="<?= $login_url ?>" class="block w-full text-center bg-red-600 hover:bg-red-700 text-white font-semibold py-3 rounded-lg transition mb-4">
    Go to Login →
  </a>
  <div class="bg-yellow-50 border border-yellow-300 rounded-lg p-4 text-sm text-yellow-800">
    <strong>⚠️ Security:</strong> Please <strong>delete <code>install.php</code></strong> from your server immediately. Leaving it accessible is a security risk.
  </div>
</div>
</body></html>
<?php
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
            $step  = 1; // show the form again so user can fix and retry
            // Clean up partial config if written
            if (file_exists(__DIR__ . '/config.php')) {
                @unlink(__DIR__ . '/config.php');
            }
        }
    } else {
        $step = 1; // re-show step 1 form with the error
    }
}

// ── Common timezones list ─────────────────────────────────────────────────────
$timezones = [
    'Asia/Dubai' => 'Asia/Dubai (UAE)',
    'Asia/Riyadh' => 'Asia/Riyadh (Saudi Arabia)',
    'Asia/Kuwait' => 'Asia/Kuwait',
    'Asia/Qatar' => 'Asia/Qatar',
    'Asia/Bahrain' => 'Asia/Bahrain',
    'Asia/Muscat' => 'Asia/Muscat (Oman)',
    'Asia/Karachi' => 'Asia/Karachi',
    'Asia/Kolkata' => 'Asia/Kolkata (India)',
    'Asia/Manila' => 'Asia/Manila',
    'Asia/Cairo' => 'Asia/Cairo (Egypt)',
    'Africa/Nairobi' => 'Africa/Nairobi (Kenya)',
    'Europe/London' => 'Europe/London (UK)',
    'UTC' => 'UTC',
];

// ── Page output ───────────────────────────────────────────────────────────────
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Install — Rent Manager AED</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen py-10 px-4">

<div class="max-w-xl mx-auto">
  <!-- Header -->
  <div class="text-center mb-8">
    <div class="inline-flex items-center justify-center w-16 h-16 bg-red-600 rounded-2xl mb-4">
      <span class="text-white text-2xl">🏠</span>
    </div>
    <h1 class="text-3xl font-bold text-gray-900">Rent Manager AED</h1>
    <p class="text-gray-500 mt-1">Web Installer</p>
  </div>

  <!-- Requirements Card (always visible) -->
  <div class="bg-white rounded-xl shadow p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Requirements Check</h2>
    <ul class="space-y-2">
      <?php foreach ($checks as $c): ?>
      <li class="flex items-center gap-3 text-sm">
        <?php if ($c['ok']): ?>
          <span class="text-green-600 text-lg">✅</span>
          <span class="text-gray-700"><?= htmlspecialchars($c['label']) ?></span>
        <?php else: ?>
          <span class="text-red-600 text-lg">❌</span>
          <span class="text-red-700 font-medium"><?= htmlspecialchars($c['label']) ?></span>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php if (!$all_ok): ?>
    <div class="mt-4 bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">
      Please fix the items above before continuing.
    </div>
    <?php endif; ?>
  </div>

  <?php if ($all_ok): ?>
  <!-- Installation Form -->
  <div class="bg-white rounded-xl shadow p-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-1">Database &amp; Admin Setup</h2>
    <p class="text-sm text-gray-500 mb-5">Fill in the details below and click <strong>Install Now</strong>.</p>

    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-300 rounded-lg p-4 mb-5 text-sm text-red-700">
      <strong>Error:</strong> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="post" novalidate class="space-y-5">
      <input type="hidden" name="step" value="2">

      <!-- Database Section -->
      <fieldset>
        <legend class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">Database</legend>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm text-gray-600 mb-1">DB Host <span class="text-red-500">*</span></label>
            <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">DB Name <span class="text-red-500">*</span></label>
            <input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" required>
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">DB User <span class="text-red-500">*</span></label>
            <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" required>
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">DB Password</label>
            <input type="password" name="db_pass"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
          </div>
        </div>
      </fieldset>

      <hr class="border-gray-200">

      <!-- Admin Account -->
      <fieldset>
        <legend class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">Admin Account</legend>
        <div class="space-y-3">
          <div>
            <label class="block text-sm text-gray-600 mb-1">Admin Email <span class="text-red-500">*</span></label>
            <input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" required>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm text-gray-600 mb-1">Admin Password <span class="text-red-500">*</span></label>
              <input type="password" name="admin_pass" minlength="8"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" required>
              <p class="text-xs text-gray-400 mt-1">Min. 8 characters</p>
            </div>
            <div>
              <label class="block text-sm text-gray-600 mb-1">Confirm Password <span class="text-red-500">*</span></label>
              <input type="password" name="admin_pass2" minlength="8"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" required>
            </div>
          </div>
        </div>
      </fieldset>

      <hr class="border-gray-200">

      <!-- App Settings -->
      <fieldset>
        <legend class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">App Settings</legend>
        <div class="grid grid-cols-2 gap-3">
          <div class="col-span-2">
            <label class="block text-sm text-gray-600 mb-1">Company Name <span class="text-red-500">*</span></label>
            <input type="text" name="company_name" value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" required>
          </div>
          <div class="col-span-2">
            <label class="block text-sm text-gray-600 mb-1">Timezone</label>
            <select name="timezone" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
              <?php foreach ($timezones as $tz_val => $tz_label): ?>
              <option value="<?= htmlspecialchars($tz_val) ?>" <?= (($_POST['timezone'] ?? 'Asia/Dubai') === $tz_val) ? 'selected' : '' ?>>
                <?= htmlspecialchars($tz_label) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </fieldset>

      <hr class="border-gray-200">

      <!-- SMTP (optional) -->
      <fieldset>
        <legend class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-1">SMTP Email <span class="text-gray-400 font-normal normal-case">(optional)</span></legend>
        <p class="text-xs text-gray-400 mb-3">Used for sending email receipts, rent reminders and password resets. Gmail App Password recommended.</p>
        <div class="space-y-3">
          <div>
            <label class="block text-sm text-gray-600 mb-1">Gmail Address</label>
            <input type="email" name="smtp_user" value="<?= htmlspecialchars($_POST['smtp_user'] ?? '') ?>"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">Gmail App Password</label>
            <input type="password" name="smtp_pass"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
          </div>
        </div>
      </fieldset>

      <button type="submit"
        class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-3 rounded-lg transition text-base">
        Install Now →
      </button>
    </form>
  </div>
  <?php endif; ?>

  <p class="text-center text-xs text-gray-400 mt-6">Rent Manager AED — Web Installer</p>
</div>

</body>
</html>