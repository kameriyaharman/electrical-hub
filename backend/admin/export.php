<?php
require __DIR__ . '/_bootstrap.php';
require_admin();

$t = get('t');
$sets = [
    'quotes' => "SELECT q.code, q.customer_name, q.customer_mobile, q.items, q.area, q.status, q.created_at, (SELECT COUNT(*) FROM quote_recipients r WHERE r.quote_id = q.id) sent_to, (SELECT COUNT(*) FROM quote_recipients r WHERE r.quote_id = q.id AND r.replied_at IS NOT NULL) replies FROM quote_requests q ORDER BY q.id DESC",
    'leads' => "SELECT l.created_at, l.type, s.name shop, u.name customer, u.mobile FROM leads l JOIN shops s ON s.id = l.shop_id LEFT JOIN users u ON u.id = l.user_id WHERE l.type <> 'view' ORDER BY l.id DESC",
    'payments' => "SELECT p.id, p.created_at, s.name shop, p.purpose, p.amount, p.method, p.reference, p.status FROM payments p LEFT JOIN shops s ON s.id = p.shop_id ORDER BY p.id DESC",
    'users' => "SELECT u.id, u.name, u.mobile, u.role, c.name city, u.status, u.created_at FROM users u LEFT JOIN cities c ON c.id = u.city_id ORDER BY u.id DESC",
    'shops' => "SELECT s.id, s.name, s.owner_name, s.mobile, s.shop_type, c.name city, s.area, s.address, s.lat, s.lng, s.status, s.plan, s.plan_expiry, s.views, s.calls, s.whatsapp_clicks FROM shops s LEFT JOIN cities c ON c.id = s.city_id ORDER BY s.id",
];
if (!isset($sets[$t])) exit('Unknown export');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="electrical-hub-' . $t . '-' . date('Y-m-d') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
$rows = all($sets[$t]);
if ($rows) fputcsv($out, array_keys($rows[0]));
foreach ($rows as $r) fputcsv($out, $r);
