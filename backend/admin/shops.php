<?php
require __DIR__ . '/_bootstrap.php';
require_admin();
check_csrf();

// ---------- actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)post('id');
    $act = post('action');
    $back = 'shops.php?' . http_build_query(array_filter(['tab' => post('tab'), 'view' => $id ?: null]));
    if ($act === 'approve') { q("UPDATE shops SET status = 'verified', reject_reason = NULL WHERE id = ?", [$id]); notify_owner($id, 'Your shop is live! 🎉', 'Your listing has been verified and is now visible to customers.'); flash('Shop approved and live'); }
    elseif ($act === 'reject') { q("UPDATE shops SET status = 'rejected', reject_reason = ? WHERE id = ?", [post('reason') ?: 'Details not clear', $id]); notify_owner($id, 'Listing needs changes', 'Reason: ' . (post('reason') ?: 'Details not clear') . '. Please update and resubmit.'); flash('Shop rejected'); }
    elseif ($act === 'block') { q("UPDATE shops SET status = 'blocked' WHERE id = ?", [$id]); flash('Shop blocked'); }
    elseif ($act === 'unblock') { q("UPDATE shops SET status = 'verified' WHERE id = ?", [$id]); flash('Shop unblocked'); }
    elseif ($act === 'delete') {
        foreach (['shop_categories', 'shop_brands', 'shop_products', 'shop_photos', 'offers', 'saved_shops', 'quote_recipients'] as $t) q("DELETE FROM $t WHERE shop_id = ?", [$id]);
        q("DELETE FROM shops WHERE id = ?", [$id]); flash('Shop deleted'); $back = 'shops.php';
    }
    elseif ($act === 'photo_delete') { q("DELETE FROM shop_photos WHERE id = ? AND shop_id = ?", [(int)post('photo_id'), $id]); flash('Photo removed'); }
    elseif ($act === 'import') {
        try { $n = import_shops($_FILES['file'] ?? null); flash("$n shops imported (status: verified)"); }
        catch (Exception $e) { flash($e->getMessage(), 'err'); }
        $back = 'shops.php';
    }
    redirect($back);
}

function notify_owner($shopId, $title, $body) {
    $uid = val("SELECT owner_user_id FROM shops WHERE id = ?", [$shopId]);
    if ($uid) q("INSERT INTO notifications (user_id, title, body) VALUES (?,?,?)", [$uid, $title, $body]);
}

/** Import from .csv or .xlsx — columns: name, owner, mobile, whatsapp, type, city, area, address, lat, lng, categories, brands, gstin */
function import_shops($file) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) throw new Exception('Choose a file to import');
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $rows = $ext === 'xlsx' ? read_xlsx($file['tmp_name']) : read_csv($file['tmp_name']);
    if (count($rows) < 2) throw new Exception('File has no data rows');
    $head = array_map(fn($x) => strtolower(trim($x)), array_shift($rows));
    $col = fn($r, $k) => ($i = array_search($k, $head)) !== false ? trim((string)($r[$i] ?? '')) : '';
    $n = 0;
    foreach ($rows as $r) {
        $name = $col($r, 'name'); $mobile = substr(preg_replace('/\D/', '', $col($r, 'mobile')), -10);
        if ($name === '' || strlen($mobile) !== 10) continue;
        $cityName = $col($r, 'city');
        $cityId = $cityName ? val("SELECT id FROM cities WHERE name = ?", [$cityName]) : null;
        if ($cityName && !$cityId) { q("INSERT INTO cities (name) VALUES (?)", [$cityName]); $cityId = last_id(); }
        q("INSERT INTO shops (name, owner_name, mobile, whatsapp, shop_type, city_id, area, address, lat, lng, gstin, status) VALUES (?,?,?,?,?,?,?,?,?,?,?, 'verified')",
            [$name, $col($r, 'owner'), $mobile, substr(preg_replace('/\D/', '', $col($r, 'whatsapp')), -10) ?: $mobile,
                in_array($col($r, 'type'), ['Retail', 'Wholesale', 'Both']) ? $col($r, 'type') : 'Retail', $cityId, $col($r, 'area'), $col($r, 'address'),
                $col($r, 'lat') !== '' ? (float)$col($r, 'lat') : null, $col($r, 'lng') !== '' ? (float)$col($r, 'lng') : null, $col($r, 'gstin') ?: null]);
        $sid = last_id();
        foreach (array_filter(array_map('trim', explode(',', $col($r, 'categories')))) as $c) {
            $cid = val("SELECT id FROM categories WHERE name LIKE ?", [$c . '%']); if ($cid) q("INSERT IGNORE INTO shop_categories VALUES (?,?)", [$sid, $cid]);
        }
        foreach (array_filter(array_map('trim', explode(',', $col($r, 'brands')))) as $b) {
            $bid = val("SELECT id FROM brands WHERE name = ?", [$b]); if (!$bid) { q("INSERT INTO brands (name) VALUES (?)", [$b]); $bid = last_id(); }
            q("INSERT IGNORE INTO shop_brands VALUES (?,?)", [$sid, $bid]);
        }
        $n++;
    }
    return $n;
}
function read_csv($path) { $rows = []; $fh = fopen($path, 'r'); while (($r = fgetcsv($fh)) !== false) $rows[] = $r; fclose($fh); return $rows; }
function read_xlsx($path) {
    if (!class_exists('ZipArchive')) throw new Exception('Server cannot read .xlsx — save the sheet as CSV and upload that');
    $z = new ZipArchive(); if ($z->open($path) !== true) throw new Exception('Could not open Excel file');
    $strings = [];
    if (($xml = $z->getFromName('xl/sharedStrings.xml')) !== false) {
        $sx = simplexml_load_string($xml);
        foreach ($sx->si as $si) { $t = ''; if (isset($si->t)) $t = (string)$si->t; else foreach ($si->r as $run) $t .= (string)$run->t; $strings[] = $t; }
    }
    $sheet = simplexml_load_string($z->getFromName('xl/worksheets/sheet1.xml')); $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $out = [];
        foreach ($row->c as $c) {
            $ref = preg_replace('/\d/', '', (string)$c['r']); $idx = 0;
            foreach (str_split($ref) as $ch) $idx = $idx * 26 + (ord($ch) - 64);
            $v = (string)$c->v;
            if ((string)$c['t'] === 's') $v = $strings[(int)$v] ?? '';
            elseif ((string)$c['t'] === 'inlineStr') $v = (string)$c->is->t;
            $out[$idx - 1] = $v;
        }
        if ($out) { $max = max(array_keys($out)); $rows[] = array_map(fn($i) => $out[$i] ?? '', range(0, $max)); }
    }
    return $rows;
}

// ---------- list ----------
$tab = get('tab', 'all');
$qq = get('q'); $city = (int)get('city');
$where = ['1=1']; $p = [];
$tabs = ['all' => "s.status <> 'draft'", 'pending' => "s.status = 'pending'", 'verified' => "s.status = 'verified'", 'rejected' => "s.status = 'rejected'", 'blocked' => "s.status = 'blocked'", 'draft' => "s.status = 'draft'"];
$where[] = $tabs[$tab] ?? $tabs['all'];
if ($qq !== '') { $where[] = '(s.name LIKE ? OR s.owner_name LIKE ? OR s.mobile LIKE ? OR s.area LIKE ? OR c.name LIKE ?)'; array_push($p, "%$qq%", "%$qq%", "%$qq%", "%$qq%", "%$qq%"); }
if ($city) { $where[] = 's.city_id = ?'; $p[] = $city; }
$w = implode(' AND ', $where);
$page = max(1, (int)get('page', 1)); $per = 25;
$total = (int)val("SELECT COUNT(*) FROM shops s LEFT JOIN cities c ON c.id = s.city_id WHERE $w", $p);
$rows = all("SELECT s.*, c.name city, (SELECT COUNT(*) FROM shop_photos ph WHERE ph.shop_id = s.id) photos FROM shops s LEFT JOIN cities c ON c.id = s.city_id WHERE $w ORDER BY (s.status = 'pending') DESC, s.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p);
$counts = [];
foreach ($tabs as $k => $cond) $counts[$k] = (int)val("SELECT COUNT(*) FROM shops s WHERE $cond");

$viewId = (int)get('view') ?: (int)($tab === 'pending' && $rows ? $rows[0]['id'] : 0);
$v = $viewId ? one("SELECT s.*, c.name city FROM shops s LEFT JOIN cities c ON c.id = s.city_id WHERE s.id = ?", [$viewId]) : null;

$actions = '<a class="btn" href="#" data-open="importModal">Import shops (Excel)</a><a class="btn p" href="shop_edit.php">+ Add shop</a>';
page_header('Shops & Verification', 'shops', 'Every shop listing — live-photo check, GPS match, duplicate check, approve / reject, edit, block', $actions);
?>
<div class="tabs">
<?php foreach (['all' => 'All', 'pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected', 'blocked' => 'Blocked', 'draft' => 'Incomplete'] as $k => $lbl):
    if (in_array($k, ['blocked', 'draft']) && !$counts[$k]) continue; ?>
  <a class="<?= $tab === $k ? 'on' : '' ?>" href="?tab=<?= $k ?>"><?= $lbl ?><span><?= indian_number($counts[$k]) ?></span></a>
<?php endforeach; ?>
</div>
<form class="filters"><input type="hidden" name="tab" value="<?= h($tab) ?>">
  <input type="text" name="q" placeholder="Search…" value="<?= h($qq) ?>">
  <select name="city"><option value="">All cities</option><?php foreach (all("SELECT id, name FROM cities ORDER BY name") as $c): ?><option value="<?= $c['id'] ?>" <?= $city == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select>
  <button class="btn">Filter</button>
</form>

<div class="<?= $v ? 'split' : '' ?>">
<div class="card" style="padding:6px 8px"><div class="tw"><table>
<tr><th>Shop</th><th>Owner</th><th>City / area</th><th>Type</th><th>Photos</th><th>Status</th><th>Action</th></tr>
<?php if (!$rows): ?><tr><td colspan="7" class="empty">No shops found</td></tr><?php endif; ?>
<?php foreach ($rows as $s): ?>
<tr<?= $v && $v['id'] == $s['id'] ? ' style="background:#FFF6EE"' : '' ?>>
  <td><b><?= h($s['name']) ?></b><small><?= h($s['mobile']) ?></small></td>
  <td><?= h($s['owner_name']) ?></td>
  <td><?= h($s['city']) ?><small><?= h($s['area']) ?></small></td>
  <td><?= h($s['shop_type']) ?></td>
  <td><?= $s['photos'] ? $s['photos'] . '/' . MAX_SHOP_PHOTOS : '<span class="bad">0</span>' ?></td>
  <td><?= status_badge($s['status']) ?><?php if ($s['status'] === 'rejected' && $s['reject_reason']): ?><small><?= h($s['reject_reason']) ?></small><?php endif; ?></td>
  <td style="white-space:nowrap">
    <?php if ($s['status'] === 'pending'): ?><a class="btn sm p" href="?<?= h(http_build_query(['tab' => $tab, 'view' => $s['id']])) ?>">Verify</a>
    <?php else: ?><a class="btn sm" href="?<?= h(http_build_query(['tab' => $tab, 'view' => $s['id'], 'q' => $qq])) ?>">View</a><?php endif; ?>
    <a class="btn sm" href="shop_edit.php?id=<?= $s['id'] ?>">Edit</a>
  </td>
</tr>
<?php endforeach; ?>
</table></div><?= paginate($total, $per, $page) ?></div>

<?php if ($v):
    $photos = all("SELECT * FROM shop_photos WHERE shop_id = ? ORDER BY id DESC", [$v['id']]);
    $radius = (int)setting('gps_match_radius_m', 200);
    $dists = array_filter(array_column($photos, 'distance_m'), fn($d) => $d !== null);
    $maxDist = $dists ? max($dists) : null;
    $nCam = count(array_filter($photos, fn($x) => $x['source'] === 'camera'));
    $cameraOnly = $photos && $nCam === count($photos);
    $dup = all("SELECT id, name FROM shops WHERE id <> ? AND (mobile = ? OR (name = ? AND city_id <=> ?)) LIMIT 3", [$v['id'], $v['mobile'], $v['name'], $v['city_id']]);
    $cats = all("SELECT c.name FROM shop_categories sc JOIN categories c ON c.id = sc.category_id WHERE sc.shop_id = ?", [$v['id']]);
    $brs = all("SELECT b.name FROM shop_brands sb JOIN brands b ON b.id = sb.brand_id WHERE sb.shop_id = ?", [$v['id']]);
?>
<div class="card">
  <h3><?= $v['status'] === 'pending' ? 'Verify' : 'Shop' ?> · <?= h($v['name']) ?></h3>
  <div style="font-size:12.5px;color:var(--mute);margin-top:-6px"><?= h($v['address'] ?: ($v['area'] . ', ' . $v['city'])) ?></div>
  <div class="thumbs">
    <?php foreach ($photos as $ph): ?>
      <a href="<?= h(upload_url($ph['path'])) ?>" target="_blank"><img src="<?= h(upload_url($ph['path'])) ?>" alt=""><span><?= h($ph['label']) ?> · <?= $ph['source'] === 'camera' ? '📷 ' : '🖼 ' ?><?= date('d M H:i', strtotime($ph['taken_at'])) ?></span></a>
    <?php endforeach; ?>
  </div>
  <div class="vlist">
    <div><span>Photos</span><?= count($photos) ? '<b class="' . ($nCam ? 'ok' : 'warn') . '">' . count($photos) . ' · ' . $nCam . ' live camera' . (count($photos) - $nCam ? ', ' . (count($photos) - $nCam) . ' gallery/admin' : '') . '</b>' : '<b class="bad">Missing ✕</b>' ?></div>
    <div><span>GPS match</span><?php if ($maxDist === null): ?><b class="warn">No GPS on photos</b><?php elseif ($maxDist <= $radius): ?><b class="ok">Within <?= $maxDist ?> m of pin ✓</b><?php else: ?><b class="bad"><?= $maxDist ?> m away ✕</b><?php endif; ?></div>
    <div><span>Mobile OTP</span><?= $v['mobile_verified'] ? '<b class="ok">Verified ✓</b>' : '<b class="warn">Not verified</b>' ?></div>
    <div><span>GSTIN</span><b><?= $v['gstin'] ? h($v['gstin']) : '<span style="color:var(--mute);font-weight:400">Not given</span>' ?></b></div>
    <div><span>Duplicate check</span><?= $dup ? '<b class="bad">' . h(implode(', ', array_column($dup, 'name'))) . '</b>' : '<b class="ok">No match ✓</b>' ?></div>
    <div><span>Owner</span><b><?= h($v['owner_name']) ?> · <?= h($v['mobile']) ?></b></div>
    <div><span>Type</span><b><?= h($v['shop_type']) ?><?= $v['is_service'] ? ' + Service' : '' ?></b></div>
    <div><span>Timings</span><b><?= fmt_time12($v['open_time']) ?> – <?= fmt_time12($v['close_time']) ?></b></div>
    <div><span>Categories</span><b style="text-align:right"><?= h(implode(', ', array_column($cats, 'name')) ?: '—') ?></b></div>
    <div><span>Brands</span><b style="text-align:right"><?= h(implode(', ', array_column($brs, 'name')) ?: '—') ?></b></div>
    <div><span>Location</span><b><?php if ($v['lat']): ?><a target="_blank" href="https://maps.google.com/?q=<?= $v['lat'] ?>,<?= $v['lng'] ?>">Open map ↗</a><?php else: ?>—<?php endif; ?></b></div>
    <div><span>Views · Calls · WhatsApp</span><b><?= $v['views'] ?> · <?= $v['calls'] ?> · <?= $v['whatsapp_clicks'] ?></b></div>
  </div>
  <div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap">
    <?php if ($v['status'] !== 'verified' && $v['status'] !== 'blocked'): ?>
      <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $v['id'] ?>"><input type="hidden" name="tab" value="<?= h($tab) ?>"><button class="btn g" name="action" value="approve">Approve</button></form>
    <?php endif; ?>
    <?php if ($v['status'] !== 'rejected'): ?><a class="btn r" href="#" data-open="rejectModal">Reject with reason</a><?php endif; ?>
    <?php if ($v['status'] === 'verified'): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $v['id'] ?>"><button class="btn" name="action" value="block" data-confirm="Block this shop? It will be hidden from the app.">Block</button></form><?php endif; ?>
    <?php if ($v['status'] === 'blocked'): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $v['id'] ?>"><button class="btn" name="action" value="unblock">Unblock</button></form><?php endif; ?>
    <a class="btn" href="shop_edit.php?id=<?= $v['id'] ?>">Edit</a>
    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $v['id'] ?>"><button class="btn r" name="action" value="delete" data-confirm="Delete this shop permanently?">Delete</button></form>
  </div>
</div>
<div class="modal" id="rejectModal"><form class="box f" method="post"><button type="button" class="x">×</button>
  <h3>Reject · <?= h($v['name']) ?></h3><?= csrf_field() ?><input type="hidden" name="id" value="<?= $v['id'] ?>"><input type="hidden" name="tab" value="<?= h($tab) ?>">
  <label>Reason (shown to shop owner)</label>
  <select name="reason"><option>Photos missing</option><option>Photos not taken at shop (GPS mismatch)</option><option>Shop board not visible in photo</option><option>Duplicate listing</option><option>Wrong mobile / details</option><option>Not an electrical shop</option></select>
  <button class="btn r" style="margin-top:14px" name="action" value="reject">Reject listing</button>
</form></div>
<?php endif; ?>
</div>

<div class="modal" id="importModal"><form class="box f" method="post" enctype="multipart/form-data"><button type="button" class="x">×</button>
  <h3>Import shops from Excel</h3>
  <p style="color:var(--mute);font-size:12.5px;margin:0">Upload <b>.xlsx</b> or <b>.csv</b>. First row = headings:<br><code>name, owner, mobile, whatsapp, type, city, area, address, lat, lng, categories, brands, gstin</code><br>Categories / brands can be comma-separated inside the cell. <a href="sample_import.csv">Download sample</a></p>
  <?= csrf_field() ?><label>File</label><input type="file" name="file" accept=".xlsx,.csv" required>
  <button class="btn p" style="margin-top:14px" name="action" value="import">Import</button>
</form></div>
<?php page_footer();
