<?php
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['HTTP_HOST'] = 'localhost:8083';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require '/var/www/html/config/config.inc.php';
if (Configuration::get('PS_SHOP_DOMAIN') !== 'localhost:8083' || _DB_SERVER_ !== 'target-db') { throw new RuntimeException('Only the isolated target is permitted.'); }
$db = Db::getInstance();
$shop = (int) Context::getContext()->shop->id;
$cs = (int) Language::getIdByIso('cs');
$home = (int) Context::getContext()->shop->id_category;
$registry = json_decode(Configuration::get('KAFKA_CLIENT_CATEGORY_TREE') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
$definitions = json_decode(file_get_contents('/var/www/html/modules/kafkahome/data/sections.json'), true, 512, JSON_THROW_ON_ERROR);
$errors = []; $tree = [];
$walk = function ($nodes, $parent, $path = '') use (&$walk, &$errors, &$tree, $db, $shop, $cs, $registry) {
    foreach ($nodes as $position => $node) {
        $key = $path . ($path ? '/' : '') . Tools::str2url($node['name']);
        $id = (int) ($registry[$key] ?? 0);
        $row = $db->getRow('SELECT c.id_parent,c.active,c.level_depth,c.nleft,c.nright,cs.position,cl.name FROM `' . _DB_PREFIX_ . 'category` c JOIN `' . _DB_PREFIX_ . 'category_shop` cs ON cs.id_category=c.id_category JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.id_category=c.id_category AND cl.id_shop=cs.id_shop WHERE c.id_category=' . $id . ' AND cs.id_shop=' . $shop . ' AND cl.id_lang=' . $cs);
        if (!$row || (int) $row['id_parent'] !== $parent || (int) $row['position'] !== $position || $row['name'] !== $node['name'] || !(int) $row['active']) { $errors[] = ['path' => $key, 'actual' => $row]; }
        $category = new Category($id, $cs);
        if (!$category->checkAccess(0)) { $errors[] = ['access' => $key]; }
        $tree[] = ['path' => $key, 'id' => $id, 'parent' => $parent, 'position' => $position, 'url' => Context::getContext()->link->getCategoryLink($category)];
        $walk($node['items'], $id, $key);
    }
};
$walk($definitions, $home);
$top = array_map('intval', array_column($db->executeS('SELECT c.id_category FROM `' . _DB_PREFIX_ . 'category` c JOIN `' . _DB_PREFIX_ . 'category_shop` cs ON cs.id_category=c.id_category WHERE c.active=1 AND c.id_parent=' . $home . ' AND cs.id_shop=' . $shop . ' ORDER BY cs.position'), 'id_category'));
$expectedTop = array_map(static fn ($node) => (int) $registry[Tools::str2url($node['name'])], $definitions);
if ($top !== $expectedTop) { $errors[] = ['top_level' => $top, 'expected' => $expectedTop]; }
if (in_array(127, array_map('intval', array_values($registry)), true)) { $errors[] = ['reserved_id_127_reused_for_client_tree' => true]; }
echo json_encode(['passed' => !$errors, 'node_count' => count($tree), 'errors' => $errors, 'tree' => $tree], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
exit($errors ? 1 : 0);
