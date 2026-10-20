<?php
// File cleanup before extracting the private tar backup; only files recorded by our sitemap generator.
if (PHP_SAPI !== 'cli') { exit(1); }
require '/var/www/html/config/config.inc.php';
if (Configuration::get('PS_SHOP_DOMAIN') !== 'localhost:8083' || _DB_SERVER_ !== 'target-db') { throw new RuntimeException('Not isolated target.'); }
$directory = realpath($argv[1] ?? '');
if (!$directory || !str_starts_with($directory, '/lab/seo-runs/seo-')) { throw new RuntimeException('Wrong backup directory.'); }
$previous = file($directory . '/files-before.list', FILE_IGNORE_NEW_LINES);
$manifest = _PS_ROOT_DIR_ . '/kafka-sitemap-manifest.json';
if (is_file($manifest)) {
    foreach (json_decode(file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR)['files'] as $name) {
        if (preg_match('/^(?:1_[a-z]{2}_\d+_sitemap|1_index_sitemap|sitemap)\.xml$/', $name)
            && !in_array($name, $previous, true) && is_file(_PS_ROOT_DIR_ . '/' . $name)) { unlink(_PS_ROOT_DIR_ . '/' . $name); }
    }
    if (!in_array('kafka-sitemap-manifest.json', $previous, true)) { unlink($manifest); }
}
