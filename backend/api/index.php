<?php
/**
 * Electrical Hub — REST API for the Android app.
 * Call as:  /api/?r=<route>   (works on any Apache/cPanel host without rewrites)
 * Auth:     header "X-Auth-Token: <token>" (or Authorization: Bearer <token>)
 */
require_once __DIR__ . '/../lib/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') exit;
try { run_maintenance(); } catch (Throwable $e) {}

function out($data, $code = 200) { http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function fail($msg, $code = 400) { out(['ok' => false, 'error' => $msg], $code); }

$JSON = json_decode(file_get_contents('php://input') ?: 'null', true) ?: [];
function in($k, $d = null) { global $JSON; return $_POST[$k] ?? $JSON[$k] ?? $_GET[$k] ?? $d; }

function auth_user($required = true) {
    static $u = false;
    if ($u !== false) return $u;
    $tok = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    if (!$tok && preg_match('/Bearer\s+(\S+)/', $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''), $m)) $tok = $m[1];
    if (!$tok) $tok = $_GET['token'] ?? '';
    $u = $tok ? one("SELECT u.* FROM api_tokens t JOIN users u ON u.id = t.user_id WHERE t.token = ?", [$tok]) : null;
    if ($u && $u['status'] === 'blocked') $u = null;
    if (!$u && $required) fail('Please login again', 401);
    return $u;
}

function shop_card($s, $lat = null, $lng = null) {
    $photo = val("SELECT path FROM shop_photos WHERE shop_id = ? ORDER BY (label = 'Shop front') DESC, id LIMIT 1", [$s['id']]);
    $card = [
        'id' => (int)$s['id'], 'name' => $s['name'], 'area' => $s['area'], 'city' => $s['city_name'] ?? null,
        'address' => $s['address'], 'lat' => $s['lat'] !== null ? (float)$s['lat'] : null, 'lng' => $s['lng'] !== null ? (float)$s['lng'] : null,
        'rating' => (float)$s['rating'], 'review_count' => (int)$s['review_count'], 'plan' => $s['plan'],
        'featured' => in_array($s['plan'], ['featured', 'premium']), 'verified' => $s['status'] === 'verified',
        'shop_type' => $s['shop_type'], 'open' => shop_is_open($s), 'close_time' => fmt_time12($s['close_time']),
        'open_time' => fmt_time12($s['open_time']), 'mobile' => $s['mobile'], 'whatsapp' => $s['whatsapp'] ?: $s['mobile'],
        'photo' => upload_url($photo),
    ];
    if (isset($s['distance'])) $card['distance_km'] = round((float)$s['distance'], 1);
    return $card;
}

function shops_query($opts) {
    $where = ["s.status = 'verified'"]; $p = [];
    $lat = $opts['lat'] ?? null; $lng = $opts['lng'] ?? null;
    $dist = ($lat && $lng) ? distance_sql($lat, $lng) : 'NULL';
    if (!empty($opts['city_id']) && !($lat && $lng && !empty($opts['ignore_city']))) { $where[] = 's.city_id = ?'; $p[] = (int)$opts['city_id']; }
    if (!empty($opts['category_id'])) { $where[] = 's.id IN (SELECT shop_id FROM shop_categories WHERE category_id = ?)'; $p[] = (int)$opts['category_id']; }
    if (!empty($opts['brand_id'])) { $where[] = 's.id IN (SELECT shop_id FROM shop_brands WHERE brand_id = ?)'; $p[] = (int)$opts['brand_id']; }
    if (!empty($opts['product_id'])) { $where[] = 's.id IN (SELECT shop_id FROM shop_products WHERE product_id = ?)'; $p[] = (int)$opts['product_id']; }
    if (!empty($opts['min_rating'])) { $where[] = 's.rating >= ?'; $p[] = (float)$opts['min_rating']; }
    if (!empty($opts['shop_type'])) { $where[] = "(s.shop_type = ? OR s.shop_type = 'Both')"; $p[] = $opts['shop_type']; }
    if (!empty($opts['featured'])) { $where[] = "s.plan IN ('featured','premium')"; }
    if (!empty($opts['q'])) {
        $like = '%' . $opts['q'] . '%';
        $where[] = "(s.name LIKE ? OR s.area LIKE ? OR s.address LIKE ?
            OR s.id IN (SELECT sb.shop_id FROM shop_brands sb JOIN brands b ON b.id = sb.brand_id WHERE b.name LIKE ?)
            OR s.id IN (SELECT sc.shop_id FROM shop_categories sc JOIN categories c ON c.id = sc.category_id WHERE c.name LIKE ?)
            OR s.id IN (SELECT sp.shop_id FROM shop_products sp JOIN products pr ON pr.id = sp.product_id WHERE pr.name LIKE ?))";
        array_push($p, $like, $like, $like, $like, $like, $like);
    }
    $planRank = "0";
    $sort = $opts['sort'] ?? 'nearest';
    if ($sort === 'rating') $order = "s.rating DESC";
    elseif ($dist !== 'NULL') $order = "$planRank, distance";
    else $order = "$planRank, s.rating DESC";
    if ($sort === 'nearest' && $dist !== 'NULL' && empty($opts['plan_first'])) $order = "distance";
    $limit = min(100, (int)($opts['limit'] ?? 50));
    $offset = max(0, (int)($opts['offset'] ?? 0));
    $sql = "SELECT s.*, c.name AS city_name, $dist AS distance FROM shops s LEFT JOIN cities c ON c.id = s.city_id
            WHERE " . implode(' AND ', $where) . " ORDER BY $order LIMIT $limit OFFSET $offset";
    $rows = all($sql, $p);
    $total = (int)val("SELECT COUNT(*) FROM shops s WHERE " . implode(' AND ', $where), $p);
    $cards = array_map('shop_card', $rows);
    if (!empty($opts['open_now'])) $cards = array_values(array_filter($cards, fn($c) => $c['open']));
    return [$cards, $total];
}

function send_otp_sms($mobile, $code) {
    if (setting('otp_mode') !== 'msg91' || !setting('msg91_authkey')) return false;
    $payload = json_encode(['template_id' => setting('msg91_template_id'), 'mobile' => '91' . $mobile, 'otp' => $code]);
    $ch = curl_init('https://control.msg91.com/api/v5/otp?template_id=' . urlencode(setting('msg91_template_id')) . '&mobile=91' . $mobile . '&otp=' . $code);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'authkey: ' . setting('msg91_authkey')]]);
    $res = curl_exec($ch); curl_close($ch);
    return $res !== false;
}

function my_shop($u) { return one("SELECT s.*, c.name AS city_name FROM shops s LEFT JOIN cities c ON c.id = s.city_id WHERE s.owner_user_id = ? ORDER BY s.id LIMIT 1", [$u['id']]); }

function shop_full($s, $u = null) {
    $card = shop_card($s);
    $card += [
        'owner_name' => $s['owner_name'], 'gstin' => $s['gstin'], 'pincode' => $s['pincode'], 'city_id' => (int)$s['city_id'],
        'is_service' => (bool)$s['is_service'], 'status' => $s['status'], 'reject_reason' => $s['reject_reason'],
        'open_time_24' => $s['open_time'], 'close_time_24' => $s['close_time'], 'weekly_off' => $s['weekly_off'],
        'plan_expiry' => $s['plan_expiry'], 'views' => (int)$s['views'],
    ];
    $card['photos'] = array_map(fn($p) => ['id' => (int)$p['id'], 'url' => upload_url($p['path']), 'label' => $p['label'],
        'taken_at' => date('d M H:i', strtotime($p['taken_at'])), 'distance_m' => $p['distance_m'] !== null ? (int)$p['distance_m'] : null],
        all("SELECT * FROM shop_photos WHERE shop_id = ? ORDER BY id DESC", [$s['id']]));
    $card['photos_updated'] = $card['photos'] ? $card['photos'][0]['taken_at'] : null;
    $card['categories'] = all("SELECT c.id, c.name, c.icon FROM shop_categories sc JOIN categories c ON c.id = sc.category_id WHERE sc.shop_id = ? ORDER BY c.sort", [$s['id']]);
    $card['brands'] = all("SELECT b.id, b.name FROM shop_brands sb JOIN brands b ON b.id = sb.brand_id WHERE sb.shop_id = ? ORDER BY b.name", [$s['id']]);
    $card['products'] = all("SELECT p.id, p.name, p.min_price, p.max_price, sp.price FROM shop_products sp JOIN products p ON p.id = sp.product_id WHERE sp.shop_id = ?", [$s['id']]);
    $card['offers'] = all("SELECT id, title, description, valid_till FROM offers WHERE shop_id = ? AND active = 1 AND (valid_till IS NULL OR valid_till >= CURDATE()) ORDER BY id DESC", [$s['id']]);
    $card['reviews'] = all("SELECT customer_name, rating, body, DATE_FORMAT(created_at, '%d %b %Y') AS date FROM reviews WHERE shop_id = ? AND status = 'active' ORDER BY id DESC LIMIT 20", [$s['id']]);
    $card['saved'] = $u ? (bool)val("SELECT 1 FROM saved_shops WHERE user_id = ? AND shop_id = ?", [$u['id'], $s['id']]) : false;
    return $card;
}

$r = trim($_GET['r'] ?? '', '/');

try {
    switch ($r) {

    case '':
    case 'ping':
        $dbok = false; $shops = null;
        try { $shops = (int)val("SELECT COUNT(*) FROM shops"); $dbok = true; } catch (Throwable $e) { error_log('[PING] ' . $e->getMessage()); }
        out(['ok' => true, 'app' => APP_NAME, 'db' => $dbok, 'shops' => $shops, 'time' => date('c')]);

    // ---------- Auth ----------
    case 'auth/send-otp':
        $mobile = preg_replace('/\D/', '', (string)in('mobile'));
        $mobile = substr($mobile, -10);
        if (strlen($mobile) !== 10) fail('Enter a valid 10-digit mobile number');
        // Test mode: fixed OTP (default 1234) until an SMS gateway (MSG91) is switched on in Settings
        $live = setting('otp_mode') === 'msg91' && setting('msg91_authkey');
        $code = $live ? (string)random_int(1000, 9999) : (setting('test_otp', '1234') ?: '1234');
        q("INSERT INTO otps (mobile, code, expires_at, attempts) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0)
           ON DUPLICATE KEY UPDATE code = VALUES(code), expires_at = VALUES(expires_at), attempts = 0", [$mobile, $code]);
        $sent = send_otp_sms($mobile, $code);
        $res = ['ok' => true, 'message' => 'OTP sent to +91 ' . $mobile];
        if (!$sent) $res['demo_otp'] = $code; // demo mode: no SMS gateway configured yet
        out($res);

    case 'auth/verify-otp':
        $mobile = substr(preg_replace('/\D/', '', (string)in('mobile')), -10);
        $code = trim((string)in('code'));
        $row = one("SELECT * FROM otps WHERE mobile = ?", [$mobile]);
        if (!$row || strtotime($row['expires_at']) < time()) fail('OTP expired, please resend');
        if ($row['attempts'] >= 5) fail('Too many attempts, please resend OTP');
        if (!hash_equals($row['code'], $code)) { q("UPDATE otps SET attempts = attempts + 1 WHERE mobile = ?", [$mobile]); fail('Wrong OTP'); }
        q("DELETE FROM otps WHERE mobile = ?", [$mobile]);
        $role = in('role') === 'owner' ? 'owner' : 'customer';
        $u = one("SELECT * FROM users WHERE mobile = ?", [$mobile]);
        if (!$u) {
            q("INSERT INTO users (mobile, role, city_id) VALUES (?, ?, ?)", [$mobile, $role, (int)setting('default_city_id', 1)]);
            $u = one("SELECT * FROM users WHERE id = ?", [last_id()]);
        } elseif ($role === 'owner' && $u['role'] !== 'owner') {
            q("UPDATE users SET role = 'owner' WHERE id = ?", [$u['id']]); $u['role'] = 'owner';
        }
        if ($u['status'] === 'blocked') fail('Your account is blocked. Contact support.', 403);
        // link shop if this mobile already has a listing (e.g. added by admin)
        q("UPDATE shops SET owner_user_id = ?, mobile_verified = 1 WHERE mobile = ? AND owner_user_id IS NULL", [$u['id'], $mobile]);
        q("UPDATE users SET last_login = NOW() WHERE id = ?", [$u['id']]);
        $tok = bin2hex(random_bytes(32));
        q("INSERT INTO api_tokens (token, user_id) VALUES (?, ?)", [$tok, $u['id']]);
        out(['ok' => true, 'token' => $tok, 'user' => ['id' => (int)$u['id'], 'mobile' => $u['mobile'], 'name' => $u['name'], 'role' => $role, 'city_id' => (int)$u['city_id']]]);

    case 'auth/logout':
        $tok = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
        if ($tok) q("DELETE FROM api_tokens WHERE token = ?", [$tok]);
        out(['ok' => true]);

    case 'me':
        $u = auth_user();
        $city = one("SELECT id, name, state FROM cities WHERE id = ?", [$u['city_id']]);
        out(['ok' => true, 'user' => ['id' => (int)$u['id'], 'mobile' => $u['mobile'], 'name' => $u['name'], 'role' => $u['role'], 'city' => $city],
            'counts' => [
                'saved' => (int)val("SELECT COUNT(*) FROM saved_shops WHERE user_id = ?", [$u['id']]),
                'quotes' => (int)val("SELECT COUNT(*) FROM quote_requests WHERE user_id = ?", [$u['id']]),
                'reviews' => (int)val("SELECT COUNT(*) FROM reviews WHERE user_id = ?", [$u['id']]),
                'notifications' => (int)val("SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0", [$u['id']]),
            ],
            'has_shop' => (bool)my_shop($u)]);

    case 'me/update':
        $u = auth_user();
        $name = trim((string)in('name', $u['name']));
        $city = (int)in('city_id', $u['city_id']);
        q("UPDATE users SET name = ?, city_id = ? WHERE id = ?", [$name, $city, $u['id']]);
        out(['ok' => true]);

    // ---------- Catalogue ----------
    case 'meta':
        out(['ok' => true,
            'cities' => all("SELECT id, name, state, lat, lng FROM cities WHERE active = 1 ORDER BY name"),
            'categories' => all("SELECT id, name, icon, image FROM categories WHERE active = 1 AND parent_id IS NULL ORDER BY sort, name"),
            'brands' => all("SELECT id, name FROM brands WHERE active = 1 ORDER BY name"),
            'plans' => array_map(fn($p) => $p + ['feature_list' => array_values(array_filter(explode("\n", (string)$p['features'])))],
                all("SELECT code, name, tagline, price, duration_days, photos_limit, search_position, home_banner, quote_priority, features FROM plans WHERE active = 1 ORDER BY sort")),
            'support' => ['phone' => setting('support_phone'), 'whatsapp' => setting('support_whatsapp'), 'email' => setting('support_email'), 'upi' => setting('upi_id')],
        ]);

    case 'home':
        $city = (int)in('city_id', setting('default_city_id', 1));
        $lat = in('lat'); $lng = in('lng');
        $banners = all("SELECT b.id, b.title, b.subtitle, b.image, b.shop_id FROM banners b WHERE b.status = 'active'
            AND (b.city_id IS NULL OR b.city_id = ?) AND (b.end_date IS NULL OR b.end_date >= CURDATE()) ORDER BY b.sort, b.id", [$city]);
        foreach ($banners as &$b) $b['image'] = upload_url($b['image']);
        $featured = [];
        [$nearby] = shops_query(['city_id' => $city, 'lat' => $lat, 'lng' => $lng, 'limit' => 20]);
        out(['ok' => true, 'banners' => $banners,
            'categories' => all("SELECT id, name, icon, image FROM categories WHERE active = 1 AND parent_id IS NULL ORDER BY sort"),
            'featured' => $featured, 'nearby' => $nearby]);

    case 'shops':
        $opts = ['city_id' => in('city_id'), 'lat' => in('lat'), 'lng' => in('lng'), 'q' => trim((string)in('q', '')),
            'category_id' => in('category_id'), 'brand_id' => in('brand_id'), 'product_id' => in('product_id'),
            'min_rating' => in('min_rating'), 'open_now' => in('open_now'), 'shop_type' => in('shop_type'),
            'sort' => in('sort', 'nearest'), 'limit' => in('limit', 50), 'offset' => in('offset', 0)];
        if ($opts['q'] !== '' || $opts['category_id']) q("INSERT INTO searches (term, category_id, city_id) VALUES (?,?,?)", [$opts['q'] ?: null, $opts['category_id'] ?: null, $opts['city_id'] ?: null]);
        [$cards, $total] = shops_query($opts);
        out(['ok' => true, 'total' => $total, 'shops' => $cards]);

    case 'shop':
        $id = (int)in('id');
        $s = one("SELECT s.*, c.name AS city_name FROM shops s LEFT JOIN cities c ON c.id = s.city_id WHERE s.id = ? AND s.status = 'verified'", [$id]);
        if (!$s) fail('Shop not found', 404);
        $u = auth_user(false);
        q("UPDATE shops SET views = views + 1 WHERE id = ?", [$id]);
        q("INSERT INTO leads (shop_id, user_id, type) VALUES (?, ?, 'view')", [$id, $u['id'] ?? null]);
        out(['ok' => true, 'shop' => shop_full($s, $u)]);

    case 'lead':
        $id = (int)in('shop_id'); $type = in('type');
        if (!in_array($type, ['call', 'whatsapp', 'directions', 'share'], true)) fail('Bad type');
        $u = auth_user(false);
        q("INSERT INTO leads (shop_id, user_id, type) VALUES (?, ?, ?)", [$id, $u['id'] ?? null, $type]);
        if ($type === 'call') q("UPDATE shops SET calls = calls + 1 WHERE id = ?", [$id]);
        if ($type === 'whatsapp') q("UPDATE shops SET whatsapp_clicks = whatsapp_clicks + 1 WHERE id = ?", [$id]);
        out(['ok' => true]);

    case 'products':
        $where = ['p.active = 1']; $p = [];
        if (in('category_id')) { $where[] = 'p.category_id = ?'; $p[] = (int)in('category_id'); }
        if (in('q')) { $where[] = '(p.name LIKE ? OR b.name LIKE ?)'; $p[] = '%' . in('q') . '%'; $p[] = '%' . in('q') . '%'; }
        $city = (int)in('city_id', 0);
        $cityJoin = $city ? "AND sp.shop_id IN (SELECT id FROM shops WHERE status = 'verified' AND city_id = $city)" : "AND sp.shop_id IN (SELECT id FROM shops WHERE status = 'verified')";
        $rows = all("SELECT p.id, p.name, p.unit, p.min_price, p.max_price, p.image, c.name AS category, b.name AS brand,
            (SELECT COUNT(*) FROM shop_products sp WHERE sp.product_id = p.id $cityJoin) AS shop_count
            FROM products p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN brands b ON b.id = p.brand_id
            WHERE " . implode(' AND ', $where) . " ORDER BY p.name", $p);
        foreach ($rows as &$row) $row['image'] = upload_url($row['image']);
        out(['ok' => true, 'products' => $rows]);

    case 'offers':
        $city = (int)in('city_id', 0);
        $rows = all("SELECT o.id, o.title, o.description, o.valid_till, s.id AS shop_id, s.name AS shop_name, s.area
            FROM offers o JOIN shops s ON s.id = o.shop_id WHERE o.active = 1 AND s.status = 'verified'
            AND (o.valid_till IS NULL OR o.valid_till >= CURDATE()) " . ($city ? "AND s.city_id = $city" : '') . " ORDER BY o.id DESC");
        out(['ok' => true, 'offers' => $rows]);

    // ---------- Quotes ----------
    case 'quotes/create':
        $u = auth_user();
        $items = trim((string)in('items', ''));
        $file = null;
        if (!empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) $file = save_upload($_FILES['file'], 'quotes');
        if ($items === '' && !$file) fail('Write what you need or upload your list');
        $lat = in('lat') ?: null; $lng = in('lng') ?: null;
        $city = (int)in('city_id', $u['city_id']);
        $count = max(1, min(20, (int)in('send_count', 5)));
        $verified = in('verified_only', '1') ? 1 : 0;
        q("INSERT INTO quote_requests (user_id, customer_name, customer_mobile, items, attachment, area, city_id, lat, lng, send_count, verified_only) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [$u['id'], $u['name'] ?: 'Customer', $u['mobile'], $items ?: '(see attached list)', $file, in('area'), $city, $lat, $lng, $count, $verified]);
        $qid = last_id();
        q("UPDATE quote_requests SET code = ? WHERE id = ?", ['QR-' . (2000 + $qid), $qid]);
        $shopIds = array_map('intval', (array)in('shop_ids', []));
        if (!$shopIds) {
            $order = ($lat && $lng) ? distance_sql($lat, $lng) : "s.rating DESC";
            $prio = "CASE s.plan WHEN 'premium' THEN 0 ELSE 1 END";
            $shopIds = array_column(all("SELECT s.id FROM shops s WHERE s.status = 'verified' AND s.city_id = ? ORDER BY $order LIMIT $count", [$city]), 'id');
        }
        if (in('include_shop') && !in_array((int)in('include_shop'), $shopIds)) $shopIds[] = (int)in('include_shop');
        foreach ($shopIds as $sid) q("INSERT IGNORE INTO quote_recipients (quote_id, shop_id) VALUES (?, ?)", [$qid, $sid]);
        out(['ok' => true, 'quote_id' => $qid, 'sent_to' => count($shopIds)]);

    case 'quotes':
        $u = auth_user();
        $rows = all("SELECT q.id, q.code, q.items, q.area, q.status, DATE_FORMAT(q.created_at, '%d %b %Y') AS date,
            (SELECT COUNT(*) FROM quote_recipients r WHERE r.quote_id = q.id) AS sent_to,
            (SELECT COUNT(*) FROM quote_recipients r WHERE r.quote_id = q.id AND r.replied_at IS NOT NULL) AS replies
            FROM quote_requests q WHERE q.user_id = ? ORDER BY q.id DESC", [$u['id']]);
        out(['ok' => true, 'quotes' => $rows]);

    case 'quote':
        $u = auth_user();
        $q = one("SELECT * FROM quote_requests WHERE id = ? AND user_id = ?", [(int)in('id'), $u['id']]);
        if (!$q) fail('Not found', 404);
        $q['attachment'] = upload_url($q['attachment']);
        $q['replies'] = all("SELECT s.id AS shop_id, s.name, s.area, s.mobile, COALESCE(s.whatsapp, s.mobile) AS whatsapp, r.reply_amount, r.reply_note, r.delivery, r.replied_at
            FROM quote_recipients r JOIN shops s ON s.id = r.shop_id WHERE r.quote_id = ? ORDER BY r.replied_at IS NULL, r.reply_amount", [$q['id']]);
        out(['ok' => true, 'quote' => $q]);

    // ---------- Reviews / saved ----------
    case 'reviews/create':
        $u = auth_user();
        $sid = (int)in('shop_id'); $rating = max(1, min(5, (int)in('rating')));
        if (!val("SELECT 1 FROM shops WHERE id = ?", [$sid])) fail('Shop not found');
        $existing = val("SELECT id FROM reviews WHERE shop_id = ? AND user_id = ?", [$sid, $u['id']]);
        if ($existing) q("UPDATE reviews SET rating = ?, body = ?, created_at = NOW() WHERE id = ?", [$rating, trim((string)in('body')), $existing]);
        else q("INSERT INTO reviews (shop_id, user_id, customer_name, rating, body) VALUES (?,?,?,?,?)", [$sid, $u['id'], $u['name'] ?: 'Customer', $rating, trim((string)in('body'))]);
        q("UPDATE shops SET rating = (SELECT ROUND(AVG(rating), 1) FROM reviews WHERE shop_id = ? AND status = 'active'),
            review_count = GREATEST(review_count, (SELECT COUNT(*) FROM reviews WHERE shop_id = ? AND status = 'active')) WHERE id = ?", [$sid, $sid, $sid]);
        out(['ok' => true]);

    case 'my-reviews':
        $u = auth_user();
        out(['ok' => true, 'reviews' => all("SELECT r.*, s.name AS shop_name FROM reviews r JOIN shops s ON s.id = r.shop_id WHERE r.user_id = ? ORDER BY r.id DESC", [$u['id']])]);

    case 'saved/toggle':
        $u = auth_user(); $sid = (int)in('shop_id');
        if (val("SELECT 1 FROM saved_shops WHERE user_id = ? AND shop_id = ?", [$u['id'], $sid])) {
            q("DELETE FROM saved_shops WHERE user_id = ? AND shop_id = ?", [$u['id'], $sid]); out(['ok' => true, 'saved' => false]);
        }
        q("INSERT INTO saved_shops (user_id, shop_id) VALUES (?, ?)", [$u['id'], $sid]);
        out(['ok' => true, 'saved' => true]);

    case 'saved':
        $u = auth_user();
        $rows = all("SELECT s.*, c.name AS city_name FROM saved_shops ss JOIN shops s ON s.id = ss.shop_id LEFT JOIN cities c ON c.id = s.city_id WHERE ss.user_id = ? ORDER BY ss.created_at DESC", [$u['id']]);
        out(['ok' => true, 'shops' => array_map('shop_card', $rows)]);

    case 'notifications':
        $u = auth_user();
        $rows = all("SELECT id, title, body, is_read, DATE_FORMAT(created_at, '%d %b, %h:%i %p') AS date FROM notifications WHERE user_id = ? OR user_id IS NULL ORDER BY id DESC LIMIT 50", [$u['id']]);
        q("UPDATE notifications SET is_read = 1 WHERE user_id = ?", [$u['id']]);
        out(['ok' => true, 'notifications' => $rows]);

    // ---------- Shop owner ----------
    case 'owner/shop':
        $u = auth_user();
        $s = my_shop($u);
        out(['ok' => true, 'shop' => $s ? shop_full($s, $u) : null]);

    case 'owner/shop/save':
        $u = auth_user();
        $s = my_shop($u);
        $name = trim((string)in('name'));
        $mobile = substr(preg_replace('/\D/', '', (string)in('mobile', $u['mobile'])), -10);
        if ($name === '' || strlen($mobile) !== 10) fail('Shop name and a 10-digit mobile are required');
        $f = [
            'name' => $name, 'owner_name' => trim((string)in('owner_name')), 'mobile' => $mobile,
            'whatsapp' => substr(preg_replace('/\D/', '', (string)in('whatsapp', $mobile)), -10) ?: $mobile,
            'gstin' => strtoupper(trim((string)in('gstin'))) ?: null,
            'shop_type' => in_array(in('shop_type'), ['Retail', 'Wholesale', 'Both'], true) ? in('shop_type') : 'Retail',
            'is_service' => in('is_service') ? 1 : 0, 'address' => trim((string)in('address')), 'city_id' => (int)in('city_id', $u['city_id']),
            'area' => trim((string)in('area')), 'pincode' => trim((string)in('pincode')),
            'lat' => in('lat') !== null && in('lat') !== '' ? (float)in('lat') : null, 'lng' => in('lng') !== null && in('lng') !== '' ? (float)in('lng') : null,
            'open_time' => preg_match('/^\d\d:\d\d$/', (string)in('open_time')) ? in('open_time') : ($s['open_time'] ?? '09:00'),
            'close_time' => preg_match('/^\d\d:\d\d$/', (string)in('close_time')) ? in('close_time') : ($s['close_time'] ?? '21:00'),
            'weekly_off' => in('weekly_off') ?: null,
            'mobile_verified' => $mobile === $u['mobile'] ? 1 : 0,
        ];
        if ($s) {
            $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($f)));
            q("UPDATE shops SET $sets WHERE id = ?", array_merge(array_values($f), [$s['id']]));
            $sid = (int)$s['id'];
        } else {
            $f['owner_user_id'] = $u['id']; $f['status'] = 'draft';
            q("INSERT INTO shops (" . implode(', ', array_keys($f)) . ") VALUES (" . rtrim(str_repeat('?,', count($f)), ',') . ")", array_values($f));
            $sid = last_id();
            if (!$u['name'] && $f['owner_name']) q("UPDATE users SET name = ? WHERE id = ?", [$f['owner_name'], $u['id']]);
            q("UPDATE users SET role = 'owner' WHERE id = ?", [$u['id']]);
        }
        $cats = in('categories'); if (is_string($cats)) $cats = array_filter(explode(',', $cats));
        if (is_array($cats)) { q("DELETE FROM shop_categories WHERE shop_id = ?", [$sid]); foreach ($cats as $c) q("INSERT IGNORE INTO shop_categories VALUES (?, ?)", [$sid, (int)$c]); }
        $brands = in('brands'); if (is_string($brands)) $brands = array_filter(explode(',', $brands));
        if (is_array($brands)) { q("DELETE FROM shop_brands WHERE shop_id = ?", [$sid]); foreach ($brands as $b) q("INSERT IGNORE INTO shop_brands VALUES (?, ?)", [$sid, (int)$b]); }
        $prods = in('products'); if (is_string($prods)) $prods = array_filter(explode(',', $prods));
        if (is_array($prods)) { q("DELETE FROM shop_products WHERE shop_id = ?", [$sid]); foreach ($prods as $pp) q("INSERT IGNORE INTO shop_products (shop_id, product_id) VALUES (?, ?)", [$sid, (int)$pp]); }
        out(['ok' => true, 'shop_id' => $sid]);

    case 'owner/photo':
        $u = auth_user();
        $s = my_shop($u);
        if (!$s) fail('Create your shop first');
        if (in('source', 'camera') !== 'camera') fail('Photos must be taken live with the camera');
        $limit = MAX_SHOP_PHOTOS;
        $have = (int)val("SELECT COUNT(*) FROM shop_photos WHERE shop_id = ?", [$s['id']]);
        if ($have >= $limit) fail("Maximum $limit photos per shop. Delete an old photo to add a new one.");
        $path = save_upload($_FILES['photo'] ?? null, 'shops/' . $s['id'], ['jpg', 'jpeg', 'png', 'webp']);
        $lat = in('lat') !== null && in('lat') !== '' ? (float)in('lat') : null; $lng = in('lng') !== null && in('lng') !== '' ? (float)in('lng') : null;
        $dist = ($lat && $lng && $s['lat'] && $s['lng']) ? haversine_m($lat, $lng, (float)$s['lat'], (float)$s['lng']) : null;
        q("INSERT INTO shop_photos (shop_id, path, label, lat, lng, distance_m, source) VALUES (?,?,?,?,?,?, 'camera')", [$s['id'], $path, in('label'), $lat, $lng, $dist]);
        out(['ok' => true, 'id' => last_id(), 'url' => upload_url($path), 'distance_m' => $dist]);

    case 'owner/photo/delete':
        $u = auth_user(); $s = my_shop($u);
        $ph = one("SELECT * FROM shop_photos WHERE id = ? AND shop_id = ?", [(int)in('id'), $s['id'] ?? 0]);
        if (!$ph) fail('Not found', 404);
        if (strpos($ph['path'], 'demo/') !== 0) @unlink(rtrim(UPLOAD_DIR, '/') . '/' . $ph['path']);
        q("DELETE FROM shop_photos WHERE id = ?", [$ph['id']]);
        out(['ok' => true]);

    case 'owner/submit':
        $u = auth_user(); $s = my_shop($u);
        if (!$s) fail('Create your shop first');
        $n = (int)val("SELECT COUNT(*) FROM shop_photos WHERE shop_id = ?", [$s['id']]);
        if ($n < 1) fail('Add at least one live photo of your shop');
        if (in_array($s['status'], ['draft', 'rejected'], true)) q("UPDATE shops SET status = 'pending', reject_reason = NULL WHERE id = ?", [$s['id']]);
        out(['ok' => true, 'status' => $s['status'] === 'verified' ? 'verified' : 'pending']);

    case 'owner/dashboard':
        $u = auth_user(); $s = my_shop($u);
        if (!$s) out(['ok' => true, 'shop' => null]);
        $sid = $s['id'];
        $week = (int)val("SELECT COUNT(*) FROM leads WHERE shop_id = ? AND type = 'view' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", [$sid]);
        $prev = (int)val("SELECT COUNT(*) FROM leads WHERE shop_id = ? AND type = 'view' AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)", [$sid]);
        $month = "AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')";
        $newQuotes = all("SELECT q.id, q.code, q.items, q.area, q.customer_name, q.customer_mobile, DATE_FORMAT(q.created_at, '%d %b, %h:%i %p') AS date, r.reply_amount, r.replied_at
            FROM quote_recipients r JOIN quote_requests q ON q.id = r.quote_id WHERE r.shop_id = ? ORDER BY q.id DESC LIMIT 30", [$sid]);
        out(['ok' => true, 'shop' => shop_card(one("SELECT s.*, c.name AS city_name FROM shops s LEFT JOIN cities c ON c.id = s.city_id WHERE s.id = ?", [$sid])) + ['status' => $s['status'], 'reject_reason' => $s['reject_reason'], 'plan_expiry' => $s['plan_expiry']],
            'stats' => [
                'views' => (int)$s['views'], 'views_change' => $prev ? round(($week - $prev) * 100 / $prev) : ($week ? 100 : 0),
                'calls' => (int)val("SELECT COUNT(*) FROM leads WHERE shop_id = ? AND type = 'call' $month", [$sid]),
                'whatsapp' => (int)val("SELECT COUNT(*) FROM leads WHERE shop_id = ? AND type = 'whatsapp' $month", [$sid]),
                'quotes' => count($newQuotes), 'new_quotes' => count(array_filter($newQuotes, fn($x) => !$x['replied_at'])),
                'reviews' => (int)$s['review_count'], 'photos' => (int)val("SELECT COUNT(*) FROM shop_photos WHERE shop_id = ?", [$sid]),
            ],
            'quotes' => $newQuotes]);

    case 'owner/quote-reply':
        $u = auth_user(); $s = my_shop($u);
        $qid = (int)in('quote_id');
        if (!$s || !val("SELECT 1 FROM quote_recipients WHERE quote_id = ? AND shop_id = ?", [$qid, $s['id']])) fail('Not found', 404);
        q("UPDATE quote_recipients SET reply_amount = ?, reply_note = ?, delivery = ?, replied_at = NOW() WHERE quote_id = ? AND shop_id = ?",
            [(int)in('amount'), trim((string)in('note')), trim((string)in('delivery')), $qid, $s['id']]);
        $q = one("SELECT user_id, code FROM quote_requests WHERE id = ?", [$qid]);
        if ($q['user_id']) q("INSERT INTO notifications (user_id, title, body) VALUES (?,?,?)", [$q['user_id'], "New price for {$q['code']}", "{$s['name']} quoted ₹" . indian_number((int)in('amount')) . ' · ' . trim((string)in('delivery'))]);
        out(['ok' => true]);

    case 'owner/offers':
        $u = auth_user(); $s = my_shop($u);
        out(['ok' => true, 'offers' => $s ? all("SELECT * FROM offers WHERE shop_id = ? ORDER BY id DESC", [$s['id']]) : []]);

    case 'owner/offer/save':
        $u = auth_user(); $s = my_shop($u);
        if (!$s) fail('Create your shop first');
        $title = trim((string)in('title'));
        if ($title === '') fail('Offer title is required');
        q("INSERT INTO offers (shop_id, title, description, valid_till) VALUES (?,?,?,?)", [$s['id'], $title, trim((string)in('description')), in('valid_till') ?: null]);
        out(['ok' => true]);

    case 'owner/offer/delete':
        $u = auth_user(); $s = my_shop($u);
        q("DELETE FROM offers WHERE id = ? AND shop_id = ?", [(int)in('id'), $s['id'] ?? 0]);
        out(['ok' => true]);

    case 'owner/reviews':
        $u = auth_user(); $s = my_shop($u);
        out(['ok' => true, 'reviews' => $s ? all("SELECT customer_name, rating, body, DATE_FORMAT(created_at, '%d %b %Y') AS date FROM reviews WHERE shop_id = ? AND status = 'active' ORDER BY id DESC", [$s['id']]) : []]);

    case 'owner/upgrade':
        fail('Paid plans are not available', 410);
        $u = auth_user(); $s = my_shop($u);
        if (!$s) fail('Create your shop first');
        $plan = one("SELECT * FROM plans WHERE code = ? AND active = 1", [in('plan')]);
        if (!$plan || $plan['price'] <= 0) fail('Choose a paid plan');
        q("INSERT INTO payments (shop_id, purpose, amount, method, reference, status) VALUES (?,?,?, 'UPI', ?, 'pending')", [$s['id'], $plan['name'] . ' plan', $plan['price'], trim((string)in('reference')) ?: null]);
        $pid = last_id();
        q("INSERT INTO subscriptions (shop_id, plan_code, area, amount, status, payment_id) VALUES (?,?,?,?, 'pending', ?)", [$s['id'], $plan['code'], $s['area'], $plan['price'], $pid]);
        $upi = 'upi://pay?pa=' . rawurlencode(setting('upi_id')) . '&pn=' . rawurlencode(setting('app_name', APP_NAME)) . '&am=' . $plan['price'] . '&cu=INR&tn=' . rawurlencode('EH' . $pid . ' ' . $plan['name']);
        out(['ok' => true, 'payment_id' => $pid, 'amount' => (int)$plan['price'], 'upi_link' => $upi,
            'message' => 'Pay via UPI and share the transaction ID. Your plan is activated after admin confirms payment.']);

    case 'owner/payment-ref':
        $u = auth_user(); $s = my_shop($u);
        q("UPDATE payments SET reference = ? WHERE id = ? AND shop_id = ?", [trim((string)in('reference')), (int)in('payment_id'), $s['id'] ?? 0]);
        out(['ok' => true]);

    default:
        fail('Unknown route: ' . $r, 404);
    }
} catch (Throwable $e) {
    error_log('[API] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    fail($e instanceof PDOException ? 'Server error, please try again' : $e->getMessage(), 500);
}
