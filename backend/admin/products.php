<?php
require __DIR__ . '/_bootstrap.php';
require_admin();
check_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)post('id');
    try {
        if (post('action') === 'save') {
            $img = !empty($_FILES['image']['name']) ? save_upload($_FILES['image'], 'products', ['jpg', 'jpeg', 'png', 'webp']) : null;
            $f = [post('name'), (int)post('category_id') ?: null, (int)post('brand_id') ?: null, post('unit'), (int)post('min_price'), (int)post('max_price'), post('active') ? 1 : 0];
            if ($id) { q("UPDATE products SET name=?, category_id=?, brand_id=?, unit=?, min_price=?, max_price=?, active=? WHERE id=?", array_merge($f, [$id])); if ($img) q("UPDATE products SET image=? WHERE id=?", [$img, $id]); }
            else q("INSERT INTO products (name, category_id, brand_id, unit, min_price, max_price, active, image) VALUES (?,?,?,?,?,?,?,?)", array_merge($f, [$img]));
            flash('Product saved');
        } elseif (post('action') === 'delete') { q("DELETE FROM shop_products WHERE product_id = ?", [$id]); q("DELETE FROM products WHERE id = ?", [$id]); flash('Product deleted'); }
    } catch (Exception $e) { flash($e->getMessage(), 'err'); }
    redirect('products.php');
}

$cat = (int)get('cat'); $qq = get('q');
$where = ['1=1']; $p = [];
if ($cat) { $where[] = 'p.category_id = ?'; $p[] = $cat; }
if ($qq !== '') { $where[] = 'p.name LIKE ?'; $p[] = "%$qq%"; }
$rows = all("SELECT p.*, c.name cat, b.name brand, (SELECT COUNT(*) FROM shop_products sp WHERE sp.product_id = p.id) shops FROM products p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN brands b ON b.id = p.brand_id WHERE " . implode(' AND ', $where) . " ORDER BY c.sort, p.name", $p);
$cats = all("SELECT id, name FROM categories ORDER BY sort");
$brands = all("SELECT id, name FROM brands ORDER BY name");

page_header('Products', 'products', 'Product catalogue shown in the app with price range and number of shops that stock it', '<a class="btn p" href="#" data-open="pNew">+ Add product</a>');
?>
<form class="filters"><input type="text" name="q" placeholder="Search product…" value="<?= h($qq) ?>">
<select name="cat"><option value="">All categories</option><?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $cat == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select><button class="btn">Filter</button></form>
<div class="card" style="padding:6px 8px"><div class="tw"><table>
<tr><th>Product</th><th>Category</th><th>Brand</th><th>Price range</th><th>Shops</th><th>Status</th><th></th></tr>
<?php if (!$rows): ?><tr><td colspan="7" class="empty">No products</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?><tr><td><b><?= h($r['name']) ?></b><small>per <?= h($r['unit'] ?: 'pc') ?></small></td><td><?= h($r['cat']) ?></td><td><?= h($r['brand'] ?: '—') ?></td>
<td>₹<?= indian_number($r['min_price']) ?> – ₹<?= indian_number($r['max_price']) ?></td><td><?= $r['shops'] ?></td><td><?= $r['active'] ? status_badge('active') : status_badge('hidden') ?></td>
<td><a class="btn sm" href="#" data-open="p<?= $r['id'] ?>">Edit</a></td></tr><?php endforeach; ?>
</table></div></div>
<?php foreach (array_merge([['id' => 0, 'name' => '', 'category_id' => $cat, 'brand_id' => null, 'unit' => 'pc', 'min_price' => '', 'max_price' => '', 'active' => 1]], $rows) as $r): ?>
<div class="modal" id="<?= $r['id'] ? 'p' . $r['id'] : 'pNew' ?>"><form class="box f" method="post" enctype="multipart/form-data"><button type="button" class="x">×</button>
<h3><?= $r['id'] ? 'Edit product' : 'Add product' ?></h3><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
<label>Product name</label><input type="text" name="name" required value="<?= h($r['name']) ?>">
<div class="fr"><div><label>Category</label><select name="category_id"><?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $r['category_id'] == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></div>
<div><label>Brand</label><select name="brand_id"><option value="">—</option><?php foreach ($brands as $b): ?><option value="<?= $b['id'] ?>" <?= $r['brand_id'] == $b['id'] ? 'selected' : '' ?>><?= h($b['name']) ?></option><?php endforeach; ?></select></div></div>
<div class="fr3"><div><label>Unit</label><input type="text" name="unit" value="<?= h($r['unit']) ?>"></div><div><label>Min price ₹</label><input type="number" name="min_price" value="<?= h($r['min_price']) ?>"></div><div><label>Max price ₹</label><input type="number" name="max_price" value="<?= h($r['max_price']) ?>"></div></div>
<label>Image</label><input type="file" name="image" accept="image/*">
<label><input type="checkbox" name="active" value="1" <?= $r['active'] ? 'checked' : '' ?>> Show in app</label>
<div style="display:flex;gap:8px;margin-top:12px"><button class="btn p" name="action" value="save">Save</button><?php if ($r['id']): ?><button class="btn r" name="action" value="delete" data-confirm="Delete product?">Delete</button><?php endif; ?></div>
</form></div>
<?php endforeach; page_footer();
