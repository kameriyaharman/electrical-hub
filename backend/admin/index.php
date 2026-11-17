<?php
require __DIR__ . '/_bootstrap.php';
require_admin();

// Month selector (YYYY-MM)
$month = preg_match('/^\d{4}-\d{2}$/', get('m')) ? get('m') : date('Y-m');
$start = "$month-01";
$end = date('Y-m-d', strtotime("$start +1 month"));
$label = date('M Y', strtotime($start));
$rng = [$start, $end];

$listed = (int)val("SELECT COUNT(*) FROM shops WHERE status IN ('verified','pending')");
$listedNew = (int)val("SELECT COUNT(*) FROM shops WHERE created_at >= ? AND created_at < ?", $rng);
$pending = (int)val("SELECT COUNT(*) FROM shops WHERE status = 'pending'");
$users = (int)val("SELECT COUNT(*) FROM users");
$usersNew = (int)val("SELECT COUNT(*) FROM users WHERE created_at >= ? AND created_at < ?", $rng);
$leads = (int)val("SELECT COUNT(*) FROM leads WHERE type IN ('call','whatsapp') AND created_at >= ? AND created_at < ?", $rng);
$quotesN = (int)val("SELECT COUNT(*) FROM quote_requests WHERE created_at >= ? AND created_at < ?", $rng);
$reviewsN = (int)val("SELECT COUNT(*) FROM reviews WHERE created_at >= ? AND created_at < ?", $rng);
$flaggedN = (int)val("SELECT COUNT(*) FROM reviews WHERE status = 'flagged'");

$byCity = all("SELECT c.name, COUNT(s.id) n FROM cities c LEFT JOIN shops s ON s.city_id = c.id AND s.status IN ('verified','pending') GROUP BY c.id ORDER BY n DESC LIMIT 6");
$maxCity = max(1, max(array_column($byCity, 'n') ?: [1]));
$byCat = all("SELECT c.name, COUNT(x.id) n FROM categories c LEFT JOIN searches x ON x.category_id = c.id AND x.created_at >= ? AND x.created_at < ? GROUP BY c.id ORDER BY n DESC LIMIT 6", $rng);
$maxCat = max(1, max(array_column($byCat, 'n') ?: [1]));
$top = all("SELECT s.id, s.name, c.name city, s.plan, s.views,
    (SELECT COUNT(*) FROM leads l WHERE l.shop_id = s.id AND l.type IN ('call','whatsapp','directions') AND l.created_at >= ? AND l.created_at < ?) leads
    FROM shops s LEFT JOIN cities c ON c.id = s.city_id WHERE s.status = 'verified' ORDER BY leads DESC, s.views DESC LIMIT 6", $rng);
$recentPending = all("SELECT s.id, s.name, s.owner_name, s.area, c.name city, s.created_at FROM shops s LEFT JOIN cities c ON c.id = s.city_id WHERE s.status = 'pending' ORDER BY s.id DESC LIMIT 5");

$months = '';
for ($i = 0; $i < 12; $i++) { $m = date('Y-m', strtotime(date('Y-m-01') . " -$i month")); $months .= '<option value="' . $m . '"' . ($m === $month ? ' selected' : '') . '>' . date('M Y', strtotime("$m-01")) . '</option>'; }
$sel = '<form><select name="m" onchange="this.form.submit()" style="font-weight:600;border-color:var(--navy)">' . $months . '</select></form>';

page_header('Admin Dashboard', 'dashboard', 'Shops, users, leads generated for shops, quotes &amp; reviews · ' . $label, $sel);
?>
<div class="grid k6">
  <div class="card kpi"><div class="lb"><?= icon('store') ?>Listed shops</div><div class="v"><?= indian_number($listed) ?></div><div class="s">+<?= $listedNew ?> this month</div></div>
  <a class="card kpi" href="shops.php?tab=pending" style="color:inherit"><div class="lb"><?= icon('check') ?>Pending verification</div><div class="v"><?= $pending ?></div><div class="s w">photos to check</div></a>
  <div class="card kpi"><div class="lb"><?= icon('users') ?>App users</div><div class="v"><?= indian_number($users) ?></div><div class="s">+<?= indian_number($usersNew) ?> this month</div></div>
  <div class="card kpi"><div class="lb"><?= icon('phone') ?>Calls + WhatsApp</div><div class="v"><?= indian_number($leads) ?></div><div class="s m">leads to shops</div></div>
  <a class="card kpi" href="quotes.php" style="color:inherit"><div class="lb"><?= icon('file') ?>Quote requests</div><div class="v"><?= indian_number($quotesN) ?></div><div class="s m">this month</div></a>
  <a class="card kpi" href="reviews.php" style="color:inherit"><div class="lb"><?= icon('star') ?>Reviews</div><div class="v"><?= indian_number($reviewsN) ?></div><div class="s <?= $flaggedN ? 'w' : 'm' ?>"><?= $flaggedN ?> flagged</div></a>
</div>

<div class="grid" style="grid-template-columns:1fr 1fr 1.4fr;margin-top:14px">
  <div class="card"><h3>Shops by city</h3><div class="bars">
    <?php foreach ($byCity as $c): ?><div class="row"><span><?= h($c['name']) ?></span><div class="t"><i style="width:<?= round($c['n'] * 100 / $maxCity) ?>%"></i></div><b><?= indian_number($c['n']) ?></b></div><?php endforeach; ?>
  </div></div>
  <div class="card"><h3>Searches by category</h3><div class="bars y">
    <?php foreach ($byCat as $c): ?><div class="row"><span><?= h($c['name']) ?></span><div class="t"><i style="width:<?= round($c['n'] * 100 / $maxCat) ?>%"></i></div><b><?= knum($c['n']) ?></b></div><?php endforeach; ?>
  </div></div>
  <div class="card"><h3>Top shops (leads)</h3><div class="tw"><table>
    <tr><th>Shop</th><th>City</th><th>Views</th><th>Leads</th></tr>
    <?php foreach ($top as $t): ?><tr><td><a href="shops.php?view=<?= $t['id'] ?>"><?= h($t['name']) ?></a></td><td><?= h($t['city']) ?></td><td><?= indian_number($t['views']) ?></td><td><b><?= $t['leads'] ?></b></td></tr><?php endforeach; ?>
  </table></div></div>
</div>

<div class="card" style="margin-top:14px"><h3>Waiting for verification</h3>
<?php if (!$recentPending): ?><div class="empty">No shops waiting. 🎉</div><?php else: ?>
<div class="tw"><table><tr><th>Shop</th><th>Owner</th><th>City / area</th><th>Submitted</th><th></th></tr>
<?php foreach ($recentPending as $p): ?><tr><td><b><?= h($p['name']) ?></b></td><td><?= h($p['owner_name']) ?></td><td><?= h($p['city']) ?> · <?= h($p['area']) ?></td><td><?= date('d M, h:i A', strtotime($p['created_at'])) ?></td><td><a class="btn sm p" href="shops.php?tab=pending&view=<?= $p['id'] ?>">Verify</a></td></tr><?php endforeach; ?>
</table></div><?php endif; ?></div>
<?php page_footer();
