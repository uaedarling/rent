<?php
/**
 * cron/notify_late.php — Late Payment Email Notifier
 *
 * Sends email reminders to tenants whose rent is overdue (past grace period).
 * Safe to run multiple times per day — will not re-notify tenants already
 * emailed today (checks message_logs for today's entries).
 *
 * Usage (add to crontab):
 *   0 9 * * * php /path/to/rent/cron/notify_late.php
 */

require_once __DIR__ . '/../lib/functions.php';

$settings = get_settings();
$tz       = $settings['timezone'] ?? 'Asia/Dubai';
$today    = (new DateTime('now', new DateTimeZone($tz)))->format('Y-m-d');
$pdo      = db();

$late = get_late_tenants();

if (!$late) {
    echo "[{$today}] No late tenants found.\n";
    exit(0);
}

$month = (new DateTime('now', new DateTimeZone($tz)))->format('F Y');

foreach ($late as $tenant) {
    if (empty($tenant['email'])) {
        echo "[{$today}] Skipping {$tenant['full_name']} (no email address).\n";
        continue;
    }

    // Check if we already sent a reminder to this tenant today
    $check = $pdo->prepare(
        "SELECT COUNT(*) FROM message_logs
         WHERE channel = 'email'
           AND recipient = ?
           AND subject LIKE '%Rent Reminder%'
           AND DATE(created_at) = ?"
    );
    $check->execute([$tenant['email'], $today]);
    if ((int) $check->fetchColumn() > 0) {
        echo "[{$today}] Already notified {$tenant['full_name']} today — skipping.\n";
        continue;
    }

    $company  = htmlspecialchars($settings['company_name'] ?? 'Rent Manager');
    $balance  = number_format($tenant['balance'], 2);
    $subject  = "Rent Reminder — {$month} (Unit {$tenant['unit_no']})";
    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
        body{font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:20px}
        .card{background:#fff;max-width:600px;margin:0 auto;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1)}
        .header{background:#b91c1c;color:#fff;padding:24px 32px}
        .header h1{margin:0;font-size:22px}.header p{margin:4px 0 0;opacity:.85;font-size:13px}
        .body{padding:24px 32px;font-size:14px;color:#333;line-height:1.6}
        .amount{font-size:20px;font-weight:700;color:#b91c1c}
        .footer{background:#f9f9f9;padding:16px 32px;font-size:12px;color:#888;text-align:center}
    </style></head><body>
    <div class="card">
      <div class="header">
        <h1>' . $company . '</h1>
        <p>Rent Payment Reminder</p>
      </div>
      <div class="body">
        <p>Dear ' . htmlspecialchars($tenant['full_name']) . ',</p>
        <p>This is a friendly reminder that your rent for <strong>' . htmlspecialchars($month) . '</strong>
           (Unit <strong>' . htmlspecialchars($tenant['unit_no']) . '</strong>) is overdue.</p>
        <p>Outstanding balance: <span class="amount">AED ' . $balance . '</span></p>
        <p>Please arrange payment at your earliest convenience. If you have already paid, kindly disregard this reminder.</p>
        <p>Thank you.</p>
      </div>
      <div class="footer">This is an automated reminder from ' . $company . '.</div>
    </div>
    </body></html>';

    $ok = smtp_send($tenant['email'], $subject, $html, $settings);
    $status = $ok ? 'sent' : 'failed';
    echo "[{$today}] {$status}: {$tenant['full_name']} <{$tenant['email']}> — Unit {$tenant['unit_no']} — AED {$balance}\n";
}
