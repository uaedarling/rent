<?php
require_once __DIR__ . '/../lib/functions.php';
$late = get_late_tenants();
if (!$late) exit(0);
$settings = get_settings();
foreach ($late as $L) {
    $MONTH = (new DateTime('now', new DateTimeZone($settings['timezone']??'Asia/Dubai')))->format('F Y');
    $msg = "Rent Reminder (Unit {$L['unit_no']})\nDear {$L['full_name']},\nYour rent for {$MONTH} is overdue. Balance: " . number_format($L['balance'],2) . " AED.";
    if (!empty($L['phone']) && !empty($settings['whatsapp_phone_id']) && !empty($settings['whatsapp_token'])) {
        $url = "https://graph.facebook.com/v20.0/" . $settings['whatsapp_phone_id'] . "/messages";
        $payload = json_encode(['messaging_product'=>'whatsapp','to'=>$L['phone'],'type'=>'text','text'=>['body'=>$msg]]);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $settings['whatsapp_token'], 'Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_exec($ch);
        curl_close($ch);
        log_message('whatsapp', $L['phone'], 'Reminder', $msg, 'sent', '');
    }
    if (!empty($L['email'])) {
        smtp_send($L['email'], "Rent Reminder - $MONTH (Unit {$L['unit_no']})", nl2br(htmlentities($msg)), $settings);
    }
}