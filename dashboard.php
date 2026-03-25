<?php
require_once 'lib/db.php';
session_start();
if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }
if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$pdo = db();
$counts = [];
$counts['units'] = $pdo->query('SELECT COUNT(*) FROM units')->fetchColumn();
$counts['tenants'] = $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
$counts['payments'] = $pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn();
?>
<!doctype html><html><head><meta charset='utf-8'><title>Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
</head><body class='bg-gray-50 p-6'>
<div class='max-w-4xl mx-auto'>
<div class='flex items-center justify-between mb-6'>
  <h3 class='text-2xl font-semibold'>Dashboard</h3>
  <div><a href='index.php?logout=1' class='text-sm text-gray-600'>Logout</a></div>
</div>
<p class='mb-4 space-x-2'>
<a href='units.php' class='inline-block bg-red-600 text-white px-3 py-2 rounded'>Units</a>
<a href='tenants.php' class='inline-block border border-gray-200 px-3 py-2 rounded'>Tenants</a>
<a href='payments.php' class='inline-block border border-gray-200 px-3 py-2 rounded'>Payments</a>
<a href='settings.php' class='inline-block border border-gray-200 px-3 py-2 rounded'>Settings</a>
</p>
<div class='grid grid-cols-3 gap-4'>
  <div class='bg-white p-4 rounded shadow'>Units<br><strong class='text-2xl'><?= $counts['units'] ?></strong></div>
  <div class='bg-white p-4 rounded shadow'>Tenants<br><strong class='text-2xl'><?= $counts['tenants'] ?></strong></div>
  <div class='bg-white p-4 rounded shadow'>Payments<br><strong class='text-2xl'><?= $counts['payments'] ?></strong></div>
</div>
</div>
</body></html>