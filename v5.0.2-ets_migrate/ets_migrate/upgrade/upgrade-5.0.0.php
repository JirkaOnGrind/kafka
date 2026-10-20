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

function ets_migrate_rename_global_config_5_0_0($oldKey, $newKey)
{
    $oldValue = Configuration::get($oldKey, null, 0, 0);
    if ($oldValue === false) {
        return true;
    }
    if (Configuration::get($newKey, null, 0, 0) === false) {
        if (!Configuration::updateGlobalValue($newKey, $oldValue)) {
            return false;
        }
    }
    if (!Configuration::deleteByName($oldKey)) {
        return false;
    }

    return true;
}

function ets_migrate_ensure_config_default_5_0_0($key, $defaultValue)
{
    if (Configuration::get($key, null, 0, 0) !== false) {
        return true;
    }

    return (bool) Configuration::updateGlobalValue($key, $defaultValue);
}

function ets_migrate_upgrade_database_5_0_0()
{
    $table = _DB_PREFIX_ . 'ets_em_shop_mapping';
    $db = Db::getInstance();

    $createSql = 'CREATE TABLE IF NOT EXISTS `' . bqSQL($table) . '` (
        `id_ets_em_shop_mapping` int(10) unsigned NOT NULL auto_increment,
        `id_shop_source` int(10) unsigned NOT NULL DEFAULT \'0\',
        `id_shop_target` int(10) unsigned NOT NULL DEFAULT \'0\',
        PRIMARY KEY (`id_ets_em_shop_mapping`),
        UNIQUE KEY `uniq_id_shop_source` (`id_shop_source`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4';

    if (!$db->execute($createSql)) {
        return false;
    }

    $indexes = $db->executeS('SHOW INDEX FROM `' . bqSQL($table) . '`');
    if ($indexes === false) {
        return false;
    }

    $existingIndexes = [];
    foreach ($indexes as $index) {
        if (!empty($index['Key_name'])) {
            $existingIndexes[$index['Key_name']] = true;
        }
    }

    if (isset($existingIndexes['idx_id_shop_target']) && !$db->execute('ALTER TABLE `' . bqSQL($table) . '` DROP INDEX `idx_id_shop_target`')) {
        return false;
    }

    if (isset($existingIndexes['idx_id_shop_source']) && !$db->execute('ALTER TABLE `' . bqSQL($table) . '` DROP INDEX `idx_id_shop_source`')) {
        return false;
    }

    if (!isset($existingIndexes['uniq_id_shop_source'])) {
        $cleanDuplicatesSql = 'DELETE m1 FROM `' . bqSQL($table) . '` m1
            INNER JOIN `' . bqSQL($table) . '` m2
            WHERE m1.`id_shop_source` = m2.`id_shop_source`
            AND m1.`id_ets_em_shop_mapping` < m2.`id_ets_em_shop_mapping`';
        $db->execute($cleanDuplicatesSql);

        if (!$db->execute('ALTER TABLE `' . bqSQL($table) . '` ADD UNIQUE KEY `uniq_id_shop_source` (`id_shop_source`)')) {
            return false;
        }
    }

    $statuses = $db->executeS('SHOW TABLE STATUS LIKE "' . pSQL($table) . '"');
    if ($statuses === false || !isset($statuses[0])) {
        return false;
    }
    $status = $statuses[0];

    if (empty($status['Collation']) || Tools::strpos($status['Collation'], 'utf8mb4') !== 0) {
        if (!$db->execute('ALTER TABLE `' . bqSQL($table) . '` CONVERT TO CHARACTER SET utf8mb4')) {
            return false;
        }
    }

    return true;
}

function upgrade_module_5_0_0($module)
{
    if (!is_object($module)) {
        return false;
    }

    if (!ets_migrate_upgrade_database_5_0_0()) {
        return false;
    }

    if (!ets_migrate_rename_global_config_5_0_0('ETS_EM_GENE_PRODUCT_THUMBNAIL', 'ETS_EM_GENERATE_PRODUCT_THUMBNAIL')) {
        return false;
    }
    if (!ets_migrate_rename_global_config_5_0_0('ETS_NEW_COOKIE_KEY', 'ETS_EM_SOURCE_COOKIE_KEY')) {
        return false;
    }
    if (!ets_migrate_rename_global_config_5_0_0('ETS_EM__SCHEMA_COLUMN', 'ETS_EM_SCHEMA_COLUMN')) {
        return false;
    }

    $defaults = [
        'ETS_EM_MIGRATION_METHOD' => 1,
        'ETS_EM_MIGRATE_IMAGES' => 'auto',
        'ETS_EM_GENERATE_PRODUCT_THUMBNAIL' => 'auto',
        'ETS_EM_MIGRATE_IMAGE_SPEED' => 5,
        'ETS_EM_ATTACHMENTS_FILES' => 'auto',
        'ETS_EM_ATTACHMENTS_FILES_SPEED' => 5,
        'ETS_EM_MIGRATE_EMPTY_CART' => 1,
        'ETS_EM_MIGRATE_SPEED' => 5000,
    ];

    foreach ($defaults as $key => $defaultValue) {
        if (!ets_migrate_ensure_config_default_5_0_0($key, $defaultValue)) {
            return false;
        }
    }

    if (!EtsMigrateInstall::initTabs($module)) {
        return false;
    }

    $module->unregisterHook('displayBackOfficeHeader');

    return true;
}
