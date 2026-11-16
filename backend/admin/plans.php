<?php
require __DIR__ . '/_bootstrap.php';
require_admin();
check_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)post('id'); $act = post('action');
    if ($act === 'plan_save') {
        $code = strtolower(preg_replace('/[^a-z0-9_]/i', '', post('code')));
        $f = [post('name'), post('tagline'), (int)post('price'), (int)post('duration_days') ?: 30, (int)post('photos_limit'), post('search_position'), post('home_banner') ? 1 : 0, post('quote_priority'), post('features'), (int)post('sort'), post('active') ? 1 : 0];
        if ($id) q("UPDATE plans SET name=?, tagline=?, price=?, duration_days=?, photos_limit=?, search_position=?, home_banner=?, quote_priority=?, features=?, sort=?, active=? WHERE id=?", array_merge($f, [$id]));
        else q("INSERT INTO plans (name, tagline, price, duration_days, photos_limit, search_position, home_banner, quote_priority, features, sort, active, code) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)", array_merge($f, [$code ?: 'plan' . time()]));
        flash('Plan saved');
    } elseif ($act === 'sub_add') {
        $shop = (int)post('shop_id'); $plan = post('plan_code'); $amt = (int)post('amount');
        q("INSERT INTO payments (shop_id, purpose, amount, method, reference, status) VALUES (?,?,?,?,?, 'success')", [$shop, ucfirst($plan) . ' plan', $amt, post('method', 'Cash'), post('reference')]);
        $pid = last_id();
        q("INSERT INTO subscriptions (shop_id, plan_code, area, amount, status, payment_id) VALUES (?,?,?,?, 'pending', ?)", [$shop, $plan, val("SELECT area FROM shops WHERE id = ?", [$shop]), $amt, $pid]);
        activate_subscription(last_id());
        flash('Subscription added and activated');
    } elseif ($act === 'sub_activate') { activate_subscription($id); flash('Subscription activated'); }
    elseif ($act === 'sub_cancel') {
        $sub = one("SELECT * FROM subscriptions WHERE id = ?", [$id]);
        q("UPDATE subscriptions SET status = 'cancelled' WHERE id = ?", [$id]);
        if ($sub && $sub['status'] === 'active') q("UPDATE shops SET plan = 'free', plan_expiry = NULL WHERE id = ? AND plan = ?", [$sub['shop_id'], $sub['plan_code']]);
        if ($sub && $sub['payment_id']) q("UPDATE payments SET status = 'failed' WHERE id = ? AND status = 'pending'", [$sub['payment_id']]);
        flash('Subscription cancelled');
    }
    redirect('plans.php');
}

$plans = all("SELECT p.*, (SELECT COUNT(*) FROM shops s WHERE s.plan = p.code AND s.status = 'verified') active_n FROM plans p ORDER BY sort");
$st = get('st');
$subs = all("SELECT sb.*, s.name shop, s.mobile, s.whatsapp, c.name city FROM subscriptions sb JOIN shops s ON s.id = sb.shop_id LEFT JOIN cities c ON c.id = s.city_id "
    . ($st ? "WHERE sb.status = ?" : '') . " ORDER BY sb.status = 'pending' DESC, sb.id DESC LIMIT 200", $st ? [$st] : []);
$days = (int)setting('expiry_reminder_days', 5);
$expiring = all("SELECT s.id, s.name, s.mobile, s.whatsapp, s.plan, s.plan_expiry FROM shops s WHERE s.plan <> 'free' AND s.plan_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY) ORDER BY s.plan_expiry", [$days]);

page_header('Plans, Featured Listings & Banners', 'plans', 'Subscription plans for shops, featured slots per area, home-page banners, renewals &amp; payments',
    '<a class="btn" href="#" data-open="subNew">+ Add subscription</a><a class="btn p" href="#" data-open="planNew">Create plan</a>');
?>
<div class="plans">
<?php foreach ($plans as $p): ?>
  <div class="plan <?= h($p['code']) ?>">
    <div style="display:flex;justify-content:space-between"><h4><?= h($p['name']) ?></h4><a href="#" data-open="plan<?= $p['id'] ?>" style="font-size:12px;<?= $p['code'] === 'premium' ? 'color:var(--yellow)' : '' ?>">Edit</a></div>
    <div class="pr">₹<?= indian_number($p['price']) ?><?= $p['price'] ? '<small>*/mo</small>' : '' ?></div>
    <div class="ln"><span>Photos</span><b><?= $p['photos_limit'] ?></b></div>
    <div class="ln"><span>Search position</span><b><?= h($p['search_position']) ?></b></div>
    <?php if ($p['home_banner']): ?><div class="ln"><span>Home banner</span><b>Yes</b></div><?php endif; ?>
    <?php if ($p['quote_priority'] !== 'Normal'): ?><div class="ln"><span>Quote requests</span><b><?= h($p['quote_priority']) ?></b></div><?php endif; ?>
    <div class="ac"><?= indian_number($p['active_n']) ?> active<?= $p['active'] ? '' : ' · plan hidden' ?></div>
  </div>
<?php endforeach; ?>
</div>

<?php if ($expiring): ?>
<div class="card" style="margin-bottom:14px"><h3>Expiring in next <?= $days ?> days</h3><div class="tw"><table><tr><th>Shop</th><th>Plan</th><th>Expiry</th><th></th></tr>
<?php foreach ($expiring as $e): $txt = "Hi, your Electrical Hub " . ucfirst($e['plan']) . " plan for {$e['name']} expires on " . date('d M', strtotime($e['plan_expiry'])) . ". Renew via UPI " . setting('upi_id') . " to stay on top of search. Thank you!"; ?>
<tr><td><?= h($e['name']) ?></td><td><?= plan_badge($e['plan']) ?></td><td><?= date('d M Y', strtotime($e['plan_expiry'])) ?></td><td><a class="btn sm g" target="_blank" href="<?= h(wa_link($e['whatsapp'] ?: $e['mobile'], $txt)) ?>">Send WhatsApp reminder</a></td></tr>
<?php endforeach; ?></table></div></div>
<?php endif; ?>

<div class="tabs"><?php foreach (['' => 'All', 'pending' => 'Pending', 'active' => 'Active', 'expired' => 'Expired', 'cancelled' => 'Cancelled'] as $k => $l): ?><a class="<?= $st === $k ? 'on' : '' ?>" href="?st=<?= $k ?>"><?= $l ?></a><?php endforeach; ?></div>
<div class="card" style="padding:6px 8px"><div class="tw"><table>
<tr><th>Shop</th><th>Plan</th><th>Area</th><th>Start</th><th>Expiry</th><th>Amount</th><th>Status</th><th></th></tr>
<?php if (!$subs): ?><tr><td colspan="8" class="empty">No subscriptions</td></tr><?php endif; ?>
<?php foreach ($subs as $s): ?>
<tr><td><b><?= h($s['shop']) ?></b></td><td><?= plan_badge($s['plan_code']) ?></td><td><?= h($s['city']) ?> · <?= h($s['area']) ?></td>
<td><?= $s['start_date'] ? date('d M', strtotime($s['start_date'])) : '—' ?></td><td><?= $s['expiry_date'] ? date('d M', strtotime($s['expiry_date'])) : '—' ?></td>
<td>₹<?= indian_number($s['amount']) ?></td><td><?= status_badge($s['status']) ?></td>
<td style="white-space:nowrap"><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $s['id'] ?>">
<?php if ($s['status'] === 'pending'): ?><button class="btn sm g" name="action" value="sub_activate" data-confirm="Mark payment received and activate?">Activate</button><?php endif; ?>
<?php if (in_array($s['status'], ['pending', 'active'])): ?><button class="btn sm r" name="action" value="sub_cancel" data-confirm="Cancel this subscription?">Cancel</button><?php endif; ?>
</form></td></tr>
<?php endforeach; ?></table></div></div>
<div class="note">💬 Shops get a WhatsApp reminder <?= $days ?> days before plan expiry (use the buttons above) · Payments via UPI <b><?= h(setting('upi_id')) ?></b> — shop pays in the app, you confirm here or in Payments.</div>

<?php foreach (array_merge([['id' => 0, 'code' => '', 'name' => '', 'tagline' => '', 'price' => 0, 'duration_days' => 30, 'photos_limit' => 5, 'search_position' => 'Normal', 'home_banner' => 0, 'quote_priority' => 'Normal', 'features' => '', 'sort' => count($plans), 'active' => 1]], $plans) as $p): ?>
<div class="modal" id="<?= $p['id'] ? 'plan' . $p['id'] : 'planNew' ?>"><form class="box f" method="post"><button type="button" class="x">×</button>
<h3><?= $p['id'] ? 'Edit plan · ' . h($p['name']) : 'Create plan' ?></h3><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>">
<?php if (!$p['id']): ?><label>Code (short, no spaces)</label><input type="text" name="code" required placeholder="gold"><?php endif; ?>
<div class="fr"><div><label>Name</label><input type="text" name="name" required value="<?= h($p['name']) ?>"></div><div><label>Tagline</label><input type="text" name="tagline" value="<?= h($p['tagline']) ?>"></div></div>
<div class="fr3"><div><label>Price ₹</label><input type="number" name="price" value="<?= $p['price'] ?>"></div><div><label>Duration (days)</label><input type="number" name="duration_days" value="<?= $p['duration_days'] ?>"></div><div><label>Photos limit</label><input type="number" name="photos_limit" value="<?= $p['photos_limit'] ?>"></div></div>
<div class="fr"><div><label>Search position</label><input type="text" name="search_position" value="<?= h($p['search_position']) ?>"></div><div><label>Quote requests</label><select name="quote_priority"><?php foreach (['Normal', 'First'] as $o): ?><option <?= $p['quote_priority'] === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div></div>
<label>Features (one per line — shown in app)</label><textarea name="features"><?= h($p['features']) ?></textarea>
<div class="fr"><div><label>Sort</label><input type="number" name="sort" value="<?= $p['sort'] ?>"></div><div><label>&nbsp;</label><label><input type="checkbox" name="home_banner" value="1" <?= $p['home_banner'] ? 'checked' : '' ?>> Home banner slot</label><label><input type="checkbox" name="active" value="1" <?= $p['active'] ? 'checked' : '' ?>> Active</label></div></div>
<button class="btn p" style="margin-top:12px" name="action" value="plan_save">Save plan</button>
</form></div>
<?php endforeach; ?>

<div class="modal" id="subNew"><form class="box f" method="post"><button type="button" class="x">×</button>
<h3>Add subscription (payment received offline)</h3><?= csrf_field() ?>
<label>Shop</label><select name="shop_id" required><?php foreach (all("SELECT s.id, s.name, c.name city FROM shops s LEFT JOIN cities c ON c.id = s.city_id WHERE s.status = 'verified' ORDER BY s.name") as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['name']) ?> — <?= h($s['city']) ?></option><?php endforeach; ?></select>
<div class="fr"><div><label>Plan</label><select name="plan_code"><?php foreach ($plans as $p) if ($p['price'] > 0): ?><option value="<?= h($p['code']) ?>"><?= h($p['name']) ?> (₹<?= $p['price'] ?>)</option><?php endif; ?></select></div>
<div><label>Amount ₹</label><input type="number" name="amount" required value="499"></div></div>
<div class="fr"><div><label>Method</label><select name="method"><option>UPI</option><option>Cash</option><option>Card</option><option>Bank transfer</option></select></div><div><label>Reference / UTR</label><input type="text" name="reference"></div></div>
<button class="btn p" style="margin-top:12px" name="action" value="sub_add">Add & activate</button>
</form></div>
<?php page_footer();
