<?php
// Aggregate, read-only checks. Never print customer fields, credentials or order contents.
if (is_file('/var/www/html/app/config/parameters.php')) {
    $config = require '/var/www/html/app/config/parameters.php';
    $p = $config['parameters'];
    $host=$p['database_host']; $name=$p['database_name']; $user=$p['database_user']; $password=$p['database_password']; $prefix=$p['database_prefix'];
} else {
    require '/var/www/html/config/settings.inc.php';
    $host=_DB_SERVER_; $name=_DB_NAME_; $user=_DB_USER_; $password=_DB_PASSWD_; $prefix=_DB_PREFIX_;
}
if ($host !== 'db' || !in_array($name, ['kafka','kafka_source'], true) || !preg_match('/^[a-zA-Z0-9_]+$/', $prefix)) {
    throw new RuntimeException('Migration checks are allowed only on the isolated local databases.');
}
$db = new PDO("mysql:host=db;dbname=$name;charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('START TRANSACTION READ ONLY');
$result = ['counts'=>[], 'fingerprints'=>[]];
$specs = [
    'product'=>['id_product','id_category_default','active','price'],
    'product_attribute'=>['id_product_attribute','id_product','price','default_on'],
    'category'=>['id_category','id_parent','active'],
    'customer'=>['id_customer','id_default_group','is_guest','active'],
    'address'=>['id_address','id_customer','id_country','deleted'],
    'orders'=>['id_order','id_customer','id_currency','current_state','total_paid','total_products','total_shipping','date_add'],
    'order_detail'=>['id_order_detail','id_order','product_id','product_attribute_id','product_quantity','total_price_tax_incl','total_price_tax_excl'],
    'order_invoice'=>['id_order_invoice','id_order','number','total_paid_tax_incl','date_add'],
    'order_payment'=>['id_order_payment','id_currency','amount','date_add'],
    'image'=>['id_image','id_product'],
    'attachment'=>['id_attachment'],
    'stock_available'=>['id_stock_available','id_product','id_product_attribute','id_shop','quantity'],
];
foreach ($specs as $table=>$columns) {
    $result['counts'][$table] = (int)$db->query("SELECT COUNT(*) FROM `{$prefix}{$table}`")->fetchColumn();
    $hash = hash_init('sha256');
    $quoted = '`' . implode('`,`', $columns) . '`';
    $rows = $db->query("SELECT $quoted FROM `{$prefix}{$table}` ORDER BY `{$columns[0]}`");
    while ($row = $rows->fetch(PDO::FETCH_NUM)) {
        $normalized = array_map(function($value) {
            if ($value !== null && preg_match('/^-?\d+\.\d+$/', $value)) { return rtrim(rtrim($value, '0'), '.'); }
            return $value;
        }, $row);
        hash_update($hash, json_encode($normalized) . "\n");
    }
    $result['fingerprints'][$table] = hash_final($hash);
}
$result['active_products'] = (int)$db->query("SELECT COUNT(*) FROM `{$prefix}product` WHERE active=1")->fetchColumn();
$result['orphan_combinations'] = (int)$db->query("SELECT COUNT(*) FROM `{$prefix}product_attribute` a LEFT JOIN `{$prefix}product` p ON p.id_product=a.id_product WHERE p.id_product IS NULL")->fetchColumn();
$result['historical_lines_without_product'] = (int)$db->query("SELECT COUNT(*) FROM `{$prefix}order_detail` d LEFT JOIN `{$prefix}product` p ON p.id_product=d.product_id WHERE p.id_product IS NULL")->fetchColumn();
$result['order_totals_by_currency'] = $db->query("SELECT id_currency, COUNT(*) AS orders, CAST(ROUND(SUM(total_paid),2) AS CHAR) AS total_paid FROM `{$prefix}orders` GROUP BY id_currency ORDER BY id_currency")->fetchAll(PDO::FETCH_ASSOC);
$result['mail_disabled'] = $db->query("SELECT COUNT(*) FROM `{$prefix}configuration` WHERE name='PS_MAIL_METHOD' AND value <> '3'")->fetchColumn() == 0;
$db->exec('ROLLBACK');
echo json_encode($result, JSON_PRETTY_PRINT), PHP_EOL;
