<?php
require_once __DIR__ . '/../config.php';

function db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true,
    ]);
    $pdo->exec("SET time_zone = '+05:30'");
    ensure_installed($pdo);
    return $pdo;
}

function ensure_installed($pdo) {
    $exists = $pdo->query("SHOW TABLES LIKE 'admins'")->fetchColumn();
    if ($exists) { migrate($pdo); return; }
    $sql = file_get_contents(__DIR__ . '/../install/schema.sql');
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if (stripos($stmt, 'CREATE') !== false || stripos($stmt, 'SET') === 0) $pdo->exec($stmt);
    }
    require __DIR__ . '/../install/seed.php';
    seed_database($pdo);
    migrate($pdo);
}

/** Small additive migrations for databases created by older versions. Safe to run repeatedly. */
function migrate($pdo) {
    $want = [
        'banners' => [
            'media_type' => "VARCHAR(10) NOT NULL DEFAULT 'image'",
            'video' => "VARCHAR(255) NULL",
            'radius_km' => "DECIMAL(6,1) NULL",
        ],
        'notifications' => [
            'city_id' => "INT NULL",
            'audience' => "VARCHAR(20) NOT NULL DEFAULT 'all'",
            'shop_id' => "INT NULL",
            'image' => "VARCHAR(255) NULL",
        ],
        'users' => [
            'notif_seen_id' => "INT NOT NULL DEFAULT 0",
        ],
    ];
    // App renamed: Electrical Hub -> Electrical Market Hub
    $pdo->exec("UPDATE settings SET v = 'Electrical Market Hub' WHERE k = 'app_name' AND v = 'Electrical Hub'");
    foreach ($want as $table => $cols) {
        $have = array_column($pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC), 'Field');
        foreach ($cols as $c => $def) if (!in_array($c, $have, true)) $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$c` $def");
    }
}

/** Expire paid plans whose end date has passed (cheap; runs at most once per hour). */
function run_maintenance() {
    $last = (int)setting('last_maintenance', 0);
    if (time() - $last < 3600) return;
    set_setting('last_maintenance', (string)time());
    q("UPDATE subscriptions SET status = 'expired' WHERE status = 'active' AND expiry_date < CURDATE()");
    q("UPDATE shops SET plan = 'free', plan_expiry = NULL WHERE plan <> 'free' AND plan_expiry IS NOT NULL AND plan_expiry < CURDATE()");
    q("UPDATE banners SET status = 'expired' WHERE status = 'active' AND end_date IS NOT NULL AND end_date < CURDATE()");
}

function q($sql, $params = []) {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
function all($sql, $params = []) { return q($sql, $params)->fetchAll(); }
function one($sql, $params = []) { $r = q($sql, $params)->fetch(); return $r ?: null; }
function val($sql, $params = []) { $r = q($sql, $params)->fetchColumn(); return $r === false ? null : $r; }
function last_id() { return (int)db()->lastInsertId(); }

function setting($k, $default = '') {
    static $cache = null;
    if ($cache === null) {
        $rows = all("SELECT k, v FROM settings");
        $cache = [];
        foreach ($rows as $r) $cache[$r['k']] = $r['v'];
    }
    return array_key_exists($k, $cache) ? $cache[$k] : $default;
}
function set_setting($k, $v) {
    setting('__warm__');
    q("INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)", [$k, $v]);
}

/** SQL expression for distance in km from (lat,lng) to shops.lat/lng */
function distance_sql($lat, $lng, $alias = 's') {
    $lat = (float)$lat; $lng = (float)$lng;
    return "(6371 * ACOS(LEAST(1, COS(RADIANS($lat)) * COS(RADIANS($alias.lat)) * COS(RADIANS($alias.lng) - RADIANS($lng)) + SIN(RADIANS($lat)) * SIN(RADIANS($alias.lat)))))";
}

function haversine_m($lat1, $lng1, $lat2, $lng2) {
    $r = 6371000;
    $dLat = deg2rad($lat2 - $lat1); $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return (int)round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)));
}

function shop_is_open($shop) {
    $now = date('H:i');
    if (!empty($shop['weekly_off']) && strcasecmp($shop['weekly_off'], date('D')) === 0) return false;
    $o = $shop['open_time'] ?: '09:00'; $c = $shop['close_time'] ?: '21:00';
    return $o <= $c ? ($now >= $o && $now < $c) : ($now >= $o || $now < $c);
}

function fmt_time12($t) {
    if (!$t) return '';
    return date('g:i A', strtotime($t));
}

function base_url() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/';
    // app root = folder containing config.php
    $root = rtrim(str_replace('\\', '/', dirname(dirname($script))), '/');
    if (basename(dirname($script)) !== 'api' && basename(dirname($script)) !== 'admin') {
        $root = rtrim(str_replace('\\', '/', dirname($script)), '/');
    }
    return ($https ? 'https' : 'http') . '://' . $host . $root;
}

function upload_url($path) {
    if (!$path) return null;
    if (preg_match('~^https?://~', $path)) return $path;
    if (strpos($path, 'demo/') === 0) return base_url() . '/assets/' . $path;
    return base_url() . '/' . UPLOAD_URL . '/' . ltrim($path, '/');
}

/** Save an uploaded file ($_FILES entry). Returns relative path or throws. */
function save_upload($file, $subdir, $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'], $maxMb = 10) {
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) throw new Exception('File too large for the server (max ' . ini_get('upload_max_filesize') . ')');
    if (!$file || $err !== UPLOAD_ERR_OK) throw new Exception('File upload failed');
    if ($file['size'] > $maxMb * 1024 * 1024) throw new Exception("File too large (max $maxMb MB)");
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext === '') {
        $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf', 'video/mp4' => 'mp4'];
        $ext = $map[mime_content_type($file['tmp_name'])] ?? '';
    }
    if (!in_array($ext, $allowed, true)) throw new Exception('File type not allowed');
    $dir = rtrim(UPLOAD_DIR, '/') . '/' . $subdir;
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) {
        if (!rename($file['tmp_name'], "$dir/$name")) throw new Exception('Could not save file');
    }
    return "$subdir/$name";
}

function inr($n) {
    $n = (int)$n;
    if ($n >= 10000000) return '₹' . rtrim(rtrim(number_format($n / 10000000, 2), '0'), '.') . ' Cr';
    if ($n >= 100000) return '₹' . rtrim(rtrim(number_format($n / 100000, 2), '0'), '.') . ' L';
    return '₹' . indian_number($n);
}
function indian_number($n) {
    $n = (string)(int)$n; $neg = '';
    if ($n[0] === '-') { $neg = '-'; $n = substr($n, 1); }
    if (strlen($n) <= 3) return $neg . $n;
    $last3 = substr($n, -3); $rest = substr($n, 0, -3);
    $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
    return $neg . $rest . ',' . $last3;
}
function knum($n) { return $n >= 1000 ? rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'k' : (string)$n; }
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
