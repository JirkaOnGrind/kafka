<?php
// Read-only, public catalog/SEO fields only. Never exports customers or configuration secrets.
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['HTTP_HOST'] = 'localhost:8083';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require '/var/www/html/config/config.inc.php';
if (Configuration::get('PS_SHOP_DOMAIN') !== 'localhost:8083') {
    throw new RuntimeException('SEO export is restricted to the isolated migration target.');
}
$db = Db::getInstance();
$shop = (int) Context::getContext()->shop->id;
$link = Context::getContext()->link;
$out = ['generated_at' => gmdate('c'), 'languages' => Language::getLanguages(false), 'configuration' => [], 'rows' => []];
$visitorGroup = (int) Configuration::get('PS_UNIDENTIFIED_GROUP');
$publicCategories = array_map('intval', array_column($db->executeS('SELECT c.id_category FROM `' . _DB_PREFIX_ . 'category` c'
    . ' JOIN `' . _DB_PREFIX_ . 'category_shop` cs ON cs.id_category=c.id_category AND cs.id_shop=' . $shop
    . ' JOIN `' . _DB_PREFIX_ . 'category_group` cg ON cg.id_category=c.id_category AND cg.id_group=' . $visitorGroup
    . ' WHERE c.active=1'), 'id_category'));
foreach (['PS_REWRITING_SETTINGS', 'PS_LANG_DEFAULT', 'PS_DEFAULT_LANGUAGE_URL_PREFIX', 'PS_CANONICAL_REDIRECT', 'PS_SHOP_NAME',
    'PS_ROUTE_product_rule', 'PS_ROUTE_category_rule', 'PS_ROUTE_cms_rule', 'PS_ROUTE_cms_category_rule',
    'PS_ROUTE_supplier_rule', 'PS_ROUTE_manufacturer_rule'] as $key) {
    $out['configuration'][$key] = Configuration::get($key);
}
$db->execute('START TRANSACTION READ ONLY');
foreach ($out['languages'] as $language) {
    $idLang = (int) $language['id_lang'];
    $iso = $language['iso_code'];
    $products = $db->executeS('SELECT p.id_product, ps.active, ps.visibility, ps.id_category_default, ps.cache_default_attribute, p.ean13,'
        . ' pl.name,pl.link_rewrite,pl.meta_title,pl.meta_description,cl.link_rewrite category_slug'
        . ' FROM `' . _DB_PREFIX_ . 'product` p JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.id_product=p.id_product AND ps.id_shop=' . $shop
        . ' JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.id_product=p.id_product AND pl.id_shop=ps.id_shop AND pl.id_lang=' . $idLang
        . ' LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.id_category=ps.id_category_default AND cl.id_shop=ps.id_shop AND cl.id_lang=pl.id_lang');
    foreach ($products as $row) {
        $row['url'] = $link->getProductLink((int) $row['id_product'], $row['link_rewrite'], $row['category_slug'], $row['ean13'], $idLang, $shop, null, false, false, false, [], false);
        $row['entity'] = 'product'; $row['id'] = (int) $row['id_product']; $row['language'] = $iso;
        $row['eligible'] = (bool) $language['active'] && (bool) $row['active'] && in_array($row['visibility'], ['both', 'catalog'], true)
            && in_array((int) $row['id_category_default'], $publicCategories, true)
            && trim((string) $row['link_rewrite']) !== '' && trim((string) $row['name']) !== '' && trim((string) $row['category_slug']) !== '';
        $out['rows'][] = $row;
    }
    foreach (['category', 'cms', 'manufacturer', 'supplier', 'cms_category'] as $entity) {
        $pk = 'id_' . $entity;
        $select = $entity === 'cms' ? 'cl.meta_title name,cl.link_rewrite,cl.meta_title,cl.meta_description'
            : (in_array($entity, ['manufacturer', 'supplier']) ? 'c.name,cl.meta_title,cl.meta_description'
            : 'cl.name,cl.link_rewrite,cl.meta_title,cl.meta_description');
        $joinShop = in_array($entity, ['category', 'cms', 'cms_category']) ? ' AND cl.id_shop=' . $shop : '';
        $extra = $entity === 'cms' ? ',c.indexation' : '';
        $rows = $db->executeS('SELECT c.' . $pk . ',c.active' . $extra . ',' . $select . ' FROM `' . _DB_PREFIX_ . $entity . '` c'
            . ' JOIN `' . _DB_PREFIX_ . $entity . '_lang` cl ON cl.' . $pk . '=c.' . $pk . ' AND cl.id_lang=' . $idLang . $joinShop);
        foreach ($rows as $row) {
            $id = (int) $row[$pk];
            if ($entity === 'category') { $url = $link->getCategoryLink($id, $row['link_rewrite'], $idLang); }
            elseif ($entity === 'cms') { $url = $link->getCMSLink($id, $row['link_rewrite'], null, $idLang); }
            elseif ($entity === 'cms_category') { $url = $link->getCMSCategoryLink($id, $row['link_rewrite'], $idLang); }
            elseif ($entity === 'manufacturer') { $url = $link->getManufacturerLink($id, null, $idLang); }
            else { $url = $link->getSupplierLink($id, null, $idLang); }
            $row['url'] = $url; $row['entity'] = $entity; $row['id'] = $id; $row['language'] = $iso;
            $associated = in_array($entity, ['category', 'cms', 'manufacturer', 'supplier'], true)
                ? (bool) $db->getValue('SELECT 1 FROM `' . _DB_PREFIX_ . $entity . '_shop` WHERE ' . $pk . '=' . $id . ' AND id_shop=' . $shop) : true;
            $row['eligible'] = (bool) $language['active'] && (bool) $row['active'] && $associated
                && ($entity !== 'category' || ($id > 2 && in_array($id, $publicCategories, true)))
                && ($entity !== 'cms' || (bool) $row['indexation']) && $entity !== 'cms_category'
                && (!isset($row['link_rewrite']) || trim((string) $row['link_rewrite']) !== '')
                && trim((string) $row['name']) !== '';
            $out['rows'][] = $row;
        }
    }
    $metas = $db->executeS('SELECT m.page,ml.title,ml.description,ml.url_rewrite FROM `' . _DB_PREFIX_ . 'meta` m'
        . ' JOIN `' . _DB_PREFIX_ . 'meta_lang` ml ON ml.id_meta=m.id_meta AND ml.id_lang=' . $idLang . ' AND ml.id_shop=' . $shop);
    foreach ($metas as $row) {
        if (str_starts_with($row['page'], 'module-')) { continue; }
        $row['url'] = $link->getPageLink($row['page'], null, $idLang);
        $row['entity'] = 'system'; $row['id'] = $row['page']; $row['language'] = $iso; $row['active'] = 1;
        $row['eligible'] = (bool) $language['active'] && $row['page'] === 'index';
        $out['rows'][] = $row;
    }
}
$db->execute('ROLLBACK');
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
