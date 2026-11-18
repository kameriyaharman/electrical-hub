<?php
/** Public page: delete your Electrical Market Hub account (linked from Google Play). */
require __DIR__ . '/lib/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['del_csrf'])) $_SESSION['del_csrf'] = bin2hex(random_bytes(16));

$msg = ''; $ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['del_csrf'], $_POST['csrf'] ?? '')) $msg = 'Session expired, please try again.';
    else {
        $mobile = substr(preg_replace('/\D/', '', $_POST['mobile'] ?? ''), -10);
        $u = strlen($mobile) === 10 ? one("SELECT * FROM users WHERE mobile = ?", [$mobile]) : null;
        if ($u && $u['pin_locked_until'] && strtotime($u['pin_locked_until']) > time()) $msg = 'Too many wrong PINs. Try again after 15 minutes.';
        elseif (!$u || !$u['pin_hash'] || !password_verify((string)($_POST['pin'] ?? ''), $u['pin_hash'])) {
            if ($u) {
                $f = (int)$u['pin_fails'] + 1;
                q($f >= 5 ? "UPDATE users SET pin_fails = 0, pin_locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?" : "UPDATE users SET pin_fails = pin_fails + 1 WHERE id = ?", [$u['id']]);
            }
            $msg = 'Mobile number or PIN is not correct.';
        } elseif (empty($_POST['confirm'])) $msg = 'Please tick the box to confirm.';
        else { delete_user_account($u['id']); error_log('[ACCOUNT] deleted user ' . $u['id'] . ' via web'); $ok = true; }
    }
}
$app = setting('app_name', 'Electrical Market Hub');
$wa = setting('support_whatsapp');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Delete account · <?= h($app) ?></title>
<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#F6F4F2;color:#141414;margin:0}.w{max-width:520px;margin:0 auto;padding:28px 18px}
h1{font-size:22px;margin:0 0 6px}.c{background:#fff;border-radius:16px;padding:20px;margin-top:16px;border:1px solid #E9E4DF}label{display:block;font-weight:600;font-size:13px;margin:12px 0 6px}
input[type=tel],input[type=password]{width:100%;box-sizing:border-box;border:1.5px solid #E9E4DF;border-radius:10px;padding:11px;font:inherit}button{margin-top:16px;width:100%;border:0;border-radius:10px;padding:12px;background:#E5484D;color:#fff;font-weight:700;font-size:15px;cursor:pointer}
.m{padding:10px 12px;border-radius:10px;background:#FDECEC;color:#a12127;margin-top:12px}.ok{background:#E8F7EE;color:#11663a}small,li{color:#5E5852;font-size:13.5px;line-height:1.6}.top{background:#121212;color:#fff}.top .w{padding:16px 18px;font-weight:700}.top b{color:#FF7A00}</style></head>
<body><div class="top"><div class="w">⚡ Electrical Market <b>Hub</b></div></div><div class="w">
<h1>Delete your account</h1>
<small>You can delete your <?= h($app) ?> account and its data here, or inside the app from <b>Account → Delete my account</b>.</small>
<div class="c"><b>What gets deleted</b><ul>
<li>Your profile (name, mobile number, PIN, city)</li>
<li>Your saved shops, reviews and quote requests (with any uploaded lists)</li>
<li>If you are a shop owner: your shop listing, its photos and offers</li>
<li>Your notifications and login sessions</li></ul>
<small>Deletion is permanent and done immediately. Anonymous usage counts (for example, how many times a shop's number was called) are kept without your identity. Records needed by law, if any, are kept only as long as required.</small></div>
<div class="c">
<?php if ($ok): ?><div class="m ok">Your account and its data have been deleted.</div>
<?php else: ?>
<form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['del_csrf']) ?>">
<label>Mobile number</label><input type="tel" name="mobile" maxlength="10" required value="<?= h($_POST['mobile'] ?? '') ?>">
<label>Your 4-digit PIN</label><input type="password" name="pin" inputmode="numeric" maxlength="4" required>
<label style="font-weight:400"><input type="checkbox" name="confirm" value="1"> I understand my account and data will be permanently deleted</label>
<?php if ($msg): ?><div class="m"><?= h($msg) ?></div><?php endif; ?>
<button>Delete my account</button></form>
<small style="display:block;margin-top:12px">Forgot your PIN? <?php if ($wa): ?><a href="https://wa.me/91<?= h($wa) ?>?text=<?= rawurlencode('Please delete my ' . $app . ' account. Mobile: ') ?>">Ask support on WhatsApp</a> and we will delete it for you.<?php else: ?>Contact support.<?php endif; ?></small>
<?php endif; ?></div>
<p><small><a href="privacy.php">Privacy policy</a></small></p></div></body></html>
