<?php
require __DIR__ . '/_bootstrap.php';
require_admin();
check_csrf();

function recalc_rating($shopId) {
    q("UPDATE shops SET rating = COALESCE((SELECT ROUND(AVG(rating),1) FROM reviews WHERE shop_id = ? AND status = 'active'), rating) WHERE id = ?", [$shopId, $shopId]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $r = one("SELECT * FROM reviews WHERE id = ?", [(int)post('id')]);
    if ($r) {
        $map = ['approve' => 'active', 'flag' => 'flagged', 'hide' => 'hidden'];
        if (isset($map[post('action')])) { q("UPDATE reviews SET status = ? WHERE id = ?", [$map[post('action')], $r['id']]); flash('Review ' . $map[post('action')]); }
        if (post('action') === 'delete') { q("DELETE FROM reviews WHERE id = ?", [$r['id']]); flash('Review deleted'); }
        recalc_rating($r['shop_id']);
    }
    redirect('reviews.php?st=' . urlencode(post('st')));
}

$st = get('st');
$rows = all("SELECT r.*, s.name shop FROM reviews r JOIN shops s ON s.id = r.shop_id " . ($st ? 'WHERE r.status = ?' : '') . " ORDER BY r.status = 'flagged' DESC, r.id DESC LIMIT 300", $st ? [$st] : []);
page_header('Reviews', 'reviews', 'Review moderation — keep genuine reviews, flag or hide fake / abusive ones');
?>
<div class="tabs"><?php foreach (['' => 'All', 'flagged' => 'Flagged', 'active' => 'Active', 'hidden' => 'Hidden'] as $k => $l): ?><a class="<?= $st === $k ? 'on' : '' ?>" href="?st=<?= $k ?>"><?= $l ?><span><?= (int)val('SELECT COUNT(*) FROM reviews' . ($k ? ' WHERE status = ?' : ''), $k ? [$k] : []) ?></span></a><?php endforeach; ?></div>
<div class="card" style="padding:6px 8px"><div class="tw"><table>
<tr><th>Shop</th><th>Customer</th><th>Rating</th><th>Review</th><th>Date</th><th>Status</th><th></th></tr>
<?php if (!$rows): ?><tr><td colspan="7" class="empty">No reviews</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?><tr><td><b><?= h($r['shop']) ?></b></td><td><?= h($r['customer_name']) ?></td><td><span class="stars"><?= str_repeat('★', $r['rating']) ?></span><?= str_repeat('☆', 5 - $r['rating']) ?></td>
<td style="max-width:320px"><?= h($r['body']) ?></td><td><?= date('d M Y', strtotime($r['created_at'])) ?></td><td><?= status_badge($r['status']) ?></td>
<td style="white-space:nowrap"><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="st" value="<?= h($st) ?>">
<?php if ($r['status'] !== 'active'): ?><button class="btn sm g" name="action" value="approve">Keep</button><?php endif; ?>
<?php if ($r['status'] === 'active'): ?><button class="btn sm" name="action" value="flag">Flag</button><?php endif; ?>
<?php if ($r['status'] !== 'hidden'): ?><button class="btn sm" name="action" value="hide">Hide</button><?php endif; ?>
<button class="btn sm r" name="action" value="delete" data-confirm="Delete review?">Delete</button></form></td></tr><?php endforeach; ?>
</table></div></div>
<?php page_footer();
