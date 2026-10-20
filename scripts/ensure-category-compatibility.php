<?php
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['HTTP_HOST'] = 'localhost:8083'; $_SERVER['SERVER_NAME'] = 'localhost'; $_SERVER['REQUEST_URI'] = '/';
require '/var/www/html/config/config.inc.php';
if (Configuration::get('PS_SHOP_DOMAIN') !== 'localhost:8083' || _DB_SERVER_ !== 'target-db') { throw new RuntimeException('Only isolated target.'); }
$module = Module::getInstanceByName('kafkahome');
if (!$module || !$module->active || !$module->registerHook('actionFrontControllerInitBefore')) { throw new RuntimeException('Category compatibility hook unavailable.'); }
if (!$module->registerHook('displayKafkaCustomProduction')) { throw new RuntimeException('Custom production hook unavailable.'); }
if (!Module::upgradeModuleVersion($module->name, $module->version)) { throw new RuntimeException('Cannot record project module version.'); }
echo json_encode(['module' => 'kafkahome', 'hook_ready' => true, 'vendor_review_module_unchanged' => true]), PHP_EOL;
