<?php
// Execute before Apache starts, only in an isolated restore container.
$path = '/var/www/html/app/config/parameters.php';
$config = require $path;
$p = &$config['parameters'];
$p['database_host'] = 'db';
$p['database_port'] = '';
$p['database_name'] = 'kafka';
$p['database_user'] = 'kafka';
$p['database_password'] = getenv('DB_PASSWD');
file_put_contents($path, '<?php return ' . var_export($config, true) . ';');
$db = new PDO('mysql:host=db;dbname=kafka;charset=utf8mb4', 'kafka', getenv('DB_PASSWD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$prefix = $p['database_prefix'];
if (!preg_match('/^[a-zA-Z0-9_]+$/', $prefix)) { throw new RuntimeException('Invalid table prefix'); }
$db->exec("UPDATE `{$prefix}shop_url` SET domain='localhost:8081', domain_ssl='localhost:8081', physical_uri='/'");
$statement = $db->prepare("UPDATE `{$prefix}configuration` SET value=? WHERE name=?");
foreach (['PS_SHOP_DOMAIN'=>'localhost:8081', 'PS_SHOP_DOMAIN_SSL'=>'localhost:8081', 'PS_MAIL_METHOD'=>'3', 'PS_SSL_ENABLED'=>'0', 'PS_SSL_ENABLED_EVERYWHERE'=>'0', 'PS_SMARTY_CACHE'=>'0', 'PS_SMARTY_FORCE_COMPILE'=>'2', 'PS_CSS_THEME_CACHE'=>'0', 'PS_JS_THEME_CACHE'=>'0'] as $name=>$value) {
    $statement->execute([$value, $name]);
}
echo json_encode(['products'=>(int)$db->query("SELECT COUNT(*) FROM `{$prefix}product`")->fetchColumn(), 'theme'=>$db->query("SELECT theme_name FROM `{$prefix}shop` LIMIT 1")->fetchColumn(), 'mail_disabled'=>true, 'domain'=>'localhost:8081']), PHP_EOL;
