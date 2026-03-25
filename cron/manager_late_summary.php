<?php
require_once __DIR__ . '/../lib/functions.php';
$settings = get_settings();
$late = get_late_tenants();
if (!$late || empty($settings['manager_whatsapp'])) exit(0);
$MONTH = (new DateTime('now', new DateTimeZone($settings['timezone']??'Asia/Dubai')))->format('F Y');
$lines = ["Late Payments - $MONTH"];
foreach ($late as $L) {
    $lines[] = "{$L['full_name']} (Unit {$L['unit_no']}) - " . number_format($L['balance'],2) . " AED";
}
$lines[] = "Total late: " . count($late);
$text = implode("\n", $lines);
if (!empty($settings['whatsapp_phone_id']) && !empty($settings['whatsapp_token'])) {
    $url = "https://graph.facebook.com/v20.0/" . $settings['whatsapp_phone_id'] . "/messages";
    $payload = json_encode(['messaging_product'=>'whatsapp','to'=>$settings['manager_whatsapp'],'type'=>'text','text'=>['body'=>$text]]);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $settings['whatsapp_token'], 'Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_exec($ch);
    curl_close($ch);
    log_message('whatsapp', $settings['manager_whatsapp'], 'Manager Summary', $text, 'sent', '');
}