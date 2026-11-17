<?php
require __DIR__ . '/_bootstrap.php';
require_admin();
check_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)post('id');
    try {
        if (post('action') === 'save') {
            $img = !empty($_FILES['image']['name']) ? save_upload($_FILES['image'], 'banners', ['jpg', 'jpeg', 'png', 'webp']) : null;
            $vid = !empty($_FILES['video']['name']) ? save_upload($_FILES['video'], 'banners', ['mp4'], 50) : null;
            $type = post('media_type') === 'video' ? 'video' : 'image';
            if ($type === 'video' && !$vid && !($id && val("SELECT video FROM banners WHERE id = ?", [$id]))) throw new Exception('Upload an MP4 video for a video banner');
            $radius = post('radius_km') !== '' && (int)post('shop_id') ? (float)post('radius_km') : null;
            $f = [post('title'), post('subtitle'), (int)post('shop_id') ?: null, (int)post('city_id') ?: null, post('start_date') ?: null, post('end_date') ?: null, post('status'), (int)post('sort'), $type, $radius];
            if ($id) {
                q("UPDATE banners SET title=?, subtitle=?, shop_id=?, city_id=?, start_date=?, end_date=?, status=?, sort=?, media_type=?, radius_km=? WHERE id=?", array_merge($f, [$id]));
                if ($img) q("UPDATE banners SET image=? WHERE id=?", [$img, $id]);
                if ($vid) q("UPDATE banners SET video=? WHERE id=?", [$vid, $id]);
            } else {
                q("INSERT INTO banners (title, subtitle, shop_id, city_id, start_date, end_date, status, sort, media_type, radius_km, image, video) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)", array_merge($f, [$img, $vid]));
            }
            flash('Banner saved');
        } elseif (post('action') === 'delete') { q("DELETE FROM banners WHERE id = ?", [$id]); flash('Banner deleted'); }
    } catch (Exception $e) { flash($e->getMessage(), 'err'); }
    redirect('banners.php');
}

$rows = all("SELECT b.*, s.name shop, c.name city FROM banners b LEFT JOIN shops s ON s.id = b.shop_id LEFT JOIN cities c ON c.id = b.city_id ORDER BY b.status = 'active' DESC, b.sort, b.id DESC");
$shops = all("SELECT id, name FROM shops WHERE status = 'verified' ORDER BY name");
$cities = all("SELECT id, name FROM cities ORDER BY name");
page_header('Banners & Videos', 'banners', 'Home-page slider in the app — image or video, per city or near a shop. Only admins can add these.', '<a class="btn p" href="#" data-open="bNew">+ Add banner / video</a>');
?>
<div class="grid k3">
<?php if (!$rows): ?><div class="card empty">No banners yet</div><?php endif; ?>
<?php foreach ($rows as $b): ?>
<div class="card" style="padding:0;overflow:hidden">
  <?php if ($b['media_type'] === 'video' && $b['video']): ?>
  <video src="<?= h(upload_url($b['video'])) ?>" muted loop playsinline preload="metadata" style="width:100%;height:120px;object-fit:cover;background:#000;display:block" onmouseover="this.play()" onmouseout="this.pause()"></video>
  <?php else: ?>
  <div style="height:120px;background:<?= $b['image'] ? 'url(' . h(upload_url($b['image'])) . ') center/cover' : 'linear-gradient(120deg,#121212,#F26A0B)' ?>;color:#fff;padding:16px;display:flex;flex-direction:column;justify-content:flex-end">
    <?php if (!$b['image']): ?><b style="font-size:16px"><?= h($b['title']) ?></b><small style="opacity:.85"><?= h($b['subtitle']) ?></small><?php endif; ?>
  </div>
  <?php endif; ?>
  <div style="padding:12px 14px">
    <div style="display:flex;justify-content:space-between;align-items:center"><b><?= $b['media_type'] === 'video' ? '🎬 ' : '' ?><?= h($b['title']) ?></b><?= status_badge($b['status']) ?></div>
    <small style="color:var(--mute)">📍 <?= $b['city'] ? h($b['city']) : 'All cities' ?><?= $b['radius_km'] && $b['shop'] ? ' · within ' . (float)$b['radius_km'] . ' km of ' . h($b['shop']) : ($b['shop'] ? ' · opens ' . h($b['shop']) : '') ?><?= $b['end_date'] ? ' · till ' . date('d M', strtotime($b['end_date'])) : '' ?></small>
    <div style="margin-top:8px"><a class="btn sm" href="#" data-open="b<?= $b['id'] ?>">Edit</a></div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php foreach (array_merge([['id' => 0, 'title' => '', 'subtitle' => '', 'shop_id' => null, 'city_id' => null, 'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+30 days')), 'amount' => 0, 'status' => 'active', 'sort' => 0, 'media_type' => 'image', 'radius_km' => null, 'video' => null]], $rows) as $b): ?>
<div class="modal" id="<?= $b['id'] ? 'b' . $b['id'] : 'bNew' ?>"><form class="box f" method="post" enctype="multipart/form-data"><button type="button" class="x">×</button>
<h3><?= $b['id'] ? 'Edit banner' : 'Add banner' ?></h3><?= csrf_field() ?><input type="hidden" name="id" value="<?= $b['id'] ?>">
<label>Title</label><input type="text" name="title" required value="<?= h($b['title']) ?>">
<label>Subtitle</label><input type="text" name="subtitle" value="<?= h($b['subtitle']) ?>">
<label>Type</label><div class="chips"><label><input type="radio" name="media_type" value="image" <?= $b['media_type'] !== 'video' ? 'checked' : '' ?>> Image banner</label><label><input type="radio" name="media_type" value="video" <?= $b['media_type'] === 'video' ? 'checked' : '' ?>> Video</label></div>
<label>Image (16:7, e.g. 1200×525) — for image banners, or video cover</label><input type="file" name="image" accept="image/*">
<label>Video (MP4, max 50 MB, short 5–20 sec works best · plays muted)<?= $b['video'] ? ' — current video kept if empty' : '' ?></label><input type="file" name="video" accept="video/mp4">
<h4 style="margin:16px 0 0;color:var(--navy)">Where to show</h4>
<div class="fr"><div><label>Opens shop</label><select name="shop_id"><option value="">— none —</option><?php foreach ($shops as $s): ?><option value="<?= $s['id'] ?>" <?= $b['shop_id'] == $s['id'] ? 'selected' : '' ?>><?= h($s['name']) ?></option><?php endforeach; ?></select></div>
<div><label>City</label><select name="city_id"><option value="">All cities</option><?php foreach ($cities as $c): ?><option value="<?= $c['id'] ?>" <?= $b['city_id'] == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></div></div>
<label>Only show to people within … km of the linked shop (optional)</label><input type="number" step="0.5" min="0" name="radius_km" value="<?= h($b['radius_km']) ?>" placeholder="e.g. 5 — leave empty for the whole city">
<div class="fr"><div><label>Start</label><input type="date" name="start_date" value="<?= h($b['start_date']) ?>"></div><div><label>End</label><input type="date" name="end_date" value="<?= h($b['end_date']) ?>"></div><input type="hidden" name="amount" value="0"></div>
<div class="fr"><div><label>Status</label><select name="status"><?php foreach (['active', 'pending', 'expired'] as $o): ?><option <?= $b['status'] === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div><div><label>Sort</label><input type="number" name="sort" value="<?= (int)$b['sort'] ?>"></div></div>
<div style="display:flex;gap:8px;margin-top:12px"><button class="btn p" name="action" value="save">Save</button><?php if ($b['id']): ?><button class="btn r" name="action" value="delete" data-confirm="Delete banner?">Delete</button><?php endif; ?></div>
</form></div>
<?php endforeach; page_footer();
