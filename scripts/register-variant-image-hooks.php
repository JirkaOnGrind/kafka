<?php
if (PHP_SAPI !== 'cli') { exit(1); }
require '/var/www/html/config/config.inc.php';
if (Configuration::get('PS_SHOP_DOMAIN') !== 'localhost:8083' || _DB_SERVER_ !== 'target-db') {
    throw new RuntimeException('Isolated target only.');
}
$module = Module::getInstanceByName('kafkahome');
foreach (['actionPresentProduct', 'actionPresentProductListing', 'actionPresentCartProduct'] as $hook) {
    if (!$module || !$module->registerHook($hook)) { throw new RuntimeException('Variant hook registration failed.'); }
}
Tools::clearAllCache();
echo "Native variant image presentation registered.\n";
