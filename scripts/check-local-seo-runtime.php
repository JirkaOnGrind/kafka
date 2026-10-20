<?php
// Read-only native API probes; the sample EAN is passed only in memory, never saved as catalog data.
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['HTTP_HOST'] = 'localhost:8083'; $_SERVER['SERVER_NAME'] = 'localhost'; $_SERVER['REQUEST_URI'] = '/';
require '/var/www/html/config/config.inc.php';
if (Configuration::get('PS_SHOP_DOMAIN') !== 'localhost:8083' || _DB_SERVER_ !== 'target-db') { throw new RuntimeException('Not isolated target.'); }
$context = Context::getContext();
$row = Db::getInstance()->getRow('SELECT p.id_product,ps.cache_default_attribute FROM `' . _DB_PREFIX_ . 'product` p'
    . ' JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.id_product=p.id_product WHERE ps.active=1 AND ps.cache_default_attribute>0'
    . ' AND ps.id_shop=' . (int) $context->shop->id . ' ORDER BY p.id_product');
if (!$row) { throw new RuntimeException('No existing active variant product: report missing data, do not create a fixture.'); }
$dispatcher = Dispatcher::getInstance();
$route = $dispatcher->computeRoute(Configuration::get('PS_ROUTE_product_rule'), 'product', $dispatcher->default_routes['product_rule']['keywords']);
$checks = [];
foreach (Language::getLanguages(true, $context->shop->id) as $language) {
    $id = (int) $language['id_lang']; $iso = $language['iso_code'];
    $base = $context->link->getProductLink((int) $row['id_product'], null, null, null, $id, null, null, false, false, false, [], false);
    $ean = $context->link->getProductLink((int) $row['id_product'], null, null, '1234567890123', $id, null, null, false, false, false, [], false);
    $variant = $context->link->getProductLink((int) $row['id_product'], null, null, null, $id, null, (int) $row['cache_default_attribute']);
    $relative = preg_replace('#^/' . preg_quote($iso, '#') . '/#', '/', parse_url($ean, PHP_URL_PATH));
    $matches = [];
    $checks[$iso] = [
        'native_language_prefix' => str_starts_with(parse_url($base, PHP_URL_PATH), '/' . $iso . '/'),
        'no_empty_query_marker' => !str_ends_with($base, '?'),
        'optional_ean_generated_in_memory' => str_ends_with($ean, '-1234567890123.html'),
        'optional_ean_matches_native_route' => preg_match($route['regexp'], $relative, $matches) === 1 && (int) $matches['id_product'] === (int) $row['id_product'],
        'variant_query_retained' => str_contains($variant, 'id_product_attribute=' . $row['cache_default_attribute']),
        'base_url' => $base, 'variant_url' => $variant,
    ];
}
$confirmation = file_get_contents(_PS_THEME_DIR_ . 'templates/checkout/order-confirmation.tpl');
$result = ['read_only' => true, 'sample_ean_was_not_saved' => true, 'languages' => $checks,
    'module_sha256' => hash_file('sha256', _PS_MODULE_DIR_ . 'kafkaseo/kafkaseo.php'),
    'link_override_sha256' => hash_file('sha256', (new ReflectionMethod('Link', 'getProductLink'))->getFileName()),
    'confirmation_registration_block_removed' => (bool) preg_match('/\{block name=[\'\"]account_transformation_form[\'\"]\}\s*\{\/block\}/', $confirmation),
    'confirmation_template_sha256' => hash('sha256', $confirmation)];
$passed = $result['confirmation_registration_block_removed'];
foreach ($checks as $values) { foreach ($values as $value) { if (is_bool($value) && !$value) { $passed = false; } } }
$result['passed'] = $passed;
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
exit($passed ? 0 : 1);
