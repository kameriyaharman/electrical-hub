<?php
/**
 * One-time import of all data and photos from the old server (Railway) into this server.
 * The current data here is backed up first to backend/_backup/ (not web-accessible).
 */
require __DIR__ . '/_bootstrap.php';
require_admin();
check_csrf();
set_time_limit(0);

function fetch_url($url, $timeout = 60) {
    $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'ignore_errors' => true, 'header' => "User-Agent: ElectricalMarketHub-import\r\n"]]);
    $raw = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) $code = (int)$m[1];
    return [$code, $raw];
}

$log = []; $err = '';
$src = rtrim(post('source', 'https://electrical-hub-production.up.railway.app'), '/');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'import') {
    try {
        if (!post('confirm')) throw new Exception('Tick the box to confirm replacing the data on this server.');
        [$code, $raw] = fetch_url($src . '/export-all.php?token=' . urlencode(post('token')), 120);
        $data = $raw ? json_decode($raw, true) : null;
        if ($code !== 200 || empty($data['ok'])) throw new Exception('Old server did not send data (HTTP ' . $code . '). Check the key.');
        $pdo = db();

        // 1. back up the current data on this server
        $bdir = dirname(__DIR__) . '/_backup';
        if (!is_dir($bdir)) { mkdir($bdir, 0750, true); file_put_contents($bdir . '/.htaccess', "Require all denied\nDeny from all\n"); }
        $have = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $bk = [];
        foreach ($have as $t) $bk[$t] = $pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
        $bfile = $bdir . '/before-import-' . date('Ymd-His') . '.json';
        file_put_contents($bfile, json_encode($bk, JSON_UNESCAPED_UNICODE));
        $log[] = 'Backup of this server saved: _backup/' . basename($bfile);

        // 2. replace table contents
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->beginTransaction();
        foreach ($data['tables'] as $t => $rows) {
            if (!in_array($t, $have, true) || $t === 'admins') continue;
            $cols = $pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_COLUMN);
            $pdo->exec("DELETE FROM `$t`");
            foreach ($rows as $r) {
                $r = array_intersect_key($r, array_flip($cols));
                if (!$r) continue;
                $k = array_keys($r);
                $st = $pdo->prepare("INSERT INTO `$t` (`" . implode('`,`', $k) . "`) VALUES (" . rtrim(str_repeat('?,', count($k)), ',') . ")");
                $st->execute(array_values($r));
            }
            $log[] = "$t: " . count($rows) . ' rows';
        }
        $pdo->commit();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        // 3. copy photos / videos
        $root = rtrim(UPLOAD_DIR, '/'); $ok = 0; $bad = [];
        foreach ($data['files'] as $rel) {
            if (strpos($rel, '..') !== false) continue;
            $dest = $root . '/' . $rel;
            if (is_file($dest) && filesize($dest) > 0) { $ok++; continue; }
            [$c, $bin] = fetch_url($src . '/uploads/' . str_replace('%2F', '/', rawurlencode($rel)), 120);
            if ($c === 200 && $bin !== false && $bin !== '') {
                if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0775, true);
                file_put_contents($dest, $bin); $ok++;
            } else $bad[] = $rel;
        }
        $log[] = "Photos/videos copied: $ok of " . count($data['files']);
        if ($bad) $log[] = 'Could not copy: ' . implode(', ', array_slice($bad, 0, 20));
        $log[] = 'Done. Data from the old server is now live here.';
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        $err = $e->getMessage();
    }
}

page_header('Import from old server', 'settings', 'Move all shops, users, products, photos and banners from the old server to this one');
?>
<div class="card" style="max-width:640px">
<?php if ($err): ?><div class="flash err"><?= h($err) ?></div><?php endif; ?>
<?php if ($log): ?><div class="flash ok"><?= implode('<br>', array_map('h', $log)) ?></div><?php endif; ?>
<form method="post" class="f">
  <?= csrf_field() ?><input type="hidden" name="action" value="import">
  <label>Old server address</label><input type="text" name="source" value="<?= h($src) ?>">
  <label>Migration key</label><input type="text" name="token" autocomplete="off" required>
  <p class="muted">This replaces all shops, users, products, categories, banners, cities, reviews and settings on this server with the old server's data. Admin logins here are not changed. Current data here is backed up first.</p>
  <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="confirm" value="1"> I understand the data on this server will be replaced</label>
  <button class="btn p" style="margin-top:14px">Import now</button>
</form>
</div>
<?php page_footer();
