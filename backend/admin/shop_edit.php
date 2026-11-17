<?php
require __DIR__ . '/_bootstrap.php';
require_admin();
check_csrf();

$id = (int)get('id');
$s = $id ? one("SELECT * FROM shops WHERE id = ?", [$id]) : null;
if ($id && !$s) { flash('Shop not found', 'err'); redirect('shops.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mobile = substr(preg_replace('/\D/', '', post('mobile')), -10);
    if (post('name') === '' || strlen($mobile) !== 10) { flash('Shop name and 10-digit mobile are required', 'err'); redirect('shop_edit.php' . ($id ? "?id=$id" : '')); }
    $f = [
        'name' => post('name'), 'owner_name' => post('owner_name'), 'mobile' => $mobile,
        'whatsapp' => substr(preg_replace('/\D/', '', post('whatsapp')), -10) ?: $mobile, 'gstin' => strtoupper(post('gstin')) ?: null,
        'shop_type' => post('shop_type', 'Retail'), 'is_service' => post('is_service') ? 1 : 0,
        'address' => post('address'), 'city_id' => (int)post('city_id') ?: null, 'area' => post('area'), 'pincode' => post('pincode'),
        'lat' => post('lat') !== '' ? (float)post('lat') : null, 'lng' => post('lng') !== '' ? (float)post('lng') : null,
        'open_time' => post('open_time', '09:00') ?: '09:00', 'close_time' => post('close_time', '21:00') ?: '21:00', 'weekly_off' => post('weekly_off') ?: null,
        'status' => post('status', 'verified'),
        'rating' => min(5, max(0, (float)post('rating'))),
    ];
    if ($s) {
        q("UPDATE shops SET " . implode(', ', array_map(fn($k) => "$k = ?", array_keys($f))) . " WHERE id = ?", array_merge(array_values($f), [$id]));
    } else {
        $f['mobile_verified'] = 0;
        q("INSERT INTO shops (" . implode(',', array_keys($f)) . ") VALUES (" . rtrim(str_repeat('?,', count($f)), ',') . ")", array_values($f));
        $id = last_id();
    }
    q("DELETE FROM shop_categories WHERE shop_id = ?", [$id]);
    foreach ((array)post('cats', []) as $c) q("INSERT IGNORE INTO shop_categories VALUES (?,?)", [$id, (int)$c]);
    q("DELETE FROM shop_brands WHERE shop_id = ?", [$id]);
    foreach ((array)post('brands', []) as $b) q("INSERT IGNORE INTO shop_brands VALUES (?,?)", [$id, (int)$b]);
    q("DELETE FROM shop_products WHERE shop_id = ?", [$id]);
    foreach ((array)post('products', []) as $pp) q("INSERT IGNORE INTO shop_products (shop_id, product_id) VALUES (?,?)", [$id, (int)$pp]);
    if (!empty($_FILES['photos']['name'][0])) {
        $room = MAX_SHOP_PHOTOS - (int)val("SELECT COUNT(*) FROM shop_photos WHERE shop_id = ?", [$id]);
        if (count($_FILES['photos']['name']) > $room) flash('Only ' . max(0, $room) . ' more photo(s) allowed — max ' . MAX_SHOP_PHOTOS . ' per shop', 'err');
        foreach (array_slice($_FILES['photos']['name'], 0, max(0, $room), true) as $i => $nm) {
            $file = ['name' => $nm, 'tmp_name' => $_FILES['photos']['tmp_name'][$i], 'error' => $_FILES['photos']['error'][$i], 'size' => $_FILES['photos']['size'][$i]];
            try { $path = save_upload($file, "shops/$id", ['jpg', 'jpeg', 'png', 'webp']); q("INSERT INTO shop_photos (shop_id, path, label, source) VALUES (?,?,?, 'admin')", [$id, $path, 'Photo']); }
            catch (Exception $e) { flash($e->getMessage(), 'err'); }
        }
    }
    flash('Shop saved');
    redirect('shops.php?view=' . $id);
}

$sc = $s ? array_column(all("SELECT category_id FROM shop_categories WHERE shop_id = ?", [$id]), 'category_id') : [];
$sb = $s ? array_column(all("SELECT brand_id FROM shop_brands WHERE shop_id = ?", [$id]), 'brand_id') : [];
$sp = $s ? array_column(all("SELECT product_id FROM shop_products WHERE shop_id = ?", [$id]), 'product_id') : [];
$v = fn($k, $d = '') => h($s[$k] ?? $d);
page_header($s ? 'Edit shop' : 'Add shop', 'shops', $s ? h($s['name']) : 'Create a listing on behalf of a shop owner', '<a class="btn" href="shops.php">← Back</a>');
?>
<form method="post" enctype="multipart/form-data" class="f"><?= csrf_field() ?>
<div class="grid k2">
<div class="card"><h3>Shop details</h3>
  <label>Shop name *</label><input type="text" name="name" required value="<?= $v('name') ?>">
  <div class="fr"><div><label>Owner name</label><input type="text" name="owner_name" value="<?= $v('owner_name') ?>"></div>
  <div><label>GSTIN (optional)</label><input type="text" name="gstin" value="<?= $v('gstin') ?>"></div></div>
  <div class="fr"><div><label>Mobile *</label><input type="tel" name="mobile" required value="<?= $v('mobile') ?>"></div>
  <div><label>WhatsApp</label><input type="tel" name="whatsapp" value="<?= $v('whatsapp') ?>"></div></div>
  <label>Shop type</label><div class="chips">
    <?php foreach (['Retail', 'Wholesale', 'Both'] as $t): ?><label><input type="radio" name="shop_type" value="<?= $t ?>" <?= ($s['shop_type'] ?? 'Retail') === $t ? 'checked' : '' ?>> <?= $t ?></label><?php endforeach; ?>
    <label><input type="checkbox" name="is_service" value="1" <?= !empty($s['is_service']) ? 'checked' : '' ?>> Service / electrician</label>
  </div>
  <label>Categories</label><div class="chips">
    <?php foreach (all("SELECT id, name FROM categories WHERE active = 1 ORDER BY sort") as $c): ?><label><input type="checkbox" name="cats[]" value="<?= $c['id'] ?>" <?= in_array($c['id'], $sc) ? 'checked' : '' ?>> <?= h($c['name']) ?></label><?php endforeach; ?>
  </div>
  <label>Brands available</label><div class="chips">
    <?php foreach (all("SELECT id, name FROM brands WHERE active = 1 ORDER BY name") as $b): ?><label><input type="checkbox" name="brands[]" value="<?= $b['id'] ?>" <?= in_array($b['id'], $sb) ? 'checked' : '' ?>> <?= h($b['name']) ?></label><?php endforeach; ?>
  </div>
  <label>Products stocked</label><div class="chips">
    <?php foreach (all("SELECT id, name FROM products WHERE active = 1 ORDER BY name") as $pr): ?><label><input type="checkbox" name="products[]" value="<?= $pr['id'] ?>" <?= in_array($pr['id'], $sp) ? 'checked' : '' ?>> <?= h($pr['name']) ?></label><?php endforeach; ?>
  </div>
</div>
<div>
<div class="card"><h3>Location & timings</h3>
  <label>Address</label><input type="text" name="address" value="<?= $v('address') ?>">
  <div class="fr3"><div><label>City</label><select name="city_id"><?php foreach (all("SELECT id, name FROM cities ORDER BY name") as $c): ?><option value="<?= $c['id'] ?>" <?= ($s['city_id'] ?? 1) == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></div>
  <div><label>Area</label><input type="text" name="area" value="<?= $v('area') ?>" list="areas"></div>
  <div><label>Pincode</label><input type="text" name="pincode" value="<?= $v('pincode') ?>"></div></div>
  <datalist id="areas"><?php foreach (all("SELECT DISTINCT name FROM areas ORDER BY name") as $a): ?><option value="<?= h($a['name']) ?>"><?php endforeach; ?></datalist>
  <div class="fr"><div><label>Latitude</label><input type="text" name="lat" value="<?= $v('lat') ?>" placeholder="13.0418"></div>
  <div><label>Longitude</label><input type="text" name="lng" value="<?= $v('lng') ?>" placeholder="80.2341"></div></div>
  <small style="color:var(--mute)">Tip: Google Maps → long-press the shop → copy the numbers shown.</small>
  <div class="fr3"><div><label>Opens</label><input type="time" name="open_time" value="<?= $v('open_time', '09:00') ?>"></div>
  <div><label>Closes</label><input type="time" name="close_time" value="<?= $v('close_time', '21:00') ?>"></div>
  <div><label>Weekly off</label><select name="weekly_off"><option value="">None</option><?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d): ?><option <?= ($s['weekly_off'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option><?php endforeach; ?></select></div></div>
</div>
<div class="card" style="margin-top:14px"><h3>Status & photos</h3>
  <div class="fr"><div><label>Status</label><select name="status"><?php foreach (['verified', 'pending', 'rejected', 'blocked', 'draft'] as $st): ?><option value="<?= $st ?>" <?= ($s['status'] ?? 'verified') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option><?php endforeach; ?></select></div>
  <div><label>Rating</label><input type="number" step="0.1" min="0" max="5" name="rating" value="<?= $v('rating', '0') ?>"></div></div>
  <label>Add photos (admin upload · max <?= MAX_SHOP_PHOTOS ?> per shop)</label><input type="file" name="photos[]" accept="image/*" multiple>
</div>
</div>
</div>
<div style="margin-top:16px"><button class="btn p">Save shop</button> <a class="btn" href="shops.php">Cancel</a></div>
</form>
<?php page_footer();
