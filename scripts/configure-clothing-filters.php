<?php
// Assign the existing native facet template to the live clothing category.
if (PHP_SAPI !== 'cli') { exit(1); }
require '/var/www/html/config/config.inc.php';
if (Configuration::get('PS_SHOP_DOMAIN') !== 'localhost:8083' || _DB_SERVER_ !== 'target-db') {
    throw new RuntimeException('Not isolated target.');
}
$db = Db::getInstance();
$templates = $db->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'layered_filter` ORDER BY date_add DESC');
$categories = $db->executeS('SELECT c.id_category, c.id_parent, cl.name FROM `' . _DB_PREFIX_ . 'category` c JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.id_category=c.id_category JOIN `' . _DB_PREFIX_ . 'lang` l ON l.id_lang=cl.id_lang WHERE l.iso_code="cs" AND cl.id_shop=1');
$root = (int) Configuration::get('PS_HOME_CATEGORY');
$matches = array_values(array_filter($categories, fn ($c) => $c['name'] === 'Oblečení' && (int) $c['id_parent'] === $root));
if (count($matches) !== 1) { throw new RuntimeException('Clothing category is ambiguous or absent.'); }
$id = (int) $matches[0]['id_category'];
$children = array_map(fn ($c) => (int) $c['id_category'], array_filter($categories, fn ($c) => (int) $c['id_parent'] === $id));
$selected = null;
foreach ($templates as $row) {
    $data = Tools::unSerialize($row['filters']);
    if (!in_array('category', $data['controllers'], true) || !in_array(1, $data['shop_list'])) { continue; }
    if (in_array($id, $data['categories'])) {
        if (!(int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'layered_category` WHERE controller="category" AND id_shop=1 AND id_category=' . $id)) {
            throw new RuntimeException('Template exists but derived facets are missing.');
        }
        echo json_encode(['changed' => false, 'category' => $id, 'reason' => 'Already configured']), PHP_EOL;
        exit;
    }
    if ($selected === null && array_intersect($children, $data['categories'])) {
        $selected = $row;
        $selected['decoded'] = $data;
    }
}
if (!$selected) { throw new RuntimeException('No existing child facet template.'); }
$options = getopt('', ['apply', 'backup:']);
if (isset($options['apply'])) {
    $backup = $options['backup'] ?? '';
    $directory = realpath(dirname($backup));
    $webroot = realpath(_PS_ROOT_DIR_);
    if (!$directory || $directory === $webroot || str_starts_with($directory . '/', $webroot . '/')) {
        throw new RuntimeException('Backup must be outside web root.');
    }
    $snapshot = [];
    foreach (['layered_filter', 'layered_filter_shop', 'layered_category'] as $table) {
        $snapshot[$table] = $db->executeS('SELECT * FROM `' . _DB_PREFIX_ . $table . '`');
        if ($snapshot[$table] === false) { throw new RuntimeException('Cannot read backup table.'); }
    }
    $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $handle = fopen($backup, 'x');
    if (!$handle) { throw new RuntimeException('Cannot create new private backup.'); }
    $written = fwrite($handle, $json);
    fclose($handle);
    chmod($backup, 0600);
    if ($written !== strlen($json)) { throw new RuntimeException('Incomplete backup.'); }
    $data = $selected['decoded'];
    $data['categories'][] = $id;
    if (!$db->update('layered_filter', ['filters' => pSQL(serialize($data)), 'n_categories' => count($data['categories'])],
        'id_layered_filter=' . (int) $selected['id_layered_filter'])) {
        throw new RuntimeException('Cannot update native facet template.');
    }
    $module = Module::getInstanceByName('ps_facetedsearch');
    if (!$module || !$module->active) {
        throw new RuntimeException('Cannot rebuild native facet assignments. Private backup preserved.');
    }
    $module->buildLayeredCategories();
    if (!(int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'layered_category` WHERE controller="category" AND id_shop=1 AND id_category=' . $id)) {
        throw new RuntimeException('Native facets were not generated. Private backup preserved.');
    }
}
echo json_encode(['applied' => isset($options['apply']), 'category' => $id, 'template' => (int) $selected['id_layered_filter']], JSON_THROW_ON_ERROR), PHP_EOL;
