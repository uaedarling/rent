<?php
require_once 'lib/auth.php';
require_once 'lib/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

// Redirect already-logged-in users
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php'); exit;
}

$err = '';

if (isset($_POST['email'])) {
    // CSRF verification
    if (!csrf_verify()) {
        $err = 'Invalid request. Please try again.';
    } else {
        // Rate limiting: block after 5 failed attempts for 15 minutes
        $now = time();
        if (!empty($_SESSION['login_fails']) && $_SESSION['login_fails'] >= 5) {
            $locked_until = ($_SESSION['login_locked_at'] ?? 0) + 900; // 15 min
            if ($now < $locked_until) {
                $wait = ceil(($locked_until - $now) / 60);
                $err = "Too many failed attempts. Please wait {$wait} minute(s) and try again.";
            } else {
                // Lockout expired — reset counters
                $_SESSION['login_fails'] = 0;
                unset($_SESSION['login_locked_at']);
            }
        }

        if (empty($err)) {
            $stmt = db()->prepare('SELECT * FROM users WHERE email=? LIMIT 1');
            $stmt->execute([$_POST['email']]);
            $u = $stmt->fetch();
            if ($u && password_verify($_POST['password'], $u['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $u['id'];
                $_SESSION['role']      = $u['role'];
                $_SESSION['full_name'] = $u['full_name'];
                $_SESSION['login_fails'] = 0;
                unset($_SESSION['login_locked_at']);
                header('Location: dashboard.php'); exit;
            } else {
                $_SESSION['login_fails'] = ($_SESSION['login_fails'] ?? 0) + 1;
                if ($_SESSION['login_fails'] >= 5) {
                    $_SESSION['login_locked_at'] = $now;
                }
                $err = 'Invalid email or password.';
            }
        }
    }
}

$csrf = csrf_token();
?>
<!doctype html><html><head><meta charset='utf-8'><title>Login</title>
<script src="https://cdn.tailwindcss.com"></script>
</head><body class='bg-gray-50 p-6'>
<div class='max-w-md mx-auto bg-white p-6 rounded shadow'>
<h3 class='text-xl font-semibold mb-4'>Rent Manager AED</h3>
<?php if (!empty($err)): ?>
<div class="mb-3 text-red-600"><?= htmlspecialchars($err) ?></div>
<?php endif; ?>
<form method='post' class='space-y-3'>
  <input type='hidden' name='csrf_token' value='<?= htmlspecialchars($csrf) ?>'>
  <div><label class='text-sm'>Email</label><input class='mt-1 block w-full border rounded p-2' name='email' type='email' autocomplete='username'></div>
  <div><label class='text-sm'>Password</label><input class='mt-1 block w-full border rounded p-2' name='password' type='password' autocomplete='current-password'></div>
  <div><button class='w-full bg-red-600 text-white py-2 rounded'>Login</button></div>
</form>
</div>
</body></html>
