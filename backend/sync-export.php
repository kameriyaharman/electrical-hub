<?php
/** Temporary read-only export used to reconcile the old (Railway) and new (BigRock) servers. Removed after the merge. */
require __DIR__ . '/lib/db.php';
header('Content-Type: application/json');
if (!hash_equals('43719dd8ff13f38d8597d92c72fa891e27d1788de6995353143d6a24673a680e', hash('sha256', (string)($_GET['token'] ?? '')))) { http_response_code(403); exit('{"ok":false}'); }
$pdo = db(); $out = ['ok' => true, 'host' => $_SERVER['HTTP_HOST'] ?? '', 'at' => date('c'), 'tables' => []];
foreach ($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $t) {
    if (in_array($t, ['admins', 'otps', 'api_tokens'], true)) continue;
    $out['tables'][$t] = $pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
}
$root = rtrim(UPLOAD_DIR, '/'); $out['files'] = [];
if (is_dir($root)) foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $f)
    if ($f->isFile() && $f->getFilename()[0] !== '.') $out['files'][] = ltrim(substr($f->getPathname(), strlen($root)), '/');
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
