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

class EtsMigrateCmsLegacyPostImport
{
    private static $langSchemaByTable = [];

    public static function normalize($import, $table)
    {
        if (!$import instanceof EtsMigrateDataImport) {
            return true;
        }
        if (!self::isLegacy($import)) {
            return true;
        }
        $table = trim($table);
        if ($table === 'cms_category_shop') {
            return self::fillEntityShopAssociation($import, 'cms_category', 'id_cms_category');
        }
        if ($table === 'cms_shop') {
            return self::fillEntityShopAssociation($import, 'cms', 'id_cms');
        }
        if ($table === 'cms_category_lang') {
            return self::replicateLangRowsForShops($import, 'cms_category_lang');
        }
        if ($table === 'cms_lang') {
            return self::replicateLangRowsForShops($import, 'cms_lang');
        }

        return true;
    }

    public static function isLegacyLang($import, $table)
    {
        return self::isLegacy($import) && in_array(trim($table), ['cms_lang', 'cms_category_lang']);
    }

    private static function fillEntityShopAssociation($import, $entityTable, $entityIdField)
    {
        $entityTable = trim($entityTable);
        $entityIdField = trim($entityIdField);
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $entityTable) || !preg_match('/^[a-zA-Z0-9_]+$/', $entityIdField)) {
            return false;
        }
        $shopTable = $entityTable . '_shop';
        if (!EtsMigrateTools::tableExists($entityTable) || !EtsMigrateTools::tableExists($shopTable)) {
            return true;
        }
        $idShop = (int) EtsMigrateTools::resolveDefaultShopId($import);
        if ($idShop <= 0) {
            return true;
        }

        if (!Db::getInstance()->execute('INSERT IGNORE INTO `' . _DB_PREFIX_ . bqSQL($shopTable) . '` (`' . bqSQL($entityIdField) . '`, `id_shop`)
            SELECT a.`' . bqSQL($entityIdField) . '`, ' . (int) $idShop . '
            FROM `' . _DB_PREFIX_ . bqSQL($entityTable) . '` a')) {
            self::logSqlFailure('CMS legacy shop association failed on ' . $entityTable);

            return false;
        }

        return true;
    }

    private static function describeLangTable($table)
    {
        if (!array_key_exists($table, self::$langSchemaByTable)) {
            self::$langSchemaByTable[$table] = EtsMigrateTools::describeTable($table);
        }

        return self::$langSchemaByTable[$table];
    }

    private static function replicateLangRowsForShops($import, $table)
    {
        $table = trim($table);
        if (!in_array($table, ['cms_lang', 'cms_category_lang'], true)) {
            return true;
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return false;
        }
        if (!EtsMigrateTools::tableExists($table)) {
            return true;
        }
        $schema = self::describeLangTable($table);
        if ($schema === false) {
            return false;
        }
        if (!is_array($schema) || !count($schema)) {
            return true;
        }
        $hasIdShop = false;
        foreach ($schema as $column) {
            if (isset($column['Field']) && $column['Field'] === 'id_shop') {
                $hasIdShop = true;
                break;
            }
        }
        if (!$hasIdShop) {
            return true;
        }
        $fields = [];
        $selects = [];
        foreach ($schema as $column) {
            if (!isset($column['Field'])) {
                continue;
            }
            $field = trim($column['Field']);
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
                return false;
            }
            $fields[] = '`' . bqSQL($field) . '`';
            $selects[] = $field === 'id_shop' ? '%d' : 'a.`' . bqSQL($field) . '`';
        }
        if (!count($fields)) {
            return true;
        }
        $shops = EtsMigrateTools::resolveTargetShopIds($import);
        $sourceShop = count($shops) > 0 ? (int) reset($shops) : 0;
        if ($sourceShop <= 0) {
            return true;
        }
        foreach ($shops as $idShop) {
            $query = sprintf('INSERT IGNORE INTO `' . _DB_PREFIX_ . bqSQL($table) . '` (' . implode(',', $fields) . ')
                SELECT ' . implode(',', $selects) . '
                FROM `' . _DB_PREFIX_ . bqSQL($table) . '` a
                WHERE a.`id_shop` = %d', (int) $idShop, (int) $sourceShop);
            if (!Db::getInstance()->execute($query)) {
                self::logSqlFailure('CMS legacy lang replicate failed on ' . $table);

                return false;
            }
        }

        return true;
    }

    private static function logSqlFailure($prefix)
    {
        $message = Db::getInstance()->getMsgError();
        if (!is_string($message) || $message === '') {
            return;
        }
        if (class_exists('EtsMigrateDataImport', false) && method_exists('EtsMigrateDataImport', 'writeLog')) {
            EtsMigrateDataImport::writeLog($prefix . ': ' . $message, 'ERROR');
        }
    }

    private static function isLegacy($import)
    {
        return version_compare($import->source_version, '1.6.0.12', '<');
    }
}
