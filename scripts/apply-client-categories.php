<?php
// Explicit local taxonomy change; never run on a public shop. Existing product URLs/defaults are preserved.
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['HTTP_HOST'] = 'localhost:8083';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require '/var/www/html/config/config.inc.php';
if (Configuration::get('PS_SHOP_DOMAIN') !== 'localhost:8083' || _DB_SERVER_ !== 'target-db') {
    throw new RuntimeException('Only the isolated local target is permitted.');
}
$apply = in_array('--apply', $argv, true);
$db = Db::getInstance();
$shop = (int) Context::getContext()->shop->id;
$home = (int) Context::getContext()->shop->id_category;
$cs = (int) Language::getIdByIso('cs');
$definitions = json_decode(file_get_contents(__DIR__ . '/sections.json'), true, 512, JSON_THROW_ON_ERROR);
$reserved = json_decode(file_get_contents(__DIR__ . '/reserved-category-ids.json'), true, 512, JSON_THROW_ON_ERROR);
$registry = json_decode(Configuration::get('KAFKA_CLIENT_CATEGORY_TREE') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
$nextId = max((int) $db->getValue('SELECT MAX(id_category) FROM `' . _DB_PREFIX_ . 'category`'), max($reserved ?: [0])) + 1;
// Repair a new local identity only if it collided with a historically missing source category.
// Never change or delete an imported category or any product association.
if ($apply && (int) ($registry['obleceni'] ?? 0) === 127 && in_array(127, $reserved, true)) {
    $local = new Category(127);
    if (($local->name[$cs] ?? '') !== 'Oblečení'
        || (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'category_product` WHERE id_category=127')
        || (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product_shop` WHERE id_category_default=127')) { throw new RuntimeException('Reserved ID collision requires manual review.'); }
    $db->execute('START TRANSACTION');
    try {
        foreach (['category', 'category_lang', 'category_shop', 'category_group'] as $table) {
            if (!$db->update($table, ['id_category' => $nextId], 'id_category=127')) { throw new RuntimeException('Reserved ID repair failed.'); }
        }
        if (!$db->update('category', ['id_parent' => $nextId], 'id_parent=127')) { throw new RuntimeException('Reserved parent repair failed.'); }
        $registry['obleceni'] = $nextId++;
        if (!Configuration::updateValue('KAFKA_CLIENT_CATEGORY_TREE', json_encode($registry, JSON_THROW_ON_ERROR))) { throw new RuntimeException('Registry repair failed.'); }
        Category::regenerateEntireNtree();
        $db->execute('COMMIT');
    } catch (Throwable $e) { $db->execute('ROLLBACK'); throw $e; }
}
$rows = $db->executeS('SELECT c.id_category,c.id_parent,c.active,cl.name FROM `' . _DB_PREFIX_ . 'category` c JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.id_category=c.id_category WHERE cl.id_shop=' . $shop . ' AND cl.id_lang=' . $cs);
$used = [];
$changes = [];
$normal = static fn ($name) => Tools::str2url(trim($name));
$resolve = function (array $node, int $parent, int $position, string $path) use (&$resolve, &$registry, &$used, &$changes, &$rows, &$nextId, $db, $shop, $cs, $normal, $apply) {
    $path .= ($path ? '/' : '') . $normal($node['name']);
    $id = 0;
    if (!empty($registry[$path])) {
        foreach ($rows as $row) {
            if ((int) $row['id_category'] === (int) $registry[$path]) { $id = (int) $row['id_category']; break; }
        }
    }
    if (!$id) {
        // Parent scope distinguishes catalog Placky/Čepice/etc. from custom-print categories.
        foreach ([$parent, null] as $scope) {
            foreach (array_unique(array_merge($node['aliases'], [$node['name']])) as $alias) {
                $matches = array_values(array_filter($rows, static fn ($r) => !isset($used[(int) $r['id_category']]) && (int) $r['active'] === 1 && ($scope === null || (int) $r['id_parent'] === $scope) && $normal($r['name']) === $normal($alias)));
                if (count($matches) === 1) { $id = (int) $matches[0]['id_category']; break 2; }
            }
        }
    }
    $isNew = !$id;
    $category = $id ? new Category($id) : new Category();
    $changes[] = ['path' => $path, 'id' => $id ?: null, 'parent' => $parent, 'action' => $id ? 'reuse' : 'create'];
    if ($apply) {
        if (!$id) {
            $category->id = $nextId++;
            $category->force_id = true;
            $category->id_shop_default = $shop;
            foreach (Language::getLanguages(false) as $lang) {
                $category->name[(int) $lang['id_lang']] = $node['name'];
                $category->link_rewrite[(int) $lang['id_lang']] = $normal($node['name']);
            }
        } else {
            // Keep all existing slugs, translations, descriptions and SEO metadata.
            $category->name[$cs] = $node['name'];
        }
        $category->id_parent = $parent;
        $category->active = true;
        $category->position = $position;
        if (!($id ? $category->update() : $category->add())) { throw new RuntimeException('Category write failed: ' . $path); }
        $id = (int) $category->id;
        if ($isNew) {
            foreach (['PS_UNIDENTIFIED_GROUP', 'PS_GUEST_GROUP', 'PS_CUSTOMER_GROUP'] as $key) {
                if (!$db->insert('category_group', ['id_category' => $id, 'id_group' => (int) Configuration::get($key)], false, true, Db::INSERT_IGNORE)) { throw new RuntimeException('Group access failed.'); }
            }
        }
        $registry[$path] = $id;
        $changes[array_key_last($changes)]['id'] = $id;
    }
    // Negative plan-only IDs ensure new parents cannot accidentally match existing branches.
    $id = $id ?: -(count($changes) + 1);
    $used[$id] = true;
    foreach ($node['items'] as $index => $child) { $resolve($child, $id, $index, $path); }
};
if ($apply) { $db->execute('START TRANSACTION'); }
try {
    foreach ($definitions as $index => $node) { $resolve($node, $home, $index, ''); }
    if ($apply) {
        // Mixed historical categories remain reachable by their original URLs, outside the new Home tree.
        // No product associations or default product categories are removed or guessed.
        $legacyNames = ['PLACKY,ŠPENDLÍKY,PINY', 'ČEPICE , ŠÁTKY', 'TAŠKY,PENĚŽENKY', 'OBOJKY KRAVATY', 'KNIHY,FANZINY', 'HROTY,PYRAMIDY,CVOKY'];
        $root = (int) Configuration::get('PS_ROOT_CATEGORY');
        foreach ($rows as $row) {
            $id = (int) $row['id_category'];
            if (!isset($used[$id]) && (int) $row['active'] === 1 && in_array($normal($row['name']), array_map($normal, $legacyNames), true)) {
                if (!$db->update('category', ['id_parent' => $root], 'id_category=' . $id)) { throw new RuntimeException('Legacy preservation failed.'); }
            }
        }
        $setPositions = function (array $nodes, string $path = '') use (&$setPositions, $registry, $normal, $db, $shop) {
            foreach ($nodes as $position => $node) {
                $key = $path . ($path ? '/' : '') . $normal($node['name']);
                $id = (int) $registry[$key];
                if (!$db->update('category', ['position' => $position], 'id_category=' . $id)
                    || !$db->update('category_shop', ['position' => $position], 'id_category=' . $id . ' AND id_shop=' . $shop)) { throw new RuntimeException('Position failed.'); }
                $setPositions($node['items'], $key);
            }
        };
        $setPositions($definitions);
        Category::regenerateEntireNtree();
        if (!Configuration::updateValue('KAFKA_CLIENT_CATEGORY_TREE', json_encode($registry, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))) { throw new RuntimeException('Registry write failed.'); }
        $db->execute('COMMIT');
    }
} catch (Throwable $e) {
    if ($apply) { $db->execute('ROLLBACK'); }
    throw $e;
}
echo json_encode(['applied' => $apply, 'nodes' => count($changes), 'changes' => $changes, 'registry' => $registry], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
