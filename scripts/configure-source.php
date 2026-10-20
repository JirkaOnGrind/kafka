<?php
// Do not bootstrap the historical shop until its database has been neutralized.
require '/var/www/html/config/settings.inc.php';
if (_DB_SERVER_ !== 'db' || _DB_NAME_ !== 'kafka_source' || _DB_USER_ !== 'kafka_source') {
    throw new RuntimeException('Source DB must be the isolated clone.');
}
if (!preg_match('/^[a-zA-Z0-9_]+$/', _DB_PREFIX_)) { throw new RuntimeException('Invalid prefix'); }
$db = new PDO('mysql:host=db;dbname=kafka_source;charset=utf8', _DB_USER_, _DB_PASSWD_, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$prefix = _DB_PREFIX_;
$db->exec("UPDATE `{$prefix}shop_url` SET domain='localhost:8082', domain_ssl='localhost:8082', physical_uri='/'");
$db->exec("UPDATE `{$prefix}module` SET active=0");
// Preserve safe presentation modules to make the archived catalogue inspectable.
$db->exec("UPDATE `{$prefix}module` SET active=1 WHERE name IN ('blockcategories','blocksearch','blockcart','blockuserinfo','blocktopmenu','blockcms','blockcontact','blockcontactinfos','blockcurrencies','blocklanguages','homefeatured','homeslider','blocknewproducts','blockspecials','blockbestsellers','themeconfigurator','productcomments','blockreassurance','graphnvd3')");
$query = $db->prepare("UPDATE `{$prefix}configuration` SET value=? WHERE name=?");
foreach (['PS_SHOP_DOMAIN'=>'localhost:8082','PS_SHOP_DOMAIN_SSL'=>'localhost:8082','PS_MAIL_METHOD'=>'3','PS_SSL_ENABLED'=>'0','PS_SSL_ENABLED_EVERYWHERE'=>'0','PS_DISABLE_OVERRIDES'=>'1','PS_SMARTY_CACHE'=>'0','PS_SMARTY_FORCE_COMPILE'=>'2','PS_CIPHER_ALGORITHM'=>'0','PS_SHOP_ENABLE'=>'1','PS_REWRITING_SETTINGS'=>'0','PS_ORDER_PROCESS_TYPE'=>'0'] as $key=>$value) { $query->execute([$value,$key]); }
// Do not retain copied production API/SMTP credentials in clone configuration.
$db->exec("UPDATE `{$prefix}configuration` SET value='' WHERE name REGEXP 'SMTP|API.*(KEY|TOKEN|SECRET)|COMGATE|MERGADO|ZASILKOVNA|PACKETA|DPD|PASSWORD|PASSWD|SECRET'");
$query->execute(['3','PS_MAIL_METHOD']);
$password = getenv('SOURCE_ADMIN_PASSWORD');
if (!$password || strlen($password) < 24) { throw new RuntimeException('Missing local clone admin password'); }
$employee = $db->query("SELECT id_employee FROM `{$prefix}employee` WHERE active=1 AND id_profile=1 ORDER BY id_employee LIMIT 1")->fetchColumn();
if (!$employee) { throw new RuntimeException('No source administrator found'); }
$admin = $db->prepare("UPDATE `{$prefix}employee` SET email='admin@kafka-source.local', passwd=?, last_passwd_gen=DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id_employee=?");
$admin->execute([md5(_COOKIE_KEY_ . $password), $employee]);
// Remove only generated class indices so restored core classes are indexed anew.
foreach (['class_index.php','class_stub.php'] as $file) {
    $generated = '/var/www/html/cache/' . $file;
    if (is_file($generated) && !unlink($generated)) { throw new RuntimeException('Cannot rebuild source class cache'); }
}
echo json_encode(['php'=>PHP_VERSION,'version'=>_PS_VERSION_,'products'=>(int)$db->query("SELECT COUNT(*) FROM `{$prefix}product`")->fetchColumn(),'orders'=>(int)$db->query("SELECT COUNT(*) FROM `{$prefix}orders`")->fetchColumn(),'active_modules'=>(int)$db->query("SELECT COUNT(*) FROM `{$prefix}module` WHERE active=1")->fetchColumn(),'mail_disabled'=>true,'domain'=>'localhost:8082']), PHP_EOL;
