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

class EtsMigrateWorkflow
{
    public $module;
    public $controller;
    public $context;
    public $migrateLockHeld = false;

    public function __construct($module, $controller, $context)
    {
        $this->module = $module;
        $this->controller = $controller;
        $this->context = $context;
    }

    public function acquireMigrateLock()
    {
        $lockKey = 'ets_em_migrate_queue';
        $lockAcquired = Db::getInstance()->getValue("SELECT GET_LOCK('" . pSQL($lockKey) . "', 0)", false);
        if ($lockAcquired === null || $lockAcquired === false) {
            return [
                'error' => $this->module->l('Could not acquire migration lock. Please try again.', 'AdminETSEMMigrateController'),
            ];
        }
        if ((int) $lockAcquired === 0) {
            if (function_exists('http_response_code')) {
                http_response_code(429);
            }

            return [
                'error' => $this->module->l('Migration is already running. Please wait.', 'AdminETSEMMigrateController'),
            ];
        }
        $this->migrateLockHeld = true;

        return true;
    }

    public function releaseMigrateLock()
    {
        $lockKey = 'ets_em_migrate_queue';
        $released = Db::getInstance()->getValue("SELECT RELEASE_LOCK('" . pSQL($lockKey) . "')", false);
        if ($released === null || $released === false || (int) $released === 0) {
            return false;
        }
        $this->migrateLockHeld = false;

        return true;
    }

    protected function getMigrateTableAllowlist($task, $migrate_tables, $import, $resource = null)
    {
        $allowlist = [];
        if (is_array($migrate_tables)) {
            foreach ($migrate_tables as $table) {
                $table = trim((string) $table);
                if ($table !== '') {
                    $allowlist[$table] = true;
                }
            }
        }
        $queue = $import instanceof EtsMigrateDataImport ? $import->getGroupTables() : [];
        if (is_array($queue)) {
            foreach ($queue as $table) {
                $table = trim((string) $table);
                if ($table !== '') {
                    $allowlist[$table] = true;
                }
            }
        }
        if (!is_array($resource)) {
            $resource = EtsMigrateResourceCatalog::getResources($task);
        }
        if (is_array($resource) && isset($resource['tables']) && is_array($resource['tables'])) {
            foreach ($resource['tables'] as $table) {
                $table = trim((string) $table);
                if ($table !== '') {
                    $allowlist[$table] = true;
                }
            }
        }

        return $allowlist;
    }

    public function getMigrateQueueTasks($import)
    {
        $dataToMigrate = $import instanceof EtsMigrateDataImport ? $import->getDataToMigrate() : [];
        if (!is_array($dataToMigrate)) {
            return [];
        }
        $queue = [];
        foreach ($dataToMigrate as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $queue[] = $item;
            }
        }

        return $queue;
    }

    public function resumeMigration($step)
    {
        $step = (int) $step;
        if ($step === 1 || $step === 2) {
            $import = EtsMigrateDataImport::getInstance($this->context);
            $import->init();
            $migrated = $import->getMigrated();
            if (is_array($migrated) && count($migrated) > 0) {
                $diff = $import->getPendingMigrateTasks();
                if (count($diff) > 0) {
                    $task = array_shift($diff);
                    Configuration::updateGlobalValue('ETS_EM_MIGRATE_SPEED', EtsMigrateConfigForm::clampMigrateSpeed(Tools::getValue('ETS_EM_MIGRATE_SPEED')));
                    $info = $import->getSourceMigrationMetadata($task);

                    return [
                        'continue' => 1,
                        'process' => EtsMigrateConfigForm::renderMigrateTemplates($this->module, $this->context, 'process'),
                        'step' => 3,
                        'migrating' => $task,
                        'percent' => isset($info['nb_group_table']) && (int) $info['nb_group_table'] > 0 ? $import->getCount() * 100 / $info['nb_group_table'] : 0,
                    ];
                }

                return $this->processFinished('finished', $import);
            }
        }

        return [
            'error' => $this->module->l('Cannot resume migration. Start a new migration.', 'AdminETSEMMigrateController'),
        ];
    }

    public function processConnectorQueue()
    {
        $lockResult = $this->acquireMigrateLock();
        if ($lockResult !== true) {
            return $lockResult;
        }
        $import = EtsMigrateDataImport::getInstance($this->context)->init();
        if (!empty(EtsMigrateDataImport::$shop_mapping_load_failed)) {
            return [
                'error' => $this->module->l('Shop mapping is unavailable. Save shop mapping and try again.', 'AdminETSEMMigrateController'),
            ];
        }
        static $configurationDefaults = null;
        if ($configurationDefaults === null) {
            $configurationDefaults = EtsMigrateConfigForm::loadConfigurationDefaultsForForm($this->module);
        }
        $import->setFieldsDefault($configurationDefaults);

        $queue = $this->getMigrateQueueTasks($import);
        if (count($queue) === 0) {
            return [
                'error' => $this->module->l('Migration queue is empty or invalid.', 'AdminETSEMMigrateController'),
            ];
        }

        static $terminalTasks = ['images' => true, 'files' => true, 'finished' => true];
        $queueMap = array_fill_keys($queue, true);
        $array_diff = array_diff($queue, $import->getMigrated());
        if (count($array_diff) > 0) {
            while (count($array_diff) > 0) {
                $task = array_shift($array_diff);
                if (isset($queueMap[$task]) && !in_array($task, $import->getMigrated(), true)) {
                    switch (trim($task)) {
                        case 'images':
                            return $this->processMigrateImage($task, $import);
                        case 'files':
                            return $this->processMigrateFile($task, $import);
                        case 'finished':
                            return $this->processFinished($task, $import);
                        default:
                            if (trim($task) == 'minor_data') {
                                if (!$import->getLanguagePack()) {
                                    $languagePackPayload = $this->processDownloadAndInstallLanguagePack($import);
                                    if (isset($languagePackPayload['error']) || isset($languagePackPayload['ok'])) {
                                        return $languagePackPayload;
                                    }
                                    $import->setLanguagePack(1);
                                }
                            }
                            $resource = null;
                            if (!($migrate_tables = $import->getGroupTables())) {
                                if (trim($task) === 'minor_data'
                                    && is_array(EtsMigrateDataImport::$mapping_shop)
                                    && count(EtsMigrateDataImport::$mapping_shop) > 1
                                    && !Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE')
                                ) {
                                    $shop_target = 0;
                                    foreach (EtsMigrateDataImport::$mapping_shop as $id_shop_target) {
                                        if ($id_shop_target >= 0) {
                                            ++$shop_target;
                                            if ($shop_target > 1) {
                                                break;
                                            }
                                        }
                                    }
                                    if ($shop_target > 1) {
                                        if (!Configuration::updateGlobalValue('PS_MULTISHOP_FEATURE_ACTIVE', 1)) {
                                            return [
                                                'error' => $this->module->l('Could not enable multi-store feature.', 'AdminETSEMMigrateController'),
                                            ];
                                        }
                                        if (class_exists('Shop') && method_exists('Shop', 'resetStaticCache')) {
                                            Shop::resetStaticCache();
                                        }
                                        $tab = Tab::getInstanceFromClassName('AdminShopGroup');
                                        $tab->active = true;
                                        if (!$tab->update()) {
                                            return [
                                                'error' => $this->module->l('Could not enable multi-store admin tab.', 'AdminETSEMMigrateController'),
                                            ];
                                        }
                                    }
                                }
                                $resource = EtsMigrateResourceCatalog::getResources($task);
                                if (!is_array($resource)
                                    || !isset($resource['tables'])
                                    || !is_array($resource['tables'])
                                    || !count($resource['tables'])
                                ) {
                                    $import->setMigrated($task);
                                    continue 2;
                                }
                                $infos = $import->getSourceMigrationMetadata();
                                $group_images = [];
                                if ($import->auto_migrate_image
                                    && isset($infos['images'])
                                    && (int) $infos['images'] > 0
                                    && isset($resource['images'])
                                    && count($images = $resource['images']) > 0
                                ) {
                                    foreach ($images as $table => $struct) {
                                        if (is_array($struct) && count($struct) > 0) {
                                            $group_images[] = $table;
                                        }
                                    }
                                }
                                $group_files = [];
                                if ($import->auto_migrate_file
                                    && isset($infos['files'])
                                    && (int) $infos['files'] > 0
                                    && isset($resource['files'])
                                    && count($files = $resource['files']) > 0
                                ) {
                                    foreach ($files as $table => $struct) {
                                        if (is_array($struct) && count($struct) > 0) {
                                            $group_files[] = $table;
                                        }
                                    }
                                }
                                $migrate_tables = $import
                                    ->setGroupTables($resource['tables'])
                                    ->setTableImages($group_images)
                                    ->setTableFiles($group_files)
                                    ->getGroupTables();
                                if ($import->shouldDeleteData()) {
                                    $cleanContinue = $this->buildCleanBeforeMigrateContinueResponse($import, $task, $resource);
                                    if ($cleanContinue !== null) {
                                        return $cleanContinue;
                                    }
                                }
                            } elseif ($import->shouldDeleteData() && $import->isCleanMediaPending()) {
                                $resource = EtsMigrateResourceCatalog::getResources($task);
                                if (!is_array($resource)
                                    || !isset($resource['tables'])
                                    || !is_array($resource['tables'])
                                    || !count($resource['tables'])
                                ) {
                                    $import->setMigrated($task);
                                    continue 2;
                                }
                                $cleanContinue = $this->buildCleanBeforeMigrateContinueResponse($import, $task, $resource);
                                if ($cleanContinue !== null) {
                                    return $cleanContinue;
                                }
                            }
                            if ($migrate_tables) {
                                return $this->processMigrateData($task, $migrate_tables, $import, $resource);
                            } elseif (!isset($terminalTasks[trim($task)])) {
                                return [
                                    'error' => sprintf($this->module->l('Could not load tables for task "%s".', 'AdminETSEMMigrateController'), $task),
                                ];
                            }
                            break;
                    }
                }
            }
        }

        return $this->processFinished('finished', $import);
    }

    protected function buildCleanBeforeMigrateContinueResponse($import, $task, $resource)
    {
        $cleanResult = $import->cleanBeforeMigrateData($resource);
        if ($cleanResult === false) {
            return [
                'error' => $this->module->l('Could not clean data before migration.', 'AdminETSEMMigrateController'),
            ];
        }
        if ($cleanResult !== 'continue') {
            return null;
        }
        $recordsTask = (int) $import->getCount();
        $infoTask = $import->getSourceMigrationMetadata($task);

        return [
            'ok' => 1,
            'step' => 3,
            'continue' => 1,
            'cleaning' => 1,
            'migrating' => $task,
            'records_task' => $recordsTask,
            'percent' => isset($infoTask['nb_group_table']) && (int) $infoTask['nb_group_table'] > 0
                ? ($recordsTask / $infoTask['nb_group_table']) * 100
                : 0,
        ];
    }

    public function isTableInMigrationQueue($import, $migrated_tables, $table)
    {
        if (is_array($migrated_tables) && in_array($table, $migrated_tables, true)) {
            return true;
        }
        $remaining = $import instanceof EtsMigrateDataImport ? $import->getGroupTables() : [];

        return is_array($remaining) && in_array($table, $remaining, true);
    }

    protected function buildAdaptiveRetryPayload($import, $task)
    {
        $records_task = (int) $import->getCount();
        $info_task = $import->getSourceMigrationMetadata($task);

        return [
            'adaptive_retry' => 1,
            'migrate_speed' => $import->getEffectiveMigrateSpeed(),
            'migrating' => $task,
            'records_task' => $records_task,
            'percent' => isset($info_task['nb_group_table']) && (int) $info_task['nb_group_table'] > 0 ? ($records_task / $info_task['nb_group_table']) * 100 : 0,
            'task_complete' => 0,
        ];
    }

    public function guardOrStartSourceConnection()
    {
        $import = EtsMigrateDataImport::getInstance($this->context);
        $import->init();
        $migrated = $import->getMigrated();
        if (is_array($migrated) && count(array_filter($migrated)) > 0) {
            $pending = $import->getPendingMigrateTasks();
            if (count($pending) > 0 && !(int) Tools::getValue('force_new_source')) {
                return [
                    'error' => $this->module->l('A migration is already in progress. Use New migration before connecting a new source.', 'AdminETSEMMigrateController'),
                ];
            }
        }
        if (!EtsMigrateDataImport::cleanAll($this->context, false)) {
            return [
                'error' => $this->module->l('Could not reset migration data. Please try again.', 'AdminETSEMMigrateController'),
            ];
        }

        return $this->fetchSourceStoreMetadata();
    }

    public function fetchSourceStoreMetadata()
    {
        $import = EtsMigrateDataImport::getInstance($this->context);
        $tables = EtsMigrateResourceCatalog::getResources(null, true);
        $response = $this->processRequestApi(json_encode($tables), ['infos' => 1]);
        if (!is_array($response)) {
            return [
                'error' => $this->module->l('The source store is not reachable. Check the URL, access token, and network connection.', 'AdminETSEMMigrateController'),
            ];
        }
        if ((!isset($response['error']) || trim((string) $response['error']) == '')
            && (!isset($response['ps_version']) || !is_string($response['ps_version']) || !preg_match('/^[0-9]+(?:\\.[0-9]+){1,3}$/', $response['ps_version']))
        ) {
            return [
                'error' => $this->module->l('Empty or invalid response from the source store.', 'AdminETSEMMigrateController'),
            ];
        }
        if (!isset($response['error']) || trim((string) $response['error']) == '') {
            if (isset($response['cookie_key'])
                && trim((string) $response['cookie_key']) !== ''
            ) {
                $sourceCookieKey = (string) $response['cookie_key'];
                $savedPrimary = Configuration::updateGlobalValue('ETS_EM_SOURCE_COOKIE_KEY', $sourceCookieKey);
                $savedLegacy = Configuration::updateGlobalValue('ETS_NEW_COOKIE_KEY', $sourceCookieKey);
                if (!$savedPrimary && !$savedLegacy) {
                    unset($response['cookie_key']);

                    return [
                        'error' => $this->module->l('The source store cookie key could not be saved. Check the database and try again.', 'AdminETSEMMigrateController'),
                    ];
                }
            }
            unset($response['cookie_key']);
            if ($response) {
                $import->setSourceMigrationMetadata($response);
            }
            if (!Configuration::updateGlobalValue('ETS_EM_MIGRATE_VERSION', $response['ps_version'])) {
                return [
                    'error' => $this->module->l('The source store version could not be saved. Check the database and try again.', 'AdminETSEMMigrateController'),
                ];
            }
            $response['target_shops'] = Shop::getShops();
            if (!isset($response['ps_version']) || !$response['ps_version']) {
                $this->controller->errors[] = $this->module->l('The source store is not reachable. Check the URL, access token, and network connection.', 'AdminETSEMMigrateController');
            }
            if (count($this->controller->errors) > 0) {
                $response['error'] = Tools::nl2br(implode(PHP_EOL, $this->controller->errors));
            }
        } else {
            $response['error'] = (string) $response['error'];
        }
        unset($response['cookie_key']);

        return $response;
    }

    public function processRequestApi($table, $params = [], $offset = 0, $speed = 0, $images = [], $files = [])
    {
        $requestApi = EtsMigrateApiEndpoint::getInstance()->getRequestApi();
        if ($requestApi === '') {
            return ['ok' => 0, 'error' => $this->module->l('Remote request failed. Check the source store URL and try again.', 'AdminETSEMMigrateController')];
        }
        if (is_array($table)
            && !isset($params['infos'])
        ) {
            $table = implode(',', $table);
        }
        $http_build_query = [
            'table' => $table,
            'limit' => $speed,
            'offset' => $offset,
            'ps_version' => _PS_VERSION_,
        ];
        if (is_array($images)
            && count($images) > 0
        ) {
            $http_build_query['images'] = implode(',', $images);
        }
        if (is_array($files)
            && count($files) > 0
        ) {
            $http_build_query['files'] = implode(',', $files);
        }
        if ($params) {
            foreach ($params as $key => $param) {
                if (trim($param) !== '') {
                    $http_build_query[$key] = $param;
                }
            }
        }
        if (!isset($http_build_query['token'])) {
            $accessToken = trim((string) Tools::getValue('ETS_EM_ACCESS_TOKEN'));
            if ($accessToken === '') {
                $accessToken = trim((string) Configuration::getGlobalValue('ETS_EM_ACCESS_TOKEN'));
            }
            if ($accessToken !== '') {
                $http_build_query['token'] = $accessToken;
            }
        }
        $response = EtsMigrateHttpTransport::fetchRemoteContents($requestApi, false, null, 10, $http_build_query);
        if (!$response) {
            return ['ok' => 0, 'error' => $this->module->l('Remote request failed. Check the source store URL and try again.', 'AdminETSEMMigrateController')];
        }
        $response = json_decode($response, true);
        if (isset($response['json_error_utf8']) && $response['json_error_utf8'] > 0) {
            $response = EtsMigrateApiResponse::normalizeToUtf8($response);
        }

        return $response;
    }

    public function processDownloadAndInstallLanguagePack($import)
    {
        $time = time();
        $import = EtsMigrateDataImport::getInstance($this->context)->init();
        $infos = $import->getSourceMigrationMetadata();

        if (!$import->getLanguageMigrate()
            && isset($infos['languages'])
            && is_array($infos['languages'])
            && count($infos['languages']) > 0
        ) {
            foreach ($infos['languages'] as $l) {
                if (isset($l['iso_code'])
                    && trim($l['iso_code']) !== ''
                    && ($iso_code = trim(Tools::strtolower($l['iso_code'])))
                    && (int) Language::getIdByIso($iso_code) <= 0
                ) {
                    $import->setLanguageMigrate($iso_code);
                }
            }
        }

        $languages = $import->getLanguageMigrate();
        if (is_array($languages)
            && count($languages) > 0
        ) {
            $diff = array_diff($languages, $import->getMigratedLang());
            while (count($diff) > 0) {
                $iso_code = array_shift($diff);
                if (isset(EtsMigrateDataImport::$iso_code_mappings[$iso_code]) && trim(EtsMigrateDataImport::$iso_code_mappings[$iso_code]) !== '') {
                    $iso_code = EtsMigrateDataImport::$iso_code_mappings[$iso_code];
                }
                if (!$import->getMigratedLang($iso_code)) {
                    if (Language::downloadAndInstallLanguagePack($iso_code) !== true) {
                        $language_name = '';
                        if (isset($infos['languages'])
                            && is_array($infos['languages'])
                            && count($infos['languages']) > 0
                        ) {
                            foreach ($infos['languages'] as $l) {
                                if ($iso_code == trim($l['iso_code'])) {
                                    $language_name = $l['name'];
                                    break;
                                }
                            }
                        }
                        $language_name = Tools::htmlentitiesUTF8(strip_tags((string) $language_name));
                        $iso_code_safe = Tools::htmlentitiesUTF8(strip_tags(trim((string) $iso_code)));
                        $localizationUrl = $this->context->link->getAdminLink('AdminLocalization', true);
                        $this->controller->errors[] = sprintf($this->module->l('Language "%1$s" (ISO %2$s) could not be imported. Open Localization (%3$s) to install it manually.', 'AdminETSEMMigrateController'), $language_name !== '' ? $language_name : $iso_code_safe, $iso_code_safe, $localizationUrl);
                    } else {
                        $process_time = time() - $time;
                        $import->setMigratedLang($iso_code);

                        return [
                            'ok' => 1,
                            'migrated_lang' => $iso_code,
                            'process_time' => $process_time,
                            'migrating' => 'minor_data',
                            'percent' => (80 / count($languages)) * count($import->getMigratedLang()),
                            'task_complete' => 0,
                        ];
                    }
                }
            }
        }
        if (is_array($this->controller->errors)
            && count($this->controller->errors) > 0
        ) {
            return [
                'error' => Tools::nl2br(implode(PHP_EOL, $this->controller->errors)),
            ];
        }

        return [];
    }

    public function processMigrateData($task, $migrate_tables, $import = null, $resource = null)
    {
        $time = time();
        $import = EtsMigrateDataImport::getInstance($this->context)->init();
        if (is_array($migrate_tables)
            && count($migrate_tables) > 0
        ) {
            $migrateTableMap = array_fill_keys($migrate_tables, true);
            if (isset($migrateTableMap['currency_lang']) && version_compare($import->source_version, '1.7.6.0', '<')) {
                unset($migrateTableMap['currency_lang']);
                $migrate_tables = array_keys($migrateTableMap);
            }
            $params = ['multi_shops' => count(EtsMigrateDataImport::$source_shops) > 0 ? implode(',', EtsMigrateDataImport::$source_shops) : ''];
            if (trim($task) == 'orders'
                && isset($migrateTableMap['cart'])
            ) {
                $params['ignore_cart'] = (int) Configuration::getGlobalValue('ETS_EM_MIGRATE_EMPTY_CART') > 0 ? 0 : 1;
            }
            $speed = $import->getEffectiveMigrateSpeed();
            $response = $this->processRequestApi($migrate_tables, $params, $import->getOffset(), $speed, $import->getTableImages(), $import->getTableFiles());
            $connect_time = time() - $time;
            $time = time();

            if (is_array($response) && isset($response['error']) && trim((string) $response['error']) !== '') {
                return [
                    'error' => $this->module->l('Could not import data from the source store.', 'AdminETSEMMigrateController'),
                ];
            }
            if (!is_array($response)) {
                if ($import->canAdaptiveRetryImport($connect_time)) {
                    $import->registerAdaptiveRetry();

                    return $this->buildAdaptiveRetryPayload($import, $task);
                }

                return [
                    'error' => $this->module->l('The source store is not reachable. Check the URL, access token, and network connection.', 'AdminETSEMMigrateController'),
                ];
            }
            if (count($response) > 0) {
                if (isset($response['empty']) && $response['empty']) {
                    $import
                        ->setCount()
                        ->setMigrated($task)
                        ->setOffset()
                        ->setMigrateFields()
                        ->setMigrate();

                    return [
                        'ok' => 1,
                        'table_importing' => $import->getMigrateActive(),
                        'migrating' => $task,
                        'percent' => 100,
                        'task_complete' => in_array($task, $import->getMigrated(), true) ? 1 : 0,
                    ];
                }
                $migrated_tables = [];
                $records_task = (int) $import->getCount();
                $response_total = 0;
                $allowlist = $this->getMigrateTableAllowlist($task, $migrate_tables, $import, $resource);
                foreach ($response as $table => $package) {
                    if (EtsMigrateApiResponse::isResponseMetaKey($table)) {
                        continue;
                    }
                    if (!is_array($package)) {
                        continue;
                    }
                    if (!preg_match('/^[a-zA-Z0-9_]+$/', (string) $table)) {
                        continue;
                    }
                    if (EtsMigrateApiResponse::isDeniedSecretTable($table)) {
                        continue;
                    }
                    if (!isset($allowlist[$table])) {
                        continue;
                    }
                    $data = isset($package['data']) && is_array($package['data']) ? $package['data'] : [];
                    $hasEnd = isset($package['end']) && $package['end'] > 0;
                    if (is_array($data) && ($count = count($data)) > 0) {
                        $foreign_keys = [];
                        if ($import->shouldKeepOldId()) {
                            $foreign_keys = EtsMigrateForeignKeyCatalog::getForeignKeyMap($table);
                            $ignore_fields = $import->getIgnoreFields();
                            if (is_array($foreign_keys) && count($foreign_keys) > 0 && is_array($ignore_fields) && count($ignore_fields) > 0) {
                                $foreign_keys = array_intersect_key($foreign_keys, $ignore_fields + ['customer' => 'id_customer']);
                            }
                        } elseif (trim($table) !== 'category') {
                            $foreign_keys = EtsMigrateForeignKeyCatalog::getForeignKeyMap($table);
                        }
                        $importResult = $import->importData($table, $data, $foreign_keys);
                        if (isset($importResult['ok'])
                            && (int) $importResult['ok'] > 0
                        ) {
                            $response_total += $count;
                            if ($hasEnd) {
                                $migratedTableResult = $this->setMigratedTable($import, $table, $migrated_tables);
                                if (is_array($migratedTableResult)) {
                                    return $migratedTableResult;
                                }
                            } else {
                                $import->setOffset($import->getOffset() + $count);
                            }
                        } elseif (!is_array($importResult)) {
                            $total_time_fail = time() - $time + $connect_time;
                            if ($response_total === 0 && $import->canAdaptiveRetryImport($total_time_fail)) {
                                $import->registerAdaptiveRetry();

                                return $this->buildAdaptiveRetryPayload($import, $task);
                            }
                            $import->reduceRuntimeMigrateSpeed();

                            return [
                                'error' => sprintf($this->module->l('Importing data into table "%s" failed due to a timeout. Lower migration speed or check MySQL execution limits.', 'AdminETSEMMigrateController'), $table),
                            ];
                        } else {
                            $total_time_fail = time() - $time + $connect_time;
                            $isSkippedFailure = isset($importResult['error'])
                                && stripos($importResult['error'], 'skipped') !== false;
                            if ($response_total === 0 && !$isSkippedFailure && $import->canAdaptiveRetryImport($total_time_fail)) {
                                $import->registerAdaptiveRetry();

                                return $this->buildAdaptiveRetryPayload($import, $task);
                            }
                            $import->reduceRuntimeMigrateSpeed();

                            return [
                                'error' => !empty($importResult['error'])
                                    ? $importResult['error']
                                    : sprintf($this->module->l('Could not import data into table "%s". Check the migration log and try again.', 'AdminETSEMMigrateController'), $table),
                            ];
                        }
                    } elseif ($hasEnd) {
                        $migratedTableResult = $this->setMigratedTable($import, $table, $migrated_tables);
                        if (is_array($migratedTableResult)) {
                            return $migratedTableResult;
                        }
                    }
                }
                $records_task += $response_total;
                $records_left = $import->getRecordsSource($task) - $records_task;
                $diff = array_diff($migrate_tables, $migrated_tables);
                $import
                    ->setGroupTables($diff)
                    ->setCount($records_task);
                if (count($diff) <= 0) {
                    $import
                        ->setCount()
                        ->setMigrated($task)
                        ->setOffset()
                        ->setMigrateFields();
                }
                $process_time = time() - $time;
                $total_time = $process_time + $connect_time;
                $import->tuneRuntimeMigrateSpeed($total_time, $response_total);

                return $this->buildBatchProgressResponse($import, $task, $records_task, $diff, $migrated_tables, [
                    'table_importing' => $import->getMigrateActive(),
                    'total_time' => $total_time,
                    'connect_time' => $connect_time,
                    'process_time' => $process_time,
                    'migrate_speed' => $import->getEffectiveMigrateSpeed(),
                    'records_left' => $records_left,
                ]);
            }

            return [
                'error' => $this->module->l('Empty or invalid response from the source store.', 'AdminETSEMMigrateController'),
            ];
        }

        return [
            'error' => $this->module->l('Migration log not found; migration cannot continue.', 'AdminETSEMMigrateController'),
        ];
    }

    public function processMigrateImage($task, $import = null)
    {
        return $this->processMigrateMediaBatch($task, [
            'expected_task' => 'images',
            'before_generate' => 'prepareImageMigrateQueue',
            'speed_property' => 'migrate_image_speed',
            'structure_getter' => 'getMigrateImages',
            'field_unique' => EtsMigrateDataImport::$image_field_unique,
            'import_action' => 'importImage',
            'empty_fetch_log' => 'Empty image fetch for table ',
            'response_includes_offset' => false,
            'treat_false_import_as_error' => false,
        ]);
    }

    public function processMigrateFile($task, $import = null)
    {
        return $this->processMigrateMediaBatch($task, [
            'expected_task' => 'files',
            'before_generate' => 'prepareFileMigrateQueue',
            'speed_property' => 'migrate_file_speed',
            'structure_getter' => 'getMigrateFiles',
            'field_unique' => EtsMigrateDataImport::$file_field_unique,
            'import_action' => 'importFiles',
            'empty_fetch_log' => 'Empty file fetch for table ',
            'response_includes_offset' => true,
            'treat_false_import_as_error' => true,
        ]);
    }

    private function processMigrateMediaBatch($task, $mediaBatch)
    {
        $time = time();
        $import = EtsMigrateDataImport::getInstance($this->context)->init();
        $expectedTask = $mediaBatch['expected_task'];
        if (trim($task) !== $expectedTask) {
            $import->setMigrated($task);

            return [
                'ok' => 1,
                'migrating' => $task,
                'percent' => 100,
                'task_complete' => 1,
            ];
        }

        $prepareMediaQueue = $mediaBatch['before_generate'];
        $import->$prepareMediaQueue($task);
        if ($tables = $import->getGroupTables()) {
            $records_task = (int) $import->getCount();
            $total = 0;
            $migrated_tables = [];
            $speedProperty = $mediaBatch['speed_property'];
            $structureGetter = $mediaBatch['structure_getter'];
            $fieldUnique = $mediaBatch['field_unique'];
            $importAction = $mediaBatch['import_action'];
            $migrateSpeed = $import->$speedProperty;

            while (count($tables) > 0 && $total < $migrateSpeed) {
                $table = array_shift($tables);
                $offset = $import->getOffset();
                $limit = $migrateSpeed - $total;
                $structures = $import->$structureGetter($table);

                $fields = [];
                if (is_array($structures)
                    && count($structures) > 0
                ) {
                    $fields = [];
                    foreach ($structures as $struct) {
                        $id_entity = $import->resolveMediaEntityFieldName($table, $struct) . $fieldUnique;
                        if ($import->hasOldId($id_entity, $table)) {
                            $fields[] = $id_entity;
                        }
                    }
                }
                $data = count($fields) > 0 ? $import->fetchMigrateRows($table, 'a.*', false, $offset, $limit, $fields) : [];
                if (count($fields) > 0 && !is_array($data)) {
                    $fetchErrorMessage = $expectedTask === 'images'
                        ? $this->module->l('Could not fetch image records from table "%s".', 'AdminETSEMMigrateController')
                        : $this->module->l('Could not fetch file records from table "%s".', 'AdminETSEMMigrateController');

                    return [
                        'error' => sprintf($fetchErrorMessage, $table),
                        'migrating' => $task,
                    ];
                }
                if (is_array($data)
                    && ($count = count($data)) > 0
                ) {
                    $total += $count;
                    $importResult = $import->$importAction($table, $data);
                    $importFailed = ($importResult === false);
                    if ($importFailed) {
                        $migrateErrorMessage = $expectedTask === 'images'
                            ? $this->module->l('An error occurred while migrating images', 'AdminETSEMMigrateController')
                            : $this->module->l('An error occurred while migrating files', 'AdminETSEMMigrateController');

                        return [
                            'error' => $migrateErrorMessage,
                            'migrating' => $task,
                        ];
                    }
                    if ($count < $limit) {
                        $import->setOffset();
                        $migrated_tables[] = $table;
                    } else {
                        $import->setOffset($offset + $count);
                    }
                } else {
                    EtsMigrateDataImport::writeLog($mediaBatch['empty_fetch_log'] . $table . ', marking table migrated', 'INFO');
                    $import->setOffset();
                    $migrated_tables[] = $table;
                }
            }

            $records_task += $total;
            $diff = array_diff($import->getGroupTables(), $migrated_tables);

            $import
                ->setGroupTables($diff)
                ->setCount($records_task);
            if (count($diff) <= 0) {
                $import
                    ->setCount()
                    ->setMigrated($task)
                    ->setOffset();
            }
            $process_time = time() - $time;
            $info_task = $import->getSourceMigrationMetadata($task);
            $records_left = (isset($info_task['nb_group_table']) ? (int) $info_task['nb_group_table'] : 0) - $records_task;
            $extra = [
                'process_time' => $process_time,
                'records_left' => $records_left,
            ];
            if (!empty($mediaBatch['response_includes_offset'])) {
                $extra['offset'] = $import->getOffset();
            }

            return $this->buildBatchProgressResponse($import, $task, $records_task, $diff, $migrated_tables, $extra);
        }
        $import
            ->setCount()
            ->setMigrated($task)
            ->setOffset()
            ->setGroupTables();

        return [
            'ok' => 1,
            'migrating' => $task,
            'percent' => 100,
            'task_complete' => in_array($task, $import->getMigrated()) ? 1 : 0,
        ];
    }

    public function finalizeMigration()
    {
        $import = EtsMigrateDataImport::getInstance($this->context)->init();
        $migrated = $import->getMigrated();
        $configMigrated = trim((string) Configuration::getGlobalValue('ETS_EM_DATA_MIGRATED'));
        $isFinished = (is_array($migrated) && in_array('finished', $migrated))
            || ($configMigrated !== '' && in_array('finished', explode(',', $configMigrated)));
        if (!$isFinished) {
            return [
                'error' => $this->module->l('Migration is not finished.', 'AdminETSEMMigrateController'),
            ];
        }
        if (!EtsMigrateDataImport::cleanAll($this->context)) {
            return [
                'error' => $this->module->l('Could not reset migration data. Please try again.', 'AdminETSEMMigrateController'),
            ];
        }

        return [
            'ok' => 1,
            'finalized' => 1,
        ];
    }

    private function indexMigratedProducts()
    {
        if (!class_exists('Search') || !method_exists('Search', 'indexation')) {
            return '';
        }
        if (EtsMigrateTools::columnExists('product', 'id_product_ets_old')) {
            $marked = Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'product` p
                LEFT JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON (ps.`id_product` = p.`id_product`)
                SET p.`indexed` = 0, ps.`indexed` = 0
                WHERE p.`id_product_ets_old` IS NOT NULL');
            if (!$marked) {
                EtsMigrateDataImport::writeLog('Could not mark migrated products for search indexing: ' . Db::getInstance()->getMsgError(), 'ERROR');
            }
        }
        $indexed = false;
        try {
            $indexed = Search::indexation(false);
        } catch (Throwable $exception) {
            EtsMigrateDataImport::writeLog('Search indexation failed after migration: ' . $exception->getMessage(), 'ERROR');
        }
        if ($indexed === false) {
            return $this->module->l('Products were migrated, but the search index could not be completed. Open Shop Parameters > Search and add missing products to the index.', 'AdminETSEMMigrateController');
        }

        return '';
    }

    private function getImportedEmployeeEmails()
    {
        if (!EtsMigrateTools::columnExists('employee', 'id_employee_ets_old') || !EtsMigrateTools::columnExists('employee', 'passwd_old_wp')) {
            return [];
        }
        $rows = Db::getInstance()->executeS('SELECT `email` FROM `' . _DB_PREFIX_ . 'employee` WHERE `id_employee_ets_old` IS NOT NULL AND `passwd_old_wp` IS NOT NULL AND `passwd_old_wp` <> \'\' ORDER BY `email`');
        if ($rows === false) {
            EtsMigrateDataImport::writeLog('Imported employee e-mail lookup failed: ' . Db::getInstance()->getMsgError(), 'ERROR');

            return [];
        }

        return array_column($rows, 'email');
    }

    public function processFinished($task, $import = null)
    {
        $time = time();
        $import = EtsMigrateDataImport::getInstance($this->context)->init();
        $already_finished = (bool) $import->getMigrated('finished');
        if (!$already_finished && ($import->getMigrated('product') || $import->getMigrated('category'))) {
            @ini_set('max_execution_time', 7200);
            if (class_exists('Tools') && method_exists('Tools', 'generateHtaccess')) {
                Tools::generateHtaccess();
            }
        }
        $searchIndexWarning = !$already_finished && $import->getMigrated('product') ? $this->indexMigratedProducts() : '';
        $manual_images = $manual_files = false;

        $infos = $import->getSourceMigrationMetadata();
        $groups_images = $import->getMigrateImages();
        if (
            !$import->auto_migrate_image
            && !$import->getMigrated('images')
            && isset($infos['images'])
            && (int) $infos['images'] > 0
            && is_array($groups_images)
            && count($groups_images) > 0
        ) {
            $manual_images = $groups_images;
        }

        $groups_files = $import->getMigrateFiles();
        if (
            !$import->auto_migrate_file
            && !$import->getMigrated('files')
            && isset($infos['files'])
            && (int) $infos['files'] > 0
            && is_array($groups_files)
            && count($groups_files) > 0
        ) {
            $manual_files = $groups_files;
        }

        $manual_products_thumbnail = !$import->auto_product_thumb && $import->getMigrated('product');

        $keep_pwd = ($import->getMigrated('employee') || $import->getMigrated('customer')) && !(int) Db::getInstance()->getValue('SELECT `id_module` FROM `' . _DB_PREFIX_ . 'module` WHERE `name` = "ets_passwordkeeper"');
        $keep_pwd_install_failed = 0;
        $keep_pwd_error = '';
        if ($keep_pwd) {
            $plugin_name = 'ets_passwordkeeper';
            $plugin_file = _PS_MODULE_DIR_ . $plugin_name . DIRECTORY_SEPARATOR . $plugin_name . '.php';
            $plugin_ready = is_dir(_PS_MODULE_DIR_ . $plugin_name) && file_exists($plugin_file);
            if (!$plugin_ready) {
                $plugin_file_path = dirname(__DIR__) . '/plugins/' . $plugin_name . '.zip';
                $zip_extracted = EtsMigrateZipSecurity::extractZipSafely($plugin_file_path, _PS_MODULE_DIR_);
                $plugin_ready = $zip_extracted && is_dir(_PS_MODULE_DIR_ . $plugin_name) && file_exists($plugin_file);
                if (!$plugin_ready) {
                    $keep_pwd_install_failed = 1;
                    $keep_pwd_error = $this->module->l('Could not extract Password Keeper. Install it manually to keep source passwords.', 'AdminETSEMMigrateController');
                    EtsMigrateDataImport::writeLog('Password Keeper extract failed: ' . $plugin_file_path, 'ERROR');
                }
            }
            if ($plugin_ready) {
                $sourceCookieKey = (string) (Configuration::getGlobalValue('ETS_EM_SOURCE_COOKIE_KEY') ?: Configuration::getGlobalValue('ETS_NEW_COOKIE_KEY'));
                if ($sourceCookieKey !== '') {
                    Configuration::updateGlobalValue('ETS_NEW_COOKIE_KEY', $sourceCookieKey);
                }
                $password_keeper = Module::getInstanceByName($plugin_name);
                if (!$password_keeper instanceof Module) {
                    $keep_pwd_install_failed = 1;
                    $keep_pwd_error = $this->module->l('Could not install Password Keeper. Install it manually to keep source passwords.', 'AdminETSEMMigrateController');
                } else {
                    try {
                        $passwordKeeperInstalled = (bool) $password_keeper->install();
                        $keep_pwd = !$passwordKeeperInstalled;
                        if (!$passwordKeeperInstalled) {
                            $keep_pwd_install_failed = 1;
                            $keep_pwd_error = $this->module->l('Could not install Password Keeper. Install it manually to keep source passwords.', 'AdminETSEMMigrateController');
                        }
                    } catch (Throwable $exception) {
                        $keep_pwd = true;
                        $keep_pwd_install_failed = 1;
                        $keep_pwd_error = $this->module->l('Could not install Password Keeper. Install it manually to keep source passwords.', 'AdminETSEMMigrateController');
                        EtsMigrateDataImport::writeLog('Password Keeper install exception: ' . $exception->getMessage(), 'ERROR');
                    }
                }
            }
        }
        $sourceVersion = $import->source_version;
        $targetVersion = _PS_VERSION_;
        $showEmployeePasswordResetNotice = $import->getMigrated('employee')
            && $sourceVersion !== ''
            && version_compare($sourceVersion, '1.7.0.0', '<')
            && version_compare($targetVersion, '9.0.0', '>=');
        $employeePasswordResetEmails = $showEmployeePasswordResetNotice ? $this->getImportedEmployeeEmails() : [];

        $followupCsBlog = $import->getMigrated('cs_blog') ? 1 : 0;

        if (!$already_finished) {
            $import->setMigrated($task);
        }

        $process_time = time() - $time;

        $sourceCookieKey = (string) (Configuration::getGlobalValue('ETS_EM_SOURCE_COOKIE_KEY') ?: Configuration::getGlobalValue('ETS_NEW_COOKIE_KEY'));

        return [
            'ok' => 1,
            'end' => 1,
            'process_time' => $process_time,
            'migrating' => $task,
            'percent' => 100,
            'task_complete' => 1,
            'images' => $manual_images,
            'files' => $manual_files,
            'ps_root_dir' => '',
            'products_thumb' => $manual_products_thumbnail,
            'keep_pwd' => $keep_pwd,
            'keep_pwd_install_failed' => $keep_pwd_install_failed,
            'keep_pwd_error' => strip_tags((string) $keep_pwd_error),
            'show_employee_password_reset_notice' => $showEmployeePasswordResetNotice,
            'employee_password_reset_emails' => $employeePasswordResetEmails,
            'warning' => $searchIndexWarning,
            'followup_csblog' => $followupCsBlog,
            'has_cookie_key' => $sourceCookieKey !== '',
            'cookie_key' => $sourceCookieKey,
            'new_cookie_key' => $sourceCookieKey,
        ];
    }

    public function buildBatchProgressResponse($import, $task, $records_task, $diff, $migrated_tables, $extra = [])
    {
        $info_task = $import->getSourceMigrationMetadata($task);
        $nb_group_table = isset($info_task['nb_group_table']) ? (int) $info_task['nb_group_table'] : 0;

        return array_merge([
            'ok' => 1,
            'tables_migrated' => count($migrated_tables) > 0 ? implode(',', $migrated_tables) : '',
            'tables_left' => count($diff) > 0 ? implode(',', $diff) : '',
            'migrating' => $task,
            'records_task' => $records_task,
            'percent' => $nb_group_table > 0 ? ($records_task / $nb_group_table) * 100 : 0,
            'nb_group_table' => $nb_group_table,
            'task_complete' => in_array($task, $import->getMigrated()) ? 1 : 0,
        ], $extra);
    }

    public function setMigratedTable($import, $table, &$migrated_tables)
    {
        if ($import instanceof EtsMigrateDataImport && trim($table) !== '') {
            $migrated_tables[] = $table;
            $import
                ->setOffset()
                ->setMigrateFields($table);

            return EtsMigratePostImportRouter::routeAfterTableMigrated($this, $import, $table, $migrated_tables);
        }
    }
}
