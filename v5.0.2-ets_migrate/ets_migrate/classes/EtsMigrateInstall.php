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

class EtsMigrateInstall
{
    public const _INSTALL_SQL_FILE = 'install.sql';

    public const _UNINSTALL_SQL_FILE = 'uninstall.sql';

    public static $HOOKs = [];

    public static function installAfterParent(Ets_migrate $module)
    {
        return self::initHooks($module)
            && self::initTabs($module)
            && self::initConfigs($module);
    }

    public static function uninstallBeforeParent(Ets_migrate $module)
    {
        if (!EtsMigrateMultiStatementConfig::restoreDisabledStatus()) {
            return false;
        }
        $module->unregisterHook('displayBackOfficeHeader');
        if (!self::initTabs($module, true) || !self::initHooks($module, true)) {
            return false;
        }
        if (!self::initConfigs($module, true)) {
            return false;
        }
        if (!EtsMigrateDataImport::deleteAllMigrationConfiguration()) {
            return false;
        }
        if (!EtsMigrateDataImport::cleanDb()) {
            return false;
        }

        return self::executeSQL($module, self::_UNINSTALL_SQL_FILE);
    }

    public static function initTabs(Ets_migrate $module, $uninstall = false)
    {
        $prefix = 'AdminETSEM';
        $tabs = [
            [
                'class_name' => 'Migrate',
                'origin' => 'Migration API',
                'name' => $module->l('Migration API', 'AdminETSEMMigrateController'),
                'active' => 0,
            ],
            [
                'class_name' => 'Download',
                'origin' => 'Secure downloads',
                'name' => $module->l('Secure downloads', 'AdminETSEMDownloadController'),
                'active' => 0,
            ],
        ];
        $languages = Language::getLanguages(false);
        foreach ($tabs as $t) {
            $tab = Tab::getInstanceFromClassName($prefix . $t['class_name']);
            if (!$uninstall && $tab->id <= 0) {
                $tab = new Tab();
                $tab->class_name = $prefix . $t['class_name'];
                $tab->name = [];
                if (is_array($languages) && count($languages) > 0) {
                    foreach ($languages as $l) {
                        $tab->name[$l['id_lang']] = self::resolveModuleTranslation($module, $t['origin'], $l['iso_code']);
                    }
                }
                $tab->id_parent = 0;
                $tab->active = (bool) $t['active'];
                $tab->module = $module->name;
                if (!$tab->add()) {
                    return false;
                }
            } elseif ($uninstall && $tab->id > 0 && !$tab->delete()) {
                return false;
            }
        }

        return true;
    }

    public static function initHooks(Ets_migrate $module, $unregister = false)
    {
        if (self::$HOOKs) {
            foreach (self::$HOOKs as $hook) {
                if (!$unregister && !$module->registerHook($hook) || $unregister && !$module->unregisterHook($hook)) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function executeSQL(Ets_migrate $module, $sql_file)
    {
        if (!file_exists($dest = dirname(__FILE__) . '/../sql/' . $sql_file)) {
            return false;
        } elseif (!$sql = Tools::file_get_contents($dest)) {
            return false;
        }
        $sql = str_replace(['PREFIX_', 'ENGINE_TYPE'], [_DB_PREFIX_, _MYSQL_ENGINE_], $sql);
        $sql = preg_split("/;\s*[\r\n]+/", trim($sql));
        if ($sql) {
            foreach ($sql as $query) {
                $query = trim($query);
                if ($query === '') {
                    continue;
                }
                if (!Db::getInstance()->execute($query)) {
                    $message = Db::getInstance()->getMsgError();
                    PrestaShopLogger::addLog('ets_migrate: SQL execute failed (' . $sql_file . '): ' . $message, 3);

                    return false;
                }
            }
        }

        return true;
    }

    public static function initConfigs(Ets_migrate $module, $uninstall = false)
    {
        if ($configs = EtsMigrateConfigForm::getConfigs($module)) {
            $languages = [];
            foreach ($configs as $key => $config) {
                if ($uninstall) {
                    if (!Configuration::deleteByName($key)) {
                        return false;
                    }
                } else {
                    if (!count($languages)) {
                        $languages = Language::getLanguages(false);
                    }
                    $global = isset($config['global']) && $config['global'] ? 1 : 0;
                    if (isset($config['lang']) && $config['lang']) {
                        $values = [];
                        foreach ($languages as $l) {
                            $values[$l['id_lang']] = $config['default'] ?? '';
                        }
                        if (!EtsMigrateConfigForm::setConfigurationValue($key, $global, $values, true)) {
                            return false;
                        }
                    } else {
                        if (!EtsMigrateConfigForm::setConfigurationValue($key, $global, $config['default'] ?? '', true)) {
                            return false;
                        }
                    }
                }
            }
        }

        return true;
    }

    public static function resolveModuleTranslation(Ets_migrate $module, $origin, $lang, $specific = null)
    {
        if (is_array($lang)) {
            $iso_code = $lang['iso_code'];
        } elseif (is_object($lang)) {
            $iso_code = $lang->iso_code;
        } else {
            $language = new Language($lang);
            $iso_code = $language->iso_code;
        }

        $files_by_priority = _PS_MODULE_DIR_ . $module->name . '/translations/' . $iso_code . '.php';

        if (!file_exists($files_by_priority)) {
            return stripslashes($origin);
        }

        $string = preg_replace("/\\\*'/", "\'", $origin);
        $key = md5($string);
        $new_key = Tools::strtolower('<{' . $module->name . '}prestashop>' . ($specific ?: $module->name)) . '_' . $key;

        $translationContent = Tools::file_get_contents($files_by_priority);
        if ($translationContent === false) {
            return stripslashes($origin);
        }

        preg_match('/(\$_MODULE\[\'' . preg_quote($new_key) . '\'\]\s*=\s*\')(.*)(\';)/', $translationContent, $matches);

        if ($matches && isset($matches[2])) {
            return stripslashes($matches[2]);
        }

        return stripslashes($origin);
    }
}
