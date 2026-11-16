<?php
require __DIR__ . '/_bootstrap.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $a = one("SELECT * FROM admins WHERE email = ?", [post('email')]);
    if ($a && password_verify(post('password'), $a['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin'] = ['id' => $a['id'], 'name' => $a['name'], 'email' => $a['email']];
        redirect('index.php');
    }
    $err = 'Wrong email or password';
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin login · Electrical Hub</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/admin.css?v=3"></head>
<body class="login"><form class="box f" method="post">
<div class="logo"><span class="bolt"><?= icon('bolt') ?></span><span>Electrical<b>Hub</b></span></div>
<p style="text-align:center;color:var(--mute);margin:0 0 14px">Admin panel</p>
<?php if ($err): ?><div class="flash err"><?= h($err) ?></div><?php endif; ?>
<?= csrf_field() ?>
<label>Email</label><input type="email" name="email" required value="<?= h(post('email')) ?>" autofocus>
<label>Password</label><input type="password" name="password" required>
<button class="btn p" style="width:100%;justify-content:center;margin-top:18px;padding:11px">Login</button>
</form></body></html>
