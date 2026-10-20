<?php
if (PHP_SAPI !== 'cli') { exit(1); }
require '/var/www/html/config/config.inc.php';
if (Configuration::get('PS_SHOP_DOMAIN') !== 'localhost:8083' || _DB_SERVER_ !== 'target-db') {
    throw new RuntimeException('Isolated target only.');
}
$module = Module::getInstanceByName('kafkahome');
foreach (['additionalCustomerFormFields', 'additionalCustomerAddressFields'] as $hook) {
    if (!$module || !$module->registerHook($hook)) { throw new RuntimeException('Form hook registration failed.'); }
}
Configuration::updateValue('PS_CUSTOMER_OPTIN', false);
Tools::clearAllCache();
echo "Native form hooks registered; partner offers disabled; caches cleared.\n";
