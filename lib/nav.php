<?php
// Call after require_login()
$nav_user = htmlspecialchars($_SESSION['full_name'] ?? 'User');
$nav_role = $_SESSION['role'] ?? '';
$nav_csrf = csrf_token();
$nav_base = htmlspecialchars(APP_BASE_URL);
?>
<nav class="bg-red-700 text-white px-6 py-3 flex items-center justify-between mb-6">
  <span class="font-bold text-lg">Rent Manager AED</span>
  <div class="flex gap-4 text-sm">
    <a href="<?= $nav_base ?>dashboard.php" class="hover:underline">Dashboard</a>
    <a href="<?= $nav_base ?>units.php" class="hover:underline">Units</a>
    <a href="<?= $nav_base ?>tenants.php" class="hover:underline">Tenants</a>
    <a href="<?= $nav_base ?>payments.php" class="hover:underline">Payments</a>
    <a href="<?= $nav_base ?>pending.php" class="hover:underline">Pending</a>
    <a href="<?= $nav_base ?>import.php" class="hover:underline">Import</a>
    <a href="<?= $nav_base ?>ledger.php" class="hover:underline">Ledger</a>
    <a href="<?= $nav_base ?>settings.php" class="hover:underline">Settings</a>
    <?php if ($nav_role === 'admin'): ?>
    <a href="<?= $nav_base ?>admin/users.php" class="hover:underline">Users</a>
    <?php endif; ?>
  </div>
  <div class="text-sm">
    <?= $nav_user ?> &nbsp;
    <a href="<?= $nav_base ?>profile.php" class="hover:underline">My Profile</a> &nbsp;
    <a href="<?= $nav_base ?>logout.php?token=<?= urlencode($nav_csrf) ?>" class="underline">Logout</a>
  </div>
</nav>
