<?php
if (PHP_SAPI !== 'cli') { exit(1); }
// Local-only, idempotent registration. No catalog or URL changes.
require '/var/www/html/config/config.inc.php';
if (!in_array(Context::getContext()->shop->domain, ['localhost:8083', 'localhost:8080'], true)) {
    throw new RuntimeException('Local shops only.');
}
$module = Module::getInstanceByName('kafkahome');
if (!$module || !$module->registerHook('displayKafkaCustomProduction')) { throw new RuntimeException('Hook registration failed'); }
Tools::clearAllCache();
echo "Custom production hook registered; template cache cleared.\n";
