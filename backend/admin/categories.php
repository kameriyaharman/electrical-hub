<?php
require __DIR__ . '/_bootstrap.php';
require_admin();
check_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('action'); $id = (int)post('id');
    try {
        if ($act === 'cat_save') {
            $img = !empty($_FILES['image']['name']) ? save_upload($_FILES['image'], 'categories', ['jpg', 'jpeg', 'png', 'webp']) : null;
            $f = [post('name'), post('icon') ?: null, (int)post('parent_id') ?: null, (int)post('sort'), post('active') ? 1 : 0];
            if ($id) { q("UPDATE categories SET name=?, icon=?, parent_id=?, sort=?, active=? WHERE id=?", array_merge($f, [$id])); if ($img) q("UPDATE categories SET image=? WHERE id=?", [$img, $id]); }
            else q("INSERT INTO categories (name, icon, parent_id, sort, active, image) VALUES (?,?,?,?,?,?)", array_merge($f, [$img]));
            flash('Category saved');
        } elseif ($act === 'cat_delete') {
            q("DELETE FROM shop_categories WHERE category_id = ?", [$id]); q("UPDATE categories SET parent_id = NULL WHERE parent_id = ?", [$id]); q("DELETE FROM categories WHERE id = ?", [$id]); flash('Category deleted');
        } elseif ($act === 'brand_save') {
            $logo = !empty($_FILES['logo']['name']) ? save_upload($_FILES['logo'], 'brands', ['jpg', 'jpeg', 'png', 'webp']) : null;
            if ($id) { q("UPDATE brands SET name=?, categories=?, active=? WHERE id=?", [post('name'), post('categories'), post('active') ? 1 : 0, $id]); if ($logo) q("UPDATE brands SET logo=? WHERE id=?", [$logo, $id]); }
            else q("INSERT INTO brands (name, categories, active, logo) VALUES (?,?,?,?)", [post('name'), post('categories'), post('active') ? 1 : 0, $logo]);
            flash('Brand saved');
        } elseif ($act === 'brand_delete') {
            q("DELETE FROM shop_brands WHERE brand_id = ?", [$id]); q("UPDATE products SET brand_id = NULL WHERE brand_id = ?", [$id]); q("DELETE FROM brands WHERE id = ?", [$id]); flash('Brand deleted');
        }
    } catch (Exception $e) { flash($e->getMessage(), 'err'); }
    redirect('categories.php');
}

$cats = all("SELECT c.*, p.name parent, (SELECT COUNT(*) FROM shop_categories sc JOIN shops s ON s.id = sc.shop_id AND s.status = 'verified' WHERE sc.category_id = c.id) shops FROM categories c LEFT JOIN categories p ON p.id = c.parent_id ORDER BY COALESCE(c.parent_id, c.id), c.parent_id IS NOT NULL, c.sort");
$brands = all("SELECT b.*, (SELECT COUNT(*) FROM shop_brands sb JOIN shops s ON s.id = sb.shop_id AND s.status = 'verified' WHERE sb.brand_id = b.id) shops FROM brands b ORDER BY shops DESC, b.name");
$viewBrand = (int)get('brand');

page_header('Categories & Brands', 'categories', 'Manage categories with images, sub-categories, brand directory and which shops stock which brands', '<a class="btn p" href="#" data-open="catNew">+ Add category</a>');
?>
<div class="cats">
<?php foreach ($cats as $c): ?>
  <div class="cat"><a class="ed" href="#" data-open="cat<?= $c['id'] ?>">Edit</a>
    <div class="em"><?= $c['image'] ? '<img src="' . h(upload_url($c['image'])) . '">' : h($c['icon'] ?: '⚡') ?></div>
    <b><?= h($c['name']) ?></b><small><?= $c['parent'] ? 'in ' . h($c['parent']) . ' · ' : '' ?><?= indian_number($c['shops']) ?> shops<?= $c['active'] ? '' : ' · hidden' ?></small>
  </div>
<?php endforeach; ?>
</div>

<div class="card" style="margin-top:18px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px"><h3 style="margin:0">Brand directory</h3><a class="btn sm p" href="#" data-open="brandNew">+ Add brand</a></div>
  <div class="tw"><table><tr><th>Brand</th><th>Category</th><th>Shops</th><th>Brand page</th><th></th></tr>
  <?php foreach ($brands as $b): ?>
    <tr><td><b><?= h($b['name']) ?></b></td><td><?= h($b['categories']) ?></td><td><a href="?brand=<?= $b['id'] ?>#bs"><?= indian_number($b['shops']) ?></a></td><td><?= $b['active'] ? status_badge('active') : status_badge('hidden') ?></td>
      <td style="white-space:nowrap"><a class="btn sm" href="#" data-open="brand<?= $b['id'] ?>">Edit</a></td></tr>
  <?php endforeach; ?></table></div>
</div>

<?php if ($viewBrand): $bn = val("SELECT name FROM brands WHERE id = ?", [$viewBrand]); $bs = all("SELECT s.id, s.name, s.area, c.name city FROM shop_brands sb JOIN shops s ON s.id = sb.shop_id LEFT JOIN cities c ON c.id = s.city_id WHERE sb.brand_id = ? ORDER BY c.name, s.name", [$viewBrand]); ?>
<div class="card" style="margin-top:14px" id="bs"><h3>Shops stocking <?= h($bn) ?> (<?= count($bs) ?>)</h3>
<div class="tw"><table><tr><th>Shop</th><th>City / area</th></tr>
<?php foreach ($bs as $s): ?><tr><td><a href="shops.php?view=<?= $s['id'] ?>"><?= h($s['name']) ?></a></td><td><?= h($s['city']) ?> · <?= h($s['area']) ?></td></tr><?php endforeach; ?>
</table></div></div>
<?php endif; ?>

<?php
$parents = all("SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY sort");
foreach (array_merge([['id' => 0, 'name' => '', 'icon' => '', 'parent_id' => null, 'sort' => count($cats), 'active' => 1]], $cats) as $c): ?>
<div class="modal" id="<?= $c['id'] ? 'cat' . $c['id'] : 'catNew' ?>"><form class="box f" method="post" enctype="multipart/form-data"><button type="button" class="x">×</button>
  <h3><?= $c['id'] ? 'Edit category' : 'Add category' ?></h3><?= csrf_field() ?><input type="hidden" name="id" value="<?= $c['id'] ?>">
  <label>Name</label><input type="text" name="name" required value="<?= h($c['name']) ?>">
  <div class="fr"><div><label>Icon (emoji)</label><input type="text" name="icon" value="<?= h($c['icon']) ?>" placeholder="💡"></div><div><label>Sort order</label><input type="number" name="sort" value="<?= (int)$c['sort'] ?>"></div></div>
  <label>Parent (for sub-category)</label><select name="parent_id"><option value="">— Main category —</option><?php foreach ($parents as $p) if ($p['id'] != $c['id']): ?><option value="<?= $p['id'] ?>" <?= $c['parent_id'] == $p['id'] ? 'selected' : '' ?>><?= h($p['name']) ?></option><?php endif; ?></select>
  <label>Image</label><input type="file" name="image" accept="image/*">
  <label><input type="checkbox" name="active" value="1" <?= $c['active'] ? 'checked' : '' ?>> Show in app</label>
  <div style="display:flex;gap:8px;margin-top:12px"><button class="btn p" name="action" value="cat_save">Save</button>
  <?php if ($c['id']): ?><button class="btn r" name="action" value="cat_delete" data-confirm="Delete this category?">Delete</button><?php endif; ?></div>
</form></div>
<?php endforeach;
foreach (array_merge([['id' => 0, 'name' => '', 'categories' => '', 'active' => 1]], $brands) as $b): ?>
<div class="modal" id="<?= $b['id'] ? 'brand' . $b['id'] : 'brandNew' ?>"><form class="box f" method="post" enctype="multipart/form-data"><button type="button" class="x">×</button>
  <h3><?= $b['id'] ? 'Edit brand' : 'Add brand' ?></h3><?= csrf_field() ?><input type="hidden" name="id" value="<?= $b['id'] ?>">
  <label>Brand name</label><input type="text" name="name" required value="<?= h($b['name']) ?>">
  <label>Categories (text)</label><input type="text" name="categories" value="<?= h($b['categories']) ?>" placeholder="Switches, MCB, fans">
  <label>Logo</label><input type="file" name="logo" accept="image/*">
  <label><input type="checkbox" name="active" value="1" <?= $b['active'] ? 'checked' : '' ?>> Active</label>
  <div style="display:flex;gap:8px;margin-top:12px"><button class="btn p" name="action" value="brand_save">Save</button>
  <?php if ($b['id']): ?><button class="btn r" name="action" value="brand_delete" data-confirm="Delete this brand?">Delete</button><?php endif; ?></div>
</form></div>
<?php endforeach; page_footer();
