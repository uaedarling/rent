<?php
require_once 'lib/auth.php';
require_once 'lib/db.php';
require_login();
$pdo = db();
$settings = get_settings();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { header('Location: settings.php'); exit; }
    $stmt = $pdo->prepare('INSERT INTO settings (id, company_name, manager_name, manager_email, manager_whatsapp, whatsapp_phone_id, whatsapp_token, whatsapp_template, smtp_host, smtp_port, smtp_user, smtp_pass, from_email, from_name, timezone)
        VALUES (1,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
            company_name=VALUES(company_name), manager_name=VALUES(manager_name),
            manager_email=VALUES(manager_email), manager_whatsapp=VALUES(manager_whatsapp),
            whatsapp_phone_id=VALUES(whatsapp_phone_id), whatsapp_token=VALUES(whatsapp_token),
            whatsapp_template=VALUES(whatsapp_template),
            smtp_host=VALUES(smtp_host), smtp_port=VALUES(smtp_port),
            smtp_user=VALUES(smtp_user), smtp_pass=VALUES(smtp_pass),
            from_email=VALUES(from_email), from_name=VALUES(from_name), timezone=VALUES(timezone)');
    $stmt->execute([
        $_POST['company_name'], $_POST['manager_name'], $_POST['manager_email'],
        $_POST['manager_whatsapp'], $_POST['whatsapp_phone_id'], $_POST['whatsapp_token'],
        $_POST['whatsapp_template'] ?? 'payment_receipt',
        $_POST['smtp_host'], $_POST['smtp_port'], $_POST['smtp_user'], $_POST['smtp_pass'],
        $_POST['from_email'], $_POST['from_name'], $_POST['timezone'],
    ]);
    header('Location: settings.php'); exit;
}
$csrf = csrf_token();
?>
<!doctype html><html><head><meta charset='utf-8'><title>Settings</title>
<script src="https://cdn.tailwindcss.com"></script>
</head><body class='bg-gray-50'>
<?php require 'lib/nav.php'; ?>
<div class='max-w-4xl mx-auto px-4'>
<div class='bg-white p-4 rounded shadow mb-4'>
<h3 class='text-lg font-semibold mb-2'>Settings</h3>
<form method='post' class='space-y-3'>
  <input type='hidden' name='csrf_token' value='<?= htmlspecialchars($csrf) ?>'>
  <div><label class='text-sm'>Company name</label><input name='company_name' class='mt-1 block w-full border p-2 rounded' value='<?= htmlspecialchars($settings['company_name'] ?? '') ?>'></div>
  <div><label class='text-sm'>Manager name</label><input name='manager_name' class='mt-1 block w-full border p-2 rounded' value='<?= htmlspecialchars($settings['manager_name'] ?? '') ?>'></div>
  <div class='grid grid-cols-2 gap-2'>
    <div><label class='text-sm'>Manager email</label><input name='manager_email' class='mt-1 block w-full border p-2 rounded' value='<?= htmlspecialchars($settings['manager_email'] ?? '') ?>'></div>
    <div><label class='text-sm'>Manager WhatsApp (E.164)</label><input name='manager_whatsapp' class='mt-1 block w-full border p-2 rounded' value='<?= htmlspecialchars($settings['manager_whatsapp'] ?? '') ?>'></div>
  </div>
  <h5 class='mt-2 font-medium'>WhatsApp Cloud API</h5>
  <div class='grid grid-cols-2 gap-2'>
    <input name='whatsapp_phone_id' class='border p-2 rounded' placeholder='Phone ID' value='<?= htmlspecialchars($settings['whatsapp_phone_id'] ?? '') ?>'>
    <input name='whatsapp_token' class='border p-2 rounded' placeholder='Token' value='<?= htmlspecialchars($settings['whatsapp_token'] ?? '') ?>'>
  </div>
  <div>
    <label class='text-sm'>WhatsApp Template Name</label>
    <input name='whatsapp_template' class='mt-1 block w-full border p-2 rounded' placeholder='payment_receipt' value='<?= htmlspecialchars($settings['whatsapp_template'] ?? 'payment_receipt') ?>'>
  </div>
  <div class='bg-blue-50 border border-blue-200 rounded p-3 text-sm text-blue-800'>
    <strong>Template format:</strong> Your approved WhatsApp template must have 6 body variables:<br>
    <code class='text-xs bg-blue-100 px-1 rounded block mt-1'>
      "Dear {{1}}, we received your payment of AED {{2}} for unit {{3}} period {{4}} on {{5}}. View your receipt: {{6}} Thank you!"
    </code>
    <p class='mt-1 text-xs text-blue-700'>{{1}} = Tenant name, {{2}} = Amount, {{3}} = Unit, {{4}} = Period, {{5}} = Payment date, {{6}} = Receipt link (auto-generated)</p>
  </div>
  <h5 class='mt-2 font-medium'>SMTP</h5>
  <div class='grid grid-cols-2 gap-2'>
    <input name='smtp_host' class='border p-2 rounded' placeholder='smtp.gmail.com' value='<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>'>
    <input name='smtp_port' class='border p-2 rounded' placeholder='587' value='<?= htmlspecialchars($settings['smtp_port'] ?? '') ?>'>
    <input name='smtp_user' class='border p-2 rounded' placeholder='you@gmail.com' value='<?= htmlspecialchars($settings['smtp_user'] ?? '') ?>'>
    <input name='smtp_pass' class='border p-2 rounded' placeholder='App password' value='<?= htmlspecialchars($settings['smtp_pass'] ?? '') ?>'>
  </div>
  <div class='grid grid-cols-2 gap-2'>
    <input name='from_email' class='border p-2 rounded' placeholder='From email' value='<?= htmlspecialchars($settings['from_email'] ?? '') ?>'>
    <input name='from_name' class='border p-2 rounded' placeholder='From name' value='<?= htmlspecialchars($settings['from_name'] ?? 'Rent Manager') ?>'>
  </div>
  <div><label class='text-sm'>Timezone</label><input name='timezone' class='mt-1 block w-full border p-2 rounded' value='<?= htmlspecialchars($settings['timezone'] ?? 'Asia/Dubai') ?>'></div>
  <button class='mt-3 bg-red-600 text-white px-4 py-2 rounded'>Save Settings</button>
</form>
</div>
</div>
</body></html>
