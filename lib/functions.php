<?php
require_once __DIR__ . '/db.php';
function generate_token() { return bin2hex(random_bytes(32)); }
function log_message($channel, $recipient, $subject, $body, $status = 'sent', $error = '') {
    $stmt = db()->prepare('INSERT INTO message_logs (channel, recipient, subject, body, status, error) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$channel, $recipient, $subject, $body, $status, $error]);
}
// Send email using PHP mail() or log a warning when SMTP credentials are set.
// For production with SMTP, install PHPMailer via Composer.
function smtp_send($to, $subject, $html, $settings) {
    $from_email = $settings['from_email'] ?? 'no-reply@domain.com';
    $from_name  = $settings['from_name']  ?? 'Rent Manager';
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . $from_name . " <" . $from_email . ">\r\n";

    if (!empty($settings['smtp_host']) && !empty($settings['smtp_user']) && !empty($settings['smtp_pass'])) {
        // SMTP credentials are configured but full SMTP sending requires PHPMailer.
        // Falling back to PHP mail() — install PHPMailer for proper SMTP/TLS support.
        error_log('smtp_send: SMTP credentials set but PHPMailer not installed; using mail() fallback.');
        log_message('email', $to, $subject, strip_tags($html), 'sent', 'smtp-fallback-mail');
    }

    $ok = mail($to, $subject, $html, $headers);
    log_message('email', $to, $subject, strip_tags($html), $ok ? 'sent' : 'failed', $ok ? '' : 'mail-failed');
    return $ok;
}
function send_receipt_email($tenant_id, $payment_id) {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT p.*, t.full_name, t.email, t.ledger_token, u.unit_no FROM payments p JOIN tenants t ON t.id=p.tenant_id JOIN units u ON u.id=t.unit_id WHERE p.id=?');
    $stmt->execute([$payment_id]);
    $row = $stmt->fetch();
    if (!$row) return false;
    $settings = get_settings();
    $company  = htmlspecialchars($settings['company_name'] ?? 'Rent Manager');
    $host        = preg_replace('/[^a-zA-Z0-9.\-:]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $ledger_link = (defined('APP_BASE_URL') ? APP_BASE_URL : 'http://' . $host . '/')
        . 'ledger.php?token=' . $row['ledger_token'];
    $receipt_no = '#' . str_pad($row['id'], 6, '0', STR_PAD_LEFT);
    $period_fmt = date('F Y', strtotime($row['period_ym'] . '-01'));
    $subject = "Payment Receipt – " . $receipt_no . " – Unit " . $row['unit_no'];
    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
        body{font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:20px}
        .card{background:#fff;max-width:600px;margin:0 auto;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1)}
        .header{background:#b91c1c;color:#fff;padding:24px 32px}
        .header h1{margin:0;font-size:22px}.header p{margin:4px 0 0;opacity:.85;font-size:13px}
        .body{padding:24px 32px}
        table{width:100%;border-collapse:collapse;margin-top:16px}
        td{padding:10px 0;border-bottom:1px solid #eee;font-size:14px}
        td:first-child{color:#555;width:45%}td:last-child{font-weight:600}
        .paid-stamp{display:inline-block;border:3px solid #16a34a;color:#16a34a;padding:6px 18px;
            border-radius:4px;font-size:20px;font-weight:700;letter-spacing:2px;transform:rotate(-5deg);margin-top:16px}
        .footer{background:#f9f9f9;padding:16px 32px;font-size:12px;color:#888;text-align:center}
        a{color:#b91c1c}
    </style></head><body>
    <div class="card">
      <div class="header">
        <h1>' . $company . '</h1>
        <p>Payment Receipt</p>
      </div>
      <div class="body">
        <table>
          <tr><td>Receipt No.</td><td>' . htmlspecialchars($receipt_no) . '</td></tr>
          <tr><td>Issue Date</td><td>' . htmlspecialchars(date('d M Y')) . '</td></tr>
          <tr><td>Tenant</td><td>' . htmlspecialchars($row['full_name']) . '</td></tr>
          <tr><td>Unit</td><td>' . htmlspecialchars($row['unit_no']) . '</td></tr>
          <tr><td>Period</td><td>' . htmlspecialchars($period_fmt) . '</td></tr>
          <tr><td>Amount Paid</td><td>AED ' . number_format((float)$row['amount_aed'], 2) . '</td></tr>
          <tr><td>Payment Date</td><td>' . htmlspecialchars($row['paid_at']) . '</td></tr>
          <tr><td>Payment Method</td><td>' . htmlspecialchars($row['method']) . '</td></tr>
        </table>
        <div><span class="paid-stamp">PAID</span></div>
        <p style="margin-top:20px;font-size:13px">
          <a href="' . $ledger_link . '">View your full payment history (ledger)</a>
        </p>
      </div>
      <div class="footer">Thank you for your payment. This is an automated receipt.</div>
    </div>
    </body></html>';
    return smtp_send($row['email'], $subject, $html, $settings);
}
function send_password_reset_email($to, $reset_link, $settings) {
    $company = htmlspecialchars($settings['company_name'] ?? 'Rent Manager');
    $subject = 'Password Reset Request';
    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
        body{font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:20px}
        .card{background:#fff;max-width:600px;margin:0 auto;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1)}
        .header{background:#b91c1c;color:#fff;padding:24px 32px}
        .header h1{margin:0;font-size:22px}.header p{margin:4px 0 0;opacity:.85;font-size:13px}
        .body{padding:24px 32px;font-size:14px;color:#333;line-height:1.6}
        .btn{display:inline-block;background:#b91c1c;color:#fff;padding:12px 28px;border-radius:4px;text-decoration:none;font-weight:700;margin:20px 0}
        .footer{background:#f9f9f9;padding:16px 32px;font-size:12px;color:#888;text-align:center}
    </style></head><body>
    <div class="card">
      <div class="header"><h1>' . $company . '</h1><p>Password Reset</p></div>
      <div class="body">
        <p>You requested a password reset. Click the button below to set a new password. This link expires in 1 hour.</p>
        <a href="' . htmlspecialchars($reset_link) . '" class="btn">Reset Password</a>
        <p>If you did not request this, you can safely ignore this email.</p>
        <p style="font-size:12px;color:#999">Or copy this link: ' . htmlspecialchars($reset_link) . '</p>
      </div>
      <div class="footer">This is an automated message from ' . $company . '. Do not reply.</div>
    </div>
    </body></html>';
    return smtp_send($to, $subject, $html, $settings);
}
function send_whatsapp_receipt($tenant_id, $payment_id) {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT p.*, t.full_name, t.phone, u.unit_no FROM payments p JOIN tenants t ON t.id=p.tenant_id JOIN units u ON u.id=t.unit_id WHERE p.id=?');
    $stmt->execute([$payment_id]);
    $row = $stmt->fetch();
    if (!$row) return false;
    $settings = get_settings();
    $phone_id = $settings['whatsapp_phone_id'] ?? '';
    $token    = $settings['whatsapp_token']    ?? '';
    $template = $settings['whatsapp_template'] ?? 'payment_receipt';
    if (!$phone_id || !$token) return false;
    $to = preg_replace('/[^0-9]/', '', $row['phone'] ?? '');
    if (!$to) return false;
    $scheme      = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host        = preg_replace('/[^a-zA-Z0-9.\-]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $hmac        = hash_hmac('sha256', $payment_id, DB_PASS);
    $receipt_url = (defined('APP_BASE_URL') ? APP_BASE_URL : $scheme . '://' . $host . '/')
        . 'receipt.php?id=' . intval($payment_id) . '&token=' . $hmac;
    $period_fmt  = date('F Y', strtotime($row['period_ym'] . '-01'));
    $params = [
        ['type' => 'text', 'text' => $row['full_name']],
        ['type' => 'text', 'text' => number_format((float)$row['amount_aed'], 2)],
        ['type' => 'text', 'text' => $row['unit_no']],
        ['type' => 'text', 'text' => $period_fmt],
        ['type' => 'text', 'text' => $row['paid_at']],
        ['type' => 'text', 'text' => $receipt_url],
    ];
    $payload = json_encode([
        'messaging_product' => 'whatsapp',
        'to'                => $to,
        'type'              => 'template',
        'template'          => [
            'name'       => $template,
            'language'   => ['code' => 'en'],
            'components' => [[
                'type'       => 'body',
                'parameters' => $params,
            ]],
        ],
    ]);
    $url = 'https://graph.facebook.com/v19.0/' . $phone_id . '/messages';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
    ]);
    $resp  = curl_exec($ch);
    $err   = curl_error($ch);
    $http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $ok = ($http >= 200 && $http < 300 && !$err);
    log_message('whatsapp', $to, 'payment_receipt', $receipt_url, $ok ? 'sent' : 'failed', $err ?: ($ok ? '' : $resp));
    return $ok;
}
function get_late_tenants() {
    $settings = get_settings();
    $tz = $settings['timezone'] ?? 'Asia/Dubai';
    $ym = (new DateTime('now', new DateTimeZone($tz)))->format('Y-m');
    $pdo = db();
    $stmt = $pdo->query("SELECT t.id AS tenant_id, t.full_name, t.email, t.phone, t.due_day, t.grace_days, u.unit_no, u.monthly_rent_aed FROM tenants t JOIN units u ON u.id=t.unit_id WHERE u.status='active'");
    $rows = $stmt->fetchAll();
    $late = [];
    foreach ($rows as $r) {
        $due_day = max(1, min(28, (int)$r['due_day']));
        $due = new DateTime(date('Y-m-') . str_pad($due_day, 2, '0', STR_PAD_LEFT), new DateTimeZone($tz));
        $cutoff = clone $due;
        $grace = (int)$r['grace_days'];
        if ($grace) $cutoff->modify("+{$grace} days");
        $p = $pdo->prepare('SELECT COALESCE(SUM(amount_aed),0) FROM payments WHERE tenant_id=? AND period_ym=?');
        $p->execute([$r['tenant_id'], $ym]);
        $paid = (float)$p->fetchColumn();
        $rent = (float)$r['monthly_rent_aed'];
        $now = new DateTime('now', new DateTimeZone($tz));
        if ($now > $cutoff && $paid + 0.0001 < $rent) {
            $late[] = ['tenant_id' => $r['tenant_id'], 'full_name' => $r['full_name'], 'email' => $r['email'], 'phone' => $r['phone'], 'unit_no' => $r['unit_no'], 'rent' => $rent, 'paid' => $paid, 'balance' => max(0, $rent - $paid), 'period_ym' => $ym];
        }
    }
    return $late;
}
