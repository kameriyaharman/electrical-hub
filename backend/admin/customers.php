<?php
require __DIR__ . '/_bootstrap.php';
require_admin();
check_csrf();

function admin_valid_pin($p) {
    if (!preg_match('/^\d{4}$/', (string)$p)) throw new Exception('PIN must be exactly 4 digits');
    return (string)$p;
}
/** Shops added by admin with this mobile get linked to the user, so the owner sees them in the app. */
function link_shops($uid, $mobile) {
    q("UPDATE shops SET owner_user_id = ?, mobile_verified = 1 WHERE mobile = ? AND owner_user_id IS NULL", [$uid, $mobile]);
    if (val("SELECT 1 FROM shops WHERE owner_user_id = ?", [$uid])) q("UPDATE users SET role = 'owner' WHERE id = ?", [$uid]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)post('id');
    try {
        if (post('action') === 'block') { q("UPDATE users SET status = 'blocked' WHERE id = ?", [$id]); q("DELETE FROM api_tokens WHERE user_id = ?", [$id]); flash('User blocked'); }
        if (post('action') === 'unblock') { q("UPDATE users SET status = 'active' WHERE id = ?", [$id]); flash('User unblocked'); }
        if (post('action') === 'set_pin') {
            $u = one("SELECT * FROM users WHERE id = ?", [$id]);
            if (!$u) throw new Exception('User not found');
            $pin = admin_valid_pin(post('pin'));
            q("UPDATE users SET pin_hash = ?, pin_fails = 0, pin_locked_until = NULL WHERE id = ?", [password_hash($pin, PASSWORD_DEFAULT), $id]);
            q("DELETE FROM api_tokens WHERE user_id = ?", [$id]);   // log out everywhere with the old PIN
            link_shops($id, $u['mobile']);
            flash("PIN set for {$u['mobile']}. Tell the user their new PIN.");
        }
        if (post('action') === 'add_user') {
            $mobile = substr(preg_replace('/\D/', '', post('mobile')), -10);
            if (strlen($mobile) !== 10) throw new Exception('Enter a 10-digit mobile number');
            $pin = admin_valid_pin(post('pin'));
            if (val("SELECT 1 FROM users WHERE mobile = ?", [$mobile])) throw new Exception('This mobile is already registered — use "Set PIN" on that user');
            $role = post('role') === 'owner' ? 'owner' : 'customer';
            q("INSERT INTO users (mobile, name, role, city_id, pin_hash) VALUES (?,?,?,?,?)", [$mobile, post('name') ?: null, $role, (int)post('city_id') ?: (int)setting('default_city_id', 1), password_hash($pin, PASSWORD_DEFAULT)]);
            link_shops(last_id(), $mobile);
            flash("User $mobile added. They can log in with PIN $pin.");
        }
    } catch (Exception $e) { flash($e->getMessage(), 'err'); }
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
$cities = all("SELECT id, name FROM cities ORDER BY name");

page_header('Customers', 'customers', 'App users — customers and shop owners. Login is mobile number + 4-digit PIN; set or reset a PIN here.',
    '<a class="btn p" href="#" data-open="addUser">+ Add user</a><a class="btn" href="notifications.php">Send notification</a><a class="btn" href="export.php?t=users">Export CSV</a>');
?>
<form class="filters"><input type="text" name="q" placeholder="Name or mobile…" value="<?= h($qq) ?>">
<select name="role"><option value="">All users</option><option value="customer" <?= $role === 'customer' ? 'selected' : '' ?>>Customers</option><option value="owner" <?= $role === 'owner' ? 'selected' : '' ?>>Shop owners</option></select><button class="btn">Filter</button></form>
<div class="card" style="padding:6px 8px"><div class="tw"><table>
<tr><th>User</th><th>Mobile</th><th>City</th><th>Type</th><th>PIN</th><th>Quotes</th><th>Reviews</th><th>Calls / WA</th><th>Joined</th><th>Status</th><th></th></tr>
<?php if (!$rows): ?><tr><td colspan="11" class="empty">No users</td></tr><?php endif; ?>
<?php foreach ($rows as $u):
    $locked = $u['pin_locked_until'] && strtotime($u['pin_locked_until']) > time(); ?>
<tr><td><b><?= h($u['name'] ?: '—') ?></b></td><td><?= h($u['mobile']) ?></td><td><?= h($u['city']) ?></td><td><?= $u['role'] === 'owner' ? '<span class="badge b-yellow">Shop owner</span>' : '<span class="badge b-blue">Customer</span>' ?></td>
<td><?= $locked ? '<span class="badge b-red">Locked</span>' : ($u['pin_hash'] ? '<span class="badge b-green">Set</span>' : '<span class="badge b-grey">Not set</span>') ?></td>
<td><?= $u['quotes'] ?></td><td><?= $u['reviews'] ?></td><td><?= $u['contacts'] ?></td><td><?= date('d M Y', strtotime($u['created_at'])) ?></td><td><?= status_badge($u['status']) ?></td>
<td style="white-space:nowrap"><a class="btn sm" href="#" data-open="pin<?= $u['id'] ?>"><?= $u['pin_hash'] ? 'Reset PIN' : 'Set PIN' ?></a>
<form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="q" value="<?= h($qq) ?>">
<?php if ($u['status'] === 'blocked'): ?><button class="btn sm" name="action" value="unblock">Unblock</button><?php else: ?><button class="btn sm r" name="action" value="block" data-confirm="Block this user?">Block</button><?php endif; ?></form></td></tr>
<?php endforeach; ?>
</table></div><?= paginate($total, $per, $page) ?></div>

<?php foreach ($rows as $u): ?>
<div class="modal" id="pin<?= $u['id'] ?>"><form class="box f" method="post"><button type="button" class="x">×</button>
<h3><?= $u['pin_hash'] ? 'Reset PIN' : 'Set PIN' ?> · <?= h($u['name'] ?: $u['mobile']) ?></h3><?= csrf_field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="q" value="<?= h($qq) ?>">
<p style="color:var(--mute);font-size:12.5px;margin:0">Mobile <?= h($u['mobile']) ?>. The user will be logged out and must use this new PIN. Any shop added with this mobile number gets linked to this user.</p>
<label>New 4-digit PIN</label><input type="text" name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4" required value="<?= sprintf('%04d', random_int(1000, 9999)) ?>">
<button class="btn p" style="margin-top:12px" name="action" value="set_pin">Save PIN</button>
</form></div>
<?php endforeach; ?>

<div class="modal" id="addUser"><form class="box f" method="post"><button type="button" class="x">×</button><h3>Add user</h3><?= csrf_field() ?>
<div class="fr"><div><label>Mobile *</label><input type="tel" name="mobile" maxlength="10" required></div><div><label>Name</label><input type="text" name="name"></div></div>
<div class="fr"><div><label>Type</label><select name="role"><option value="customer">Customer</option><option value="owner">Shop owner</option></select></div>
<div><label>City</label><select name="city_id"><?php foreach ($cities as $c): ?><option value="<?= $c['id'] ?>" <?= setting('default_city_id') == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></div></div>
<label>4-digit PIN *</label><input type="text" name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4" required value="<?= sprintf('%04d', random_int(1000, 9999)) ?>">
<p style="color:var(--mute);font-size:12px;margin:6px 0 0">Shop owner: first add their shop in Shops with the same mobile — it links automatically.</p>
<button class="btn p" style="margin-top:12px" name="action" value="add_user">Add user</button></form></div>
<?php page_footer();
