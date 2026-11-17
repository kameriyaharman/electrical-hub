<?php
require __DIR__ . '/_bootstrap.php';
require_admin();
check_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)post('id');
    if (post('action') === 'close') { q("UPDATE quote_requests SET status = 'closed' WHERE id = ?", [$id]); flash('Request closed'); }
    if (post('action') === 'add_shop') { q("INSERT IGNORE INTO quote_recipients (quote_id, shop_id) VALUES (?,?)", [$id, (int)post('shop_id')]); flash('Shop added to request'); }
    redirect('quotes.php?view=' . $id);
}

$month = "created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')";
$qCount = (int)val("SELECT COUNT(*) FROM quote_requests WHERE $month");
$replies = (int)val("SELECT COUNT(*) FROM quote_recipients r JOIN quote_requests q ON q.id = r.quote_id WHERE r.replied_at IS NOT NULL AND q.$month");
$calls = (int)val("SELECT COUNT(*) FROM leads WHERE type = 'call' AND $month");
$wa = (int)val("SELECT COUNT(*) FROM leads WHERE type = 'whatsapp' AND $month");

$qq = get('q');
$rows = all("SELECT q.*, (SELECT COUNT(*) FROM quote_recipients r WHERE r.quote_id = q.id) sent, (SELECT COUNT(*) FROM quote_recipients r WHERE r.quote_id = q.id AND r.replied_at IS NOT NULL) replied
  FROM quote_requests q " . ($qq ? "WHERE q.code LIKE ? OR q.customer_name LIKE ? OR q.items LIKE ? OR q.area LIKE ?" : '') . " ORDER BY q.id DESC LIMIT 200", $qq ? array_fill(0, 4, "%$qq%") : []);
$leadRows = all("SELECT l.type, l.created_at, s.name shop, u.name uname, u.mobile FROM leads l JOIN shops s ON s.id = l.shop_id LEFT JOIN users u ON u.id = l.user_id WHERE l.type IN ('call','whatsapp','directions') ORDER BY l.id DESC LIMIT 15");
$view = (int)get('view') ? one("SELECT * FROM quote_requests WHERE id = ?", [(int)get('view')]) : null;

page_header('Quote Requests, Leads & Reviews', 'quotes', 'Customer requirements sent to nearby shops, shop replies, call / WhatsApp lead logs', '<a class="btn" href="export.php?t=quotes">Export</a><a class="btn" href="export.php?t=leads">Export leads</a>');
?>
<div class="grid k4" style="margin-bottom:14px">
  <div class="card kpi"><div class="lb"><?= icon('file') ?>Quote requests (<?= date('M') ?>)</div><div class="v"><?= indian_number($qCount) ?></div></div>
  <div class="card kpi"><div class="lb"><?= icon('msg') ?>Shop replies</div><div class="v"><?= indian_number($replies) ?></div><div class="s m">avg <?= $qCount ? round($replies / $qCount, 1) : 0 ?> per request</div></div>
  <div class="card kpi"><div class="lb"><?= icon('phone') ?>Calls to shops</div><div class="v"><?= indian_number($calls) ?></div></div>
  <div class="card kpi"><div class="lb"><?= icon('msg') ?>WhatsApp clicks</div><div class="v"><?= indian_number($wa) ?></div></div>
</div>

<div class="<?= $view ? 'split' : '' ?>">
<div class="card" style="padding:12px 8px"><h3 style="padding:0 8px">Quote requests</h3>
<form class="filters" style="padding:0 8px"><input type="text" name="q" placeholder="Search…" value="<?= h($qq) ?>"><button class="btn">Search</button></form>
<div class="tw"><table><tr><th>Request</th><th>Customer</th><th>Items</th><th>Area</th><th>Sent to</th><th>Replies</th><th>Status</th></tr>
<?php if (!$rows): ?><tr><td colspan="7" class="empty">No quote requests yet</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?><tr<?= $view && $view['id'] == $r['id'] ? ' style="background:#FFF6EE"' : '' ?>><td><a href="?view=<?= $r['id'] ?>"><b><?= h($r['code']) ?></b></a><small><?= date('d M', strtotime($r['created_at'])) ?></small></td><td><?= h($r['customer_name']) ?></td>
<td style="max-width:220px"><?= h(mb_strimwidth(str_replace("\n", ', ', $r['items']), 0, 60, '…')) ?><?= $r['attachment'] ? ' 📎' : '' ?></td><td><?= h($r['area']) ?></td><td><?= $r['sent'] ?> shops</td><td><b><?= $r['replied'] ?></b></td><td><?= status_badge($r['status']) ?></td></tr><?php endforeach; ?>
</table></div></div>

<?php if ($view): $rec = all("SELECT r.*, s.name, s.mobile, s.plan FROM quote_recipients r JOIN shops s ON s.id = r.shop_id WHERE r.quote_id = ? ORDER BY r.replied_at IS NULL, r.reply_amount", [$view['id']]); ?>
<div class="card"><h3><?= h($view['code']) ?> · <?= h($view['customer_name']) ?></h3>
<div style="white-space:pre-line;background:var(--bg);border-radius:10px;padding:10px;font-size:12.5px"><?= h($view['items']) ?></div>
<?php if ($view['attachment']): ?><p><a target="_blank" href="<?= h(upload_url($view['attachment'])) ?>">📎 View uploaded list</a></p><?php endif; ?>
<div class="vlist"><div><span>Mobile</span><b><?= h($view['customer_mobile']) ?></b></div><div><span>Area</span><b><?= h($view['area']) ?></b></div><div><span>Only verified</span><b><?= $view['verified_only'] ? 'Yes' : 'No' ?></b></div></div>
<h3 style="margin-top:14px">Replies</h3>
<?php foreach ($rec as $x): ?><div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #F2EEEA;font-size:12.5px"><span><?= h($x['name']) ?></span><b><?= $x['replied_at'] ? '₹' . indian_number($x['reply_amount']) . ' · ' . h($x['delivery']) : '<span style="color:var(--mute);font-weight:400">awaiting</span>' ?></b></div><?php endforeach; ?>
<form method="post" class="f" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $view['id'] ?>">
<label>Forward to another shop</label><div style="display:flex;gap:6px"><select name="shop_id"><?php foreach (all("SELECT id, name FROM shops WHERE status = 'verified' AND city_id <=> ? ORDER BY name", [$view['city_id']]) as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['name']) ?></option><?php endforeach; ?></select><button class="btn" name="action" value="add_shop">Add</button></div>
<?php if ($view['status'] !== 'closed'): ?><button class="btn r" style="margin-top:10px" name="action" value="close">Close request</button><?php endif; ?></form>
</div>
<?php endif; ?>
</div>

<div class="card" style="margin-top:14px"><h3>Latest call / WhatsApp leads</h3><div class="tw"><table><tr><th>When</th><th>Shop</th><th>Customer</th><th>Type</th></tr>
<?php foreach ($leadRows as $l): ?><tr><td><?= date('d M, h:i A', strtotime($l['created_at'])) ?></td><td><?= h($l['shop']) ?></td><td><?= h($l['uname'] ?: 'Guest') ?> <small><?= h($l['mobile']) ?></small></td><td><span class="badge <?= $l['type'] === 'call' ? 'b-blue' : ($l['type'] === 'whatsapp' ? 'b-green' : 'b-grey') ?>"><?= ucfirst($l['type']) ?></span></td></tr><?php endforeach; ?>
</table></div><p style="font-size:12px"><a href="reviews.php">Go to review moderation →</a></p></div>
<?php page_footer();
