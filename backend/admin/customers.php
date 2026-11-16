<?php
require __DIR__ . '/_bootstrap.php';
require_admin();
check_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)post('id');
    if (post('action') === 'block') { q("UPDATE users SET status = 'blocked' WHERE id = ?", [$id]); q("DELETE FROM api_tokens WHERE user_id = ?", [$id]); flash('User blocked'); }
    if (post('action') === 'unblock') { q("UPDATE users SET status = 'active' WHERE id = ?", [$id]); flash('User unblocked'); }
    if (post('action') === 'notify') {
        $uid = post('all') ? null : $id;
        q("INSERT INTO notifications (user_id, title, body) VALUES (?,?,?)", [$uid, post('title'), post('body')]);
        flash($uid ? 'Notification sent' : 'Notification sent to all users');
    }
    redirect('customers.php?' . http_build_query(['q' => post('q')]));
}

$qq = get('q'); $role = get('role');
$where = ['1=1']; $p = [];
if ($qq !== '') { $where[] = '(u.name LIKE ? OR u.mobile LIKE ?)'; $p[] = "%$qq%"; $p[] = "%$qq%"; }
if ($role) { $where[] = 'u.role = ?'; $p[] = $role; }
$w = implode(' AND ', $where);
$page = max(1, (int)get('page', 1)); $per = 30;
$total = (int)val("SELECT COUNT(*) FROM users u WHERE $w", $p);
$rows = all("SELECT u.*, c.name city,
  (SELECT COUNT(*) FROM quote_requests q WHERE q.user_id = u.id) quotes,
  (SELECT COUNT(*) FROM reviews r WHERE r.user_id = u.id) reviews,
  (SELECT COUNT(*) FROM leads l WHERE l.user_id = u.id AND l.type IN ('call','whatsapp')) contacts
  FROM users u LEFT JOIN cities c ON c.id = u.city_id WHERE $w ORDER BY u.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p);

page_header('Customers', 'customers', 'App users — customers and shop owners', '<a class="btn" href="#" data-open="nAll">Send notification to all</a><a class="btn" href="export.php?t=users">Export CSV</a>');
?>
<form class="filters"><input type="text" name="q" placeholder="Name or mobile…" value="<?= h($qq) ?>">
<select name="role"><option value="">All users</option><option value="customer" <?= $role === 'customer' ? 'selected' : '' ?>>Customers</option><option value="owner" <?= $role === 'owner' ? 'selected' : '' ?>>Shop owners</option></select><button class="btn">Filter</button></form>
<div class="card" style="padding:6px 8px"><div class="tw"><table>
<tr><th>User</th><th>Mobile</th><th>City</th><th>Type</th><th>Quotes</th><th>Reviews</th><th>Calls / WA</th><th>Joined</th><th>Status</th><th></th></tr>
<?php if (!$rows): ?><tr><td colspan="10" class="empty">No users</td></tr><?php endif; ?>
<?php foreach ($rows as $u): ?><tr><td><b><?= h($u['name'] ?: '—') ?></b></td><td><?= h($u['mobile']) ?></td><td><?= h($u['city']) ?></td><td><?= $u['role'] === 'owner' ? '<span class="badge b-yellow">Shop owner</span>' : '<span class="badge b-blue">Customer</span>' ?></td>
<td><?= $u['quotes'] ?></td><td><?= $u['reviews'] ?></td><td><?= $u['contacts'] ?></td><td><?= date('d M Y', strtotime($u['created_at'])) ?></td><td><?= status_badge($u['status']) ?></td>
<td style="white-space:nowrap"><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="q" value="<?= h($qq) ?>">
<?php if ($u['status'] === 'blocked'): ?><button class="btn sm" name="action" value="unblock">Unblock</button><?php else: ?><button class="btn sm r" name="action" value="block" data-confirm="Block this user?">Block</button><?php endif; ?></form></td></tr><?php endforeach; ?>
</table></div><?= paginate($total, $per, $page) ?></div>
<div class="modal" id="nAll"><form class="box f" method="post"><button type="button" class="x">×</button><h3>Notification to all app users</h3><?= csrf_field() ?><input type="hidden" name="all" value="1">
<label>Title</label><input type="text" name="title" required><label>Message</label><textarea name="body"></textarea>
<button class="btn p" style="margin-top:12px" name="action" value="notify">Send</button><p style="color:var(--mute);font-size:12px">Shows in the app under Account → Notifications.</p></form></div>
<?php page_footer();
