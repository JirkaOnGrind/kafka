<?php
/**
 * Copyright ETS Software Technology Co., Ltd
 *
 * NOTICE OF LICENSE
 *
 * This file is not open source! Each license that you purchased is only available for 1 website only.
 * If you want to use this file on more websites (or projects), you need to purchase additional licenses.
 * You are not allowed to redistribute, resell, lease, license, sub-license or offer our resources to any third party.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future.
 *
 * @author ETS Software Technology Co., Ltd
 * @copyright  ETS Software Technology Co., Ltd
 * @license    Valid for 1 website (or project) for each purchase of license
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class EtsMigrateTools
{
    protected static $shop_share_context = [];
    protected static $table_exists = [];
    protected static $column_exists = [];
    protected static $index_exists = [];
    protected static $describe_table = [];
    protected static $module_enabled = [];

    public static function clearSchemaCache()
    {
        self::$table_exists = [];
        self::$column_exists = [];
        self::$index_exists = [];
        self::$describe_table = [];
    }

    public static function queryTable($table, $countOnly = true, $select = null, $offset = 0, $limit = 0, $operators = [], $firstRowOnly = false)
    {
        $invalidResult = ($countOnly || $firstRowOnly) ? 0 : false;
        if (!$table || !is_string($table) || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return $invalidResult;
        }

        if (!$countOnly) {
            if ($select === null || (is_string($select) && trim($select) === '')) {
                $select = '*';
            } elseif (!is_string($select) || !preg_match('/^[a-zA-Z0-9_`,\.\s\*]+$/', $select)) {
                return $invalidResult;
            }
        }

        if (!is_array($operators)) {
            return $invalidResult;
        }
        foreach ($operators as $operator) {
            if (!is_string($operator)
                || $operator === ''
                || !preg_match('/^[a-zA-Z0-9_`\.\s=<>\'\-]+$/', $operator)
                || preg_match('/(--|\/\*|\bUNION\b)/i', $operator)
            ) {
                return $invalidResult;
            }
        }

        $dq = new DbQuery();
        $dq->from($table);

        if ($countOnly) {
            $dq->select('COUNT(*)');
        } else {
            $dq->select($select);
        }

        foreach ($operators as $operator) {
            $dq->where($operator);
        }

        if ($countOnly || $firstRowOnly) {
            $value = Db::getInstance()->getValue($dq);
            if ($value === false) {
                $message = Db::getInstance()->getMsgError();
                if (is_string($message) && $message !== '') {
                    return false;
                }

                return 0;
            }

            return (int) $value;
        }

        if ($limit) {
            $dq->limit($limit, $offset);
        }

        return Db::getInstance()->executeS($dq);
    }

    public static function tableExists($table)
    {
        if (!$table || !is_string($table) || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return false;
        }
        if (array_key_exists($table, self::$table_exists)) {
            return self::$table_exists[$table];
        }

        $rows = Db::getInstance()->executeS('SHOW TABLES LIKE \'' . _DB_PREFIX_ . bqSQL($table) . '\'');
        if ($rows === false) {
            return false;
        }

        self::$table_exists[$table] = $rows;

        return $rows;
    }

    public static function columnExists($table, $column)
    {
        if (!$column || !is_string($column) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            return false;
        }
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, self::$column_exists)) {
            return self::$column_exists[$cacheKey];
        }
        $tableRows = self::tableExists($table);
        if (!$tableRows) {
            if (!array_key_exists($table, self::$table_exists)) {
                return false;
            }
            self::$column_exists[$cacheKey] = false;

            return false;
        }

        $rows = Db::getInstance()->executeS('SHOW COLUMNS FROM `' . _DB_PREFIX_ . bqSQL($table) . '` LIKE \'' . pSQL($column) . '\'');
        if ($rows === false) {
            return false;
        }

        self::$column_exists[$cacheKey] = $rows;

        return $rows;
    }

    public static function describeTable($table)
    {
        if (!$table || !is_string($table) || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return false;
        }
        if (array_key_exists($table, self::$describe_table)) {
            return self::$describe_table[$table];
        }
        $tableRows = self::tableExists($table);
        if (!$tableRows) {
            if (!array_key_exists($table, self::$table_exists)) {
                return false;
            }
            self::$describe_table[$table] = false;

            return false;
        }

        $rows = Db::getInstance()->executeS('DESCRIBE `' . _DB_PREFIX_ . bqSQL($table) . '`');
        if ($rows === false) {
            return false;
        }

        self::$describe_table[$table] = $rows;

        return $rows;
    }

    protected static function logDbFailure($context)
    {
        $message = Db::getInstance()->getMsgError();
        if (!is_string($message) || $message === '') {
            return false;
        }
        $logMessage = $context . ': ' . $message;
        if (class_exists('EtsMigrateDataImport', false) && method_exists('EtsMigrateDataImport', 'writeLog')) {
            EtsMigrateDataImport::writeLog($logMessage, 'ERROR');
        } else {
            error_log('ets_migrate: ' . $logMessage);
        }

        return false;
    }

    protected static function executeOrLog($sql, $context)
    {
        if (Db::getInstance()->execute($sql)) {
            return true;
        }

        return self::logDbFailure($context);
    }

    public static function indexExists($table, $index)
    {
        if (!$table || !is_string($table) || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return false;
        }
        if (!$index || !is_string($index) || !preg_match('/^[a-zA-Z0-9_]+$/', $index)) {
            return false;
        }
        $cacheKey = $table . '.' . $index;
        if (array_key_exists($cacheKey, self::$index_exists)) {
            return self::$index_exists[$cacheKey];
        }
        if (!self::tableExists($table)) {
            if (!array_key_exists($table, self::$table_exists)) {
                return false;
            }
            self::$index_exists[$cacheKey] = false;

            return false;
        }
        $indexes = Db::getInstance()->executeS('SHOW INDEX FROM `' . _DB_PREFIX_ . bqSQL($table) . '`');
        if ($indexes === false) {
            return false;
        }
        if (!$indexes) {
            self::$index_exists[$cacheKey] = false;

            return false;
        }
        foreach ($indexes as $row) {
            if ($row['Key_name'] === $index) {
                $found = [$row];
                self::$index_exists[$cacheKey] = $found;

                return $found;
            }
        }
        self::$index_exists[$cacheKey] = false;

        return false;
    }

    public static function isModuleEnabled($moduleName)
    {
        if (!is_string($moduleName) || trim($moduleName) === '' || !Validate::isModuleName($moduleName)) {
            return false;
        }
        if (array_key_exists($moduleName, self::$module_enabled)) {
            return self::$module_enabled[$moduleName];
        }
        $module = Module::getInstanceByName($moduleName);
        if (!Validate::isLoadedObject($module)) {
            self::$module_enabled[$moduleName] = false;

            return false;
        }
        $enabled = (int) $module->active === 1;
        self::$module_enabled[$moduleName] = $enabled;

        return $enabled;
    }

    public static function quickSort($list, $field = 'position')
    {
        if (!is_array($list) || count($list) <= 1) {
            return $list;
        }
        uasort($list, function ($leftItem, $rightItem) use ($field) {
            if ($leftItem[$field] == $rightItem[$field]) {
                return 0;
            }

            return ($leftItem[$field] < $rightItem[$field]) ? -1 : 1;
        });

        return $list;
    }

    public static function clearShopShareContextCache()
    {
        self::$shop_share_context = [];
    }

    public static function shopShare($id_shop = 0, $context = null)
    {
        $id_shop = (int) $id_shop;
        if ($id_shop <= 0 && isset($context->shop) && Validate::isLoadedObject($context->shop)) {
            $id_shop = (int) $context->shop->id;
        }
        if ($id_shop <= 0) {
            $id_shop = (int) Configuration::get('PS_SHOP_DEFAULT');
        }
        if ($id_shop <= 0 && self::tableExists('shop')) {
            $id_shop = (int) Db::getInstance()->getValue('SELECT `id_shop` FROM `' . _DB_PREFIX_ . 'shop` ORDER BY `id_shop` ASC');
        }
        if ($id_shop <= 0) {
            return [
                'id_shop' => 0,
                'id_shop_group' => 0,
                'share_customer' => 0,
                'share_stock' => 0,
                'share_order' => 0,
            ];
        }
        if (!isset(self::$shop_share_context[$id_shop])) {
            $row = Db::getInstance()->getRow('
                SELECT s.`id_shop`, s.`id_shop_group`, sg.`share_customer`, sg.`share_stock`, sg.`share_order`
                FROM `' . _DB_PREFIX_ . 'shop` s
                LEFT JOIN `' . _DB_PREFIX_ . 'shop_group` sg ON (sg.`id_shop_group` = s.`id_shop_group`)
                WHERE s.`id_shop` = ' . (int) $id_shop);
            if (!$row) {
                $message = Db::getInstance()->getMsgError();
                if (is_string($message) && $message !== '') {
                    return self::logDbFailure('shopShare id_shop=' . (int) $id_shop);
                }

                return false;
            }
            $shareCustomer = (int) $row['share_customer'];
            $shareStock = (int) $row['share_stock'];
            $shareOrder = (int) $row['share_order'];
            if (!$shareCustomer || !$shareStock) {
                $shareOrder = 0;
            }
            self::$shop_share_context[$id_shop] = [
                'id_shop' => (int) $row['id_shop'],
                'id_shop_group' => (int) $row['id_shop_group'],
                'share_customer' => $shareCustomer,
                'share_stock' => $shareStock,
                'share_order' => $shareOrder,
            ];
        }

        return self::$shop_share_context[$id_shop];
    }

    public static function resolveTargetShopIds($import = null)
    {
        $shops = [];
        $mappingShop = EtsMigrateDataImport::$mapping_shop;
        $targetShops = EtsMigrateDataImport::$target_shops;
        $mappingEmpty = !is_array($mappingShop) || !count($mappingShop);
        $targetEmpty = !is_array($targetShops) || !count($targetShops);
        if (is_array($mappingShop)) {
            foreach ($mappingShop as $idShop) {
                if ((int) $idShop > 0) {
                    $shops[(int) $idShop] = (int) $idShop;
                }
            }
        }
        if (!count($shops) && is_array($targetShops)) {
            foreach ($targetShops as $idShop) {
                if ((int) $idShop > 0) {
                    $shops[(int) $idShop] = (int) $idShop;
                }
            }
        }
        if (!count($shops) && $mappingEmpty && $targetEmpty) {
            if ($import instanceof EtsMigrateDataImport && isset($import->context->shop) && Validate::isLoadedObject($import->context->shop)) {
                $shops[(int) $import->context->shop->id] = (int) $import->context->shop->id;
            }
            $idShop = (int) Configuration::get('PS_SHOP_DEFAULT');
            if ($idShop <= 0 && self::tableExists('shop')) {
                $idShop = (int) Db::getInstance()->getValue('SELECT `id_shop` FROM `' . _DB_PREFIX_ . 'shop` ORDER BY `id_shop` ASC');
            }
            if ($idShop > 0) {
                $shops[$idShop] = $idShop;
            }
        }

        return array_values($shops);
    }

    public static function resolveDefaultShopId($import = null)
    {
        $shops = self::resolveTargetShopIds($import);
        if (count($shops) > 0) {
            return (int) reset($shops);
        }
        $context = ($import instanceof EtsMigrateDataImport && isset($import->context)) ? $import->context : null;
        $share = self::shopShare(0, $context);
        if (!is_array($share)) {
            return 0;
        }
        $idShop = isset($share['id_shop']) ? (int) $share['id_shop'] : 0;
        if ($idShop > 0) {
            return $idShop;
        }

        return 0;
    }

    public static function normalizeGroupShare()
    {
        if (!self::tableExists('shop_group')) {
            return true;
        }

        return Db::getInstance()->execute('
            UPDATE `' . _DB_PREFIX_ . 'shop_group`
            SET `share_order` = 0
            WHERE `share_customer` = 0 OR `share_stock` = 0
        ');
    }

    public static function updateDeliveryMultiShop()
    {
        $ok = Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'delivery` SET `id_shop`=NULL WHERE `id_shop`=0');
        $ok = $ok && Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'delivery` SET `id_shop_group`=NULL WHERE `id_shop_group`=0');

        return (bool) $ok;
    }

    public static function remapCategoryParents()
    {
        $idHomeCategory = (int) Configuration::get('PS_HOME_CATEGORY');
        $idRootCategory = (int) Configuration::get('PS_ROOT_CATEGORY');
        $parentRemapped = Db::getInstance()->execute('
                UPDATE `' . _DB_PREFIX_ . 'category` 
                SET 
                    `id_parent` = ' . $idHomeCategory . ' 
                WHERE `is_root_category` != 1 
                    AND `id_parent` = ' . $idRootCategory);
        if (!$parentRemapped) {
            return false;
        }

        return Db::getInstance()->execute('
                UPDATE `' . _DB_PREFIX_ . 'category` c
                    JOIN `' . _DB_PREFIX_ . 'category` lc ON c.`id_parent` = lc.`id_category_ets_old`
                SET 
                    c.`id_parent` = lc.`id_category`
                WHERE c.`id_parent` != 0 AND c.is_root_category != 1
            ');
    }

    public static function resetRootCategory()
    {
        static $configs = [
            'PS_HOME_CATEGORY' => [
                'field' => 'is_root_category',
                'value' => 1,
            ],
            'PS_ROOT_CATEGORY' => [
                'field' => 'id_parent',
                'value' => 0,
            ],
        ];
        foreach ($configs as $key => $config) {
            $configuredId = (int) Configuration::get($key);
            if (($id_category = (int) Db::getInstance()->getValue('SELECT `id_category` FROM `' . _DB_PREFIX_ . 'category` WHERE `' . pSQL($config['field']) . '`=' . (int) $config['value']))
                && $id_category != $configuredId
            ) {
                if (!Db::getInstance()->update('configuration', ['value' => (int) $id_category], 'name=\'' . pSQL($key) . '\'')) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function updateShopCategory()
    {
        $shops = Db::getInstance()->executeS('SELECT `id_shop`, `id_category` FROM `' . _DB_PREFIX_ . 'shop`');
        if ($shops === false) {
            return self::logDbFailure('updateShopCategory shops');
        }
        if (!count($shops)) {
            return true;
        }

        $rootCategories = Db::getInstance()->executeS('SELECT `id_category`, `id_category_ets_old` FROM `' . _DB_PREFIX_ . 'category` WHERE `is_root_category` = 1');
        if ($rootCategories === false) {
            return self::logDbFailure('updateShopCategory root categories');
        }
        $rootIds = [];
        $rootByOld = [];
        if (is_array($rootCategories)) {
            foreach ($rootCategories as $row) {
                $rootIds[(int) $row['id_category']] = true;
                $oldId = isset($row['id_category_ets_old']) ? (int) $row['id_category_ets_old'] : 0;
                if ($oldId > 0) {
                    $rootByOld[$oldId] = (int) $row['id_category'];
                }
            }
        }

        $homeConfigs = Db::getInstance()->executeS('SELECT `id_shop`, `value` FROM `' . _DB_PREFIX_ . 'configuration` WHERE `name` = \'PS_HOME_CATEGORY\'');
        if ($homeConfigs === false) {
            return self::logDbFailure('updateShopCategory PS_HOME_CATEGORY');
        }
        $homeByShop = [];
        $homeGlobal = 0;
        if (is_array($homeConfigs)) {
            foreach ($homeConfigs as $row) {
                if ($row['id_shop'] === null || $row['id_shop'] === '') {
                    $homeGlobal = (int) $row['value'];
                } else {
                    $homeByShop[(int) $row['id_shop']] = (int) $row['value'];
                }
            }
        }

        foreach ($shops as $shop) {
            $id_shop = (int) $shop['id_shop'];
            if ($id_shop <= 0) {
                continue;
            }
            $current = (int) $shop['id_category'];
            if ($current > 0 && isset($rootIds[$current])) {
                continue;
            }
            $id_category = 0;
            if ($current > 0 && isset($rootByOld[$current])) {
                $id_category = (int) $rootByOld[$current];
            }
            if ($id_category <= 0) {
                $id_category = isset($homeByShop[$id_shop]) ? (int) $homeByShop[$id_shop] : (int) $homeGlobal;
            }
            if ($id_category > 0 && $id_category != $current) {
                if (!Db::getInstance()->update('shop', ['id_category' => $id_category], 'id_shop = ' . $id_shop)) {
                    return self::logDbFailure('updateShopCategory shop id_shop=' . $id_shop);
                }
            }
        }

        return true;
    }

    public static function repairCategoryParentCycles()
    {
        $result = Db::getInstance()->executeS('SELECT `id_category`, `id_parent` FROM `' . _DB_PREFIX_ . 'category`');
        if ($result === false) {
            return self::logDbFailure('repairCategoryParentCycles select');
        }
        $categories = [];
        $parents = [];
        if (is_array($result)) {
            foreach ($result as $item) {
                $categories[$item['id_category']] = (int) $item['id_parent'];
                $parents[$item['id_parent']][] = (int) $item['id_category'];
            }
        }
        $cycleCategoryIds = [];
        if ($categories && $parents) {
            foreach ($parents as $idParent => $list) {
                if (isset($categories[$idParent]) && in_array($categories[$idParent], $list)) {
                    $cycleCategoryIds[] = $idParent;
                }
            }
        }
        $idRootCategory = (int) Configuration::get('PS_HOME_CATEGORY');
        if ($cycleCategoryIds) {
            if (!Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'category` SET `id_parent`=' . (int) $idRootCategory . ' WHERE `id_category` IN (' . implode(',', array_map('intval', $cycleCategoryIds)) . ')')) {
                return self::logDbFailure('repairCategoryParentCycles update');
            }
        }

        return true;
    }

    public static function ensureStockAvailableRows()
    {
        if (!self::tableExists('stock_available') || !self::tableExists('product') || !self::tableExists('product_shop') || !self::tableExists('shop')) {
            return true;
        }

        $quantityColumn = self::columnExists('product', 'quantity') ? 'p.`quantity`' : '0';
        $missingSimpleProductsQuery = '
            INSERT IGNORE INTO `' . _DB_PREFIX_ . 'stock_available` (
                `id_product`,
                `id_product_attribute`,
                `id_shop`,
                `id_shop_group`,
                `quantity`,
                `depends_on_stock`,
                `out_of_stock`
            )
            SELECT
                p.`id_product`,
                0,
                0,
                s.`id_shop_group`,
                ' . $quantityColumn . ',
                0,
                COALESCE(p.`out_of_stock`, 0)
            FROM `' . _DB_PREFIX_ . 'product` p
            INNER JOIN `' . _DB_PREFIX_ . 'product_shop` shop ON (shop.`id_product` = p.`id_product`)
            INNER JOIN `' . _DB_PREFIX_ . 'shop` s ON (s.`id_shop` = shop.`id_shop`)
            INNER JOIN `' . _DB_PREFIX_ . 'shop_group` sg ON (sg.`id_shop_group` = s.`id_shop_group` AND sg.`share_stock` = 1)
            LEFT JOIN `' . _DB_PREFIX_ . 'stock_available` stock ON (
                stock.`id_product` = p.`id_product`
                AND stock.`id_product_attribute` = 0
                AND stock.`id_shop` = 0
                AND stock.`id_shop_group` = s.`id_shop_group`
            )
            WHERE stock.`id_stock_available` IS NULL
            GROUP BY p.`id_product`, s.`id_shop_group`
            UNION ALL
            SELECT
                p.`id_product`,
                0,
                shop.`id_shop`,
                0,
                ' . $quantityColumn . ',
                0,
                COALESCE(p.`out_of_stock`, 0)
            FROM `' . _DB_PREFIX_ . 'product` p
            INNER JOIN `' . _DB_PREFIX_ . 'product_shop` shop ON (shop.`id_product` = p.`id_product`)
            INNER JOIN `' . _DB_PREFIX_ . 'shop` s ON (s.`id_shop` = shop.`id_shop`)
            LEFT JOIN `' . _DB_PREFIX_ . 'shop_group` sg ON (sg.`id_shop_group` = s.`id_shop_group`)
            LEFT JOIN `' . _DB_PREFIX_ . 'stock_available` stock ON (
                stock.`id_product` = p.`id_product`
                AND stock.`id_product_attribute` = 0
                AND stock.`id_shop` = shop.`id_shop`
                AND stock.`id_shop_group` = 0
            )
            WHERE stock.`id_stock_available` IS NULL
                AND COALESCE(sg.`share_stock`, 0) = 0
            GROUP BY p.`id_product`, shop.`id_shop`
        ';
        if (!Db::getInstance()->execute($missingSimpleProductsQuery)) {
            return self::logDbFailure('ensureStockAvailableRows missingSimpleProducts');
        }

        $aggregateCombinationsQuery = '
            INSERT INTO `' . _DB_PREFIX_ . 'stock_available` (
                `id_product`,
                `id_product_attribute`,
                `id_shop`,
                `id_shop_group`,
                `quantity`,
                `depends_on_stock`,
                `out_of_stock`
            )
            SELECT
                sa.`id_product`,
                0,
                sa.`id_shop`,
                sa.`id_shop_group`,
                SUM(sa.`quantity`),
                0,
                COALESCE(p.`out_of_stock`, 0)
            FROM `' . _DB_PREFIX_ . 'stock_available` sa
            INNER JOIN `' . _DB_PREFIX_ . 'product` p ON (p.`id_product` = sa.`id_product`)
            WHERE sa.`id_product_attribute` > 0
            GROUP BY sa.`id_product`, sa.`id_shop`, sa.`id_shop_group`
            ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`)
        ';
        if (!Db::getInstance()->execute($aggregateCombinationsQuery)) {
            return self::logDbFailure('ensureStockAvailableRows aggregateCombinations');
        }

        $outOfStockResult = self::executeOrLog('
            UPDATE `' . _DB_PREFIX_ . 'stock_available` st
            LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.`id_product` = st.`id_product`
            SET st.`out_of_stock` = p.`out_of_stock`
            WHERE st.`out_of_stock` IS NULL OR st.`out_of_stock` <= 0
        ', 'ensureStockAvailableRows out_of_stock');
        if ($outOfStockResult === false) {
            return false;
        }

        if (class_exists('Cache')) {
            Cache::clean('StockAvailable::*');
        }

        return true;
    }

    public static function setProductSuppliers()
    {
        if (!self::tableExists('product_supplier') || !self::tableExists('product') || !self::tableExists('product_attribute')) {
            return true;
        }

        $ps_currency_default = Db::getInstance()->getValue('SELECT `value` FROM `' . _DB_PREFIX_ . 'configuration` WHERE name="PS_CURRENCY_DEFAULT"');
        if ($ps_currency_default === false) {
            $message = Db::getInstance()->getMsgError();
            if (is_string($message) && $message !== '') {
                return self::logDbFailure('setProductSuppliers PS_CURRENCY_DEFAULT');
            }

            return false;
        }
        $ps_currency_default = (int) $ps_currency_default;

        return self::executeOrLog('
            INSERT IGNORE INTO `' . _DB_PREFIX_ . 'product_supplier`
            (`id_product`, `id_product_attribute`, `id_supplier`,
                `product_supplier_reference`, `product_supplier_price_te`,
                `id_currency`)
            SELECT p.`id_product`, 0, p.`id_supplier`,
                p.`supplier_reference`, p.`wholesale_price`,
                ' . (int) $ps_currency_default . '
            FROM `' . _DB_PREFIX_ . 'product` p
            WHERE p.`id_supplier` > 0
            UNION ALL
            SELECT p.`id_product`, pa.`id_product_attribute`, p.`id_supplier`,
                pa.`supplier_reference`, pa.`wholesale_price`,
                ' . (int) $ps_currency_default . '
            FROM `' . _DB_PREFIX_ . 'product` p
            INNER JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON pa.`id_product` = p.`id_product`
            WHERE p.`id_supplier` > 0
        ', 'setProductSuppliers');
    }

    public static function setCacheDefaultAttribute()
    {
        return self::executeOrLog('
            UPDATE `' . _DB_PREFIX_ . 'product` p SET `cache_default_attribute` = (SELECT `id_product_attribute` FROM `' . _DB_PREFIX_ . 'product_attribute` WHERE `id_product` = p.`id_product` AND default_on = 1 LIMIT 1) 
            WHERE `cache_default_attribute` IS NULL
        ', 'setCacheDefaultAttribute');
    }

    public static function upgradeAttributeGroup()
    {
        return self::executeOrLog('UPDATE `' . _DB_PREFIX_ . 'attribute_group` SET `group_type` = IF(`is_color_group`=1, "color", "select") WHERE `is_color_group` = 1 OR `group_type` is NULL OR `group_type`=""', 'upgradeAttributeGroup');
    }

    public static function insertMissingProductAttributeLangRows()
    {
        if (!self::columnExists('product_attribute', 'id_product_attribute_ets_old')) {
            return true;
        }

        return self::executeOrLog('INSERT IGNORE INTO `' . _DB_PREFIX_ . 'product_attribute_lang`
            (id_product_attribute, id_lang, available_now, available_later)
            SELECT pa.id_product_attribute, l.id_lang, \'\', \'\'
            FROM `' . _DB_PREFIX_ . 'product_attribute` pa CROSS JOIN `' . _DB_PREFIX_ . 'lang` l WHERE `id_product_attribute_ets_old` is NOT NULL;', 'insertMissingProductAttributeLangRows');
    }

    public static function updateProductType()
    {
        if (!self::columnExists('product', 'product_type')) {
            return true;
        }

        $productTypeUpdated = Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'product` SET `product_type` = "standard"');
        if (!$productTypeUpdated) {
            return self::logDbFailure('updateProductType standard');
        }
        if (self::columnExists('product', 'cache_default_attribute')) {
            if (!Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'product` SET `product_type` = "combinations" WHERE `cache_default_attribute` != 0')) {
                return self::logDbFailure('updateProductType combinations');
            }
        }
        if (self::columnExists('product', 'cache_is_pack')) {
            if (!Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'product` SET `product_type` = "pack" WHERE `cache_is_pack` = 1')) {
                return self::logDbFailure('updateProductType pack');
            }
        }
        if (self::columnExists('product', 'is_virtual')) {
            if (!Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'product` SET `product_type` = "virtual" WHERE `is_virtual` = 1')) {
                return self::logDbFailure('updateProductType virtual');
            }
        }

        return true;
    }

    public static function insertMissingAttributeStock()
    {
        if (!self::tableExists('stock_available') || !self::tableExists('product_attribute_shop') || !self::tableExists('product') || !self::tableExists('shop')) {
            return true;
        }

        $columns = [
            'id_product',
            'id_product_attribute',
            'id_shop',
            'id_shop_group',
            'quantity',
            'depends_on_stock',
            'out_of_stock',
        ];
        $selectShareStock = [
            'pas.`id_product`',
            'pas.`id_product_attribute`',
            '0 AS `id_shop`',
            's.`id_shop_group` AS `id_shop_group`',
            '0 AS `quantity`',
            '0 AS `depends_on_stock`',
            'COALESCE(p.`out_of_stock`, 0) AS `out_of_stock`',
        ];
        $selectShopStock = [
            'pas.`id_product`',
            'pas.`id_product_attribute`',
            'pas.`id_shop` AS `id_shop`',
            '0 AS `id_shop_group`',
            '0 AS `quantity`',
            '0 AS `depends_on_stock`',
            'COALESCE(p.`out_of_stock`, 0) AS `out_of_stock`',
        ];
        if (self::columnExists('stock_available', 'physical_quantity')) {
            $columns[] = 'physical_quantity';
            $selectShareStock[] = '0 AS `physical_quantity`';
            $selectShopStock[] = '0 AS `physical_quantity`';
        }
        if (self::columnExists('stock_available', 'reserved_quantity')) {
            $columns[] = 'reserved_quantity';
            $selectShareStock[] = '0 AS `reserved_quantity`';
            $selectShopStock[] = '0 AS `reserved_quantity`';
        }
        if (self::columnExists('stock_available', 'location')) {
            $columns[] = 'location';
            $selectShareStock[] = '\'\' AS `location`';
            $selectShopStock[] = '\'\' AS `location`';
        }

        $quotedColumns = [];
        foreach ($columns as $column) {
            $quotedColumns[] = '`' . bqSQL($column) . '`';
        }

        $query = '
            INSERT IGNORE INTO `' . _DB_PREFIX_ . 'stock_available` (' . implode(', ', $quotedColumns) . ')
            SELECT 
                ' . implode(",\n                ", $selectShareStock) . '
            FROM `' . _DB_PREFIX_ . 'product_attribute_shop` pas
            INNER JOIN `' . _DB_PREFIX_ . 'product` p ON p.`id_product` = pas.`id_product`
            INNER JOIN `' . _DB_PREFIX_ . 'shop` s ON (s.`id_shop` = pas.`id_shop`)
            INNER JOIN `' . _DB_PREFIX_ . 'shop_group` sg ON (sg.`id_shop_group` = s.`id_shop_group` AND sg.`share_stock` = 1)
            LEFT JOIN `' . _DB_PREFIX_ . 'stock_available` sa ON (
                sa.`id_product` = pas.`id_product`
                AND sa.`id_product_attribute` = pas.`id_product_attribute`
                AND sa.`id_shop` = 0
                AND sa.`id_shop_group` = s.`id_shop_group`
            )
            WHERE sa.`id_stock_available` IS NULL
            UNION ALL
            SELECT 
                ' . implode(",\n                ", $selectShopStock) . '
            FROM `' . _DB_PREFIX_ . 'product_attribute_shop` pas
            INNER JOIN `' . _DB_PREFIX_ . 'product` p ON p.`id_product` = pas.`id_product`
            INNER JOIN `' . _DB_PREFIX_ . 'shop` s ON (s.`id_shop` = pas.`id_shop`)
            LEFT JOIN `' . _DB_PREFIX_ . 'shop_group` sg ON (sg.`id_shop_group` = s.`id_shop_group`)
            LEFT JOIN `' . _DB_PREFIX_ . 'stock_available` sa ON (
                sa.`id_product` = pas.`id_product`
                AND sa.`id_product_attribute` = pas.`id_product_attribute`
                AND sa.`id_shop` = pas.`id_shop`
                AND sa.`id_shop_group` = 0
            )
            WHERE sa.`id_stock_available` IS NULL
                AND COALESCE(sg.`share_stock`, 0) = 0
        ';

        return self::executeOrLog($query, 'insertMissingAttributeStock');
    }

    public static function syncOrderCurrentState()
    {
        return self::executeOrLog('
            UPDATE `' . _DB_PREFIX_ . 'orders` o
            SET o.`current_state` = IFNULL((
                SELECT oh.`id_order_state`
                FROM `' . _DB_PREFIX_ . 'order_history` oh
                WHERE oh.`id_order` = o.`id_order`
                ORDER BY oh.`date_add` DESC
                LIMIT 1
            ), 0);
        ', 'syncOrderCurrentState');
    }

    public static function insertOrderInvoice()
    {
        return self::executeOrLog('
            INSERT IGNORE INTO `' . _DB_PREFIX_ . 'order_invoice` (`id_order`, `number`, `total_discount_tax_excl`, `total_discount_tax_incl`, `total_paid_tax_excl`, `total_paid_tax_incl`, `total_products`, `total_products_wt`, `total_shipping_tax_excl`, `total_shipping_tax_incl`, `total_wrapping_tax_excl`, `total_wrapping_tax_incl`, `note`, `date_add`)
                SELECT o.`id_order`, o.`invoice_number`, o.`total_discounts_tax_excl`, o.`total_discounts_tax_incl`, o.`total_paid_tax_excl`, o.`total_paid_tax_incl`, o.`total_products`, o.`total_products_wt`, o.`total_shipping_tax_excl`, o.`total_shipping_tax_incl`, o.`total_wrapping_tax_excl`, o.`total_wrapping_tax_incl`, \'\', o.`invoice_date`
                FROM `' . _DB_PREFIX_ . 'orders` o
                LEFT JOIN `' . _DB_PREFIX_ . 'order_invoice` oi ON oi.`id_order` = o.`id_order`
                WHERE o.`invoice_number` != 0
                    AND oi.`id_order_invoice` IS NULL
        ', 'insertOrderInvoice');
    }

    public static function updateOrderDetail()
    {
        return self::executeOrLog('
            UPDATE `' . _DB_PREFIX_ . 'order_detail` od
            SET od.`id_order_invoice` = IFNULL((
                SELECT oi.`id_order_invoice`
                FROM `' . _DB_PREFIX_ . 'order_invoice` oi
                WHERE oi.`id_order` = od.`id_order` LIMIT 1
            ), 0);
        ', 'updateOrderDetail');
    }

    public static function addOrderReference()
    {
        return self::executeOrLog('UPDATE `' . _DB_PREFIX_ . 'orders` SET `reference` = id_order WHERE `reference` IS NULL OR `reference` = \'\'', 'addOrderReference');
    }

    public static function updateOrderReference()
    {
        return self::executeOrLog('UPDATE `' . _DB_PREFIX_ . 'orders` SET `reference` = LPAD(`reference`, 9 , \'0\') WHERE CHAR_LENGTH(IFNULL(`reference`, \'\')) < 9', 'updateOrderReference');
    }

    public static function insertOrderCarrier()
    {
        return self::executeOrLog('
        INSERT IGNORE INTO `' . _DB_PREFIX_ . 'order_carrier` (`id_order`, `id_carrier`, `id_order_invoice`, `weight`, `shipping_cost_tax_excl`, `shipping_cost_tax_incl`, `tracking_number`, `date_add`)
            SELECT o.`id_order`, o.`id_carrier`, (
                SELECT oi.`id_order_invoice`
                FROM `' . _DB_PREFIX_ . 'order_invoice` oi
                WHERE oi.`id_order` = o.`id_order` LIMIT 1
            ), (
                SELECT SUM(`product_weight`)
                FROM `' . _DB_PREFIX_ . 'order_detail` od
                WHERE od.`id_order` = o.`id_order` LIMIT 1
            ), o.`total_shipping_tax_excl`, o.`total_shipping_tax_incl`, o.`shipping_number`, o.`date_add`
            FROM `' . _DB_PREFIX_ . 'orders` o
            LEFT JOIN `' . _DB_PREFIX_ . 'order_carrier` oc ON oc.`id_order` = o.`id_order`
            WHERE oc.`id_order_carrier` IS NULL
        ', 'insertOrderCarrier');
    }

    public static function insertOrderPayment()
    {
        return self::executeOrLog('
        INSERT IGNORE INTO `' . _DB_PREFIX_ . 'order_payment` (`order_reference`, `id_currency`, `amount`, `payment_method`, `conversion_rate`, `date_add`)
            (
                SELECT o.`reference`, o.`id_currency`, o.`total_paid_real`, o.`payment`, o.`conversion_rate`, o.`date_add`
                FROM `' . _DB_PREFIX_ . 'orders` o
                LEFT JOIN `' . _DB_PREFIX_ . 'order_payment` op ON (op.`order_reference` = o.`reference`)
                WHERE op.`id_order_payment` IS NULL
            );
        ', 'insertOrderPayment');
    }

    public static function updateOrderPayment()
    {
        if (!self::executeOrLog('UPDATE `' . _DB_PREFIX_ . 'order_payment` SET transaction_id=\'\' WHERE transaction_id is NULL', 'updateOrderPayment transaction_id')) {
            return false;
        }
        if (!self::executeOrLog('UPDATE `' . _DB_PREFIX_ . 'order_payment` SET card_number=\'\' WHERE card_number is NULL', 'updateOrderPayment card_number')) {
            return false;
        }
        if (!self::executeOrLog('UPDATE `' . _DB_PREFIX_ . 'order_payment` SET card_brand=\'\' WHERE card_brand is NULL', 'updateOrderPayment card_brand')) {
            return false;
        }
        if (!self::executeOrLog('UPDATE `' . _DB_PREFIX_ . 'order_payment` SET card_expiration=\'\' WHERE card_expiration is NULL', 'updateOrderPayment card_expiration')) {
            return false;
        }

        return self::executeOrLog('UPDATE `' . _DB_PREFIX_ . 'order_payment` SET card_holder=\'\' WHERE card_holder is NULL', 'updateOrderPayment card_holder');
    }

    public static function syncCustomerGroup()
    {
        if (!self::tableExists('customer') || !self::tableExists('shop')) {
            return true;
        }

        return self::executeOrLog('
            UPDATE `' . _DB_PREFIX_ . 'customer` c
            INNER JOIN `' . _DB_PREFIX_ . 'shop` s ON (s.`id_shop` = c.`id_shop`)
            SET c.`id_shop_group` = s.`id_shop_group`
            WHERE c.`id_shop` > 0
        ', 'syncCustomerGroup');
    }

    public static function repairCustomerGroups()
    {
        if (!self::tableExists('customer') || !self::tableExists('group')) {
            return true;
        }

        $idCustomerGroup = self::getValidGroupId((int) Configuration::get('PS_CUSTOMER_GROUP'));
        $idGuestGroup = self::getValidGroupId((int) Configuration::get('PS_GUEST_GROUP'));
        if ($idCustomerGroup <= 0) {
            return false;
        }
        if ($idGuestGroup <= 0) {
            $idGuestGroup = $idCustomerGroup;
        }

        $hasOld = self::columnExists('group', 'id_group_ets_old');
        if ($hasOld) {
            if (!Db::getInstance()->execute('
                UPDATE `' . _DB_PREFIX_ . 'customer` c
                INNER JOIN `' . _DB_PREFIX_ . 'group` map ON map.`id_group_ets_old` = c.`id_default_group`
                LEFT JOIN `' . _DB_PREFIX_ . 'group` g ON g.`id_group` = c.`id_default_group`
                SET c.`id_default_group` = map.`id_group`
                WHERE c.`id_default_group` > 0 AND g.`id_group` IS NULL
            ')) {
                return false;
            }
        }

        if (!Db::getInstance()->execute('
            UPDATE `' . _DB_PREFIX_ . 'customer` c
            LEFT JOIN `' . _DB_PREFIX_ . 'group` g ON g.`id_group` = c.`id_default_group`
            SET c.`id_default_group` = IF(c.`is_guest` = 1, ' . (int) $idGuestGroup . ', ' . (int) $idCustomerGroup . ')
            WHERE c.`id_default_group` <= 0 OR g.`id_group` IS NULL
        ')) {
            return false;
        }

        if (!self::tableExists('customer_group')) {
            return true;
        }

        if ($hasOld) {
            if (!Db::getInstance()->execute('
                INSERT IGNORE INTO `' . _DB_PREFIX_ . 'customer_group` (`id_customer`, `id_group`)
                SELECT cg.`id_customer`, map.`id_group`
                FROM `' . _DB_PREFIX_ . 'customer_group` cg
                INNER JOIN `' . _DB_PREFIX_ . 'group` map ON map.`id_group_ets_old` = cg.`id_group`
                LEFT JOIN `' . _DB_PREFIX_ . 'group` g ON g.`id_group` = cg.`id_group`
                WHERE g.`id_group` IS NULL
            ')) {
                return false;
            }
        }

        if (!Db::getInstance()->execute('
            INSERT IGNORE INTO `' . _DB_PREFIX_ . 'customer_group` (`id_customer`, `id_group`)
            SELECT cg.`id_customer`, c.`id_default_group`
            FROM `' . _DB_PREFIX_ . 'customer_group` cg
            INNER JOIN `' . _DB_PREFIX_ . 'customer` c ON c.`id_customer` = cg.`id_customer`
            LEFT JOIN `' . _DB_PREFIX_ . 'group` g ON g.`id_group` = cg.`id_group`
            WHERE g.`id_group` IS NULL AND c.`id_default_group` > 0
        ')) {
            return false;
        }

        if (!Db::getInstance()->execute('
            DELETE cg FROM `' . _DB_PREFIX_ . 'customer_group` cg
            LEFT JOIN `' . _DB_PREFIX_ . 'group` g ON g.`id_group` = cg.`id_group`
            WHERE g.`id_group` IS NULL
        ')) {
            return false;
        }

        return (bool) Db::getInstance()->execute('
            INSERT IGNORE INTO `' . _DB_PREFIX_ . 'customer_group` (`id_customer`, `id_group`)
            SELECT c.`id_customer`, c.`id_default_group`
            FROM `' . _DB_PREFIX_ . 'customer` c
            WHERE c.`id_default_group` > 0
        ');
    }

    protected static function getValidGroupId($idGroup)
    {
        $idGroup = (int) $idGroup;
        if ($idGroup > 0
            && (int) Db::getInstance()->getValue('SELECT `id_group` FROM `' . _DB_PREFIX_ . 'group` WHERE `id_group` = ' . $idGroup)
        ) {
            return $idGroup;
        }

        return (int) Db::getInstance()->getValue('SELECT `id_group` FROM `' . _DB_PREFIX_ . 'group` ORDER BY `id_group` ASC');
    }

    public static function addGroups()
    {
        if (!self::tableExists('group') || !self::tableExists('module_group')) {
            return true;
        }
        if (!self::columnExists('group', 'id_group_ets_old')) {
            return true;
        }

        $idUnidentifiedGroup = (int) Configuration::get('PS_UNIDENTIFIED_GROUP');
        if ($idUnidentifiedGroup <= 0) {
            return false;
        }

        return self::executeOrLog('
            INSERT IGNORE INTO `' . _DB_PREFIX_ . 'module_group` (`id_module`, `id_shop`, `id_group`)
            SELECT mg.`id_module`, mg.`id_shop`, g.`id_group`
            FROM `' . _DB_PREFIX_ . 'module_group` mg
            INNER JOIN `' . _DB_PREFIX_ . 'group` g ON g.`id_group_ets_old` IS NOT NULL
            WHERE mg.`id_group` = ' . (int) $idUnidentifiedGroup . '
        ', 'addGroups');
    }

    public static function updateSpecificPrice()
    {
        return self::executeOrLog('UPDATE `' . _DB_PREFIX_ . 'specific_price` SET `price` = -1 WHERE `price` = 0', 'updateSpecificPrice');
    }

    public static function updateCarrierReference()
    {
        return self::executeOrLog('UPDATE `' . _DB_PREFIX_ . 'carrier` SET `id_reference` = `id_carrier` WHERE `id_reference` IS NULL OR `id_reference` = 0 OR `id_reference` != `id_carrier`', 'updateCarrierReference');
    }

    public static function getDuplicateLanguages()
    {
        if (!self::columnExists('lang', 'id_lang_ets_old')) {
            return [];
        }

        $dq = new DbQuery();
        $dq
            ->select('GROUP_CONCAT(`id_lang` SEPARATOR \',\')')
            ->from('lang')
            ->where('id_lang_ets_old is NULL');

        $res = Db::getInstance()->getValue($dq);
        if ($res === false) {
            $message = Db::getInstance()->getMsgError();
            if (is_string($message) && $message !== '') {
                return false;
            }

            return [];
        }

        return self::toPositiveIntList((string) $res);
    }

    public static function toPositiveIntList($ids)
    {
        if (!is_array($ids)) {
            $ids = explode(',', (string) $ids);
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        })));
    }

    public static function fillMissingCurrencyLang()
    {
        if (!self::tableExists('currency_lang') || !self::tableExists('currency') || !self::tableExists('lang')) {
            return true;
        }

        $columns = ['id_currency', 'id_lang', 'name', 'symbol'];
        $selects = ['c.`id_currency`', 'l.`id_lang`', 'c.`iso_code`', 'c.`iso_code`'];
        if (self::columnExists('currency_lang', 'pattern')) {
            $columns[] = 'pattern';
            $selects[] = '\'\'';
        }

        $quotedColumns = [];
        foreach ($columns as $column) {
            $quotedColumns[] = '`' . bqSQL($column) . '`';
        }

        return self::executeOrLog('
            INSERT IGNORE INTO `' . _DB_PREFIX_ . 'currency_lang` (' . implode(', ', $quotedColumns) . ')
            SELECT ' . implode(', ', $selects) . '
            FROM `' . _DB_PREFIX_ . 'currency` c
            CROSS JOIN `' . _DB_PREFIX_ . 'lang` l
            WHERE NOT EXISTS (
                SELECT 1 FROM `' . _DB_PREFIX_ . 'currency_lang` cl
                WHERE cl.`id_currency` = c.`id_currency` AND cl.`id_lang` = l.`id_lang`
            )
        ', 'fillMissingCurrencyLang');
    }

    public static function updateShopImage()
    {
        if (!self::tableExists('image') || !self::tableExists('image_shop') || !self::tableExists('shop')) {
            return true;
        }

        Db::getInstance()->execute('SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE `' . _DB_PREFIX_ . 'image_shop`; SET FOREIGN_KEY_CHECKS = 1');

        return self::executeOrLog('
            INSERT INTO `' . _DB_PREFIX_ . 'image_shop` (`id_product`, `id_image`, `id_shop`, `cover`)
            SELECT i.`id_product`, i.`id_image`, s.`id_shop`, i.`cover`
            FROM `' . _DB_PREFIX_ . 'image` i
            CROSS JOIN `' . _DB_PREFIX_ . 'shop` s
        ', 'updateShopImage');
    }
}
