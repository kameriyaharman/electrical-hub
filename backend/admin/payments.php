<?php
require __DIR__ . '/_bootstrap.php';
require_admin();
check_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)post('id');
    if (post('action') === 'confirm') {
        q("UPDATE payments SET status = 'success' WHERE id = ?", [$id]);
        $sub = val("SELECT id FROM subscriptions WHERE payment_id = ? AND status = 'pending'", [$id]);
        if ($sub) activate_subscription($sub);
        flash('Payment confirmed' . ($sub ? ' and plan activated' : ''));
    } elseif (post('action') === 'fail') {
        q("UPDATE payments SET status = 'failed' WHERE id = ?", [$id]);
        q("UPDATE subscriptions SET status = 'cancelled' WHERE payment_id = ? AND status = 'pending'", [$id]);
        flash('Payment marked failed');
    }
    redirect('payments.php');
}

$st = get('st'); $month = preg_match('/^\d{4}-\d{2}$/', get('m')) ? get('m') : '';
$where = ['1=1']; $p = [];
if ($st) { $where[] = 'p.status = ?'; $p[] = $st; }
if ($month) { $where[] = "DATE_FORMAT(p.created_at, '%Y-%m') = ?"; $p[] = $month; }
$rows = all("SELECT p.*, s.name shop FROM payments p LEFT JOIN shops s ON s.id = p.shop_id WHERE " . implode(' AND ', $where) . " ORDER BY p.status = 'pending' DESC, p.id DESC LIMIT 300", $p);
$thisMonth = (int)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'success' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");
$lastMonth = (int)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'success' AND created_at >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m-01') AND created_at < DATE_FORMAT(NOW(), '%Y-%m-01')");
$pendingAmt = (int)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'pending'");
$total = (int)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'success'");

page_header('Payments', 'payments', 'Subscription &amp; banner payments — confirm UPI payments to activate plans', '<a class="btn" href="export.php?t=payments">Export CSV</a>');
?>
<div class="grid k4" style="margin-bottom:14px">
  <div class="card kpi"><div class="lb">This month</div><div class="v"><?= inr($thisMonth) ?></div></div>
  <div class="card kpi"><div class="lb">Last month</div><div class="v"><?= inr($lastMonth) ?></div></div>
  <div class="card kpi"><div class="lb">Waiting confirmation</div><div class="v"><?= inr($pendingAmt) ?></div><div class="s w">check UPI app</div></div>
  <div class="card kpi"><div class="lb">All-time revenue</div><div class="v"><?= inr($total) ?></div></div>
</div>
<div class="tabs"><?php foreach (['' => 'All', 'pending' => 'Pending', 'success' => 'Success', 'failed' => 'Failed'] as $k => $l): ?><a class="<?= $st === $k ? 'on' : '' ?>" href="?st=<?= $k ?>"><?= $l ?></a><?php endforeach; ?></div>
<div class="card" style="padding:6px 8px"><div class="tw"><table>
<tr><th>#</th><th>Date</th><th>Shop</th><th>Purpose</th><th>Amount</th><th>Method</th><th>Reference / UTR</th><th>Status</th><th></th></tr>
<?php if (!$rows): ?><tr><td colspan="9" class="empty">No payments</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?><tr><td>EH<?= $r['id'] ?></td><td><?= date('d M Y', strtotime($r['created_at'])) ?></td><td><?= h($r['shop'] ?: '—') ?></td><td><?= h($r['purpose']) ?></td><td><b>₹<?= indian_number($r['amount']) ?></b></td><td><?= h($r['method']) ?></td><td><?= h($r['reference'] ?: '—') ?></td><td><?= status_badge($r['status']) ?></td>
<td style="white-space:nowrap"><?php if ($r['status'] === 'pending'): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn sm g" name="action" value="confirm" data-confirm="Confirm ₹<?= $r['amount'] ?> received?">Confirm</button> <button class="btn sm r" name="action" value="fail">Fail</button></form><?php endif; ?></td></tr><?php endforeach; ?>
</table></div></div>
<?php page_footer();
