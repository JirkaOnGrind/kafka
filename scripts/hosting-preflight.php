<?php
// Temporary, read-only hosting check. Never accepts credentials or runs SQL.
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');

$host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
if ($host !== 'kafka.jirkanetrh.cz') {
    http_response_code(403);
    exit('Kontrola je určená pouze pro kafka.jirkanetrh.cz.');
}

function escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function bytes(string $value): int {
    $value = trim($value);
    $number = (int) $value;
    if ($number < 0) { return PHP_INT_MAX; }
    $unit = strtolower(substr($value, -1));
    return $number * match ($unit) { 'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1 };
}

$checks = [];
$checks[] = ['PHP pro PS 9.1', PHP_VERSION, PHP_VERSION_ID >= 80100 && PHP_VERSION_ID < 80600];
foreach (['curl', 'dom', 'fileinfo', 'gd', 'iconv', 'intl', 'json', 'mbstring', 'openssl', 'pdo_mysql', 'simplexml', 'zip'] as $extension) {
    $checks[] = ['Rozšíření ' . $extension, extension_loaded($extension) ? 'Dostupné' : 'Chybí', extension_loaded($extension)];
}
$memory = (string) ini_get('memory_limit');
$checks[] = ['Paměť (doporučeno alespoň 512 MB)', $memory, bytes($memory) >= 536870912];
$time = (int) ini_get('max_execution_time');
$checks[] = ['Čas běhu (pro přenos doporučeno 300 s)', $time === 0 ? 'Bez limitu' : $time . ' s', $time === 0 || $time >= 300];
$checks[] = ['Limit nahrávaného souboru', (string) ini_get('upload_max_filesize'), null];
$checks[] = ['Limit požadavku', (string) ini_get('post_max_size'), null];
$checks[] = ['Zápis do složky testovacího webu', is_writable(__DIR__) ? 'Povolen' : 'Zakázán', is_writable(__DIR__)];
$https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');
$checks[] = ['HTTPS při tomto otevření', $https ? 'Aktivní' : 'PHP jej nepotvrdilo', $https];

echo '<!doctype html><html lang="cs"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Kafka — kontrola hostingu</title><style>body{font:16px system-ui;max-width:820px;margin:32px auto;padding:0 18px;color:#18202b}table{border-collapse:collapse;width:100%}td,th{padding:10px;text-align:left;border-bottom:1px solid #ddd}.ok{color:#16703c}.warn{color:#9b4300}</style><h1>Kafka — kontrola hostingu</h1><p>Tato stránka pouze čte parametry PHP. Nic neinstaluje ani nemění databázi.</p><table><tr><th>Kontrola</th><th>Výsledek</th><th>Stav</th></tr>';
foreach ($checks as [$label, $value, $ok]) {
    echo '<tr><td>' . escape($label) . '</td><td>' . escape((string) $value) . '</td><td class="' . ($ok === false ? 'warn' : 'ok') . '">' . ($ok === null ? 'Informace' : ($ok ? 'OK' : 'Prověřit')) . '</td></tr>';
}
echo '</table><p><strong>Databáze ani moduly tímto nejsou ověřené.</strong> Verzi databáze a skutečné spuštění shopu zkontrolujeme samostatně.</p><p>Po převzetí výsledku tento kontrolní soubor z FTP smaž.</p></html>';
