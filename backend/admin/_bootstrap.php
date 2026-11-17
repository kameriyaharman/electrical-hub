<?php
require_once __DIR__ . '/../lib/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('ehub_admin');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function admin() { return $_SESSION['admin'] ?? null; }
function require_admin() {
    if (!admin()) { header('Location: login.php'); exit; }
}
function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function check_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
        http_response_code(400); exit('Session expired. Go back and reload the page.');
    }
}
function flash($msg = null, $type = 'ok') {
    if ($msg !== null) { $_SESSION['flash'] = [$msg, $type]; return; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}
function redirect($url) { header('Location: ' . $url); exit; }
function post($k, $d = '') { return isset($_POST[$k]) ? (is_string($_POST[$k]) ? trim($_POST[$k]) : $_POST[$k]) : $d; }
function get($k, $d = '') { return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }

function plan_badge($plan) {
    $map = ['premium' => 'b-navy', 'featured' => 'b-yellow', 'free' => 'b-grey', 'banner' => 'b-blue'];
    return '<span class="badge ' . ($map[$plan] ?? 'b-grey') . '">' . h(ucfirst($plan)) . '</span>';
}
function status_badge($st) {
    $map = ['verified' => 'b-green', 'active' => 'b-green', 'success' => 'b-green', 'pending' => 'b-amber', 'draft' => 'b-grey',
        'rejected' => 'b-red', 'blocked' => 'b-red', 'flagged' => 'b-red', 'expired' => 'b-grey', 'hidden' => 'b-grey', 'failed' => 'b-red', 'open' => 'b-blue', 'closed' => 'b-grey'];
    return '<span class="badge ' . ($map[$st] ?? 'b-grey') . '">' . h(ucfirst($st)) . '</span>';
}

function page_header($title, $active, $subtitle = '', $actions = '') {
    require_admin();
    run_maintenance();
    $a = admin();
    $pending = (int)val("SELECT COUNT(*) FROM shops WHERE status = 'pending'");
    $flagged = (int)val("SELECT COUNT(*) FROM reviews WHERE status = 'flagged'");
    $notif = $pending + $flagged;
    $nav = [
        'MAIN' => [['index.php', 'dashboard', 'Dashboard', 'grid'], ['shops.php', 'shops', 'Shops & Verification', 'store'],
            ['categories.php', 'categories', 'Categories & Brands', 'tag'], ['products.php', 'products', 'Products', 'box']],
        'PROMOTION' => [['banners.php', 'banners', 'Banners', 'image']],
        'USERS' => [['customers.php', 'customers', 'Customers', 'users'], ['quotes.php', 'quotes', 'Quote Requests', 'file'], ['reviews.php', 'reviews', 'Reviews', 'msg']],
        'SYSTEM' => [['cities.php', 'cities', 'Cities & Areas', 'pin'], ['settings.php', 'settings', 'Settings', 'cog']],
    ];
    $f = flash();
    ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> · <?= h(setting('app_name', APP_NAME)) ?> Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/admin.css?v=3">
</head><body>
<aside class="side" id="side">
  <a class="logo" href="index.php"><span class="bolt"><?= icon('bolt') ?></span><span>Electrical<b>Hub</b></span></a>
  <?php foreach ($nav as $group => $items): ?>
    <div class="grp"><?= $group ?></div>
    <?php foreach ($items as $it): ?>
      <a class="nav <?= $active === $it[1] ? 'on' : '' ?>" href="<?= $it[0] ?>"><?= icon($it[3]) ?><span><?= $it[2] ?></span>
        <?php if ($it[1] === 'shops' && $pending): ?><em><?= $pending ?></em><?php endif; ?>
        <?php if ($it[1] === 'reviews' && $flagged): ?><em><?= $flagged ?></em><?php endif; ?>
      </a>
    <?php endforeach; ?>
  <?php endforeach; ?>
</aside>
<div class="main">
  <header class="top">
    <button class="menu" onclick="document.getElementById('side').classList.toggle('open')"><?= icon('menu') ?></button>
    <form class="search" action="shops.php"><?= icon('search') ?><input name="q" placeholder="Search shop / owner / mobile / city..." value="<?= h(get('q')) ?>"></form>
    <a class="bell" href="shops.php?tab=pending" title="Pending items"><?= icon('bell') ?><?php if ($notif): ?><i><?= $notif ?></i><?php endif; ?></a>
    <div class="me"><span class="av"><?= h(strtoupper(substr($a['name'], 0, 1))) ?></span><div><b><?= h($a['name']) ?></b><small>Super admin</small></div>
      <a href="logout.php" class="lo" title="Logout"><?= icon('logout') ?></a></div>
  </header>
  <section class="content">
    <div class="ph"><div><h1><?= h($title) ?></h1><?php if ($subtitle): ?><p><?= $subtitle ?></p><?php endif; ?></div><div class="acts"><?= $actions ?></div></div>
    <?php if ($f): ?><div class="flash <?= $f[1] ?>"><?= h($f[0]) ?></div><?php endif; ?>
<?php
}

function page_footer() {
    ?>
  </section>
</div>
<script>
document.querySelectorAll('[data-confirm]').forEach(function (el) {
  el.addEventListener('click', function (e) { if (!confirm(el.dataset.confirm)) e.preventDefault(); });
});
document.querySelectorAll('[data-open]').forEach(function (el) {
  el.addEventListener('click', function (e) { e.preventDefault(); document.getElementById(el.dataset.open).classList.add('show'); });
});
document.querySelectorAll('.modal').forEach(function (m) {
  m.addEventListener('click', function (e) { if (e.target === m || e.target.classList.contains('x')) m.classList.remove('show'); });
});
</script>
</body></html>
<?php
}

function icon($n) {
    $p = [
        'bolt' => '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8z"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'store' => '<path d="M3 9l1.5-5h15L21 9M3 9v11h18V9M3 9h18M9 20v-6h6v6"/>',
        'tag' => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'box' => '<path d="M21 8 12 3 3 8v8l9 5 9-5V8zM3 8l9 5 9-5M12 13v8"/>',
        'star' => '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>',
        'card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
        'msg' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'pin' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'cog' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
        'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'rupee' => '<path d="M6 3h12M6 8h12M6 13l8.5 8M6 13h3c6.7 0 6.7-10 0-10"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
    ];
    return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . ($p[$n] ?? '') . '</svg>';
}

/** Activate a subscription: dates, shop plan, linked payment. */
function activate_subscription($subId) {
    $sub = one("SELECT * FROM subscriptions WHERE id = ?", [$subId]);
    if (!$sub) return;
    $days = (int)val("SELECT duration_days FROM plans WHERE code = ?", [$sub['plan_code']]) ?: 30;
    // extend from current expiry if the shop still has time left on the same plan
    $cur = one("SELECT plan, plan_expiry FROM shops WHERE id = ?", [$sub['shop_id']]);
    $from = ($cur && $cur['plan'] === $sub['plan_code'] && $cur['plan_expiry'] && $cur['plan_expiry'] >= date('Y-m-d')) ? date('Y-m-d', strtotime($cur['plan_expiry'] . ' +1 day')) : date('Y-m-d');
    $to = date('Y-m-d', strtotime("$from +" . ($days - 1) . " days"));
    q("UPDATE subscriptions SET status = 'active', start_date = ?, expiry_date = ? WHERE id = ?", [$from, $to, $subId]);
    if (in_array($sub['plan_code'], array_column(all("SELECT code FROM plans"), 'code'), true)) {
        q("UPDATE shops SET plan = ?, plan_expiry = ? WHERE id = ?", [$sub['plan_code'], $to, $sub['shop_id']]);
    }
    if ($sub['payment_id']) q("UPDATE payments SET status = 'success' WHERE id = ?", [$sub['payment_id']]);
    $uid = val("SELECT owner_user_id FROM shops WHERE id = ?", [$sub['shop_id']]);
    if ($uid) q("INSERT INTO notifications (user_id, title, body) VALUES (?,?,?)", [$uid, ucfirst($sub['plan_code']) . ' plan active', 'Valid till ' . date('d M Y', strtotime($to)) . '. Thank you!']);
}

function wa_link($mobile, $text) { return 'https://wa.me/91' . preg_replace('/\D/', '', $mobile) . '?text=' . rawurlencode($text); }

function paginate($total, $per, $page) {
    $pages = max(1, (int)ceil($total / $per));
    if ($pages <= 1) return '';
    $q = $_GET; $html = '<div class="pager">';
    for ($i = 1; $i <= $pages; $i++) { $q['page'] = $i; $html .= '<a class="' . ($i == $page ? 'on' : '') . '" href="?' . h(http_build_query($q)) . '">' . $i . '</a>'; }
    return $html . '</div>';
}
