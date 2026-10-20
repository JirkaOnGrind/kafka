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

class EtsMigrateOrderSchemaPatch
{
    private const MAX_KEYSET_BATCHES_PER_CALL = 2;

    private static $orderDetailColumns;
    private static $orderColumns;
    private static $orderDetailColumnSet;
    private static $orderDetailFloatColumns = [
        'product_price' => true,
        'reduction_percent' => true,
        'reduction_amount' => true,
        'group_reduction' => true,
        'product_quantity_discount' => true,
        'tax_rate' => true,
        'ecotax' => true,
        'ecotax_tax_rate' => true,
    ];

    private static function localizeMessage($message)
    {
        $module = Module::getInstanceByName('ets_migrate');
        if ($module instanceof Module) {
            return $module->l($message, 'AdminETSEMMigrateController');
        }

        return $message;
    }

    private static function errorResult($message)
    {
        return ['error' => 1, 'msg' => self::localizeMessage($message)];
    }

    private static function sqlFailureResult($message, $databaseError = '')
    {
        self::dropTempTables();
        if (is_string($databaseError) && $databaseError !== '') {
            self::logInsertFailure('schema patch', $databaseError);
        }

        return self::errorResult($message);
    }

    private static function clearLastOrderPatchId()
    {
        Configuration::deleteByName('ETS_EM_ORDER_PATCH_LAST_ID');
    }

    private static function persistLastOrderPatchId($lastOrderId)
    {
        Configuration::updateGlobalValue('ETS_EM_ORDER_PATCH_LAST_ID', (string) (int) $lastOrderId);
    }

    private static function dropTempTables()
    {
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'ets_em_orders_2`');
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'ets_em_order_detail_2`');
        self::clearLastOrderPatchId();
        EtsMigrateTools::clearSchemaCache();
    }

    private static function loadOrderDetailColumns()
    {
        if (self::$orderDetailColumns !== null) {
            return self::$orderDetailColumns;
        }

        $columns = [];
        $columnSet = [];
        $schema = EtsMigrateTools::describeTable('order_detail');
        if (!is_array($schema) || !count($schema)) {
            return false;
        }
        foreach ($schema as $field) {
            $name = isset($field['Field']) ? trim($field['Field']) : '';
            if ($name === '' || $name === 'id_order_invoice' || !Validate::isTableOrIdentifier($name)) {
                continue;
            }
            $safeName = bqSQL($name);
            $columns[] = $safeName;
            $columnSet[$safeName] = true;
        }
        if (!$columns) {
            return false;
        }
        self::$orderDetailColumns = $columns;
        self::$orderDetailColumnSet = $columnSet;

        return $columns;
    }

    private static function loadOrderColumns()
    {
        if (self::$orderColumns !== null) {
            return self::$orderColumns;
        }

        $columns = [];
        $schema = EtsMigrateTools::describeTable('orders');
        if (!$schema) {
            return false;
        }
        foreach ($schema as $field) {
            $name = isset($field['Field']) ? trim($field['Field']) : '';
            if ($name === '' || !Validate::isTableOrIdentifier($name)) {
                continue;
            }
            $columns[] = bqSQL($name);
        }
        if (!$columns) {
            return false;
        }
        self::$orderColumns = $columns;

        return $columns;
    }

    private static function logInsertFailure($context, $databaseError)
    {
        $message = 'ets_migrate: could not insert ' . $context . ': ' . $databaseError;
        if (class_exists('PrestaShopLogger')) {
            PrestaShopLogger::addLog($message, 3);
        } else {
            error_log($message);
        }
    }

    private static function flushRewriteBatch($insertOrder, &$valuesOrder, $insertOrderDetail, &$valuesOrderDetail)
    {
        if (!empty($valuesOrder)) {
            if (!Db::getInstance()->execute($insertOrder . implode(',', $valuesOrder))) {
                $databaseError = Db::getInstance()->getMsgError();
                self::logInsertFailure('order record', $databaseError);
                $valuesOrder = [];
                $valuesOrderDetail = [];

                return self::sqlFailureResult('Could not insert order record. Check the migration log.', $databaseError);
            }
            $valuesOrder = [];
        }
        if (!empty($valuesOrderDetail)) {
            if (!Db::getInstance()->execute($insertOrderDetail . implode(',', $valuesOrderDetail))) {
                $databaseError = Db::getInstance()->getMsgError();
                self::logInsertFailure('order detail record', $databaseError);
                $valuesOrderDetail = [];

                return self::sqlFailureResult('Could not insert order detail record. Check the migration log.', $databaseError);
            }
            $valuesOrderDetail = [];
        }

        return true;
    }

    private static function prefetchOrderDetailsByOrderIds($orderIds, $columnsSql)
    {
        $grouped = [];
        foreach ($orderIds as $orderId) {
            $grouped[(int) $orderId] = [];
        }
        if (!$orderIds) {
            return $grouped;
        }
        $ids = implode(',', array_map('intval', $orderIds));
        $result = Db::getInstance()->query('SELECT ' . $columnsSql . ' FROM `' . _DB_PREFIX_ . 'order_detail`'
            . ' WHERE `id_order` IN (' . $ids . ')');
        if ($result === false) {
            return false;
        }
        while ($row = Db::getInstance()->nextRow($result)) {
            $grouped[(int) $row['id_order']][] = $row;
        }

        return $grouped;
    }

    private static function prefetchCustomerDefaultGroups($customerIds)
    {
        $map = [];
        foreach ($customerIds as $customerId) {
            $map[(int) $customerId] = 0;
        }
        if (!$customerIds) {
            return $map;
        }
        $ids = implode(',', array_map('intval', $customerIds));
        $rows = Db::getInstance()->executeS('SELECT `id_customer`, `id_default_group` FROM `' . _DB_PREFIX_ . 'customer`'
            . ' WHERE `id_customer` IN (' . $ids . ')');
        if ($rows === false) {
            return false;
        }
        foreach ($rows as $row) {
            $map[(int) $row['id_customer']] = (int) $row['id_default_group'];
        }

        return $map;
    }

    private static function prefetchPriceDisplayMethods($groupIds)
    {
        $map = [];
        foreach ($groupIds as $groupId) {
            $map[(int) $groupId] = 0;
        }
        if (!$groupIds) {
            return $map;
        }
        $ids = implode(',', array_map('intval', $groupIds));
        $rows = Db::getInstance()->executeS('SELECT `id_group`, `price_display_method` FROM `' . _DB_PREFIX_ . 'group`'
            . ' WHERE `id_group` IN (' . $ids . ')');
        if ($rows === false) {
            return false;
        }
        foreach ($rows as $row) {
            $map[(int) $row['id_group']] = (int) $row['price_display_method'];
        }

        return $map;
    }

    public static function run()
    {
        if (!defined('PS_TAX_EXC')) {
            define('PS_TAX_EXC', 1);
        }

        if (!defined('PS_TAX_INC')) {
            define('PS_TAX_INC', 0);
        }
        $col_order_detail = self::loadOrderDetailColumns();
        if ($col_order_detail === false) {
            return self::errorResult('Unable to get fields list from order_detail table');
        }

        $insert_order_detail = 'INSERT INTO `' . _DB_PREFIX_ . 'ets_em_order_detail_2` (`' . implode('`, `', $col_order_detail) . '`) VALUES ';
        $orderDetailColumnsSql = '`' . implode('`, `', $col_order_detail) . '`';

        $col_orders = self::loadOrderColumns();
        if ($col_orders === false) {
            return self::errorResult('Unable to get fields list from orders table');
        }

        $insert_order = 'INSERT INTO `' . _DB_PREFIX_ . 'ets_em_orders_2` (`' . implode('`, `', $col_orders) . '`) VALUES ';
        $orderColumnsSql = '`' . implode('`, `', $col_orders) . '`';

        $isResume = (bool) EtsMigrateTools::tableExists('ets_em_orders_2');
        if ($isResume) {
            $lastOrderId = (int) Configuration::getGlobalValue('ETS_EM_ORDER_PATCH_LAST_ID');
        } else {
            if (!self::duplicateTables()) {
                return self::errorResult('Unable to duplicate tables orders and order_detail');
            }
            $lastOrderId = 0;
        }

        $wrappingTaxRates = [];
        $step = 3000;
        $values_order = [];
        $values_order_detail = [];
        $flushCounter = 0;
        $flush_limit = 200;
        $batchesThisCall = 0;
        $ordersRemaining = false;

        while ($batchesThisCall < self::MAX_KEYSET_BATCHES_PER_CALL) {
            $order_res = Db::getInstance()->query('SELECT ' . $orderColumnsSql . ' FROM `' . _DB_PREFIX_ . 'orders`'
                . ' WHERE `id_order` > ' . (int) $lastOrderId
                . ' ORDER BY `id_order` ASC LIMIT ' . (int) $step);
            if ($order_res === false) {
                return self::sqlFailureResult('Unable to read orders batch. Check the migration log.', Db::getInstance()->getMsgError());
            }

            $orders = [];
            $orderIds = [];
            $customerIds = [];
            while ($order = Db::getInstance()->nextRow($order_res)) {
                $orders[] = $order;
                $orderId = (int) $order['id_order'];
                $orderIds[] = $orderId;
                $customerIds[(int) $order['id_customer']] = (int) $order['id_customer'];
                $lastOrderId = $orderId;
            }

            if (!$orders) {
                $ordersRemaining = false;
                break;
            }

            ++$batchesThisCall;
            $ordersRemaining = count($orders) >= $step;

            $orderDetailsByOrder = self::prefetchOrderDetailsByOrderIds($orderIds, $orderDetailColumnsSql);
            if ($orderDetailsByOrder === false) {
                return self::sqlFailureResult('Unable to read order_detail batch. Check the migration log.', Db::getInstance()->getMsgError());
            }

            $customerDefaultGroups = self::prefetchCustomerDefaultGroups(array_values($customerIds));
            if ($customerDefaultGroups === false) {
                return self::sqlFailureResult('Unable to read customer default group. Check the migration log.', Db::getInstance()->getMsgError());
            }

            $groupIds = [];
            foreach ($customerDefaultGroups as $defaultGroupId) {
                $groupIds[(int) $defaultGroupId] = (int) $defaultGroupId;
            }
            $priceDisplayMethods = self::prefetchPriceDisplayMethods(array_values($groupIds));
            if ($priceDisplayMethods === false) {
                return self::sqlFailureResult('Unable to read price display method. Check the migration log.', Db::getInstance()->getMsgError());
            }

            foreach ($orders as $order) {
                $sum_total_products = 0;
                $sum_tax_amount = 0;
                $idShop = !empty($order['id_shop']) ? (int) $order['id_shop'] : 0;
                if (!isset($wrappingTaxRates[$idShop])) {
                    $wrappingTaxRates[$idShop] = 1 + ((float) self::readGiftWrappingTax($idShop) / 100);
                }
                $wrapping_tax_rate = $wrappingTaxRates[$idShop];
                $default_group_id = isset($customerDefaultGroups[(int) $order['id_customer']])
                    ? (int) $customerDefaultGroups[(int) $order['id_customer']]
                    : 0;
                $price_display_method = isset($priceDisplayMethods[$default_group_id])
                    ? (int) $priceDisplayMethods[$default_group_id]
                    : 0;
                $orderDetailsList = $orderDetailsByOrder[(int) $order['id_order']]
                    ?? [];
                foreach ($orderDetailsList as $order_details) {
                    $products = self::setProductPrices($order_details, $price_display_method, $idShop);
                    $tax_rate = 1 + ((float) $products['tax_rate'] / 100);
                    $reduction_amount_tax_incl = (float) $products['reduction_amount'];

                    $sum_total_products += $products['total_price'];

                    $sum_tax_amount += $products['total_wt'] - $products['total_price'];

                    $order_details['reduction_amount_tax_incl'] = $reduction_amount_tax_incl;
                    $order_details['reduction_amount_tax_excl'] = (float) self::roundPrice($reduction_amount_tax_incl / $tax_rate, $idShop);
                    $order_details['total_price_tax_incl'] = (float) $products['total_wt'];
                    $order_details['total_price_tax_excl'] = (float) $products['total_price'];
                    $order_details['unit_price_tax_incl'] = (float) $products['product_price_wt'];
                    $order_details['unit_price_tax_excl'] = (float) $products['product_price'];

                    foreach (array_keys($order_details) as $k) {
                        if (!isset(self::$orderDetailColumnSet[$k])) {
                            unset($order_details[$k]);
                        } elseif (isset(self::$orderDetailFloatColumns[$k])) {
                            $order_details[$k] = (float) $order_details[$k];
                        } else {
                            $order_details[$k] = Db::getInstance()->escape($order_details[$k]);
                        }
                    }
                    $values_order_detail[] = '(\'' . implode('\', \'', $order_details) . '\')';
                    unset($order_details);
                }

                $average_tax_used = 1;
                if ($sum_total_products > 0) {
                    $average_tax_used += $sum_tax_amount / $sum_total_products;
                }
                $average_tax_used = round($average_tax_used, 4);
                $carrier_tax_rate = 1;
                if (isset($order['carrier_tax_rate'])) {
                    $carrier_tax_rate = 1 + ((float) $order['carrier_tax_rate'] / 100);
                }

                $total_discount_tax_excl = $order['total_discounts'] / $average_tax_used;
                $order['total_discounts_tax_incl'] = (float) $order['total_discounts'];
                $order['total_discounts_tax_excl'] = (float) $total_discount_tax_excl;

                $order['total_shipping_tax_incl'] = (float) $order['total_shipping'];
                $order['total_shipping_tax_excl'] = (float) ($order['total_shipping'] / $carrier_tax_rate);
                $shipping_taxes = $order['total_shipping_tax_incl'] - $order['total_shipping_tax_excl'];

                $order['total_wrapping_tax_incl'] = (float) $order['total_wrapping'];
                $order['total_wrapping_tax_excl'] = ((float) $order['total_wrapping'] / $wrapping_tax_rate);
                $wrapping_taxes = $order['total_wrapping_tax_incl'] - $order['total_wrapping_tax_excl'];

                $product_taxes = $order['total_products_wt'] - $order['total_products'];
                $order['total_paid_tax_incl'] = (float) $order['total_paid'];
                $order['total_paid_tax_excl'] = (float) $order['total_paid'] - $shipping_taxes - $wrapping_taxes - $product_taxes;
                $order_row = [];
                foreach ($col_orders as $column) {
                    $value = $order[$column] ?? '';
                    $order_row[] = Db::getInstance()->escape($value);
                }
                $values_order[] = '(\'' . implode('\', \'', $order_row) . '\')';

                unset($order);
                ++$flushCounter;

                if ($flushCounter >= $flush_limit) {
                    $flushCounter = 0;
                    $flushResult = self::flushRewriteBatch($insert_order, $values_order, $insert_order_detail, $values_order_detail);
                    if ($flushResult !== true) {
                        return $flushResult;
                    }
                }
            }
        }

        if ($ordersRemaining) {
            $moreOrders = (int) Db::getInstance()->getValue('SELECT 1 FROM `' . _DB_PREFIX_ . 'orders`'
                . ' WHERE `id_order` > ' . (int) $lastOrderId
                . ' LIMIT 1');
            $ordersRemaining = $moreOrders > 0;
        }

        $flushResult = self::flushRewriteBatch($insert_order, $values_order, $insert_order_detail, $values_order_detail);
        if ($flushResult !== true) {
            return $flushResult;
        }

        if ($ordersRemaining) {
            self::persistLastOrderPatchId($lastOrderId);

            return ['error' => 0, 'continue' => 1];
        }

        if (!self::renameTables()) {
            self::dropTempTables();

            return self::errorResult('Unable to rename tables ets_em_orders_2 and ets_em_order_detail_2 to orders and order_detail');
        }

        self::clearLastOrderPatchId();

        return ['error' => 0];
    }

    private static function readGiftWrappingTax($idShop)
    {
        $idShop = (int) $idShop;
        $raw = $idShop > 0
            ? Configuration::get('PS_GIFT_WRAPPING_TAX', null, null, $idShop)
            : Configuration::get('PS_GIFT_WRAPPING_TAX');
        if ($raw === false || trim((string) $raw) === '') {
            return 0;
        }

        return $raw;
    }

    private static function roundPrice($val, $idShop = null)
    {
        static $priceRoundModes;
        $cacheKey = (int) $idShop;
        if (!isset($priceRoundModes[$cacheKey])) {
            $raw = $cacheKey > 0
                ? Configuration::get('PS_PRICE_ROUND_MODE', null, null, $cacheKey)
                : Configuration::get('PS_PRICE_ROUND_MODE');
            if ($raw === false || trim((string) $raw) === '') {
                $raw = 2;
            }
            $priceRoundModes[$cacheKey] = (int) $raw;
        }

        switch ($priceRoundModes[$cacheKey]) {
            case 0:
                return ceil($val * 100) / 100;
            case 1:
                return floor($val * 100) / 100;
            default:
                return (float) round($val, 2);
        }
    }

    private static function duplicateTables()
    {
        $res = true;
        $res &= Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'ets_em_orders_2`');
        $res &= Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'ets_em_order_detail_2`');
        $res &= Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS
        `' . _DB_PREFIX_ . 'ets_em_orders_2` LIKE `' . _DB_PREFIX_ . 'orders`');
        $res &= Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS
        `' . _DB_PREFIX_ . 'ets_em_order_detail_2` LIKE `' . _DB_PREFIX_ . 'order_detail`');

        return $res;
    }

    private static function renameTables()
    {
        $ok = Db::getInstance()->execute('RENAME TABLE
                `' . _DB_PREFIX_ . 'orders` TO `' . _DB_PREFIX_ . 'ets_em_orders_old`,
                `' . _DB_PREFIX_ . 'ets_em_orders_2` TO `' . _DB_PREFIX_ . 'orders`,
                `' . _DB_PREFIX_ . 'order_detail` TO `' . _DB_PREFIX_ . 'ets_em_order_detail_old`,
                `' . _DB_PREFIX_ . 'ets_em_order_detail_2` TO `' . _DB_PREFIX_ . 'order_detail`');
        if (!$ok) {
            return false;
        }

        $ok = Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'ets_em_orders_old`');
        $ok = $ok && Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'ets_em_order_detail_old`');
        if ($ok) {
            EtsMigrateTools::clearSchemaCache();
        }

        return (bool) $ok;
    }

    private static function setProductPrices($row, $tax_calculation_method, $idShop = null)
    {
        if ($tax_calculation_method == PS_TAX_EXC) {
            $row['product_price'] = self::roundPrice($row['product_price'], $idShop);
        } else {
            $row['product_price_wt'] = self::roundPrice($row['product_price'] * (1 + $row['tax_rate'] / 100), $idShop);
        }

        $group_reduction = 1;
        if ($row['group_reduction'] > 0) {
            $group_reduction = 1 - $row['group_reduction'] / 100;
        }

        if ($row['reduction_percent'] != 0) {
            if ($tax_calculation_method == PS_TAX_EXC) {
                $row['product_price'] = ($row['product_price'] - $row['product_price'] * ($row['reduction_percent'] * 0.01));
            } else {
                $reduction = self::roundPrice($row['product_price_wt'] * ($row['reduction_percent'] * 0.01), $idShop);
                $row['product_price_wt'] = self::roundPrice($row['product_price_wt'] - $reduction, $idShop);
            }
        }

        if ($row['reduction_amount'] != 0) {
            if ($tax_calculation_method == PS_TAX_EXC) {
                $row['product_price'] = ($row['product_price'] - ($row['reduction_amount'] / (1 + $row['tax_rate'] / 100)));
            } else {
                $row['product_price_wt'] = self::roundPrice($row['product_price_wt'] - $row['reduction_amount'], $idShop);
            }
        }

        if ($row['group_reduction'] > 0) {
            if ($tax_calculation_method == PS_TAX_EXC) {
                $row['product_price'] = $row['product_price'] * $group_reduction;
            } else {
                $row['product_price_wt'] = self::roundPrice($row['product_price_wt'] * $group_reduction, $idShop);
            }
        }

        if (($row['reduction_percent'] || $row['reduction_amount'] || $row['group_reduction']) && $tax_calculation_method == PS_TAX_EXC) {
            $row['product_price'] = self::roundPrice($row['product_price'], $idShop);
        }

        if ($tax_calculation_method == PS_TAX_EXC) {
            $row['product_price_wt'] = self::roundPrice($row['product_price'] * (1 + ($row['tax_rate'] * 0.01)), $idShop) + self::roundPrice($row['ecotax'] * (1 + $row['ecotax_tax_rate'] / 100), $idShop);
        } else {
            $row['product_price_wt_but_ecotax'] = $row['product_price_wt'];
            $row['product_price_wt'] = self::roundPrice($row['product_price_wt'] + $row['ecotax'] * (1 + $row['ecotax_tax_rate'] / 100), $idShop);
        }

        if ($tax_calculation_method != PS_TAX_EXC) {
            $row['product_price'] = $row['product_price_wt'] / (1 + $row['tax_rate'] / 100);
        }

        $row['total_wt'] = $row['product_quantity'] * $row['product_price_wt'];
        $row['total_price'] = $row['product_quantity'] * $row['product_price'];

        return $row;
    }
}
