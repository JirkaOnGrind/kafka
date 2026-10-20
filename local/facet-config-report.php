<?php
require '/var/www/html/config/config.inc.php';
foreach(Db::getInstance()->executeS('SELECT id_layered_filter,name,filters FROM '._DB_PREFIX_.'layered_filter') as $r) { $r['filters']=Tools::unSerialize($r['filters']); echo json_encode($r,JSON_UNESCAPED_UNICODE),PHP_EOL; }
