<?php
/**
 * One-time data export for moving to a new server.
 * Works only when the MIGRATE_TOKEN environment variable is set on this server and the same token is passed.
 * Remove the variable (or this file) once the move is done.
 */
require __DIR__ . '/lib/db.php';
header('Content-Type: application/json');
$tok = getenv('MIGRATE_TOKEN');
if (!$tok || strlen($tok) < 24 || !hash_equals($tok, (string)($_GET['token'] ?? ''))) { http_response_code(403); exit('{"ok":false,"error":"forbidden"}'); }

$skip = ['admins', 'otps'];   // admin logins stay as set on the new server
$pdo = db();
$out = ['ok' => true, 'exported_at' => date('c'), 'tables' => [], 'files' => []];
foreach ($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $t) {
    if (in_array($t, $skip, true)) continue;
    $out['tables'][$t] = $pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
}
$root = rtrim(UPLOAD_DIR, '/');
if (is_dir($root)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) if ($f->isFile() && $f->getFilename()[0] !== ".") $out['files'][] = ltrim(substr($f->getPathname(), strlen($root)), '/');
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
