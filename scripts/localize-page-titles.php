<?php
// CLI-only repair of English installer titles in the Czech local shop metadata.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
require '/var/www/html/config/config.inc.php';
if (!in_array(Configuration::get('PS_SHOP_DOMAIN'), ['localhost:8080', 'localhost:8083'], true)) {
    throw new RuntimeException('This repair is restricted to the local development shops.');
}

$titles = [
    'address' => ['Address', 'Adresa'],
    'addresses' => ['Addresses', 'Moje adresy'],
    'authentication' => ['Login', 'Přihlášení'],
    'best-sales' => ['Best sales', 'Nejprodávanější'],
    'cart' => ['Cart', 'Košík'],
    'contact' => ['Contact us', 'Kontakt'],
    'discount' => ['Discount', 'Slevové kupóny'],
    'guest-tracking' => ['Guest tracking', 'Sledování objednávky'],
    'history' => ['Order history', 'Historie objednávek'],
    'identity' => ['Identity', 'Osobní údaje'],
    'manufacturer' => ['Manufacturers', 'Značky'],
    'my-account' => ['My account', 'Můj účet'],
    'new-products' => ['New products', 'Novinky'],
    'order' => ['Order', 'Dokončení objednávky'],
    'order-confirmation' => ['Order confirmation', 'Potvrzení objednávky'],
    'order-follow' => ['Order follow', 'Vrácení zboží'],
    'order-opc' => ['Order', 'Dokončení objednávky'],
    'order-slip' => ['Credit slip', 'Dobropisy'],
    'pagenotfound' => ['404 error', 'Stránka nenalezena'],
    'password' => ['Forgot your password', 'Obnovení hesla'],
    'prices-drop' => ['Prices drop', 'Slevy'],
    'products-comparison' => ['Products Comparison', 'Porovnání produktů'],
    'search' => ['Search', 'Vyhledávání'],
    'sitemap' => ['Sitemap', 'Mapa webu'],
    'stores' => ['Stores', 'Prodejny'],
    'supplier' => ['Suppliers', 'Dodavatelé'],
];
$languageId = (int) Language::getIdByIso('cs');
$shopId = (int) Context::getContext()->shop->id;
if (!$languageId || !$shopId) {
    throw new RuntimeException('Czech language or shop context is missing.');
}
$db = Db::getInstance();
$rows = $db->executeS('SELECT m.page, ml.id_meta, ml.id_lang, ml.id_shop, ml.title FROM `'
    . _DB_PREFIX_ . 'meta` m JOIN `' . _DB_PREFIX_ . 'meta_lang` ml ON m.id_meta=ml.id_meta'
    . ' WHERE ml.id_lang=' . $languageId . ' AND ml.id_shop=' . $shopId);
if ($rows === false) {
    throw new RuntimeException('Cannot read page metadata.');
}
$changes = [];
foreach ($rows as $row) {
    if (isset($titles[$row['page']]) && $row['title'] === $titles[$row['page']][0]) {
        $row['new_title'] = $titles[$row['page']][1];
        $changes[] = $row;
    }
}
$options = getopt('', ['apply', 'backup:']);
if (isset($options['apply']) && $changes) {
    $backup = $options['backup'] ?? '';
    $directory = realpath(dirname($backup));
    $webroot = realpath(_PS_ROOT_DIR_);
    if (!$directory || $directory === $webroot || str_starts_with($directory . '/', $webroot . '/')) {
        throw new RuntimeException('Provide an existing backup directory outside the web root.');
    }
    $handle = fopen($backup, 'x');
    if (!$handle) {
        throw new RuntimeException('Cannot create a new metadata backup.');
    }
    $json = json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    $written = fwrite($handle, $json);
    fclose($handle);
    if ($written !== strlen($json)) {
        throw new RuntimeException('Incomplete backup; metadata was not changed.');
    }
    $db->execute('START TRANSACTION');
    try {
        foreach ($changes as $row) {
            $where = 'id_meta=' . (int) $row['id_meta'] . ' AND id_lang=' . $languageId
                . ' AND id_shop=' . $shopId . " AND title='" . pSQL($row['title']) . "'";
            if (!$db->update('meta_lang', ['title' => pSQL($row['new_title'])], $where)) {
                throw new RuntimeException('Cannot update page metadata.');
            }
        }
        $db->execute('COMMIT');
    } catch (Throwable $error) {
        $db->execute('ROLLBACK');
        throw $error;
    }
}
echo json_encode(['applied' => isset($options['apply']), 'count' => count($changes), 'changes' => $changes],
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
