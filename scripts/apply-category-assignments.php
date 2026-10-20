<?php
// Apply an evidence-backed additive plan only. Product/category metadata and old links are immutable.
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['HTTP_HOST'] = 'localhost:8083'; $_SERVER['SERVER_NAME'] = 'localhost'; $_SERVER['REQUEST_URI'] = '/';
require '/var/www/html/config/config.inc.php';
if (Configuration::get('PS_SHOP_DOMAIN') !== 'localhost:8083' || _DB_SERVER_ !== 'target-db') { throw new RuntimeException('Only isolated target.'); }
if (!str_starts_with(__DIR__, '/lab/import-runs/import-category-products-')) { throw new RuntimeException('Use a private backed-up run directory.'); }
$plan = json_decode(file_get_contents(__DIR__ . '/assignment-plan.json'), true, 512, JSON_THROW_ON_ERROR);
if ($plan['schema'] !== 1 || $plan['missing_navigation_after'] !== 0 || $plan['unresolved']) { throw new RuntimeException('Incomplete classification plan.'); }
if (!hash_equals($plan['catalog_sha256'], hash_file('sha256', __DIR__ . '/catalog-before.json'))) { throw new RuntimeException('Catalog input changed.'); }
$registry = json_decode(Configuration::get('KAFKA_CLIENT_CATEGORY_TREE'), true, 512, JSON_THROW_ON_ERROR);
if ($registry !== $plan['expected_registry']) { throw new RuntimeException('Managed tree changed. Rebuild the plan.'); }
$legacyTargets = ['legacy/plackyspendlikypiny' => 3, 'legacy/cepice-satky' => 32, 'legacy/taskypenezenky' => 58];
if ($plan['legacy_targets'] !== $legacyTargets) { throw new RuntimeException('Unknown legacy compatibility targets.'); }
$targets = array_merge($registry, $legacyTargets);
$db = Db::getInstance();
$orders = ['product' => 'id_product', 'product_shop' => 'id_product,id_shop', 'product_lang' => 'id_product,id_shop,id_lang',
    'category' => 'id_category', 'category_lang' => 'id_category,id_shop,id_lang', 'category_shop' => 'id_category,id_shop',
    'category_group' => 'id_category,id_group', 'category_product' => 'id_category,id_product'];
$encode = static fn ($rows) => json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$db->execute('START TRANSACTION');
try {
    $beforeLinks = [];
    foreach ($orders as $table => $order) {
        $rows = $db->executeS('SELECT * FROM `' . _DB_PREFIX_ . $table . '` ORDER BY ' . $order . ($table === 'category_product' ? ' FOR UPDATE' : ''));
        if (!hash_equals($plan['expected_fingerprints'][$table], hash('sha256', $encode($rows)))) { throw new RuntimeException('Stale plan: ' . $table); }
        if ($table === 'category_product') { $beforeLinks = $rows; }
    }
    $positions = [];
    foreach ($beforeLinks as $row) { $cid = (int) $row['id_category']; $positions[$cid] = max($positions[$cid] ?? -1, (int) $row['position']); }
    foreach ($plan['assignments'] as $assignment) {
        $pid = (int) $assignment['id_product']; $cid = (int) $assignment['id_category'];
        if (($targets[$assignment['path']] ?? 0) !== $cid || !$pid) { throw new RuntimeException('Invalid target identity.'); }
        $position = ($positions[$cid] ?? -1) + 1; $positions[$cid] = $position;
        if (!$db->insert('category_product', ['id_category' => $cid, 'id_product' => $pid, 'position' => $position])) { throw new RuntimeException('Product link insertion failed.'); }
    }
    $preserved = [];
    foreach ($orders as $table => $order) {
        if ($table === 'category_product') { continue; }
        $actual = hash('sha256', $encode($db->executeS('SELECT * FROM `' . _DB_PREFIX_ . $table . '` ORDER BY ' . $order)));
        if (!hash_equals($plan['expected_fingerprints'][$table], $actual)) { throw new RuntimeException('Protected metadata changed: ' . $table); }
        $preserved[$table] = $actual;
    }
    $oldKeys = [];
    foreach ($beforeLinks as $row) { $oldKeys[$row['id_category'] . '/' . $row['id_product']] = true; }
    $afterLinks = $db->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'category_product` ORDER BY id_category,id_product');
    $remainingOld = array_values(array_filter($afterLinks, static fn ($r) => isset($oldKeys[$r['id_category'] . '/' . $r['id_product']])));
    if (!hash_equals($plan['expected_fingerprints']['category_product'], hash('sha256', $encode($remainingOld)))
        || count($afterLinks) !== count($beforeLinks) + count($plan['assignments'])) { throw new RuntimeException('Existing associations changed.'); }
    $db->execute('COMMIT');
} catch (Throwable $e) { $db->execute('ROLLBACK'); throw $e; }
// Invalidate derived output only after a successful committed, verified catalog transaction.
$facets = Module::getInstanceByName('ps_facetedsearch');
if ($facets && $facets->active && !$facets->invalidateLayeredFilterBlockCache()) { throw new RuntimeException('Category links applied; derived facet cache invalidation failed.'); }
Context::getContext()->smarty->clearCompiledTemplate();
Context::getContext()->smarty->clearAllCache();
Cache::clean('*');
echo json_encode(['applied' => true, 'added_links' => count($plan['assignments']), 'original_links_preserved' => count($beforeLinks),
    'product_and_category_tables_unchanged' => $preserved, 'default_categories_and_urls_preserved' => true], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
