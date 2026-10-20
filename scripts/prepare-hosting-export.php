<?php
// CLI only. Writes export helpers to /tmp, never changes the source shop or DB.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require '/var/www/html/config/config.inc.php';
$prefix = _DB_PREFIX_;
if (!preg_match('/^[a-zA-Z0-9_]+$/', $prefix)) { throw new RuntimeException('Invalid prefix'); }
$products = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . $prefix . 'product`');
if (_PS_VERSION_ !== '9.1.5' || $products !== 19) {
    throw new RuntimeException('Expected PS 9.1.5 with 19 demo products; inspect source before exporting.');
}
$config = require '/var/www/html/app/config/parameters.php';
$p = &$config['parameters'];
$p['database_host'] = 'DOPLNIT_DB_SERVER';
$p['database_port'] = '';
$p['database_name'] = 'jirkanetrhcz02';
$p['database_user'] = 'DOPLNIT_DB_UZIVATELE';
$p['database_password'] = 'DOPLNIT_DB_HESLO';
foreach (['mailer_host','mailer_user','mailer_password'] as $key) { $p[$key] = ''; }
$p['mailer_transport'] = 'smtp';
$p['secret'] = bin2hex(random_bytes(32));
$p['cookie_key'] = bin2hex(random_bytes(32));
$p['cookie_iv'] = bin2hex(random_bytes(8));
if (isset($p['new_cookie_key'])) {
    $p['new_cookie_key'] = \Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString();
}
file_put_contents('/tmp/kafka-hosting-parameters.php', '<?php return ' . var_export($config, true) . ';' . PHP_EOL);
$password = 'KafkaTest!' . bin2hex(random_bytes(16));
$hash = password_hash($password, PASSWORD_BCRYPT);
file_put_contents('/tmp/kafka-hosting-login.txt', "Testovací administrace: https://kafka.jirkanetrh.cz/admin-dev/\nE-mail: admin@kafka-test.invalid\nHeslo: " . $password . "\nPouze soukromě, nenahrávat na FTP.\n");
$sql = "\n-- Test target only. Appended to the demo source export.\n";
$sql .= "UPDATE `{$prefix}shop_url` SET domain='kafka.jirkanetrh.cz', domain_ssl='kafka.jirkanetrh.cz', physical_uri='/';\n";
$settings = ['PS_SHOP_DOMAIN'=>'kafka.jirkanetrh.cz','PS_SHOP_DOMAIN_SSL'=>'kafka.jirkanetrh.cz','PS_SHOP_NAME'=>'Kafka Monster — TEST','PS_SHOP_EMAIL'=>'admin@kafka-test.invalid','PS_MAIL_METHOD'=>'3','PS_SHOP_ENABLE'=>'0','PS_MAINTENANCE_IP'=>'','PS_SSL_ENABLED'=>'1','PS_SSL_ENABLED_EVERYWHERE'=>'1','PS_SMARTY_CACHE'=>'0','PS_CSS_THEME_CACHE'=>'0','PS_JS_THEME_CACHE'=>'0'];
foreach ($settings as $name=>$value) {
    $sql .= "UPDATE `{$prefix}configuration` SET value='" . pSQL($value) . "' WHERE name='" . $name . "';\n";
}
$sql .= "UPDATE `{$prefix}configuration` SET value='' WHERE name IN ('PS_MAIL_SERVER','PS_MAIL_USER','PS_MAIL_PASSWD');\n";
$sql .= "UPDATE `{$prefix}employee` SET active=0;\n";
$sql .= "UPDATE `{$prefix}employee` SET active=1,email='admin@kafka-test.invalid',passwd='" . pSQL($hash) . "' WHERE id_employee=(SELECT id FROM (SELECT MIN(id_employee) AS id FROM `{$prefix}employee`) AS chosen);\n";
$sql .= "UPDATE `{$prefix}module` SET active=0 WHERE name IN ('ps_checkpayment','ps_wirepayment','ps_cashondelivery','ps_checkout','ps_emailsubscription','ps_reminder','cronjobs');\n";
$sql .= "DELETE ms FROM `{$prefix}module_shop` ms JOIN `{$prefix}module` m ON m.id_module=ms.id_module WHERE m.active=0;\n";
file_put_contents('/tmp/kafka-hosting-tail.sql', $sql);
echo json_encode(['version'=>_PS_VERSION_,'products'=>$products,'theme'=>Db::getInstance()->getValue('SELECT theme_name FROM `' . $prefix . 'shop`'),'source_unchanged'=>true]), PHP_EOL;
