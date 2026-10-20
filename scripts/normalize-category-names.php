<?php
// Exact, repeatable display-name correction on the isolated migration target.
if (PHP_SAPI !== 'cli') { exit(1); }
require '/var/www/html/config/config.inc.php';
if (Configuration::get('PS_SHOP_DOMAIN') !== 'localhost:8083' || _DB_SERVER_ !== 'target-db') {
    throw new RuntimeException('Not isolated target.');
}
$names = [
    'PÁNSKÁ TÍLKA' => 'Pánská tílka',
    'PÁNSKÁ TRIČKA' => 'Pánská trička',
    'PÁNSKÉ MIKINY' => 'Pánské mikiny',
    'MIKINY NA ZIP PÁNSKÉ' => 'Mikiny na zip pánské',
    'DÁMSKÁ TÍLKA' => 'Dámská tílka',
    'DÁMSKÁ TRIČKA' => 'Dámská trička',
    'DÁMSKÉ MIKINY' => 'Dámské mikiny',
    'MIKINY NA ZIP DÁMSKÉ' => 'Mikiny na zip dámské',
    'SUKNĚ' => 'Sukně',
];
$db = Db::getInstance();
$rows = $db->executeS('SELECT id_category, id_lang, id_shop, name FROM `' . _DB_PREFIX_ . 'category_lang`');
if ($rows === false) { throw new RuntimeException('Cannot read categories.'); }
$changes = [];
foreach ($rows as $row) {
    if (isset($names[$row['name']])) {
        $row['new_name'] = $names[$row['name']];
        $changes[] = $row;
    }
}
$options = getopt('', ['apply', 'backup:']);
if (isset($options['apply']) && $changes) {
    $backup = $options['backup'] ?? '';
    $directory = realpath(dirname($backup));
    $webroot = realpath(_PS_ROOT_DIR_);
    if (!$directory || $directory === $webroot || str_starts_with($directory . '/', $webroot . '/')) {
        throw new RuntimeException('Provide a backup directory outside web root.');
    }
    $json = json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    $handle = fopen($backup, 'x');
    if (!$handle) { throw new RuntimeException('Cannot create backup.'); }
    $written = fwrite($handle, $json);
    fclose($handle);
    if ($written !== strlen($json)) { throw new RuntimeException('Incomplete backup.'); }
    $db->execute('START TRANSACTION');
    try {
        foreach ($changes as $row) {
            $where = 'id_category=' . (int) $row['id_category'] . ' AND id_lang=' . (int) $row['id_lang']
                . ' AND id_shop=' . (int) $row['id_shop'] . " AND BINARY name='" . pSQL($row['name']) . "'";
            if (!$db->update('category_lang', ['name' => pSQL($row['new_name'])], $where)) {
                throw new RuntimeException('Cannot update category name.');
            }
        }
        $db->execute('COMMIT');
    } catch (Throwable $error) {
        $db->execute('ROLLBACK');
        throw $error;
    }
    Cache::clean('*');
    Context::getContext()->smarty->clearAllCache();
    Context::getContext()->smarty->clearCompiledTemplate();
}
if (isset($options['apply'])) {
    // Facet labels are persisted separately from Smarty and core caches.
    $facets = Module::getInstanceByName('ps_facetedsearch');
    if ($facets && !$facets->invalidateLayeredFilterBlockCache()) {
        throw new RuntimeException('Cannot refresh facet labels.');
    }
}
echo json_encode(['applied' => isset($options['apply']), 'count' => count($changes), 'changes' => $changes],
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
