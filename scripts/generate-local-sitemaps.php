<?php
// Generate only from existing public catalog rows. Local execution and host guard live in the exporter.
if (PHP_SAPI !== 'cli') { exit(1); }
ob_start();
require __DIR__ . '/export-seo-catalog.php';
$catalog = json_decode(ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
$eligible = array_values(array_filter($catalog['rows'], fn ($r) => $r['eligible']));
$languages = [];
foreach ($catalog['languages'] as $language) {
    if ($language['active']) { $languages[$language['iso_code']] = $language['language_code']; }
}
$groups = [];
foreach ($eligible as $row) {
    if (parse_url($row['url'], PHP_URL_HOST) !== 'localhost' || parse_url($row['url'], PHP_URL_PORT) !== 8083
        || parse_url($row['url'], PHP_URL_QUERY) || parse_url($row['url'], PHP_URL_FRAGMENT)) { throw new RuntimeException('Noncanonical sitemap URL.'); }
    $groups[$row['entity'] . ':' . $row['id']][$row['language']] = $row['url'];
}
$escape = fn ($s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
$files = [];
$counts = [];
foreach ($languages as $iso => $code) {
    $rows = array_values(array_filter($eligible, fn ($r) => $r['language'] === $iso));
    $counts[$iso] = count($rows);
    foreach (array_chunk($rows, 2000) as $part => $chunk) {
        $name = '1_' . $iso . '_' . $part . '_sitemap.xml';
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        foreach ($chunk as $row) {
            $xml .= '<url><loc>' . $escape($row['url']) . '</loc>';
            foreach ($groups[$row['entity'] . ':' . $row['id']] as $altIso => $url) {
                $xml .= '<xhtml:link rel="alternate" hreflang="' . $escape($languages[$altIso]) . '" href="' . $escape($url) . '"/>';
            }
            $xml .= "</url>\n";
        }
        $files[$name] = $xml . '</urlset>';
    }
}
$index = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach (array_keys($files) as $name) { $index .= '<sitemap><loc>http://localhost:8083/' . $name . "</loc></sitemap>\n"; }
$index .= '</sitemapindex>';
$files['1_index_sitemap.xml'] = $index;
$files['sitemap.xml'] = $index;
$manifestPath = _PS_ROOT_DIR_ . '/kafka-sitemap-manifest.json';
$oldFiles = is_file($manifestPath) ? (json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR)['files'] ?? []) : [];
// Write and validate every file before publishing, then publish index last.
foreach ($files as $name => $xml) {
    if (simplexml_load_string($xml) === false || file_put_contents(_PS_ROOT_DIR_ . '/' . $name . '.new', $xml) !== strlen($xml)) { throw new RuntimeException('Sitemap write/validation failed.'); }
}
foreach ($files as $name => $xml) {
    if (!rename(_PS_ROOT_DIR_ . '/' . $name . '.new', _PS_ROOT_DIR_ . '/' . $name)) { throw new RuntimeException('Cannot publish sitemap.'); }
}
foreach (array_diff($oldFiles, array_keys($files)) as $old) {
    if (preg_match('/^1_[a-z]{2}_\d+_sitemap\.xml$/', $old) && is_file(_PS_ROOT_DIR_ . '/' . $old)) { unlink(_PS_ROOT_DIR_ . '/' . $old); }
}
$manifest = ['generated_at' => gmdate('c'), 'local_noindex_preview' => true, 'counts' => $counts, 'files' => array_keys($files)];
file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
