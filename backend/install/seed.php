<?php
/** Initial data — runs once, automatically, when the database is empty. */
function seed_database($pdo) {
    $ins = function ($sql, $p = []) use ($pdo) { $st = $pdo->prepare($sql); $st->execute($p); return (int)$pdo->lastInsertId(); };

    // Admin (change password from Settings after first login)
    $ins("INSERT INTO admins (name, email, password_hash) VALUES (?,?,?)",
        ['Admin', 'admin@electricalhub.in', password_hash('Admin@123', PASSWORD_DEFAULT)]);

    $settings = [
        'app_name' => 'Electrical Hub',
        'support_phone' => '9220892667',
        'support_whatsapp' => '9220892667',
        'support_email' => 'support@electricalhub.in',
        'upi_id' => 'electricalhub@upi',
        'otp_mode' => 'demo',            // demo = OTP shown in app; msg91 = real SMS
        'msg91_authkey' => '',
        'msg91_template_id' => '',
        'gps_match_radius_m' => '200',
        'expiry_reminder_days' => '5',
        'default_city_id' => '1',
        'home_banner_title' => 'All Electrical Products in One Place',
    ];
    foreach ($settings as $k => $v) $ins("INSERT INTO settings (k, v) VALUES (?,?)", [$k, $v]);

    $cities = [
        ['Chennai', 'Tamil Nadu', 13.0827, 80.2707],
        ['Coimbatore', 'Tamil Nadu', 11.0168, 76.9558],
        ['Madurai', 'Tamil Nadu', 9.9252, 78.1198],
        ['Trichy', 'Tamil Nadu', 10.7905, 78.7047],
        ['Salem', 'Tamil Nadu', 11.6643, 78.1460],
        ['New Delhi', 'Delhi', 28.6139, 77.2090],
    ];
    foreach ($cities as $c) $ins("INSERT INTO cities (name, state, lat, lng) VALUES (?,?,?,?)", $c);
    $areas = [
        [1, 'GN Street', '600017'], [1, 'Parrys', '600001'], [1, 'T. Nagar', '600017'], [1, 'Anna Nagar', '600040'],
        [1, 'Velachery', '600042'], [1, 'Tambaram', '600045'], [2, 'Gandhipuram', '641012'], [2, 'RS Puram', '641002'],
        [3, 'Simmakkal', '625001'], [4, 'Thillai Nagar', '620018'], [5, 'Fairlands', '636016'], [6, 'Dwarka', '110075'],
        [6, 'Bhagirath Palace', '110006'],
    ];
    foreach ($areas as $a) $ins("INSERT INTO areas (city_id, name, pincode) VALUES (?,?,?)", $a);

    $cats = [
        ['Wires & Cables', '🔌'], ['Switches & Sockets', '🎚️'], ['Lighting (LED)', '💡'], ['MCB & DB', '⚡'],
        ['Fans', '🌀'], ['Conduits & Fittings', '🧰'], ['Motors & Pumps', '⚙️'], ['Electrical Tools', '🛠️'], ['Solar', '☀️'],
    ];
    foreach ($cats as $i => $c) $ins("INSERT INTO categories (name, icon, sort) VALUES (?,?,?)", [$c[0], $c[1], $i]);

    $brands = [
        ['Havells', 'Switches, MCB, fans, wires'], ['Polycab', 'Wires & cables'], ['Philips', 'Lighting'],
        ['Crompton', 'Fans, pumps'], ['Anchor', 'Switches & sockets'], ['Finolex', 'Wires & cables'],
        ['Legrand', 'Switches, MCB & DB'], ['Schneider', 'MCB & DB'], ['Syska', 'Lighting'], ['Orient', 'Fans'],
    ];
    foreach ($brands as $b) $ins("INSERT INTO brands (name, categories) VALUES (?,?)", $b);

    $plans = [
        ['free', 'Free listing', 'Basic presence', 0, 5, 'Normal', 0, 'Normal', "Shop profile + 5 photos\nCall & WhatsApp buttons", 0],
        ['featured', 'Featured', 'Top of your area', 499, 20, 'Top 3 in area', 0, 'Normal', "Featured badge\nTop 3 in search\n20 photos + offers", 1],
        ['premium', 'Premium', 'Max visibility', 999, 50, 'Top of city', 1, 'First', "Home page banner slot\nQuote requests first\nVerified + priority support", 2],
    ];
    foreach ($plans as $p) $ins("INSERT INTO plans (code, name, tagline, price, photos_limit, search_position, home_banner, quote_priority, features, sort) VALUES (?,?,?,?,?,?,?,?,?,?)", $p);

    $products = [
        ['Polycab FR Wire 1.5 sq mm (90 m)', 1, 2, 'coil', 1650, 1820],
        ['Havells Lifeline 2.5 sq mm (90 m)', 1, 1, 'coil', 2650, 2900],
        ['Finolex FR 1.0 sq mm (90 m)', 1, 6, 'coil', 1150, 1290],
        ['Havells MCB 32A Double Pole', 4, 1, 'pc', 540, 610],
        ['Legrand 8-way DB SPN', 4, 7, 'pc', 1450, 1690],
        ['Philips 9W LED Bulb (pack of 4)', 3, 3, 'pack', 299, 360],
        ['Syska 12W LED Panel Round', 3, 9, 'pc', 320, 410],
        ['Crompton 1200 mm Ceiling Fan', 5, 4, 'pc', 1890, 2150],
        ['Orient Wall Fan 400 mm', 5, 10, 'pc', 1990, 2300],
        ['Anchor Roma 6A Switch', 2, 5, 'pc', 28, 36],
        ['Havells 1 HP Monoblock Pump', 7, 1, 'pc', 5400, 6100],
        ['Solar Panel 335W Mono PERC', 9, null, 'pc', 8200, 9400],
    ];
    foreach ($products as $p) $ins("INSERT INTO products (name, category_id, brand_id, unit, min_price, max_price) VALUES (?,?,?,?,?,?)", $p);

    // Customers
    $customers = [['9876543210', 'Ravi Kumar'], ['9840011223', 'Prakash'], ['9840022334', 'Meena'], ['9840033445', 'Ramesh'],
        ['9840044556', 'Anitha'], ['9840055667', 'Siva Constructions']];
    foreach ($customers as $c) $ins("INSERT INTO users (mobile, name, city_id) VALUES (?,?,1)", $c);

    // Shops: name, owner, mobile, type, city, area, lat, lng, status, plan, rating, cats, brands
    $shops = [
        ['KK Electrical', 'Karthik K.', '9000000001', 'Both', 1, 'GN Street', 13.0418, 80.2341, 'verified', 'premium', 4.6, [1, 2, 3, 4, 5], [1, 2, 3, 4, 5, 6, 7], 'No. 222, GN Street, Chennai – 600017'],
        ['Sri Murugan Electricals', 'Murugan S.', '9000000002', 'Wholesale', 1, 'Parrys', 13.0900, 80.2880, 'verified', 'featured', 4.4, [1, 2, 4, 6], [1, 2, 5, 6], '14, Armenian St, Parrys, Chennai – 600001'],
        ['Lakshmi Electrical Traders', 'Lakshmi N.', '9000000003', 'Retail', 1, 'T. Nagar', 13.0405, 80.2337, 'verified', 'free', 4.3, [2, 3, 5], [3, 4, 5], '33, Usman Road, T. Nagar, Chennai – 600017'],
        ['City Lights & Switches', 'Rajesh P.', '9000000004', 'Retail', 1, 'Anna Nagar', 13.0850, 80.2101, 'verified', 'free', 4.1, [2, 3], [3, 9], '2nd Ave, Anna Nagar, Chennai – 600040'],
        ['Velachery Electricals', 'Suresh', '9000000005', 'Retail', 1, 'Velachery', 12.9815, 80.2180, 'pending', 'free', 0, [1, 3, 5], [2, 4], '100 Feet Rd, Velachery, Chennai – 600042'],
        ['Tambaram Power Point', 'Ganesh R.', '9000000006', 'Both', 1, 'Tambaram', 12.9249, 80.1000, 'verified', 'featured', 4.2, [1, 4, 7], [1, 8], 'GST Road, Tambaram, Chennai – 600045'],
        ['Kovai Power House', 'Senthil V.', '9000000007', 'Wholesale', 2, 'Gandhipuram', 11.0168, 76.9658, 'verified', 'featured', 4.5, [1, 4, 7, 9], [1, 2, 8], 'Cross Cut Rd, Gandhipuram, Coimbatore'],
        ['RS Puram Electricals', 'Kumar M.', '9000000008', 'Retail', 2, 'RS Puram', 11.0089, 76.9500, 'verified', 'free', 4.0, [2, 3, 5], [5, 10], 'DB Road, RS Puram, Coimbatore'],
        ['Meenakshi Electricals', 'Pandi', '9000000009', 'Both', 3, 'Simmakkal', 9.9252, 78.1198, 'verified', 'free', 4.2, [1, 2, 3], [2, 5], 'Simmakkal, Madurai'],
        ['New Star Electricals', 'Anand', '9000000010', 'Retail', 3, 'Simmakkal', 9.9310, 78.1220, 'rejected', 'free', 0, [3], [], 'North Masi St, Madurai'],
        ['Rock City Electricals', 'Vijay', '9000000011', 'Retail', 4, 'Thillai Nagar', 10.8270, 78.6860, 'verified', 'free', 3.9, [1, 3, 5], [4, 9], 'Thillai Nagar, Trichy'],
        ['Salem Wires & Lights', 'Mani', '9000000012', 'Wholesale', 5, 'Fairlands', 11.6750, 78.1400, 'verified', 'free', 4.1, [1, 3], [2, 6], 'Fairlands, Salem'],
        ['Dwarka Electrical Hub', 'Akash P.', '9000000013', 'Both', 6, 'Dwarka', 28.5921, 77.0460, 'verified', 'featured', 4.5, [1, 2, 3, 4, 5], [1, 2, 3, 7], 'Sector 7, Dwarka, New Delhi – 110075'],
    ];
    foreach ($shops as $i => $s) {
        $expiry = $s[9] === 'free' ? null : date('Y-m-d', strtotime('+' . (10 + $i) . ' days'));
        $views = $s[8] === 'verified' ? rand(150, 1300) : rand(0, 30);
        $sid = $ins("INSERT INTO shops (name, owner_name, mobile, whatsapp, shop_type, city_id, area, lat, lng, status, plan, plan_expiry, rating, address, mobile_verified, views, calls, whatsapp_clicks, reject_reason, open_time, close_time)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,?,?,?,?)",
            [$s[0], $s[1], $s[2], $s[2], $s[3], $s[4], $s[5], $s[6], $s[7], $s[8], $s[9], $expiry, $s[10], $s[13], $views, (int)($views * 0.07), (int)($views * 0.11),
                $s[8] === 'rejected' ? 'Photos missing' : null, '09:00', $i == 3 ? '10:00' : '21:00']);
        foreach ($s[11] as $c) $ins("INSERT INTO shop_categories VALUES (?,?)", [$sid, $c]);
        foreach ($s[12] as $b) $ins("INSERT INTO shop_brands VALUES (?,?)", [$sid, $b]);
        foreach ([1, 4, 6, 8] as $pid) if (rand(0, 1)) $ins("INSERT INTO shop_products VALUES (?,?,NULL)", [$sid, $pid]);
        if ($s[9] !== 'free') {
            $amt = $s[9] === 'premium' ? 999 : 499;
            $pay = $ins("INSERT INTO payments (shop_id, purpose, amount, method, reference, status, created_at) VALUES (?,?,?,?,?, 'success', ?)",
                [$sid, ucfirst($s[9]) . ' plan', $amt, 'UPI', 'UPI' . rand(100000000, 999999999), date('Y-m-d H:i:s', strtotime('-' . (20 - $i) . ' days'))]);
            $ins("INSERT INTO subscriptions (shop_id, plan_code, area, start_date, expiry_date, amount, status, payment_id) VALUES (?,?,?,?,?,?, 'active', ?)",
                [$sid, $s[9], $s[5], date('Y-m-d', strtotime($expiry . ' -30 days')), $expiry, $amt, $pay]);
        }
        // leads over the last 30 days
        if ($s[8] === 'verified') {
            for ($d = 0; $d < 25; $d++) {
                $type = ['call', 'whatsapp', 'view', 'directions'][rand(0, 3)];
                $ins("INSERT INTO leads (shop_id, user_id, type, created_at) VALUES (?,?,?,?)", [$sid, rand(1, 6), $type, date('Y-m-d H:i:s', strtotime('-' . rand(0, 29) . ' days -' . rand(0, 600) . ' minutes'))]);
            }
        }
    }
    $ins("INSERT INTO subscriptions (shop_id, plan_code, area, amount, status) VALUES (3, 'banner', 'T. Nagar', 1500, 'pending')");
    $ins("INSERT INTO payments (shop_id, purpose, amount, method, status) VALUES (3, 'Home banner', 1500, 'UPI', 'pending')");

    // Photo placeholders (camera, GPS-stamped)
    foreach ([1 => ['Shop front', 'Inside view', 'Product shelves', 'Billing counter'], 2 => ['Shop front', 'Inside view', 'Product shelves'], 5 => ['Inside view', 'Product shelves', 'Billing counter', 'Shop front']] as $sid => $labels) {
        $sh = $pdo->query("SELECT lat, lng FROM shops WHERE id = $sid")->fetch();
        foreach ($labels as $j => $l) {
            $ins("INSERT INTO shop_photos (shop_id, path, label, lat, lng, distance_m, source, taken_at) VALUES (?,?,?,?,?,?, 'camera', ?)",
                [$sid, 'demo/photo' . (($j % 4) + 1) . '.svg', $l, $sh['lat'] + 0.0002, $sh['lng'] + 0.0001, 25 + $j * 5, date('Y-m-d H:i:s', strtotime('-1 day +' . $j . ' minutes'))]);
        }
    }

    // Reviews
    $reviews = [[1, 2, 'Prakash', 5, 'Genuine Havells stock, good price', 'active'], [4, 3, 'Meena', 2, 'Shop closed at listed time', 'flagged'],
        [2, 4, 'Ramesh', 4, 'Wholesale rates for contractors', 'active'], [1, 1, 'Ravi Kumar', 5, 'Got all wiring items in one visit', 'active'],
        [3, 5, 'Anitha', 4, 'Good collection of fans', 'active']];
    foreach ($reviews as $r) $ins("INSERT INTO reviews (shop_id, user_id, customer_name, rating, body, status) VALUES (?,?,?,?,?,?)", $r);
    $pdo->exec("UPDATE shops s SET review_count = (SELECT COUNT(*) FROM reviews r WHERE r.shop_id = s.id) + FLOOR(RAND()*40) WHERE status='verified'");
    $pdo->exec("UPDATE shops SET review_count = 212 WHERE id = 1");

    // Quote requests
    $quotes = [
        [1, 'Ravi Kumar', '9876543210', "Wire 1.5 sq mm × 5 coils\nMCB 32A × 4\nLED panel 12W × 20", 'Velachery, Chennai', 12.9815, 80.2180, 5],
        [6, 'Siva Constructions', '9840055667', "Full house wiring list (3BHK)", 'Tambaram, Chennai', 12.9249, 80.1000, 8],
        [5, 'Anitha', '9840044556', "Ceiling fan × 3", 'Anna Nagar, Chennai', 13.0850, 80.2101, 5],
    ];
    foreach ($quotes as $qi => $qr) {
        $qid = $ins("INSERT INTO quote_requests (user_id, customer_name, customer_mobile, items, area, city_id, lat, lng, send_count, created_at) VALUES (?,?,?,?,?,1,?,?,?,?)",
            [$qr[0], $qr[1], $qr[2], $qr[3], $qr[4], $qr[5], $qr[6], $qr[7], date('Y-m-d H:i:s', strtotime('-' . ($qi * 2) . ' days'))]);
        $pdo->exec("UPDATE quote_requests SET code = CONCAT('QR-', 2210 - $qi * 2) WHERE id = $qid");
        foreach ([1, 2, 3, 4, 6] as $k => $sid) {
            $replied = $k < 2 + $qi;
            $ins("INSERT INTO quote_recipients (quote_id, shop_id, reply_amount, delivery, replied_at) VALUES (?,?,?,?,?)",
                [$qid, $sid, $replied ? 14820 + $k * 280 : null, $replied ? ($k == 0 ? 'Delivery today' : 'Tomorrow') : null, $replied ? date('Y-m-d H:i:s') : null]);
        }
    }

    // Search stats
    $terms = [1 => 42, 3 => 36, 5 => 29, 4 => 21, 9 => 14, 2 => 12];
    foreach ($terms as $cid => $n) for ($i = 0; $i < $n; $i++)
        $ins("INSERT INTO searches (category_id, city_id, created_at) VALUES (?,?,?)", [$cid, rand(1, 5), date('Y-m-d H:i:s', strtotime('-' . rand(0, 28) . ' days'))]);

    $ins("INSERT INTO banners (title, subtitle, shop_id, city_id, status, sort) VALUES ('All Electrical Products in One Place', 'Wires · Switches · Lighting · Fans', NULL, NULL, 'active', 0)");
    $ins("INSERT INTO banners (title, subtitle, shop_id, city_id, status, sort, amount, start_date, end_date) VALUES ('KK Electrical — Havells Week', 'Up to 18% off on Havells MCBs', 1, 1, 'active', 1, 1500, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY))");
    $ins("INSERT INTO offers (shop_id, title, description, valid_till) VALUES (1, 'Havells Week', 'Up to 18% off on Havells MCBs & DBs', DATE_ADD(CURDATE(), INTERVAL 10 DAY))");
    $ins("INSERT INTO offers (shop_id, title, description, valid_till) VALUES (2, 'Contractor rates', 'Special wholesale price on Polycab coils (min 10)', DATE_ADD(CURDATE(), INTERVAL 20 DAY))");
}
