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

class EtsMigratePostImportRouter
{
    private static $orders2TempExists;

    public static function routeAfterTableMigrated($workflow, $import, $table, &$migrated_tables)
    {
        $table = trim($table);
        if ($table !== 'order_detail' && self::orders2TempExists()) {
            $resumeResult = self::runOrderSchemaPatchOrContinue($workflow, $import, $migrated_tables);
            if ($resumeResult !== null) {
                return $resumeResult;
            }
            self::$orders2TempExists = false;
            self::runOrderToolsPostImport();
        }

        switch ($table) {
            case 'shop_group':
                EtsMigrateTools::normalizeGroupShare();
                break;
            case 'product':
                if (version_compare($import->source_version, '1.7.8.0', '<') && version_compare(_PS_VERSION_, '1.7.8.0', '>=')) {
                    EtsMigrateTools::updateProductType();
                }
                break;
            case 'image':
                if (version_compare($import->source_version, '1.5.0.0', '<') && version_compare(_PS_VERSION_, '1.5.1.0', '>=')) {
                    EtsMigrateTools::updateShopImage();
                }
                break;
            case 'category':
                if ($import->shouldKeepOldId() && version_compare($import->source_version, '1.5.0.5', '<') && version_compare(_PS_VERSION_, '1.5.0.5', '>=')) {
                    if (!function_exists('generateRootCategory')) {
                        require_once dirname(__FILE__) . '/schema_patch/generateRootCategory.php';
                    }
                    generateRootCategory();
                }
                if (!$import->shouldKeepOldId()) {
                    EtsMigrateTools::remapCategoryParents();
                } else {
                    EtsMigrateTools::resetRootCategory();
                }
                EtsMigrateTools::updateShopCategory();
                EtsMigrateTools::repairCategoryParentCycles();
                if (class_exists('Category') && method_exists('Category', 'regenerateEntireNtree')) {
                    Category::regenerateEntireNtree();
                }
                break;
            case 'cms_category_shop':
            case 'cms_shop':
            case 'cms_lang':
            case 'cms_category_lang':
                EtsMigrateCmsLegacyPostImport::normalize($import, $table);
                break;
            case 'attribute_group':
                if (version_compare($import->source_version, '1.5.0.1', '<') && version_compare(_PS_VERSION_, '1.5.0.1', '>=')) {
                    EtsMigrateTools::upgradeAttributeGroup();
                    if (!function_exists('addGroupAttributePosition')) {
                        require_once dirname(__FILE__) . '/schema_patch/addGroupAttributePosition.php';
                    }
                    addGroupAttributePosition();
                }
                break;
            case 'attribute':
                if (version_compare($import->source_version, '1.5.0.1', '<') && version_compare(_PS_VERSION_, '1.5.0.1', '>=')) {
                    if (!function_exists('addAttributePosition')) {
                        require_once dirname(__FILE__) . '/schema_patch/addAttributePosition.php';
                    }
                    addAttributePosition();
                }
                break;
            case 'stock_available':
                EtsMigrateTools::insertMissingAttributeStock();
                EtsMigrateTools::ensureStockAvailableRows();
                break;
            case 'product_attribute':
                if (version_compare($import->source_version, '1.7.8.0', '<') && version_compare(_PS_VERSION_, '1.7.8.0', '>=')) {
                    EtsMigrateTools::setCacheDefaultAttribute();
                }
                if (
                    version_compare($import->source_version, '8.1.0', '<')
                    && version_compare(_PS_VERSION_, '8.1.0', '>=')
                    && !$workflow->isTableInMigrationQueue($import, $migrated_tables, 'product_attribute_lang')
                ) {
                    EtsMigrateTools::insertMissingProductAttributeLangRows();
                }
                break;
            case 'product_attribute_lang':
                if (version_compare($import->source_version, '8.1.0', '<') && version_compare(_PS_VERSION_, '8.1.0', '>=')) {
                    EtsMigrateTools::insertMissingProductAttributeLangRows();
                }
                break;
            case 'product_supplier':
                if (version_compare($import->source_version, '1.5.0.2', '<') && version_compare(_PS_VERSION_, '1.5.0.2', '>=')) {
                    EtsMigrateTools::setProductSuppliers();
                }
                break;
            case 'lang':
                $languages = EtsMigrateTools::getDuplicateLanguages();
                if ($languages === false) {
                    return self::buildErrorResponse($workflow, 'Migrate duplicate languages lookup failed!');
                }
                if (is_array($languages) && count($languages) > 0) {
                    $import->setLanguages($languages);
                    if (!in_array((int) Configuration::get('PS_LANG_DEFAULT'), $languages)) {
                        $import->setDefaultLanguage((int) Configuration::get('PS_LANG_DEFAULT'));
                    } else {
                        $infos = $import->getSourceMigrationMetadata();
                        $import->setDefaultLanguage(isset($infos['lang_default']) && (int) $infos['lang_default'] > 0 ? $import->getNewIdByOldId('lang', 'id_lang', $infos['lang_default']) : 0);
                    }
                }
                break;
            case 'order_detail':
                if (version_compare($import->source_version, '1.5.0.2', '<') && version_compare(_PS_VERSION_, '1.5.0.2', '>=')) {
                    $patchResult = self::runOrderSchemaPatchOrContinue($workflow, $import, $migrated_tables);
                    if ($patchResult !== null) {
                        return $patchResult;
                    }
                    self::$orders2TempExists = false;
                    self::runOrderToolsPostImport();
                }
                break;
            case 'category_group':
                if (version_compare($import->source_version, '1.5.0.1', '<') && version_compare(_PS_VERSION_, '1.5.0.1', '>=')) {
                    if (!function_exists('upgradeGroups')) {
                        require_once dirname(__FILE__) . '/schema_patch/upgradeGroups.php';
                    }
                    upgradeGroups();
                }
                break;
            case 'order_history':
                if (version_compare($import->source_version, '1.5.0.6', '<') && version_compare(_PS_VERSION_, '1.5.0.6', '>=')) {
                    EtsMigrateTools::syncOrderCurrentState();
                }
                break;
            case 'specific_price':
                Configuration::updateValue('PS_SPECIFIC_PRICE_FEATURE_ACTIVE', 1);
                if (version_compare($import->source_version, '1.5.0.12', '<') && version_compare(_PS_VERSION_, '1.5.0.12', '>=')) {
                    EtsMigrateTools::updateSpecificPrice();
                }
                EtsMigrateCartRulePostImport::normalizeCatalogSpecificPriceRules($import);
                break;
            case 'carrier':
                if (version_compare($import->source_version, '1.5.0.1', '<') && version_compare(_PS_VERSION_, '1.5.0.1', '>=')) {
                    EtsMigrateTools::updateCarrierReference();
                }
                break;
            case 'group':
                EtsMigrateTools::addGroups();
                if ($import->syncDefaultGroupConfiguration() === false) {
                    return self::buildErrorResponse($workflow, 'A post-import step failed.');
                }
                break;
            case 'delivery':
                EtsMigrateTools::updateDeliveryMultiShop();
                break;
            case 'currency':
                EtsMigrateTools::fillMissingCurrencyLang();
                break;
            case 'customer':
                EtsMigrateTools::syncCustomerGroup();
                break;
            case 'customer_group':
                EtsMigrateTools::repairCustomerGroups();
                break;
            case 'cart_rule_product_rule_value':
                EtsMigrateCartRulePostImport::normalize($import);
                break;
        }
    }

    private static function runOrderSchemaPatchOrContinue($workflow, $import, &$migrated_tables)
    {
        $res = EtsMigrateOrderSchemaPatch::run();
        if (isset($res['error']) && $res['error'] > 0) {
            self::$orders2TempExists = false;

            return [
                'error' => $res['msg'] ?? $workflow->module->l('Order migration failed. Check the migration log.', 'AdminETSEMMigrateController'),
            ];
        }
        if (!empty($res['continue'])) {
            self::$orders2TempExists = true;

            return self::buildOrderPatchContinueResponse($import, $migrated_tables);
        }

        self::$orders2TempExists = false;

        return null;
    }

    private static function orders2TempExists()
    {
        if (self::$orders2TempExists === null) {
            self::$orders2TempExists = (bool) EtsMigrateTools::tableExists('ets_em_orders_2');
        }

        return self::$orders2TempExists;
    }

    private static function buildOrderPatchContinueResponse($import, &$migrated_tables)
    {
        $remaining = [];
        foreach ($import->getGroupTables() as $groupTable) {
            if ($groupTable === 'order_detail') {
                continue;
            }
            if (!in_array($groupTable, $migrated_tables, true)) {
                $remaining[] = $groupTable;
            }
        }
        if ($remaining) {
            $import->setGroupTables($remaining);
            $import->setOffset(0);
        } else {
            $import->setGroupTables(['order_detail']);
            $import->setOffset(2147483647);
        }

        $pending = $import->getPendingMigrateTasks();
        $task = count($pending) > 0 ? $pending[0] : 'orders';
        $recordsTask = (int) $import->getCount();
        $infoTask = $import->getSourceMigrationMetadata($task);
        $percent = isset($infoTask['nb_group_table']) && (int) $infoTask['nb_group_table'] > 0
            ? ($recordsTask / $infoTask['nb_group_table']) * 100
            : 0;

        return [
            'ok' => 1,
            'continue' => 1,
            'migrating' => $task,
            'records_task' => $recordsTask,
            'percent' => (float) $percent,
            'task_complete' => 0,
        ];
    }

    private static function runOrderToolsPostImport()
    {
        EtsMigrateTools::insertOrderInvoice();
        EtsMigrateTools::updateOrderDetail();
        EtsMigrateTools::insertOrderCarrier();
        EtsMigrateTools::addOrderReference();
        if (version_compare(_PS_VERSION_, '1.5.0.13', '>=')) {
            EtsMigrateTools::updateOrderReference();
            EtsMigrateTools::insertOrderPayment();
        }
        if (version_compare(_PS_VERSION_, '1.7.5.0', '>=')) {
            EtsMigrateTools::updateOrderPayment();
        }
    }

    private static function buildErrorResponse($workflow, $message)
    {
        $normalized = rtrim(trim((string) $message), '!');
        if ($normalized !== '' && substr($normalized, -1) !== '.') {
            $normalized .= '.';
        }
        if ($normalized === '') {
            $normalized = 'A post-import step failed.';
        }
        if (stripos($normalized, 'Migration stopped') === false) {
            $normalized .= ' Migration stopped.';
        }

        return [
            'error' => $workflow->module->l($normalized, 'AdminETSEMMigrateController'),
        ];
    }
}
