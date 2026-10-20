<?php
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['HTTP_HOST'] = 'localhost:8083';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require '/var/www/html/config/config.inc.php';
if (Configuration::get('PS_SHOP_DOMAIN') !== 'localhost:8083' || _DB_SERVER_ !== 'target-db') { throw new RuntimeException('Not isolated target.'); }
Context::getContext()->smarty->clearCompiledTemplate();
