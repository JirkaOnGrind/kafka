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

class EtsMigrateConfigForm
{
    public static $currentIndex;
    public static $_configs;
    public static $ignore_configs = [
        'ETS_EM_PROCESS_MIGRATION',
        'ETS_EM_MIGRATION_DONE',
        'ETS_EM_MIGRATE_VERSION',
    ];
    public static $youtube_links = [
        'en' => 'https://youtu.be/7aZiPppdW-k?si=qyqeE-kKb7EGPcWG',
        'es' => 'https://youtu.be/mYX8HnEwh7I?si=6Mptqcq0TaMP_j7P',
        'fr' => 'https://youtu.be/vOKJeE6l_vM?si=B57n_xfXw4YARSg1',
        'it' => 'https://youtu.be/t_ltvNHNnHA?si=Xv12Atm6hq7uA-c_',
    ];

    public static function canConfigureModule(Ets_migrate $module)
    {
        if (!($module instanceof Module) || !(int) $module->id) {
            return false;
        }

        return (bool) $module->getPermission('configure');
    }

    public static function clampMigrateSpeed($value)
    {
        return max(100, min(9900, (int) $value));
    }

    public static function clampMediaSpeed($value)
    {
        return max(1, min(50, (int) $value));
    }

    public static function renderConfigurePage(Ets_migrate $module, $context)
    {
        if (!$module->active) {
            return $module->fetch('module:' . $module->name . '/views/templates/admin/partials/module-disable.tpl');
        }
        if (!self::canConfigureModule($module)) {
            return $module->displayError($module->l('Access denied.', 'AdminETSEMMigrateController'));
        }
        $downloadError = (string) Tools::getValue('download_error');
        $downloadErrorHtml = '';
        if ($downloadError === 'disabled') {
            $downloadErrorHtml = $module->displayError($module->l('The migration module is disabled.', 'AdminETSEMDownloadController'));
        } elseif ($downloadError === 'access_denied') {
            $downloadErrorHtml = $module->displayError($module->l('Access denied.', 'AdminETSEMDownloadController'));
        } elseif ($downloadError === 'invalid_file') {
            $downloadErrorHtml = $module->displayError($module->l('Invalid file name.', 'AdminETSEMDownloadController'));
        } elseif ($downloadError === 'file_not_found') {
            $downloadErrorHtml = $module->displayError($module->l('The requested file does not exist.', 'AdminETSEMDownloadController'));
        }
        static $tabMigrateId;
        static $tabDownloadId;
        if ($tabMigrateId === null) {
            $tabMigrateId = (int) Tab::getIdFromClassName('AdminETSEMMigrate');
        }
        if ($tabDownloadId === null) {
            $tabDownloadId = (int) Tab::getIdFromClassName('AdminETSEMDownload');
        }
        if (!$tabMigrateId || !$tabDownloadId) {
            if (EtsMigrateInstall::initTabs($module) === false) {
                return $module->displayError($module->l('Could not initialize migration admin tabs.', 'AdminETSEMMigrateController'));
            }
            $tabMigrateId = (int) Tab::getIdFromClassName('AdminETSEMMigrate');
            $tabDownloadId = (int) Tab::getIdFromClassName('AdminETSEMDownload');
        }
        self::$currentIndex = $context->link->getAdminLink('AdminModules', true, [], [
            'configure' => $module->name,
            'tab_module' => $module->tab,
            'module_name' => $module->name,
        ]);
        self::registerConfigurePageAssets($module, $context);

        return $downloadErrorHtml . self::renderForm($module, $context);
    }

    public static function addFieldError(&$errors, &$fieldErrors, $field, $message)
    {
        $errors[] = $message;
        if ($field !== '' && $field !== null && !isset($fieldErrors[$field])) {
            $fieldErrors[$field] = $message;
        }
    }

    private static function requiredFieldMessage(Ets_migrate $module, $key, $label)
    {
        if ($key === 'ETS_EM_DOMAIN') {
            return $module->l('Enter the source store URL, including http:// or https://.', 'AdminETSEMMigrateController');
        }
        if ($key === 'ETS_EM_ACCESS_TOKEN') {
            return $module->l('Enter the access token from PrestaShop Connector on the source store.', 'AdminETSEMMigrateController');
        }

        return sprintf($module->l('%s is required. Fill in this field to continue.', 'AdminETSEMMigrateController'), $label);
    }

    private static function invalidFieldMessage(Ets_migrate $module, $label)
    {
        return sprintf($module->l('%s is not valid. Check the value and try again.', 'AdminETSEMMigrateController'), $label);
    }

    private static function saveFieldFailedMessage(Ets_migrate $module, $label)
    {
        return sprintf($module->l('%s could not be saved. Check permissions and try again.', 'AdminETSEMMigrateController'), $label);
    }

    public static function getConfigs(Ets_migrate $module, $partial = null)
    {
        if (!self::$_configs) {
            $values = [
                [
                    'id' => 'active_on',
                    'value' => 1,
                    'label' => $module->l('Enabled', 'AdminETSEMMigrateController'),
                ],
                [
                    'id' => 'active_off',
                    'value' => 0,
                    'label' => $module->l('Disabled', 'AdminETSEMMigrateController'),
                ],
            ];
            $suppliers_array = [
                [
                    'id_supplier' => 0,
                    'name' => $module->l('None', 'AdminETSEMMigrateController'),
                ],
            ];
            if ($suppliers = Supplier::getSuppliers()) {
                foreach ($suppliers as $supplier) {
                    $suppliers_array[] = [
                        'id_supplier' => (int) $supplier['id_supplier'],
                        'name' => trim($supplier['name']),
                    ];
                }
            }
            $manufacturers_array = [
                [
                    'id_manufacturer' => 0,
                    'name' => $module->l('None', 'AdminETSEMMigrateController'),
                ],
            ];
            if ($manufacturers = Manufacturer::getManufacturers()) {
                foreach ($manufacturers as $manu) {
                    $manufacturers_array[] = [
                        'id_manufacturer' => (int) $manu['id_manufacturer'],
                        'name' => trim($manu['name']),
                    ];
                }
            }
            $domain = Configuration::get('PS_SSL_ENABLED') && Configuration::get('PS_SSL_ENABLED_EVERYWHERE') ? Tools::getShopDomainSsl() : Tools::getShopDomain();
            self::$_configs = [
                'ETS_EM_DOMAIN' => [
                    'type' => 'text',
                    'label' => $module->l('Source store URL', 'AdminETSEMMigrateController'),
                    'name' => 'ETS_EM_DOMAIN',
                    'hint' => $module->l('Enter source store URL (home page URL of the source store) including http:// or https://', 'AdminETSEMMigrateController'),
                    'required' => true,
                    'global' => true,
                    'step' => '1',
                    'regex' => [
                        [
                            'pattern' => '/^(http(?:|s)):\/\/.+?$/',
                            'bool' => true,
                            'error' => $module->l('Enter the source store URL, including http:// or https://.', 'AdminETSEMMigrateController'),
                        ],
                        [
                            'pattern' => '/^(http(?:|s)):\/\/' . str_replace('/', '\/', $domain . rtrim(__PS_BASE_URI__, '/')) . '\/?$/',
                            'bool' => false,
                            'error' => $module->l('Source store and target store cannot be the same.', 'AdminETSEMMigrateController'),
                        ],
                    ],
                    'placeholder' => $module->l('https://your-store.com/', 'AdminETSEMMigrateController'),
                ],
                'ETS_EM_ACCESS_TOKEN' => [
                    'type' => 'text',
                    'label' => $module->l('Access token', 'AdminETSEMMigrateController'),
                    'required' => true,
                    'name' => 'ETS_EM_ACCESS_TOKEN',
                    'hint' => $module->l('Copy the access token from the PrestaShop Connector module installed on the source store.', 'AdminETSEMMigrateController'),
                    'global' => true,
                    'step' => '1',
                    'placeholder' => $module->l('From PrestaShop Connector on the source store', 'AdminETSEMMigrateController'),
                ],
                'ETS_EM_DATA_TO_MIGRATE' => [
                    'type' => 'checkboxes',
                    'label' => $module->l('Data to migrate', 'AdminETSEMMigrateController'),
                    'name' => 'ETS_EM_DATA_TO_MIGRATE',
                    'global' => true,
                    'group_title' => $module->l('Data entities to migrate', 'AdminETSEMMigrateController'),
                    'step' => '2',
                ],
                'ETS_EM_MIGRATION_METHOD' => [
                    'type' => 'radio',
                    'label' => $module->l('Migration mode for selected data entities', 'AdminETSEMMigrateController'),
                    'name' => 'ETS_EM_MIGRATION_METHOD',
                    'values' => [
                        [
                            'id' => 'migrate_mode_clean_keep',
                            'value' => 1,
                            'label' => $module->l('Clean and keep source IDs', 'AdminETSEMMigrateController'),
                            'p' => $module->l('Best for fresh migration. Clears current data to ensure an exact clone with original IDs.', 'AdminETSEMMigrateController'),
                        ],
                        [
                            'id' => 'migrate_mode_clean_new',
                            'value' => 2,
                            'label' => $module->l('Clean and assign new IDs', 'AdminETSEMMigrateController'),
                            'p' => $module->l('Clears current data and re-imports everything with generated new IDs.', 'AdminETSEMMigrateController'),
                        ],
                        [
                            'id' => 'migrate_mode_keep_ignore',
                            'value' => 3,
                            'label' => $module->l('Keep existing — add missing only', 'AdminETSEMMigrateController'),
                            'p' => $module->l('Ideal for incremental updates. Preserves existing data and only adds missing records.', 'AdminETSEMMigrateController'),
                        ],
                        [
                            'id' => 'migrate_mode_keep_update',
                            'value' => 4,
                            'label' => $module->l('Keep existing — add and update duplicates', 'AdminETSEMMigrateController'),
                            'p' => $module->l('Synchronizes data. Existing records are updated with latest content from source.', 'AdminETSEMMigrateController'),
                        ],
                        [
                            'id' => 'migrate_mode_append',
                            'value' => 5,
                            'label' => $module->l('Keep existing — append all with new IDs', 'AdminETSEMMigrateController'),
                            'p' => $module->l('Safely appends data with new IDs. Recommended for a one-time import to prevent duplicate records.', 'AdminETSEMMigrateController'),
                        ],
                    ],
                    'default' => 1,
                    'global' => true,
                    'group' => 'option',
                    'form_group_class' => 'ets_em_migration_method ets_migrate_option',
                    'group_title' => $module->l('Migration mode', 'AdminETSEMMigrateController'),
                    'step' => '2',
                ],
                'ETS_EM_MIGRATE_IMAGES' => [
                    'type' => 'radio',
                    'label' => $module->l('Image migration', 'AdminETSEMMigrateController'),
                    'name' => 'ETS_EM_MIGRATE_IMAGES',
                    'form_group_class' => 'group-images ets_em_migrate_images ets_migrate_option',
                    'global' => true,
                    'group' => 'option',
                    'desc' => $module->l('Automatic download is slower but needs no manual file copy. Manual skips media transfer until you copy files after migration.', 'AdminETSEMMigrateController'),
                    'values' => [
                        [
                            'id' => 'migrate_images_auto',
                            'value' => 'auto',
                            'label' => $module->l('Migrate automatically', 'AdminETSEMMigrateController'),
                        ],
                        [
                            'id' => 'migrate_images_manual',
                            'value' => 'manual',
                            'label' => $module->l('Copy manually after database migration', 'AdminETSEMMigrateController'),
                        ],
                    ],
                    'default' => 'auto',
                    'step' => '2',
                    'image' => true,
                    'group_title' => $module->l('Images', 'AdminETSEMMigrateController'),
                ],
                'ETS_EM_GENERATE_PRODUCT_THUMBNAIL' => [
                    'type' => 'radio',
                    'label' => $module->l('Product thumbnail generation', 'AdminETSEMMigrateController'),
                    'name' => 'ETS_EM_GENERATE_PRODUCT_THUMBNAIL',
                    'form_group_class' => 'group-images ets_em_generate_product_thumbnail ets_migrate_option',
                    'global' => true,
                    'group' => 'option',
                    'values' => [
                        [
                            'id' => 'product_thumbnail_auto',
                            'value' => 'auto',
                            'label' => $module->l('Generate automatically', 'AdminETSEMMigrateController'),
                        ],
                        [
                            'id' => 'product_thumbnail_manual',
                            'value' => 'manual',
                            'label' => $module->l('Generate manually after migration', 'AdminETSEMMigrateController'),
                        ],
                    ],
                    'default' => 'auto',
                    'step' => '2',
                    'image' => true,
                ],
                'ETS_EM_MIGRATE_IMAGE_SPEED' => [
                    'type' => 'text',
                    'label' => $module->l('Images migrated per request', 'AdminETSEMMigrateController'),
                    'name' => 'ETS_EM_MIGRATE_IMAGE_SPEED',
                    'validate' => 'isUnsignedInt',
                    'form_group_class' => 'group-images ets_em_migrate_image_speed ets_migrate_option',
                    'col' => 4,
                    'default' => 5,
                    'group' => 'option',
                    'global' => true,
                    'desc' => $module->l('Number of media files transferred per request. Lower this value if the server times out or runs out of memory.', 'AdminETSEMMigrateController'),
                    'suffix' => $module->l('items', 'AdminETSEMMigrateController'),
                    'step' => '2',
                    'image' => true,
                    'partial' => 'speed',
                ],
                'ETS_EM_ATTACHMENTS_FILES' => [
                    'type' => 'radio',
                    'label' => $module->l('Attachments and files migration', 'AdminETSEMMigrateController'),
                    'name' => 'ETS_EM_ATTACHMENTS_FILES',
                    'form_group_class' => 'group-files ets_em_attachments_files ets_migrate_option',
                    'global' => true,
                    'group' => 'option',
                    'desc' => $module->l('Automatic download is slower but needs no manual file copy. Manual skips file transfer until you copy files after migration.', 'AdminETSEMMigrateController'),
                    'values' => [
                        [
                            'id' => 'attachments_files_auto',
                            'value' => 'auto',
                            'label' => $module->l('Migrate automatically', 'AdminETSEMMigrateController'),
                        ],
                        [
                            'id' => 'attachments_files_manual',
                            'value' => 'manual',
                            'label' => $module->l('Copy manually after database migration', 'AdminETSEMMigrateController'),
                        ],
                    ],
                    'default' => 'auto',
                    'step' => '2',
                    'file' => true,
                    'group_title' => $module->l('Attachments and files', 'AdminETSEMMigrateController'),
                ],
                'ETS_EM_ATTACHMENTS_FILES_SPEED' => [
                    'type' => 'text',
                    'label' => $module->l('Attachments and files migrated per request', 'AdminETSEMMigrateController'),
                    'name' => 'ETS_EM_ATTACHMENTS_FILES_SPEED',
                    'validate' => 'isUnsignedInt',
                    'form_group_class' => 'group-files ets_em_attachments_files_speed ets_migrate_option',
                    'col' => 4,
                    'default' => 5,
                    'group' => 'option',
                    'global' => true,
                    'desc' => $module->l('Number of media files transferred per request. Lower this value if the server times out or runs out of memory.', 'AdminETSEMMigrateController'),
                    'suffix' => $module->l('items', 'AdminETSEMMigrateController'),
                    'step' => '2',
                    'file' => true,
                    'partial' => 'speed',
                ],
                'ETS_EM_MIGRATE_EMPTY_CART' => [
                    'type' => 'switch',
                    'is_bool' => true,
                    'label' => $module->l('Migrate empty shopping carts', 'AdminETSEMMigrateController'),
                    'name' => 'ETS_EM_MIGRATE_EMPTY_CART',
                    'form_group_class' => 'ets_em_migrate_empty_cart ets_migrate_option',
                    'desc' => $module->l('When disabled, empty carts are skipped. Only applies when Orders and shopping carts are selected.', 'AdminETSEMMigrateController'),
                    'values' => $values,
                    'default' => 1,
                    'group' => 'option',
                    'global' => true,
                    'step' => '2',
                    'group_title' => $module->l('Orders and carts', 'AdminETSEMMigrateController'),
                ],
                'ETS_EM_SUPPLIER_DEFAULT' => [
                    'type' => 'select',
                    'label' => $module->l('Default supplier', 'AdminETSEMMigrateController'),
                    'name' => 'ETS_EM_SUPPLIER_DEFAULT',
                    'options' => [
                        'query' => $suppliers_array,
                        'id' => 'id_supplier',
                        'name' => 'name',
                    ],
                    'form_group_class' => 'ets_migrate_option ets_em_supplier_default',
                    'desc' => $module->l('Used for products with no supplier when Suppliers are not migrated. Choose None to leave the field empty.', 'AdminETSEMMigrateController'),
                    'global' => true,
                    'step' => '2',
                    'field' => 'id_supplier',
                    'group_title' => $module->l('Catalog defaults', 'AdminETSEMMigrateController'),
                ],
                'ETS_EM_MANUFACTURER_DEFAULT' => [
                    'type' => 'select',
                    'label' => $module->l('Default manufacturer', 'AdminETSEMMigrateController'),
                    'name' => 'ETS_EM_MANUFACTURER_DEFAULT',
                    'options' => [
                        'query' => $manufacturers_array,
                        'id' => 'id_manufacturer',
                        'name' => 'name',
                    ],
                    'form_group_class' => 'ets_migrate_option ets_em_manufacturer_default',
                    'desc' => $module->l('Used for products with no manufacturer when Manufacturers are not migrated. Choose None to leave the field empty.', 'AdminETSEMMigrateController'),
                    'global' => true,
                    'step' => '2',
                    'field' => 'id_manufacturer',
                ],
                'ETS_EM_MIGRATE_SPEED' => [
                    'type' => 'range',
                    'label' => $module->l('Items migrated per request', 'AdminETSEMMigrateController'),
                    'desc' => $module->l('Number of records transferred per request. Reduce this value if the server is slow or requests time out.', 'AdminETSEMMigrateController'),
                    'name' => 'ETS_EM_MIGRATE_SPEED',
                    'validate' => 'isUnsignedInt',
                    'default' => 5000,
                    'min' => 100,
                    'max' => 9900,
                    'col' => 7,
                    'form_group_class' => 'ets_range_speed ets_migrate_option',
                    'group_title' => $module->l('Migration speed', 'AdminETSEMMigrateController'),
                    'global' => true,
                    'step' => '2',
                    'partial' => 'speed',
                ],
                'ETS_EM_SOURCE_COOKIE_KEY' => [
                    'type' => 'hidden',
                    'label' => $module->l('Source store cookie key', 'AdminETSEMMigrateController'),
                    'hint' => $module->l('Paste the source cookie key from app/config/parameters.php or app/config/settings.inc.php (depending on PrestaShop version) for Password Keeper.', 'AdminETSEMMigrateController'),
                    'placeholder' => $module->l('Paste cookie_key from parameters.php or settings.inc.php', 'AdminETSEMMigrateController'),
                    'form_group_class' => 'ets_migrate_option',
                    'name' => 'ETS_EM_SOURCE_COOKIE_KEY',
                    'global' => true,
                    'step' => '2',
                ],
                'ETS_EM_MIGRATE_VERSION' => [
                    'type' => 'hidden',
                    'label' => $module->l('Migration version', 'AdminETSEMMigrateController'),
                    'form_group_class' => 'ets_migrate_option',
                    'name' => 'ETS_EM_MIGRATE_VERSION',
                    'global' => true,
                    'step' => '2',
                ],
                'ETS_EM_PROCESS_MIGRATION' => [
                    'type' => 'html',
                    'label' => $module->l('Process migration', 'AdminETSEMMigrateController'),
                    'name' => 'ETS_EM_PROCESS_MIGRATION',
                    'global' => true,
                    'step' => '3',
                ],
                'ETS_EM_MIGRATION_DONE' => [
                    'type' => 'html',
                    'label' => $module->l('Done', 'AdminETSEMMigrateController'),
                    'name' => 'ETS_EM_MIGRATION_DONE',
                    'global' => true,
                    'step' => '4',
                ],
            ];
        }
        if ($partial !== null && trim($partial) !== '') {
            $fields_set = [];
            foreach (self::$_configs as $key => $config) {
                if (isset($config['partial']) && trim($config['partial']) === trim($partial)) {
                    $fields_set[$key] = $config;
                }
            }

            return $fields_set;
        }

        return self::$_configs;
    }

    public static function loadConfigurationDefaultsForForm(Ets_migrate $module)
    {
        $fields = [];
        if ($configs = self::getConfigs($module)) {
            foreach ($configs as $key => $config) {
                if (isset($config['field']) && trim($config['field']) !== '') {
                    $fields[$config['field']] = Configuration::getGlobalValue($key);
                }
            }
        }

        return $fields;
    }

    public static function getConfigurationFormValues(Ets_migrate $module, $context, $submit_action = '', $partial = null)
    {
        $fields = [];
        if ($configs = self::getConfigs($module, $partial)) {
            $languages = Language::getLanguages(false);
            if (trim($submit_action) !== '' && Tools::isSubmit($submit_action)) {
                foreach ($configs as $config) {
                    $key = $config['name'];
                    if (isset($config['lang']) && $config['lang']) {
                        foreach ($languages as $l) {
                            $fields[$key][$l['id_lang']] = Tools::getValue($key . '_' . $l['id_lang'], $config['default'] ?? '');
                        }
                    } elseif ($config['type'] == 'select' && isset($config['multiple']) && $config['multiple']) {
                        $fields[$key . ($config['type'] == 'select' ? '[]' : '')] = Tools::getValue($key, []);
                    } elseif ($config['type'] == 'group' || $config['type'] == 'checkboxes') {
                        $fields[$key] = Tools::getValue($key, []);
                    } else {
                        $fields[$key] = Tools::getValue($key, $config['default'] ?? '');
                    }
                }
            } else {
                foreach ($configs as $config) {
                    $key = $config['name'];
                    $global = !empty($config['global']) ? 1 : 0;
                    if (isset($config['lang']) && $config['lang']) {
                        foreach ($languages as $l) {
                            $fields[$key][$l['id_lang']] = self::getConfigurationValue($module, $context, $key, $global, $l['id_lang'], $config['default'] ?? '');
                        }
                    } elseif ($config['type'] == 'select' && isset($config['multiple']) && $config['multiple']) {
                        $fields[$key . ($config['type'] == 'select' ? '[]' : '')] = ($result = self::getConfigurationValue($module, $context, $key, $global)) != '' ? explode(',', $result) : [];
                    } elseif ($config['type'] == 'group' || $config['type'] == 'checkboxes') {
                        $fields[$key] = ($result = self::getConfigurationValue($module, $context, $key, $global)) != '' ? explode(',', $result) : [];
                    } else {
                        $fields[$key] = self::getConfigurationValue($module, $context, $key, $global, null, $config['default'] ?? '');
                    }
                }
            }
        }

        return $fields;
    }

    public static function getConfigurationValue(Ets_migrate $module, $context, $key, $global = false, $idLang = null, $default = '')
    {
        return Configuration::hasKey($key, $idLang, $global ? null : $context->shop->id_shop_group, $global ? null : $context->shop->id) ? ($global ? Configuration::getGlobalValue($key, $idLang) : Configuration::get($key, $idLang)) : $default;
    }

    public static function setConfigurationValue($key, $global, $values, $html = false)
    {
        return $global ? Configuration::updateGlobalValue($key, $values, $html) : Configuration::updateValue($key, $values, $html);
    }

    public static function saveConfiguration(Ets_migrate $module, $context, &$fieldErrors = null)
    {
        $errors = [];
        $fieldErrors = is_array($fieldErrors) ? $fieldErrors : [];
        $partial = trim(Tools::getValue('partial'));
        $step = (int) Tools::getValue('current_step', 0);

        if ($configs = self::getConfigs($module, $partial)) {
            $languages = Language::getLanguages(false);
            $id_lang_default = (int) Configuration::get('PS_LANG_DEFAULT');
            foreach ($configs as $key => $config) {
                if (in_array($key, self::$ignore_configs)) {
                    continue;
                }
                if ($step === 1 && isset($config['step']) && (string) $config['step'] !== '1') {
                    continue;
                }
                $label = $config['label'] ?? $key;
                if (isset($config['lang']) && $config['lang']) {
                    if (
                        isset($config['required'])
                        && $config['required']
                        && $config['type'] != 'switch'
                        && trim(Tools::getValue($key . '_' . $id_lang_default, '')) == ''
                    ) {
                        self::addFieldError($errors, $fieldErrors, $key, self::requiredFieldMessage($module, $key, $label));
                    }
                } else {
                    if (
                        isset($config['required'])
                        && $config['required']
                        && $config['type'] != 'switch'
                        && trim(Tools::getValue($key, '')) == ''
                    ) {
                        self::addFieldError($errors, $fieldErrors, $key, self::requiredFieldMessage($module, $key, $label));
                    } elseif (
                        isset($config['regex'])
                        && is_array($config['regex'])
                        && count($config['regex']) > 0
                    ) {
                        foreach ($config['regex'] as $regex) {
                            if (
                                isset($regex['bool'])
                                && isset($regex['pattern'])
                                && trim($regex['pattern']) !== ''
                            ) {
                                if ($regex['bool'] && !preg_match($regex['pattern'], trim(Tools::getValue($key))) || !$regex['bool'] && preg_match($regex['pattern'], trim(Tools::getValue($key)))) {
                                    $regexMessage = $regex['error'] ?? self::invalidFieldMessage($module, $label);
                                    self::addFieldError($errors, $fieldErrors, $key, $regexMessage);
                                }
                            }
                        }
                        if ($key === 'ETS_EM_DOMAIN' && !EtsMigrateUrlSecurity::isPublicHttpUrl(trim(Tools::getValue('ETS_EM_DOMAIN')))) {
                            self::addFieldError($errors, $fieldErrors, $key, $module->l('Use a valid http or https source store URL.', 'AdminETSEMMigrateController'));
                        }
                    } elseif (
                        isset($config['validate'])
                        && method_exists('Validate', $config['validate'])
                        && in_array($config['validate'], ['isUnsignedInt', 'isUrl', 'isGenericName', 'isCleanHtml', 'isConfigName', 'isString'], true)
                    ) {
                        $value = trim(Tools::getValue($key));
                        $ok = false;
                        switch ($config['validate']) {
                            case 'isUnsignedInt':
                                $ok = Validate::isUnsignedInt($value);
                                break;
                            case 'isUrl':
                                $ok = Validate::isUrl($value);
                                break;
                            case 'isGenericName':
                                $ok = Validate::isGenericName($value);
                                break;
                            case 'isCleanHtml':
                                $ok = Validate::isCleanHtml($value);
                                break;
                            case 'isConfigName':
                                $ok = Validate::isConfigName($value);
                                break;
                            case 'isString':
                                $ok = Validate::isString($value);
                                break;
                            default:
                                $ok = false;
                        }
                        if (!$ok) {
                            self::addFieldError($errors, $fieldErrors, $key, self::invalidFieldMessage($module, $label));
                        }
                    } elseif (
                        $config['type'] !== 'checkboxes'
                        && !Validate::isCleanHtml(trim(Tools::getValue($key)))
                    ) {
                        self::addFieldError($errors, $fieldErrors, $key, self::invalidFieldMessage($module, $label));
                    }
                }
            }
            if (!count($errors)) {
                foreach ($configs as $key => $config) {
                    if (in_array($key, self::$ignore_configs)) {
                        continue;
                    }
                    if ($step === 1 && isset($config['step']) && (string) $config['step'] !== '1') {
                        continue;
                    }
                    $label = $config['label'] ?? $key;
                    $global = isset($config['global']) && $config['global'] ? 1 : 0;
                    if (isset($config['lang']) && $config['lang']) {
                        $values = [];
                        foreach ($languages as $lang) {
                            if ($config['type'] == 'switch') {
                                $values[$lang['id_lang']] = (int) trim(Tools::getValue($key . '_' . $lang['id_lang'])) ? 1 : 0;
                            } else {
                                $values[$lang['id_lang']] = trim(Tools::getValue($key . '_' . $lang['id_lang'])) ? trim(Tools::getValue($key . '_' . $lang['id_lang'])) : trim(Tools::getValue($key . '_' . $id_lang_default));
                            }
                        }
                        if (!self::setConfigurationValue($key, $global, $values, true)) {
                            self::addFieldError($errors, $fieldErrors, $key, self::saveFieldFailedMessage($module, $label));
                        }
                    } else {
                        if ($config['type'] == 'switch') {
                            if (!self::setConfigurationValue($key, $global, (int) trim(Tools::getValue($key)) ? 1 : 0, true)) {
                                self::addFieldError($errors, $fieldErrors, $key, self::saveFieldFailedMessage($module, $label));
                            }
                        } elseif ($config['type'] == 'checkboxes' || $config['type'] == 'select' && isset($config['multiple']) && $config['multiple']) {
                            if (!self::setConfigurationValue($key, $global, implode(',', Tools::getValue($key, [])), true)) {
                                self::addFieldError($errors, $fieldErrors, $key, self::saveFieldFailedMessage($module, $label));
                            }
                        } else {
                            $val = trim(Tools::getValue($key));
                            if ($key === 'ETS_EM_MIGRATE_SPEED') {
                                $val = self::clampMigrateSpeed(Tools::getValue($key));
                            } elseif ($key === 'ETS_EM_ATTACHMENTS_FILES_SPEED' || $key === 'ETS_EM_MIGRATE_IMAGE_SPEED') {
                                $val = self::clampMediaSpeed(Tools::getValue($key));
                            }
                            if (!self::setConfigurationValue($key, $global, $val, true)) {
                                self::addFieldError($errors, $fieldErrors, $key, self::saveFieldFailedMessage($module, $label));
                            }
                        }
                    }
                }

                if (trim($partial) === '' && $step !== 1) {
                    $import = EtsMigrateDataImport::getInstance($context);
                    $import->commitMigrateOptions($module, $errors, $fieldErrors);
                }
            }
        }

        return $errors;
    }

    public static function renderForm(Ets_migrate $module, $context, $partial = null)
    {
        if ($configs = self::getConfigs($module, $partial)) {
            $fields_form_1 = [
                'form' => [
                    'legend' => [
                        'title' => $module->l('Migrate', 'AdminETSEMMigrateController'),
                    ],
                    'input' => $configs,
                ],
            ];
            $helper = new HelperForm();
            $helper->show_toolbar = false;
            $helper->table = 'module';
            $psLangDefault = (int) Configuration::get('PS_LANG_DEFAULT');
            $psBoAllowEmployeeFormLang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
            $lang = new Language($psLangDefault);
            $helper->default_form_language = $lang->id;
            $helper->module = $module;
            $helper->allow_employee_form_lang = $psBoAllowEmployeeFormLang ? $psBoAllowEmployeeFormLang : 0;
            $helper->identifier = 'id_module';
            $helper->submit_action = 'submitConfig';
            $helper->currentIndex = self::$currentIndex;
            $helper->token = $context->controller->token;
            $iso_code = Tools::strtolower($context->language->iso_code);
            $documentation_path = dirname(__FILE__) . '/../docs/readme_' . $iso_code . '.pdf';
            $document_file = 'readme_' . (file_exists($documentation_path) ? $iso_code : 'en') . '.pdf';
            $fieldsValue = self::getConfigurationFormValues($module, $context, $helper->submit_action, $partial);
            $resources = EtsMigrateResourceCatalog::getResources(null, true);
            $modulePath = $module->getPathUri();
            $helper->tpl_vars = [
                'fields_value' => $fieldsValue,
                'languages' => $context->controller->getLanguages(),
                'id_language' => $context->language->id,
                'img_path' => $modulePath . 'views/img/',
                'steps' => [
                    'step1' => $module->l('Source', 'AdminETSEMMigrateController'),
                    'step2' => $module->l('Migration options', 'AdminETSEMMigrateController'),
                    'step3' => $module->l('Process migration', 'AdminETSEMMigrateController'),
                    'step4' => $module->l('Done — enjoy your new store', 'AdminETSEMMigrateController'),
                ],
                'current_step' => (int) Tools::getValue('current_step', 1),
                'resources' => $resources,
                'download_plugin_link' => $context->link->getAdminLink('AdminETSEMDownload', true),
                'download_connector_link' => $context->link->getAdminLink('AdminETSEMDownload', true, [], ['file' => 'ets_migrate_connector.zip']),
                'download_password_keeper_link' => $context->link->getAdminLink('AdminETSEMDownload', true, [], ['file' => 'ets_passwordkeeper.zip']),
                'download_documentation_link' => $context->link->getAdminLink('AdminETSEMDownload', true, [], ['file' => $document_file]),
                'page_index' => $context->link->getPageLink('index'),
                'support_link' => 'https://addons.prestashop.com/en/contact-us?id_product=32298',
                'document_file' => $document_file,
                'partial' => $partial,
                'product_thumb_link' => $context->link->getAdminLink('AdminImages', true),
                'youtube_link' => self::$youtube_links[$iso_code] ?? self::$youtube_links['en'],
                'source_cookie_key' => (string) (Configuration::getGlobalValue('ETS_EM_SOURCE_COOKIE_KEY') ?: Configuration::getGlobalValue('ETS_NEW_COOKIE_KEY')),
            ];
            $import = EtsMigrateDataImport::getInstance($context)->init();
            if ($import->getMigrated()) {
                $sourceInfo = $import->getSourceMigrationMetadata();
                $infos = $sourceInfo;
                if (isset($infos['cookie_key'])) {
                    unset($infos['cookie_key']);
                }
                $infos['confirm'] = 1;
                $migrateReuse = [
                    'info' => $sourceInfo,
                    'resources' => $resources,
                ];
                if ($partial === null || trim((string) $partial) === '') {
                    $migrateReuse['configs'] = $configs;
                    $migrateReuse['configs_value'] = $fieldsValue;
                }
                $infos['migrate'] = self::renderMigrateTemplates($module, $context, 'migrate', $migrateReuse);
                $helper->tpl_vars['current_step'] = 2;
                $helper->tpl_vars['infos'] = $infos;
                if (isset($sourceInfo['source_shops']) && is_array($sourceInfo['source_shops'])) {
                    $sourceShops = $sourceInfo['source_shops'];
                    $targetShops = Shop::getShops();
                    $savedMapping = $import->loadMappingShops();
                    $savedMapping = is_array($savedMapping) ? $savedMapping : [];
                    $mappingRows = [];
                    $sourceIds = [];
                    $firstTargetId = 0;
                    foreach ($targetShops as $targetShop) {
                        $targetShopId = (int) ($targetShop['id_shop'] ?? 0);
                        if ($targetShopId > 0) {
                            $firstTargetId = $targetShopId;
                            break;
                        }
                    }
                    foreach ($sourceShops as $sourceShop) {
                        $sourceId = (int) ($sourceShop['id_shop'] ?? 0);
                        if ($sourceId <= 0) {
                            continue;
                        }
                        $sourceIds[] = $sourceId;
                        if (array_key_exists($sourceId, $savedMapping)) {
                            $selectedTargetId = (int) $savedMapping[$sourceId];
                        } elseif (isset($targetShops[$sourceId])) {
                            $selectedTargetId = $sourceId;
                        } else {
                            $selectedTargetId = $firstTargetId;
                        }
                        $mappingRows[] = [
                            'id_shop_source' => $sourceId,
                            'name_source' => $sourceShop['name'] ?? ('Shop ' . $sourceId),
                            'id_shop_target' => $selectedTargetId,
                        ];
                    }
                    $helper->tpl_vars['source_shops'] = $sourceShops;
                    $helper->tpl_vars['target_shops'] = $targetShops;
                    $helper->tpl_vars['shop_mapping_rows'] = $mappingRows;
                    $helper->tpl_vars['shop_mapping_ids'] = implode(',', $sourceIds);
                }
            }
            $html = $helper->generateForm([$fields_form_1]);

            return $html;
        }

        return '';
    }

    public static function renderMigrateTemplates(Ets_migrate $module, $context, $type = 'all', $reuse = null)
    {
        $data = EtsMigrateDataImport::getInstance($context);
        $reuse = is_array($reuse) ? $reuse : [];
        $tpl_vars = [
            'info' => array_key_exists('info', $reuse) ? $reuse['info'] : $data->getSourceMigrationMetadata(),
            'configs' => array_key_exists('configs', $reuse) ? $reuse['configs'] : self::getConfigs($module),
            'resources' => array_key_exists('resources', $reuse) ? $reuse['resources'] : EtsMigrateResourceCatalog::getResources(null, true),
            'configs_value' => array_key_exists('configs_value', $reuse) ? $reuse['configs_value'] : self::getConfigurationFormValues($module, $context),
        ];
        $module->assignTemplateVars($tpl_vars);
        $ret = [];
        $type = trim($type);
        if ($type === 'all' || $type === 'migrate') {
            $supplierDefaultId = (int) Configuration::getGlobalValue('ETS_EM_SUPPLIER_DEFAULT');
            $manufacturerDefaultId = (int) Configuration::getGlobalValue('ETS_EM_MANUFACTURER_DEFAULT');
            $module->assignTemplateVars([
                'ETS_EM_SUPPLIER_DEFAULT' => $supplierDefaultId > 0 ? Supplier::getNameById($supplierDefaultId) : $module->l('None', 'AdminETSEMMigrateController'),
                'ETS_EM_MANUFACTURER_DEFAULT' => $manufacturerDefaultId > 0 ? Manufacturer::getNameById($manufacturerDefaultId) : $module->l('None', 'AdminETSEMMigrateController'),
            ]);
            $ret['migrate'] = $module->fetch('module:' . $module->name . '/views/templates/admin/partials/bo-migrate.tpl');
        }
        if ($type === 'all' || $type === 'process') {
            $migrated_table = $data->getMigrated();
            $diff = $data->getPendingMigrateTasks();
            $module->assignTemplateVars([
                'migrated_tables' => $migrated_table,
                'img_path' => $module->getPathUri() . 'views/img/',
                'count' => (int) $data->getCount(),
                'migrating' => is_array($diff) && count($diff) ? array_shift($diff) : '',
            ]);
            $ret['process'] = $module->fetch('module:' . $module->name . '/views/templates/admin/partials/bo-process-migrate.tpl');
        }

        return $type !== 'all' ? array_shift($ret) : $ret;
    }

    public static function registerConfigurePageAssets(Ets_migrate $module, $context)
    {
        $modulePath = $module->getPathUri();
        $cssVersion = (int) filemtime(_PS_MODULE_DIR_ . $module->name . '/views/css/back.css');
        $javaScriptVersion = (int) filemtime(_PS_MODULE_DIR_ . $module->name . '/views/js/back.js');
        $context->controller->addJqueryPlugin('growl');
        $context->controller->addCSS($modulePath . 'views/css/back.css?v=' . $cssVersion);
        $context->controller->addJS($modulePath . 'views/js/back.js?v=' . $javaScriptVersion);
        Media::addJsDef([
            'ETS_EM_REQUEST_URL' => $context->link->getAdminLink('AdminETSEMMigrate', true),
            'ets_em_copied_translate' => $module->l('Copied', 'AdminETSEMMigrateController'),
            'ets_em_do_not_import' => $module->l('--- Do not import ---', 'AdminETSEMMigrateController'),
            'ets_em_create_shop' => $module->l('--- Create shop ---', 'AdminETSEMMigrateController'),
            'ets_em_mapping_shop_invalid' => $module->l('Shop mapping is invalid. Please select target shop or create new shop.', 'AdminETSEMMigrateController'),
            'ets_em_migrate' => $module->l('Migrate', 'AdminETSEMMigrateController'),
            'ets_em_migrate_now' => $module->l('Migrate now', 'AdminETSEMMigrateController'),
            'ets_em_new_migration' => $module->l('Start a new migration? Current progress will be cleared.', 'AdminETSEMMigrateController'),
            'ets_em_cancel_migration' => $module->l('Cancel this migration? Progress will be lost and cannot be resumed.', 'AdminETSEMMigrateController'),
            'ets_em_migration_queue_empty' => $module->l('Migration queue is empty. Click Play to retry or start a New migration.', 'AdminETSEMMigrateController'),
            'ets_em_migrate_data_empty' => $module->l('Select at least one data type to migrate.', 'AdminETSEMMigrateController'),
            'ets_em_leave_migration' => $module->l('A migration is in progress. Leaving this page may interrupt the process.', 'AdminETSEMMigrateController'),
            'ets_em_ajax_error' => $module->l('An unexpected error occurred. Please try again.', 'AdminETSEMMigrateController'),
            'ets_em_confirm_clean' => $module->l('Selected items on this store will be refreshed to match your source store. Are you ready to begin?', 'AdminETSEMMigrateController'),
            'ets_em_confirm_clean_title' => $module->l('Start migration?', 'AdminETSEMMigrateController'),
            'ets_em_confirm_clean_message' => $module->l('Selected items on this store will be refreshed to match your source store. Are you ready to begin?', 'AdminETSEMMigrateController'),
            'ets_em_confirm_clean_btn' => $module->l('Start migration', 'AdminETSEMMigrateController'),
            'ets_em_confirm_clean_cancel' => $module->l('Review settings', 'AdminETSEMMigrateController'),
            'ets_em_cancel_migration_title' => $module->l('Stop migration?', 'AdminETSEMMigrateController'),
            'ets_em_cancel_migration_message' => $module->l('Are you sure you want to stop? Current progress will be stopped and you can restart anytime.', 'AdminETSEMMigrateController'),
            'ets_em_cancel_migration_btn' => $module->l('Stop migration', 'AdminETSEMMigrateController'),
            'ets_em_cancel_migration_cancel' => $module->l('Keep running', 'AdminETSEMMigrateController'),
            'ets_em_new_migration_title' => $module->l('Start a new migration?', 'AdminETSEMMigrateController'),
            'ets_em_new_migration_message' => $module->l('This will reset your current steps so you can configure a fresh migration.', 'AdminETSEMMigrateController'),
            'ets_em_new_migration_btn' => $module->l('Start new', 'AdminETSEMMigrateController'),
            'ets_em_new_migration_cancel' => $module->l('Stay here', 'AdminETSEMMigrateController'),
            'ets_em_empty_data_title' => $module->l('Select data to migrate', 'AdminETSEMMigrateController'),
            'ets_em_empty_data_message' => $module->l('Please select at least one item (such as products, customers, or orders) to continue.', 'AdminETSEMMigrateController'),
            'ets_em_modal_btn_understood' => $module->l('Understood', 'AdminETSEMMigrateController'),
            'ETS_EM_KEEP_OLD_ID_MODES' => [
                EtsMigrateDataImport::MIGRATE_MODE_CLEAN_KEEP_ID,
                EtsMigrateDataImport::MIGRATE_MODE_KEEP_IGNORE,
                EtsMigrateDataImport::MIGRATE_MODE_KEEP_OVERWRITE,
            ],
        ]);
    }

    public static function linkLocalizationPack(Ets_migrate $module, $context, $iso_code, $language_name)
    {
        $module->assignTemplateVars([
            'iso_code' => $iso_code,
            'language_name' => $language_name,
            'link' => $context->link->getAdminLink('AdminLocalization', true),
        ]);

        return $module->fetch('module:' . $module->name . '/views/templates/admin/partials/bo-localization-pack.tpl');
    }
}
