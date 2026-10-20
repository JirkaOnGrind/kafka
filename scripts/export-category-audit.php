<?php
// Read-only catalog data only. No customers, orders, credentials or shop configuration export.
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['HTTP_HOST'] = 'localhost:8083'; $_SERVER['SERVER_NAME'] = 'localhost'; $_SERVER['REQUEST_URI'] = '/';
require '/var/www/html/config/config.inc.php';
if (Configuration::get('PS_SHOP_DOMAIN') !== 'localhost:8083' || _DB_SERVER_ !== 'target-db') { throw new RuntimeException('Only isolated target.'); }
$db = Db::getInstance(); $shop = (int) Context::getContext()->shop->id; $cs = (int) Language::getIdByIso('cs');
$db->execute('START TRANSACTION READ ONLY');
$categories = $db->executeS('SELECT c.id_category,c.id_parent,c.active,c.nleft,c.nright,cl.name,cl.link_rewrite,cl.meta_title,cl.meta_description FROM `' . _DB_PREFIX_ . 'category` c JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.id_category=c.id_category WHERE cl.id_lang=' . $cs . ' AND cl.id_shop=' . $shop . ' ORDER BY c.id_category');
$products = $db->executeS('SELECT p.id_product,ps.active,ps.visibility,ps.id_category_default,pl.name,pl.link_rewrite,pl.description_short,pl.description,pl.meta_title,pl.meta_description FROM `' . _DB_PREFIX_ . 'product` p JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.id_product=p.id_product JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.id_product=p.id_product AND pl.id_shop=ps.id_shop WHERE ps.id_shop=' . $shop . ' AND pl.id_lang=' . $cs . ' ORDER BY p.id_product');
$links = $db->executeS('SELECT id_category,id_product,position FROM `' . _DB_PREFIX_ . 'category_product` ORDER BY id_category,id_product');
$images = $db->executeS('SELECT id_product,id_image FROM `' . _DB_PREFIX_ . 'image` WHERE cover=1');
$registry = json_decode(Configuration::get('KAFKA_CLIENT_CATEGORY_TREE'), true, 512, JSON_THROW_ON_ERROR);
$fingerprints = [];
foreach (['product' => 'id_product', 'product_shop' => 'id_product,id_shop', 'product_lang' => 'id_product,id_shop,id_lang',
    'category' => 'id_category', 'category_lang' => 'id_category,id_shop,id_lang', 'category_shop' => 'id_category,id_shop',
    'category_group' => 'id_category,id_group', 'category_product' => 'id_category,id_product'] as $table => $order) {
    $fingerprints[$table] = hash('sha256', json_encode($db->executeS('SELECT * FROM `' . _DB_PREFIX_ . $table . '` ORDER BY ' . $order), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}
$db->execute('ROLLBACK');
echo json_encode(['generated_at' => gmdate('c'), 'categories' => $categories, 'products' => $products, 'links' => $links, 'covers' => $images, 'registry' => $registry, 'fingerprints' => $fingerprints], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
