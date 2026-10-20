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

class EtsMigrateCartRulePostImport
{
    private static $tableExistsCache = [];
    private static $cartRuleColumnSet;

    public static function normalize($import = null)
    {
        if (!self::tableExistsCached('cart_rule')) {
            return true;
        }

        $ok = self::fillDefaultCurrency();
        $ok = $ok && self::attachDefaultShopRestriction($import);
        $ok = $ok && self::fillMissingLanguageRows();
        $ok = $ok && self::resetRestrictionFlags();
        if ($ok && self::shouldRemapForeignIds($import)) {
            $ok = self::remapProductRuleValues();
        }

        if (!$ok) {
            return false;
        }

        if ((int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'cart_rule`') > 0) {
            if (!Configuration::updateGlobalValue('PS_CART_RULE_FEATURE_ACTIVE', 1)) {
                return false;
            }
        }

        return true;
    }

    public static function normalizeCatalogSpecificPriceRules($import = null)
    {
        if (!self::tableExistsCached('specific_price_rule')) {
            return true;
        }

        $ok = true;
        if (self::shouldRemapForeignIds($import)) {
            $ok = self::remapCatalogRuleConditions();
        }

        if (!$ok) {
            return false;
        }

        $hasSpecificPrice = self::tableExistsCached('specific_price')
            && (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'specific_price`') > 0;
        if ((int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'specific_price_rule`') > 0 || $hasSpecificPrice) {
            if (!Configuration::updateGlobalValue('PS_SPECIFIC_PRICE_FEATURE_ACTIVE', 1)) {
                return false;
            }
        }

        return true;
    }

    private static function tableExistsCached($table)
    {
        if (!isset(self::$tableExistsCache[$table])) {
            self::$tableExistsCache[$table] = (bool) EtsMigrateTools::tableExists($table);
        }

        return self::$tableExistsCache[$table];
    }

    private static function cartRuleHasColumn($field)
    {
        if (self::$cartRuleColumnSet === null) {
            self::$cartRuleColumnSet = [];
            $schema = EtsMigrateTools::describeTable('cart_rule');
            if (is_array($schema)) {
                foreach ($schema as $column) {
                    if (isset($column['Field'])) {
                        self::$cartRuleColumnSet[$column['Field']] = true;
                    }
                }
            }
        }

        return isset(self::$cartRuleColumnSet[$field]);
    }

    private static function fillDefaultCurrency()
    {
        $id_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
        if ($id_currency <= 0) {
            return true;
        }

        if (!self::executeSql('
            UPDATE `' . _DB_PREFIX_ . 'cart_rule`
            SET `reduction_currency` = ' . (int) $id_currency . '
            WHERE `reduction_amount` > 0 AND `reduction_currency` <= 0
        ', 'fillDefaultCurrency.reduction_currency')) {
            return false;
        }

        return self::executeSql('
            UPDATE `' . _DB_PREFIX_ . 'cart_rule`
            SET `minimum_amount_currency` = ' . (int) $id_currency . '
            WHERE `minimum_amount` > 0 AND `minimum_amount_currency` <= 0
        ', 'fillDefaultCurrency.minimum_amount_currency');
    }

    private static function attachDefaultShopRestriction($import = null)
    {
        if (!self::tableExistsCached('cart_rule_shop') || !self::cartRuleHasColumn('shop_restriction')) {
            return true;
        }

        $shops = EtsMigrateTools::resolveTargetShopIds($import);
        if (!count($shops)) {
            return true;
        }

        foreach ($shops as $idShop) {
            $idShop = (int) $idShop;
            if ($idShop <= 0) {
                continue;
            }
            if (!self::executeSql('
                INSERT IGNORE INTO `' . _DB_PREFIX_ . 'cart_rule_shop` (`id_cart_rule`, `id_shop`)
                SELECT cr.`id_cart_rule`, ' . $idShop . '
                FROM `' . _DB_PREFIX_ . 'cart_rule` cr
                WHERE cr.`shop_restriction` = 1
            ', 'attachDefaultShopRestriction.id_shop=' . $idShop)) {
                return false;
            }
        }

        return true;
    }

    private static function fillMissingLanguageRows()
    {
        if (!self::tableExistsCached('cart_rule_lang') || !self::tableExistsCached('lang')) {
            return true;
        }

        $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        return self::executeSql('
            INSERT IGNORE INTO `' . _DB_PREFIX_ . 'cart_rule_lang` (`id_cart_rule`, `id_lang`, `name`)
            SELECT cr.`id_cart_rule`, l.`id_lang`, IF(source_lang.`name` IS NOT NULL AND source_lang.`name` != "", source_lang.`name`, IF(cr.`code` != "", cr.`code`, CONCAT("Cart rule ", cr.`id_cart_rule`)))
            FROM `' . _DB_PREFIX_ . 'cart_rule` cr
            CROSS JOIN `' . _DB_PREFIX_ . 'lang` l
            LEFT JOIN (
                SELECT crl.`id_cart_rule`, crl.`name`
                FROM `' . _DB_PREFIX_ . 'cart_rule_lang` crl
                INNER JOIN (
                    SELECT
                        `id_cart_rule`,
                        COALESCE(MAX(IF(`id_lang` = ' . (int) $id_lang . ', `id_lang`, NULL)), MIN(`id_lang`)) AS `id_lang`
                    FROM `' . _DB_PREFIX_ . 'cart_rule_lang`
                    GROUP BY `id_cart_rule`
                ) pick ON (pick.`id_cart_rule` = crl.`id_cart_rule` AND pick.`id_lang` = crl.`id_lang`)
            ) source_lang ON (source_lang.`id_cart_rule` = cr.`id_cart_rule`)
            WHERE NOT EXISTS (
                SELECT 1
                FROM `' . _DB_PREFIX_ . 'cart_rule_lang` existing_lang
                WHERE existing_lang.`id_cart_rule` = cr.`id_cart_rule`
                    AND existing_lang.`id_lang` = l.`id_lang`
            )
        ', 'fillMissingLanguageRows');
    }

    private static function resetRestrictionFlags()
    {
        $tables = [
            'country_restriction' => 'cart_rule_country',
            'carrier_restriction' => 'cart_rule_carrier',
            'group_restriction' => 'cart_rule_group',
            'cart_rule_restriction' => 'cart_rule_combination',
            'product_restriction' => 'cart_rule_product_rule_group',
        ];

        foreach ($tables as $field => $table) {
            if (!self::cartRuleHasColumn($field) || !self::tableExistsCached($table)) {
                continue;
            }
            if (!self::resetUnusedCartRuleRestrictionFlag($field, $table)) {
                return false;
            }
        }

        return true;
    }

    private static function remapProductRuleValues()
    {
        if (!self::tableExistsCached('cart_rule_product_rule_value') || !self::tableExistsCached('cart_rule_product_rule')) {
            return true;
        }

        $maps = [
            'products' => ['product', 'id_product'],
            'categories' => ['category', 'id_category'],
            'attributes' => ['attribute', 'id_attribute'],
            'manufacturers' => ['manufacturer', 'id_manufacturer'],
            'suppliers' => ['supplier', 'id_supplier'],
        ];

        foreach ($maps as $type => $map) {
            if (!self::remapProductRuleItemIds($type, $map[0], $map[1])) {
                return false;
            }
        }

        return true;
    }

    private static function remapCatalogRuleConditions()
    {
        if (!self::tableExistsCached('specific_price_rule_condition')) {
            return true;
        }

        $maps = [
            'attribute' => ['attribute', 'id_attribute'],
            'manufacturer' => ['manufacturer', 'id_manufacturer'],
            'category' => ['category', 'id_category'],
            'supplier' => ['supplier', 'id_supplier'],
            'feature' => ['feature_value', 'id_feature_value'],
        ];

        foreach ($maps as $type => $map) {
            if (!self::remapCatalogConditionValues($type, $map[0], $map[1])) {
                return false;
            }
        }

        return true;
    }

    private static function canRemapOldIdColumn($table, $id)
    {
        if (!self::tableExistsCached($table)) {
            return false;
        }

        return (bool) EtsMigrateTools::columnExists($table, $id . '_ets_old');
    }

    private static function resetUnusedCartRuleRestrictionFlag($field, $table)
    {
        if ($table === 'cart_rule_combination') {
            $existsSql = '
                SELECT 1 FROM `' . _DB_PREFIX_ . 'cart_rule_combination` crc
                WHERE crc.`id_cart_rule_1` = cr.`id_cart_rule` OR crc.`id_cart_rule_2` = cr.`id_cart_rule`
            ';
        } else {
            $existsSql = '
                SELECT 1 FROM `' . _DB_PREFIX_ . bqSQL($table) . '` crt
                WHERE crt.`id_cart_rule` = cr.`id_cart_rule`
            ';
        }

        return self::executeSql('
            UPDATE `' . _DB_PREFIX_ . 'cart_rule` cr
            SET cr.`' . bqSQL($field) . '` = 0
            WHERE cr.`' . bqSQL($field) . '` = 1
                AND NOT EXISTS (' . $existsSql . ')
        ', 'resetUnusedCartRuleRestrictionFlag.' . $field);
    }

    private static function remapProductRuleItemIds($type, $table, $id)
    {
        if (!self::canRemapOldIdColumn($table, $id)) {
            return true;
        }

        return self::executeSql('
            UPDATE `' . _DB_PREFIX_ . 'cart_rule_product_rule_value` v
            INNER JOIN `' . _DB_PREFIX_ . 'cart_rule_product_rule` r ON (r.`id_product_rule` = v.`id_product_rule` AND r.`type` = "' . pSQL($type) . '")
            INNER JOIN `' . _DB_PREFIX_ . bqSQL($table) . '` target_item ON (target_item.`' . bqSQL($id) . '_ets_old` = v.`id_item`)
            SET v.`id_item` = target_item.`' . bqSQL($id) . '`
        ', 'remapProductRuleItemIds.' . $type);
    }

    private static function remapCatalogConditionValues($type, $table, $id)
    {
        if (!self::canRemapOldIdColumn($table, $id)) {
            return true;
        }

        // value stores entity ids as strings; join to INT *_ets_old so MySQL coerces and can use the ets_old index
        return self::executeSql('
            UPDATE `' . _DB_PREFIX_ . 'specific_price_rule_condition` c
            INNER JOIN `' . _DB_PREFIX_ . bqSQL($table) . '` target_item ON (target_item.`' . bqSQL($id) . '_ets_old` = c.`value`)
            SET c.`value` = target_item.`' . bqSQL($id) . '`
            WHERE c.`type` = "' . pSQL($type) . '"
        ', 'remapCatalogConditionValues.' . $type);
    }

    private static function shouldRemapForeignIds($import)
    {
        return !$import instanceof EtsMigrateDataImport || !$import->shouldKeepOldId();
    }

    private static function executeSql($sql, $context)
    {
        if (Db::getInstance()->execute($sql)) {
            return true;
        }

        $message = Db::getInstance()->getMsgError();
        EtsMigrateDataImport::writeLog('Cart rule post-import SQL failed [' . $context . ']' . (is_string($message) && $message !== '' ? ': ' . $message : ''), 'ERROR');

        return false;
    }
}
