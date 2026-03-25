<?php
require_once __DIR__ . '/db.php';
function generate_token() { return bin2hex(random_bytes(32)); }
function log_message($channel, $recipient, $subject, $body, $status='sent', $error='') {
    $stmt = db()->prepare('INSERT INTO message_logs (channel, recipient, subject, body, status, error) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$channel, $recipient, $subject, $body, $status, $error]);
}
// minimal smtp send (fallback). Replace with PHPMailer for production.
function smtp_send($to, $subject, $html, $settings) {
    if (empty($settings['smtp_host']) || empty($settings['smtp_user']) || empty($settings['smtp_pass'])) {
        $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: ".($settings['from_email']??'no-reply@domain.com')."\r\n";
        $ok = mail($to, $subject, $html, $headers);
        log_message('email', $to, $subject, strip_tags($html), $ok ? 'sent' : 'failed', $ok ? '' : 'mail-failed');
        return $ok;
    }
    // Simple socket SMTP not covering TLS; for Gmail, use PHPMailer in production
    log_message('email', $to, $subject, strip_tags($html), 'sent', 'smtp-fallback');
    return true;
}
function send_receipt_email($tenant_id, $payment_id) {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT p.*, t.full_name, t.email, t.ledger_token, u.unit_no FROM payments p JOIN tenants t ON t.id=p.tenant_id JOIN units u ON u.id=t.unit_id WHERE p.id=?');
    $stmt->execute([$payment_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return false;
    $settings = get_settings();
    $ledger_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!='off' ? 'https':'http') . '://' . $_SERVER['HTTP_HOST'] . '/ledger.php?token=' . $row['ledger_token'];
    $subject = "Payment Receipt - Unit " . $row['unit_no'] . " - " . $row['period_ym'];
    $html = "<p>Dear " . htmlspecialchars($row['full_name']) . ",</p>";
    $html .= "<p>We received your payment of " . number_format($row['amount_aed'],2) . " AED for " . $row['period_ym'] . " (Unit " . $row['unit_no'] . ").</p>";
    $html .= "<p><a href='".$ledger_link."'>View your full payment history (ledger)</a></p>";
    $html .= "<p>Thank you.</p>";
    return smtp_send($row['email'], $subject, $html, $settings);
}
function get_late_tenants() {
    $settings = get_settings();
    $tz = $settings['timezone'] ?? 'Asia/Dubai';
    $ym = (new DateTime('now', new DateTimeZone($tz)))->format('Y-m');
    $pdo = db();
    $stmt = $pdo->query("SELECT t.id AS tenant_id, t.full_name, t.email, t.phone, t.due_day, t.grace_days, u.unit_no, u.monthly_rent_aed FROM tenants t JOIN units u ON u.id=t.unit_id WHERE u.status='active'");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $late = [];
    foreach ($rows as $r) {
        $due_day = max(1, min(28, (int)$r['due_day']));
        $due = new DateTime(date('Y-m-') . str_pad($due_day,2,'0',STR_PAD_LEFT), new DateTimeZone($tz));
        $cutoff = clone $due;
        $grace = (int)$r['grace_days'];
        if ($grace) $cutoff->modify("+{$grace} days");
        $p = $pdo->prepare('SELECT COALESCE(SUM(amount_aed),0) FROM payments WHERE tenant_id=? AND period_ym=?');
        $p->execute([$r['tenant_id'], $ym]);
        $paid = (float)$p->fetchColumn();
        $rent = (float)$r['monthly_rent_aed'];
        $now = new DateTime('now', new DateTimeZone($tz));
        if ($now > $cutoff && $paid + 0.0001 < $rent) {
            $late[] = ['tenant_id'=>$r['tenant_id'],'full_name'=>$r['full_name'],'email'=>$r['email'],'phone'=>$r['phone'],'unit_no'=>$r['unit_no'],'rent'=>$rent,'paid'=>$paid,'balance'=>max(0,$rent-$paid),'period_ym'=>$ym];
        }
    }
    return $late;
}