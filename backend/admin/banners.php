<?php
require __DIR__ . '/_bootstrap.php';
require_admin();
check_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)post('id');
    try {
        if (post('action') === 'save') {
            $img = !empty($_FILES['image']['name']) ? save_upload($_FILES['image'], 'banners', ['jpg', 'jpeg', 'png', 'webp']) : null;
            $f = [post('title'), post('subtitle'), (int)post('shop_id') ?: null, (int)post('city_id') ?: null, post('start_date') ?: null, post('end_date') ?: null, (int)post('amount'), post('status'), (int)post('sort')];
            if ($id) { q("UPDATE banners SET title=?, subtitle=?, shop_id=?, city_id=?, start_date=?, end_date=?, amount=?, status=?, sort=? WHERE id=?", array_merge($f, [$id])); if ($img) q("UPDATE banners SET image=? WHERE id=?", [$img, $id]); }
            else {
                q("INSERT INTO banners (title, subtitle, shop_id, city_id, start_date, end_date, amount, status, sort, image) VALUES (?,?,?,?,?,?,?,?,?,?)", array_merge($f, [$img]));
                if ((int)post('amount') > 0 && post('shop_id')) q("INSERT INTO payments (shop_id, purpose, amount, method, status) VALUES (?, 'Home banner', ?, 'UPI', ?)", [(int)post('shop_id'), (int)post('amount'), post('paid') ? 'success' : 'pending']);
            }
            flash('Banner saved');
        } elseif (post('action') === 'delete') { q("DELETE FROM banners WHERE id = ?", [$id]); flash('Banner deleted'); }
    } catch (Exception $e) { flash($e->getMessage(), 'err'); }
    redirect('banners.php');
}

$rows = all("SELECT b.*, s.name shop, c.name city FROM banners b LEFT JOIN shops s ON s.id = b.shop_id LEFT JOIN cities c ON c.id = b.city_id ORDER BY b.status = 'active' DESC, b.sort, b.id DESC");
$shops = all("SELECT id, name FROM shops WHERE status = 'verified' ORDER BY name");
$cities = all("SELECT id, name FROM cities ORDER BY name");
page_header('Banners', 'banners', 'Home-page banners shown in the app — free promos or paid shop banners per city', '<a class="btn p" href="#" data-open="bNew">+ Add banner</a>');
?>
<div class="grid k3">
<?php if (!$rows): ?><div class="card empty">No banners yet</div><?php endif; ?>
<?php foreach ($rows as $b): ?>
<div class="card" style="padding:0;overflow:hidden">
  <div style="height:120px;background:<?= $b['image'] ? 'url(' . h(upload_url($b['image'])) . ') center/cover' : 'linear-gradient(120deg,#0B2559,#1E5EFF)' ?>;color:#fff;padding:16px;display:flex;flex-direction:column;justify-content:flex-end">
    <?php if (!$b['image']): ?><b style="font-size:16px"><?= h($b['title']) ?></b><small style="opacity:.85"><?= h($b['subtitle']) ?></small><?php endif; ?>
  </div>
  <div style="padding:12px 14px">
    <div style="display:flex;justify-content:space-between;align-items:center"><b><?= h($b['title']) ?></b><?= status_badge($b['status']) ?></div>
    <small style="color:var(--mute)"><?= $b['shop'] ? h($b['shop']) . ' · ' : '' ?><?= $b['city'] ? h($b['city']) : 'All cities' ?><?= $b['end_date'] ? ' · till ' . date('d M', strtotime($b['end_date'])) : '' ?><?= $b['amount'] ? ' · ₹' . indian_number($b['amount']) : '' ?></small>
    <div style="margin-top:8px"><a class="btn sm" href="#" data-open="b<?= $b['id'] ?>">Edit</a></div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php foreach (array_merge([['id' => 0, 'title' => '', 'subtitle' => '', 'shop_id' => null, 'city_id' => null, 'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+30 days')), 'amount' => 0, 'status' => 'active', 'sort' => 0]], $rows) as $b): ?>
<div class="modal" id="<?= $b['id'] ? 'b' . $b['id'] : 'bNew' ?>"><form class="box f" method="post" enctype="multipart/form-data"><button type="button" class="x">×</button>
<h3><?= $b['id'] ? 'Edit banner' : 'Add banner' ?></h3><?= csrf_field() ?><input type="hidden" name="id" value="<?= $b['id'] ?>">
<label>Title</label><input type="text" name="title" required value="<?= h($b['title']) ?>">
<label>Subtitle</label><input type="text" name="subtitle" value="<?= h($b['subtitle']) ?>">
<label>Image (optional, 16:7 recommended)</label><input type="file" name="image" accept="image/*">
<div class="fr"><div><label>Opens shop</label><select name="shop_id"><option value="">— none —</option><?php foreach ($shops as $s): ?><option value="<?= $s['id'] ?>" <?= $b['shop_id'] == $s['id'] ? 'selected' : '' ?>><?= h($s['name']) ?></option><?php endforeach; ?></select></div>
<div><label>City</label><select name="city_id"><option value="">All cities</option><?php foreach ($cities as $c): ?><option value="<?= $c['id'] ?>" <?= $b['city_id'] == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></div></div>
<div class="fr3"><div><label>Start</label><input type="date" name="start_date" value="<?= h($b['start_date']) ?>"></div><div><label>End</label><input type="date" name="end_date" value="<?= h($b['end_date']) ?>"></div><div><label>Amount ₹</label><input type="number" name="amount" value="<?= (int)$b['amount'] ?>"></div></div>
<div class="fr"><div><label>Status</label><select name="status"><?php foreach (['active', 'pending', 'expired'] as $o): ?><option <?= $b['status'] === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div><div><label>Sort</label><input type="number" name="sort" value="<?= (int)$b['sort'] ?>"></div></div>
<?php if (!$b['id']): ?><label><input type="checkbox" name="paid" value="1"> Payment already received</label><?php endif; ?>
<div style="display:flex;gap:8px;margin-top:12px"><button class="btn p" name="action" value="save">Save</button><?php if ($b['id']): ?><button class="btn r" name="action" value="delete" data-confirm="Delete banner?">Delete</button><?php endif; ?></div>
</form></div>
<?php endforeach; page_footer();
