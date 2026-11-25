<?php
/**
 * One-time merge: brings admin changes made on the old Railway server after the first import into this server.
 * Upserts categories + cities, adds shops that exist only on the old server (with their categories/brands/photos),
 * and copies missing photo/video files. Nothing on this server is deleted. Removed after use.
 */
require __DIR__ . '/lib/db.php';
header('Content-Type: application/json');
$tok = (string)($_GET['token'] ?? '');
if (!hash_equals('43719dd8ff13f38d8597d92c72fa891e27d1788de6995353143d6a24673a680e', hash('sha256', $tok))) { http_response_code(403); exit('{"ok":false}'); }
set_time_limit(0);
$src = getenv('SYNC_SRC') ?: 'https://electrical-hub-production.up.railway.app';
function get_url($u) { $c = stream_context_create(['http' => ['timeout' => 120, 'ignore_errors' => true]]); $r = @file_get_contents($u, false, $c); $code = 0;
  if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) $code = (int)$m[1]; return [$code, $r]; }
[$code, $raw] = get_url($src . '/sync-export.php?token=' . urlencode($tok));
$old = json_decode((string)$raw, true);
if ($code !== 200 || empty($old['ok'])) { http_response_code(502); exit(json_encode(['ok' => false, 'error' => 'old server export failed', 'code' => $code])); }
$pdo = db(); $log = [];

$bdir = __DIR__ . '/_backup'; if (!is_dir($bdir)) { mkdir($bdir, 0750, true); file_put_contents($bdir . '/.htaccess', "Require all denied\nDeny from all\n"); }
$bk = []; foreach (['categories', 'cities', 'shops', 'shop_categories', 'shop_brands', 'shop_photos'] as $t) $bk[$t] = $pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
file_put_contents($bdir . '/before-merge-' . date('Ymd-His') . '.json', json_encode($bk, JSON_UNESCAPED_UNICODE));

function cols($t) { static $c = []; return $c[$t] ??= db()->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_COLUMN); }
function put($t, $r, $upsert) {
  $r = array_intersect_key($r, array_flip(cols($t))); $k = array_keys($r);
  $sql = ($upsert ? 'INSERT' : 'INSERT IGNORE') . " INTO `$t` (`" . implode('`,`', $k) . "`) VALUES (" . rtrim(str_repeat('?,', count($k)), ',') . ")";
  if ($upsert) $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(fn($c) => "`$c` = VALUES(`$c`)", array_diff($k, ['id'])));
  $st = db()->prepare($sql); $st->execute(array_values($r)); return $st->rowCount();
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0'); $pdo->beginTransaction();
foreach (['categories', 'cities'] as $t) { $n = 0; foreach ($old['tables'][$t] as $r) $n += put($t, $r, true) ? 1 : 0; $log[] = "$t: $n added/updated"; }
$have = $pdo->query("SELECT id FROM shops")->fetchAll(PDO::FETCH_COLUMN);
$newShops = array_values(array_filter($old['tables']['shops'], fn($s) => !in_array($s['id'], $have)));
$ids = array_map(fn($s) => (int)$s['id'], $newShops);
foreach ($newShops as $s) { put('shops', $s, false); $log[] = 'shop added: ' . $s['name']; }
foreach (['shop_categories', 'shop_brands', 'shop_photos', 'shop_products'] as $t) foreach (($old['tables'][$t] ?? []) as $r) if (in_array((int)$r['shop_id'], $ids, true)) put($t, $r, false);
$pdo->commit(); $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$root = rtrim(UPLOAD_DIR, '/'); $ok = 0; $bad = [];
foreach ($old['files'] as $rel) {
  if (strpos($rel, '..') !== false) continue; $dest = $root . '/' . $rel;
  if (is_file($dest) && filesize($dest) > 0) continue;
  [$c, $bin] = get_url($src . '/uploads/' . str_replace('%2F', '/', rawurlencode($rel)));
  if ($c === 200 && $bin) { if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0775, true); file_put_contents($dest, $bin); $ok++; } else $bad[] = $rel;
}
$log[] = "files copied: $ok" . ($bad ? ', failed: ' . implode(', ', $bad) : '');
echo json_encode(['ok' => true, 'log' => $log], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
