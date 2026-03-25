<?php
// Call after require_login()
$nav_user = htmlspecialchars($_SESSION['full_name'] ?? 'User');
$nav_role = $_SESSION['role'] ?? '';
$nav_csrf = csrf_token();
?>
<nav class="bg-red-700 text-white px-6 py-3 flex items-center justify-between mb-6">
  <span class="font-bold text-lg">Rent Manager AED</span>
  <div class="flex gap-4 text-sm">
    <a href="/dashboard.php" class="hover:underline">Dashboard</a>
    <a href="/units.php" class="hover:underline">Units</a>
    <a href="/tenants.php" class="hover:underline">Tenants</a>
    <a href="/payments.php" class="hover:underline">Payments</a>
    <a href="/ledger.php" class="hover:underline">Ledger</a>
    <a href="/settings.php" class="hover:underline">Settings</a>
    <?php if ($nav_role === 'admin'): ?>
    <a href="/admin/users.php" class="hover:underline">Users</a>
    <?php endif; ?>
  </div>
  <div class="text-sm">
    <?= $nav_user ?> &nbsp;
    <a href="/logout.php?token=<?= urlencode($nav_csrf) ?>" class="underline">Logout</a>
  </div>
</nav>
