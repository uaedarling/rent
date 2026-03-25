<?php
require_once 'lib/db.php';
session_start();
if (isset($_POST['email'])) {
    $stmt = db()->prepare('SELECT * FROM users WHERE email=? LIMIT 1');
    $stmt->execute([$_POST['email']]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($u && password_verify($_POST['password'], $u['password'])) {
        $_SESSION['user_id'] = $u['id'];
        header('Location: dashboard.php'); exit;
    } else {
        $err = 'Invalid login';
    }
}
?>
<!doctype html><html><head><meta charset='utf-8'><title>Login</title>
<script src="https://cdn.tailwindcss.com"></script>
</head><body class='bg-gray-50 p-6'>
<div class='max-w-md mx-auto bg-white p-6 rounded shadow'>
<h3 class='text-xl font-semibold mb-4'>Rent Manager AED</h3>
<?php if(!empty($err)) echo '<div class="mb-3 text-red-600">'.htmlspecialchars($err).'</div>'; ?>
<form method='post' class='space-y-3'>
  <div><label class='text-sm'>Email</label><input class='mt-1 block w-full border rounded p-2' name='email'></div>
  <div><label class='text-sm'>Password</label><input class='mt-1 block w-full border rounded p-2' name='password' type='password'></div>
  <div><button class='w-full bg-red-600 text-white py-2 rounded'>Login</button></div>
</form>
</div>
</body></html>