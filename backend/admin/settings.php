<?php
require __DIR__ . '/_bootstrap.php';
require_admin();
check_csrf();

$keys = ['app_name', 'support_phone', 'support_whatsapp', 'support_email', 'upi_id', 'otp_mode', 'msg91_authkey', 'msg91_template_id', 'gps_match_radius_m', 'expiry_reminder_days', 'default_city_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('action') === 'save') { foreach ($keys as $k) if (isset($_POST[$k])) set_setting($k, post($k)); flash('Settings saved'); }
    if (post('action') === 'password') {
        $a = one("SELECT * FROM admins WHERE id = ?", [admin()['id']]);
        if (!password_verify(post('current'), $a['password_hash'])) flash('Current password is wrong', 'err');
        elseif (strlen(post('new')) < 8) flash('New password must be at least 8 characters', 'err');
        else { q("UPDATE admins SET password_hash = ?, email = ?, name = ? WHERE id = ?", [password_hash(post('new'), PASSWORD_DEFAULT), post('email') ?: $a['email'], post('name') ?: $a['name'], $a['id']]); $_SESSION['admin']['name'] = post('name') ?: $a['name']; flash('Login details updated'); }
    }
    if (post('action') === 'admin_add') {
        if (strlen(post('password')) < 8) flash('Password must be at least 8 characters', 'err');
        else { q("INSERT INTO admins (name, email, password_hash) VALUES (?,?,?)", [post('name'), post('email'), password_hash(post('password'), PASSWORD_DEFAULT)]); flash('Admin added'); }
    }
    redirect('settings.php');
}
$s = fn($k) => h(setting($k));
$me = one("SELECT * FROM admins WHERE id = ?", [admin()['id']]);
page_header('Settings', 'settings', 'App details, OTP / SMS, payments and admin logins');
?>
<div class="grid k2">
<form method="post" class="card f"><?= csrf_field() ?><h3>App & support</h3>
<label>App name</label><input type="text" name="app_name" value="<?= $s('app_name') ?>">
<div class="fr"><div><label>Support phone</label><input type="text" name="support_phone" value="<?= $s('support_phone') ?>"></div><div><label>Support WhatsApp</label><input type="text" name="support_whatsapp" value="<?= $s('support_whatsapp') ?>"></div></div>
<label>Support email</label><input type="text" name="support_email" value="<?= $s('support_email') ?>">
<label>UPI ID (shops pay plans here)</label><input type="text" name="upi_id" value="<?= $s('upi_id') ?>">
<div class="fr"><div><label>Default city</label><select name="default_city_id"><?php foreach (all("SELECT id, name FROM cities ORDER BY name") as $c): ?><option value="<?= $c['id'] ?>" <?= setting('default_city_id') == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></div>
<div><label>GPS match radius (m)</label><input type="number" name="gps_match_radius_m" value="<?= $s('gps_match_radius_m') ?>"></div></div>
<label>Plan expiry reminder (days before)</label><input type="number" name="expiry_reminder_days" value="<?= $s('expiry_reminder_days') ?>">
<h3 style="margin-top:18px">OTP login</h3>
<label>Mode</label><select name="otp_mode"><option value="demo" <?= setting('otp_mode') === 'demo' ? 'selected' : '' ?>>Demo — OTP shown on screen (testing)</option><option value="msg91" <?= setting('otp_mode') === 'msg91' ? 'selected' : '' ?>>MSG91 — real SMS</option></select>
<div class="fr"><div><label>MSG91 auth key</label><input type="text" name="msg91_authkey" value="<?= $s('msg91_authkey') ?>"></div><div><label>MSG91 OTP template ID</label><input type="text" name="msg91_template_id" value="<?= $s('msg91_template_id') ?>"></div></div>
<button class="btn p" style="margin-top:14px" name="action" value="save">Save settings</button>
</form>
<div>
<form method="post" class="card f"><?= csrf_field() ?><h3>My login</h3>
<div class="fr"><div><label>Name</label><input type="text" name="name" value="<?= h($me['name']) ?>"></div><div><label>Email</label><input type="email" name="email" value="<?= h($me['email']) ?>"></div></div>
<label>Current password</label><input type="password" name="current" required><label>New password</label><input type="password" name="new" required minlength="8">
<button class="btn n" style="margin-top:12px" name="action" value="password">Update login</button></form>
<form method="post" class="card f" style="margin-top:14px"><?= csrf_field() ?><h3>Admins</h3>
<?php foreach (all("SELECT name, email FROM admins") as $a): ?><div style="padding:5px 0;border-bottom:1px solid #F0F2F7"><?= h($a['name']) ?> <small style="color:var(--mute)"><?= h($a['email']) ?></small></div><?php endforeach; ?>
<div class="fr3"><div><label>Name</label><input type="text" name="name" required></div><div><label>Email</label><input type="email" name="email" required></div><div><label>Password</label><input type="password" name="password" required></div></div>
<button class="btn" style="margin-top:12px" name="action" value="admin_add">Add admin</button></form>
<div class="card" style="margin-top:14px"><h3>App API</h3><p style="font-size:12.5px;color:var(--mute);margin:0">Android app connects to:<br><code><?= h(base_url()) ?>/api/</code></p></div>
</div></div>
<?php page_footer();
