<?php
require __DIR__ . '/_bootstrap.php';
require_admin();
check_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (post('action') === 'send') {
            if (post('title') === '') throw new Exception('Title is required');
            $img = !empty($_FILES['image']['name']) ? save_upload($_FILES['image'], 'notifications', ['jpg', 'jpeg', 'png', 'webp']) : null;
            $aud = in_array(post('audience'), ['all', 'customer', 'owner'], true) ? post('audience') : 'all';
            q("INSERT INTO notifications (user_id, title, body, city_id, audience, shop_id, image) VALUES (NULL,?,?,?,?,?,?)",
                [post('title'), post('body'), (int)post('city_id') ?: null, $aud, (int)post('shop_id') ?: null, $img]);
            flash('Notification sent — it shows in the app now, and as a phone notification within ~15 minutes');
        } elseif (post('action') === 'delete') {
            q("DELETE FROM notifications WHERE id = ? AND user_id IS NULL", [(int)post('id')]);
            flash('Notification deleted');
        }
    } catch (Exception $e) { flash($e->getMessage(), 'err'); }
    redirect('notifications.php');
}

function reach($city, $aud) {
    $w = ['status = \'active\'']; $p = [];
    if ($city) { $w[] = 'city_id = ?'; $p[] = $city; }
    if ($aud === 'owner') $w[] = "role = 'owner'";
    if ($aud === 'customer') $w[] = "role <> 'owner'";
    return (int)val("SELECT COUNT(*) FROM users WHERE " . implode(' AND ', $w), $p);
}

$rows = all("SELECT n.*, c.name city, s.name shop FROM notifications n LEFT JOIN cities c ON c.id = n.city_id LEFT JOIN shops s ON s.id = n.shop_id WHERE n.user_id IS NULL ORDER BY n.id DESC LIMIT 100");
$cities = all("SELECT id, name FROM cities ORDER BY name");
$shops = all("SELECT id, name FROM shops WHERE status = 'verified' ORDER BY name");
$audLabel = ['all' => 'Everyone', 'customer' => 'Customers', 'owner' => 'Shop owners'];

page_header('Notifications', 'notifications', 'Send a message to app users — by city and user type. Shows in the app (bell icon) and as a phone notification.');
?>
<div class="split" style="grid-template-columns:380px 1fr">
<form class="card f" method="post" enctype="multipart/form-data"><?= csrf_field() ?><h3>New notification</h3>
  <label>Title *</label><input type="text" name="title" maxlength="150" required placeholder="Diwali offer — 20% off on LED lights">
  <label>Message</label><textarea name="body" maxlength="500" placeholder="Visit your nearest shop on Electrical Hub…"></textarea>
  <div class="fr"><div><label>Send to</label><select name="audience"><?php foreach ($audLabel as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></div>
  <div><label>City</label><select name="city_id"><option value="">All cities</option><?php foreach ($cities as $c): ?><option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option><?php endforeach; ?></select></div></div>
  <label>Tap opens shop (optional)</label><select name="shop_id"><option value="">— none —</option><?php foreach ($shops as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['name']) ?></option><?php endforeach; ?></select>
  <label>Image (optional)</label><input type="file" name="image" accept="image/*">
  <button class="btn p" style="margin-top:14px;width:100%;justify-content:center" name="action" value="send" data-confirm="Send this notification now?">Send notification</button>
  <p style="font-size:12px;color:var(--mute);margin:10px 0 0">Phone notifications reach Android app users within about 15 minutes (the app checks in the background). Users who turned off notifications still see it inside the app.</p>
</form>
<div class="card" style="padding:12px 8px"><h3 style="padding:0 8px">Sent</h3><div class="tw"><table>
<tr><th>Notification</th><th>To</th><th>Reach</th><th>Sent</th><th></th></tr>
<?php if (!$rows): ?><tr><td colspan="5" class="empty">Nothing sent yet</td></tr><?php endif; ?>
<?php foreach ($rows as $n): ?><tr>
  <td><b><?= h($n['title']) ?></b><small><?= h(mb_strimwidth((string)$n['body'], 0, 90, '…')) ?><?= $n['shop'] ? ' · opens ' . h($n['shop']) : '' ?></small></td>
  <td><?= $audLabel[$n['audience']] ?? 'Everyone' ?><small><?= $n['city'] ? h($n['city']) : 'All cities' ?></small></td>
  <td><?= indian_number(reach($n['city_id'], $n['audience'])) ?> users</td>
  <td><?= date('d M, h:i A', strtotime($n['created_at'])) ?></td>
  <td><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $n['id'] ?>"><button class="btn sm r" name="action" value="delete" data-confirm="Delete this notification from the app?">Delete</button></form></td>
</tr><?php endforeach; ?>
</table></div></div>
</div>
<?php page_footer();
