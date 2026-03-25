<?php
// Minimal installer - creates DB schema and initial admin + settings
if (file_exists('installed.lock')) {
    echo "Already installed. Delete installed.lock to reinstall.";
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    // write config.php
    $cfg = "<?php\n";
    $cfg .= "define('DB_HOST','".addslashes($_POST['db_host'])."');\n";
    $cfg .= "define('DB_NAME','".addslashes($_POST['db_name'])."');\n";
    $cfg .= "define('DB_USER','".addslashes($_POST['db_user'])."');\n";
    $cfg .= "define('DB_PASS','".addslashes($_POST['db_pass'])."');\n";
    $cfg .= "date_default_timezone_set('Asia/Dubai');\n";
    $cfg .= "define('CURRENCY','AED');\n";
    $cfg .= "?>\n";
    file_put_contents('config.php', $cfg);
    try {
        $pdo = new PDO("mysql:host=".$_POST['db_host'].";dbname=".$_POST['db_name'].";charset=utf8mb4", $_POST['db_user'], $_POST['db_pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $sql = file_get_contents('schema.sql');
        $pdo->exec($sql);
        // create admin
        $hash = password_hash($_POST['admin_pass'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (email,password,full_name,role) VALUES (?,?,?,?)");
        $stmt->execute([$_POST['admin_email'],$hash,'Administrator','admin']);
        // insert settings row
        $s = $pdo->prepare("INSERT INTO settings (company_name, manager_name, manager_email, manager_whatsapp, whatsapp_phone_id, whatsapp_token, smtp_host, smtp_port, smtp_user, smtp_pass, from_email, from_name, timezone) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $s->execute([$_POST['company_name']?:'Your Company','Manager',$_POST['admin_email'],'','', '','smtp.gmail.com', 587, $_POST['smtp_user'], $_POST['smtp_pass'], $_POST['smtp_user'], 'Building Rent Manager', 'Asia/Dubai']);
        file_put_contents('installed.lock', 'installed at '.date('c'));
        echo "<p>Installed successfully. <a href='index.php'>Open app</a></p>";
        exit;
    } catch (Exception $e) {
        echo "<h3>Error</h3><pre>".htmlspecialchars($e->getMessage())."</pre>";
        exit;
    }
}
?>
<!doctype html><html><head><meta charset='utf-8'><title>Installer</title>
<script src="https://cdn.tailwindcss.com"></script>
</head><body class="bg-gray-50 p-6">
<div class="max-w-3xl mx-auto bg-white p-6 rounded shadow">
<h2 class="text-2xl font-semibold mb-4">Rent Manager AED - Installer</h2>
<form method="post" class="space-y-3">
<div><label class="text-sm">DB Host</label><input name="db_host" class="block w-full border p-2 rounded" value="localhost"></div>
<div><label class="text-sm">DB Name</label><input name="db_name" class="block w-full border p-2 rounded" value="rent_manager"></div>
<div><label class="text-sm">DB User</label><input name="db_user" class="block w-full border p-2 rounded" required></div>
<div><label class="text-sm">DB Pass</label><input name="db_pass" class="block w-full border p-2 rounded"></div>
<hr>
<div><label class="text-sm">Admin Email</label><input name="admin_email" class="block w-full border p-2 rounded" required></div>
<div><label class="text-sm">Admin Password</label><input name="admin_pass" class="block w-full border p-2 rounded" required></div>
<hr>
<div><label class="text-sm">SMTP User (Gmail)</label><input name="smtp_user" class="block w-full border p-2 rounded"></div>
<div><label class="text-sm">SMTP App Password</label><input name="smtp_pass" class="block w-full border p-2 rounded"></div>
<input type="hidden" name="company_name" value="">
<button class="mt-3 bg-red-600 text-white px-4 py-2 rounded">Install</button>
</form>
</div>
</body></html>