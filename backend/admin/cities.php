<?php
require __DIR__ . '/_bootstrap.php';
require_admin();
check_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)post('id'); $a = post('action');
    if ($a === 'city_save') {
        $f = [post('name'), post('state'), post('lat') !== '' ? (float)post('lat') : null, post('lng') !== '' ? (float)post('lng') : null, post('active') ? 1 : 0];
        if ($id) q("UPDATE cities SET name=?, state=?, lat=?, lng=?, active=? WHERE id=?", array_merge($f, [$id])); else q("INSERT INTO cities (name, state, lat, lng, active) VALUES (?,?,?,?,?)", $f);
        flash('City saved');
    } elseif ($a === 'area_add') { foreach (array_filter(array_map('trim', explode(',', post('names')))) as $n) q("INSERT INTO areas (city_id, name, pincode) VALUES (?,?,?)", [(int)post('city_id'), $n, post('pincode') ?: null]); flash('Area(s) added'); }
    elseif ($a === 'area_delete') { q("DELETE FROM areas WHERE id = ?", [$id]); flash('Area deleted'); }
    redirect('cities.php?city=' . (int)post('city_id'));
}

$cities = all("SELECT c.*, (SELECT COUNT(*) FROM shops s WHERE s.city_id = c.id AND s.status = 'verified') shops, (SELECT COUNT(*) FROM users u WHERE u.city_id = c.id) users, (SELECT COUNT(*) FROM areas a WHERE a.city_id = c.id) areas FROM cities c ORDER BY shops DESC, name");
$sel = (int)get('city') ?: (int)($cities[0]['id'] ?? 0);
$areas = all("SELECT a.*, (SELECT COUNT(*) FROM shops s WHERE s.city_id = a.city_id AND s.area = a.name) shops FROM areas a WHERE a.city_id = ? ORDER BY name", [$sel]);
page_header('Cities & Areas', 'cities', 'Cities where the app is live and the areas inside them', '<a class="btn p" href="#" data-open="cNew">+ Add city</a>');
?>
<div class="split">
<div class="card" style="padding:6px 8px"><div class="tw"><table><tr><th>City</th><th>State</th><th>Shops</th><th>Users</th><th>Areas</th><th>Status</th><th></th></tr>
<?php foreach ($cities as $c): ?><tr<?= $sel == $c['id'] ? ' style="background:#FFF6EE"' : '' ?>><td><a href="?city=<?= $c['id'] ?>"><b><?= h($c['name']) ?></b></a></td><td><?= h($c['state']) ?></td><td><?= indian_number($c['shops']) ?></td><td><?= indian_number($c['users']) ?></td><td><?= $c['areas'] ?></td><td><?= $c['active'] ? status_badge('active') : status_badge('hidden') ?></td><td><a class="btn sm" href="#" data-open="c<?= $c['id'] ?>">Edit</a></td></tr><?php endforeach; ?>
</table></div></div>
<div class="card"><h3>Areas · <?= h(val("SELECT name FROM cities WHERE id = ?", [$sel])) ?></h3>
<?php foreach ($areas as $a): ?><div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #F2EEEA"><span><?= h($a['name']) ?> <small style="color:var(--mute)"><?= h($a['pincode']) ?> · <?= $a['shops'] ?> shops</small></span>
<form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $a['id'] ?>"><input type="hidden" name="city_id" value="<?= $sel ?>"><button class="btn sm r" name="action" value="area_delete" data-confirm="Delete area?">×</button></form></div><?php endforeach; ?>
<form method="post" class="f"><?= csrf_field() ?><input type="hidden" name="city_id" value="<?= $sel ?>"><label>Add areas (comma separated)</label><input type="text" name="names" placeholder="Adyar, Mylapore" required><label>Pincode (optional)</label><input type="text" name="pincode"><button class="btn p" style="margin-top:10px" name="action" value="area_add">Add</button></form>
</div></div>
<?php foreach (array_merge([['id' => 0, 'name' => '', 'state' => '', 'lat' => '', 'lng' => '', 'active' => 1]], $cities) as $c): ?>
<div class="modal" id="<?= $c['id'] ? 'c' . $c['id'] : 'cNew' ?>"><form class="box f" method="post"><button type="button" class="x">×</button><h3><?= $c['id'] ? 'Edit city' : 'Add city' ?></h3><?= csrf_field() ?><input type="hidden" name="id" value="<?= $c['id'] ?>"><input type="hidden" name="city_id" value="<?= $c['id'] ?>">
<div class="fr"><div><label>City</label><input type="text" name="name" required value="<?= h($c['name']) ?>"></div><div><label>State</label><input type="text" name="state" value="<?= h($c['state']) ?>"></div></div>
<div class="fr"><div><label>Centre latitude</label><input type="text" name="lat" value="<?= h($c['lat']) ?>"></div><div><label>Centre longitude</label><input type="text" name="lng" value="<?= h($c['lng']) ?>"></div></div>
<label><input type="checkbox" name="active" value="1" <?= $c['active'] ? 'checked' : '' ?>> Live in app</label><button class="btn p" style="margin-top:12px" name="action" value="city_save">Save</button></form></div>
<?php endforeach; page_footer();
