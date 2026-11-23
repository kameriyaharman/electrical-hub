<?php
/** Shared header/footer for the public website pages (home, privacy, terms, delete account). */
require_once __DIR__ . '/db.php';

function site_info() {
    static $i = null;
    if ($i) return $i;
    $i = [
        'app'   => setting('app_name', 'Electrical Market Hub') ?: 'Electrical Market Hub',
        'email' => setting('support_email'),
        'phone' => setting('support_phone'),
        'wa'    => setting('support_whatsapp'),
        'play'  => 'https://play.google.com/store/apps/details?id=in.electricalhub.app',
    ];
    return $i;
}

function site_head($title, $desc = '') {
    $s = site_info();
    $full = $title ? $title . ' · ' . $s['app'] : $s['app'] . ' — Find electrical shops near you';
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($full) ?></title>
<meta name="description" content="<?= h($desc ?: 'Find electrical shops, products, brands and offers near you. Shop owners can list their shop free.') ?>">
<link rel="icon" href="/assets/site/icon.png">
<style>
:root{--o:#FF7A00;--od:#E56A00;--k:#121212;--bg:#F6F4F2;--line:#E7E2DD;--mut:#6E6760}
*{box-sizing:border-box}body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:var(--bg);color:#141414;margin:0;line-height:1.65;font-size:16px}
a{color:var(--od)}.w{max-width:1040px;margin:0 auto;padding:0 20px}.nr{max-width:760px}
.top{background:var(--k);color:#fff;position:sticky;top:0;z-index:5}.top .w{display:flex;align-items:center;gap:18px;height:62px}
.logo{display:flex;align-items:center;gap:10px;color:#fff;text-decoration:none;font-weight:800;font-size:18px}.logo img{width:34px;height:34px;border-radius:9px}.logo b{color:var(--o)}
.top nav{margin-left:auto;display:flex;gap:18px}.top nav a{color:#ddd;text-decoration:none;font-size:15px}.top nav a:hover{color:#fff}
@media(max-width:640px){.top nav a.hm{display:none}}
.btn{display:inline-flex;align-items:center;gap:8px;background:var(--o);color:#fff;text-decoration:none;font-weight:700;padding:13px 22px;border-radius:12px;font-size:16px}.btn:hover{background:var(--od)}
.btn.o{background:transparent;border:2px solid #fff3;color:#fff}
.doc{padding:30px 0 60px}.doc h1{font-size:30px;margin:6px 0 2px}.doc h2{font-size:19px;margin:28px 0 6px}.doc p,.doc li{color:#2d2a27}small{color:var(--mut)}
footer{background:var(--k);color:#bbb;padding:34px 0;font-size:14px}footer .w{display:flex;flex-wrap:wrap;gap:22px;justify-content:space-between}footer a{color:#ddd;text-decoration:none;margin-right:16px}footer a:hover{color:#fff}
</style></head><body>
<header class="top"><div class="w"><a class="logo" href="/"><img src="/assets/site/icon.png" alt="">Electrical Market <b>Hub</b></a>
<nav><a class="hm" href="/#features">Features</a><a class="hm" href="/#owners">For shop owners</a><a href="/#contact">Contact</a></nav></div></header>
<?php }

function site_foot() {
    $s = site_info(); ?>
<footer><div class="w"><div><b style="color:#fff"><?= h($s['app']) ?></b><br>Find electrical shops near you.<br>© <?= date('Y') ?> <?= h($s['app']) ?></div>
<div><a href="/privacy.php">Privacy Policy</a><a href="/terms.php">Terms of Use</a><a href="/delete-account.php">Delete account</a><a href="/#contact">Contact</a></div></div></footer>
</body></html><?php }
