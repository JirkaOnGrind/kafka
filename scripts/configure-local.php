<?php
// Run only inside this project's isolated Docker shop after installation.
require '/var/www/html/config/config.inc.php';
if (Configuration::get('PS_SHOP_DOMAIN') !== 'localhost:8080') {
    throw new RuntimeException('Refusing to configure a shop outside localhost:8080.');
}
Configuration::updateValue('PS_SHOP_NAME', 'Kafka Monster — lokální vývoj');
Configuration::updateValue('PS_SHOP_EMAIL', 'admin@kafka.local');
Configuration::updateValue('PS_MAIL_METHOD', 3); // Never send customer emails locally.
Configuration::updateValue('PS_SMARTY_CACHE', 0);
Configuration::updateValue('PS_SMARTY_FORCE_COMPILE', 2);
Configuration::updateValue('PS_CSS_THEME_CACHE', 0);
Configuration::updateValue('PS_JS_THEME_CACHE', 0);

$kafkaHome = Module::getInstanceByName('kafkahome');
if (!$kafkaHome) {
    throw new RuntimeException('Kafka homepage module is missing from the project.');
}
if (!Module::isInstalled('kafkahome') && !$kafkaHome->install()) {
    throw new RuntimeException('Kafka homepage module could not be installed.');
}
foreach (['displayKafkaCategories', 'displayKafkaNavigation', 'displayKafkaTrust', 'displayKafkaFooter', 'displayKafkaCustomProduction', 'actionFrontControllerInitBefore'] as $hook) {
    if (!$kafkaHome->registerHook($hook)) {
        throw new RuntimeException('Kafka homepage hook could not be registered.');
    }
}
echo 'Local shop configured; customer email sending disabled.', PHP_EOL;
