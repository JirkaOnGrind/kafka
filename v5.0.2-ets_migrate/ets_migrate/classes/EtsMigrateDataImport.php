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

class EtsMigrateDataImport
{
    public $context;

    public static $_initialized = false;
    public static $_INSTANCE;
    public static $image_field_unique = '_img_exist';
    public static $file_field_unique = '_file_exist';
    public static $prefix = 'ETS_EM_';

    private static function getMigrationGlobalValue($preferredKey, $legacyKey)
    {
        $preferredValue = (string) Configuration::getGlobalValue($preferredKey);
        if (trim($preferredValue) !== '') {
            return $preferredValue;
        }

        return Configuration::getGlobalValue($legacyKey);
    }

    private static function localizeAdminError($message)
    {
        $module = Module::getInstanceByName('ets_migrate');
        if (!$module) {
            return $message;
        }

        return $module->l($message, 'AdminETSEMMigrateController');
    }

    public static $mapping_shop = [];
    public static $source_shops = [];
    public static $target_shops = [];
    public static $target_shop_groups = [];
    public static $mapping_shop_group = [];
    public static $domain_url;
    public static $shop_context_saved = false;
    public static $shop_context_previous;
    public static $shop_context_previous_id;
    public static $shop_object_previous;
    public static $shop_mapping_load_failed = false;

    public static $languages = [];
    public static $default_language;

    public $data_migrated = [];
    public $group_tables = [];
    public $data_to_migrate = [];
    public $data_infos = [];

    public $migrate_fields = [];
    public $migrate_primary = [];
    public $migrate_schema = [];
    public $migrate_unique = [];

    public $supplier_default = 0;
    public $manufacturer_default = 0;
    public $migration_mode = 1;

    public const MIGRATE_MODE_CLEAN_KEEP_ID = 1;
    public const MIGRATE_MODE_CLEAN_NEW_ID = 2;
    public const MIGRATE_MODE_KEEP_IGNORE = 3;
    public const MIGRATE_MODE_KEEP_OVERWRITE = 4;
    public const MIGRATE_MODE_APPEND_NEW_ID = 5;

    public const MIGRATE_SPEED_MIN = 100;
    public const MIGRATE_SPEED_SLOW_SECONDS = 40;
    public const MIGRATE_SPEED_FAST_SECONDS = 8;
    public const MIGRATE_SPEED_TIMEOUT_SECONDS = 35;
    public const ADAPTIVE_RETRY_MAX = 5;

    public $migrate_speed = 200;
    public $migrate_image_speed = 5;
    public $migrate_file_speed = 5;
    public $source_version = _PS_VERSION_;
    public $keep_passwd = 0;
    public $count = 0;
    public $time_execution = 0;
    public $auto_migrate_image = 0;
    public $auto_migrate_file = 0;
    public $auto_product_thumb = 0;

    private $old_id_exist;
    private $offset = 0;
    private $fields_default = [];
    private $migrate_active = [];
    private $schema_column = [];
    private $migrate_images = [];
    private $table_images = [];
    private $migrated_lang = [];
    private $language_pack;
    private $language_migrate = [];
    private $customer_email_exists_map;
    private static $log_buffer = [];
    private static $log_buffer_limit = 40;
    private static $id_mapping_cache_bound = 20000;

    protected static $keep_passwd_tables = [
        'employee' => 'passwd',
        'customer' => 'passwd',
    ];
    protected static $ignore_fields = [];
    public static $ignore_tables = [
        'zone' => [
            'field' => 'name',
            'pri' => 'id_zone',
        ],
        'currency' => [
            'field' => 'iso_code',
            'pri' => 'id_currency',
        ],
        'lang' => [
            'field' => 'iso_code',
            'pri' => 'id_lang',
        ],
        'employee' => [
            'field' => 'email',
            'pri' => 'id_employee',
        ],
        'country' => [
            'field' => 'iso_code',
            'pri' => 'id_country',
        ],
        'profile' => [
            'field' => 'id_profile',
            'pri' => 'id_profile',
        ],
    ];
    protected static $transactional_tables = [];
    protected static $row_data_error_numbers = [1022, 1048, 1062, 1216, 1264, 1265, 1292, 1300, 1364, 1366, 1367, 1406, 1452, 1586, 3819, 4025];
    protected static $row_not_inserted_warning_numbers = [1022, 1062, 1216, 1452, 1586, 3819, 4025];
    protected static $value_adjusted_warning_numbers = [1048, 1261, 1262, 1263, 1264, 1265, 1292, 1364, 1366, 1406];
    protected static $ignored_warning_numbers = [1287];
    protected static $native_reference_tables = [
        'group',
        'gender',
        'order_state',
        'tax',
        'tax_rules_group',
        'risk',
        'state',
        'contact',
    ];
    protected static $default_group_configuration = [
        'ps_unidentified_group' => 'PS_UNIDENTIFIED_GROUP',
        'ps_guest_group' => 'PS_GUEST_GROUP',
        'ps_customer_group' => 'PS_CUSTOMER_GROUP',
    ];
    protected static $ignore_keep_id = [
        'lang',
        'shop',
        'shop_group',
    ];
    public static $force_shop_context_tables = [
        'ets_blog_post',
    ];

    protected static $access_rights = 0775;

    public static $ignore_null_field_1770 = [
        'product',
        'product_attribute',
        'product_supplier',
        'address',
        'category_lang',
        'orders',
        'order_detail',
        'order_payment',
        'order_slip',
        'order_slip_detail',
        'supply_order',
        'supply_order_detail',
    ];

    public static $iso_code_mappings = [
        'sl' => 'si',
    ];

    private $migrate_files = [];

    private $table_files = [];

    private $id_mapping_cache = [];

    public function loadUniqueIndexes($table = null)
    {
        if ($table === null) {
            $this->migrate_unique = [];
        } else {
            if (is_array($this->migrate_unique) && count($this->migrate_unique) > 1) {
                $this->migrate_unique = [];
            }
            $indexes = Db::getInstance()->executeS('SHOW INDEXES FROM `' . _DB_PREFIX_ . bqSQL($table) . '`');
            if ($indexes === false) {
                self::writeLog('SHOW INDEXES failed on table ' . $table . ': ' . Db::getInstance()->getMsgError(), 'ERROR');

                return false;
            }
            $uniques = [];
            foreach ($indexes as $index) {
                if (!$index['Non_unique'] && $index['Key_name'] != 'PRIMARY') {
                    $uniques[$index['Key_name']][] = $index['Column_name'];
                }
            }
            $this->migrate_unique[$table] = $uniques;
        }
        Configuration::updateGlobalValue(self::$prefix . 'MIGRATE_UNIQUE', json_encode($this->migrate_unique), true);

        return $this;
    }

    public function modifyTable($table, $fields, $indexes = [])
    {
        if (!is_string($table) || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return false;
        }
        if (!is_array($fields)) {
            $fields = [$fields];
        }
        $query = '';

        if ($fields) {
            foreach ($fields as $key => $val) {
                if (!is_string($key) || !preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                    continue;
                }
                if (!is_string($val) || !preg_match('/^[a-zA-Z0-9_()\s,\'"]+$/', $val)) {
                    self::writeLog('modifyTable rejected unsafe column type for ' . $table . '.' . $key, 'ERROR');

                    return false;
                }
                if (!EtsMigrateTools::columnExists($table, $key)) {
                    $query .= 'ALTER TABLE `' . _DB_PREFIX_ . bqSQL($table) . '` ADD COLUMN `' . bqSQL($key) . '` ' . $val . ';';
                }
            }
        }

        if ($indexes) {
            foreach ($indexes as $index) {
                if (!is_string($index) || !preg_match('/^[a-zA-Z0-9_]+$/', $index)) {
                    continue;
                }
                if (!EtsMigrateTools::indexExists($table, $index)) {
                    $query .= 'ALTER TABLE `' . _DB_PREFIX_ . bqSQL($table) . '` ADD INDEX (`' . bqSQL($index) . '`);';
                }
            }
        }

        if (trim($query) !== '') {
            if (!Db::getInstance()->execute('SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";SET time_zone = "+00:00";' . $query)) {
                self::writeLog('modifyTable ALTER failed on ' . $table . ': ' . Db::getInstance()->getMsgError(), 'ERROR');

                return false;
            }
            EtsMigrateTools::clearSchemaCache();
        }

        return $this;
    }

    private function buildIdMappingCacheKey($table, $columnName, $oldId)
    {
        return $table . '|' . $columnName . '|' . (string) $oldId;
    }

    private function buildNativeExistsCacheKey($table, $columnName, $oldId)
    {
        return $table . '|' . $columnName . '|native|' . (string) $oldId;
    }

    private function collectForeignKeyPrefetchIds(&$batches, $data, $foreign_table, $id_source, $id_target)
    {
        if (!is_string($foreign_table) || !preg_match('/^[a-zA-Z0-9_]+$/', $foreign_table)
            || !is_string($id_target) || !preg_match('/^[a-zA-Z0-9_]+$/', $id_target)
            || !is_string($id_source) || !preg_match('/^[a-zA-Z0-9_]+$/', $id_source)
        ) {
            return;
        }
        $batchKey = $foreign_table . '|' . $id_target;
        if (!isset($batches[$batchKey])) {
            $batches[$batchKey] = [
                'table' => $foreign_table,
                'column' => $id_target,
                'ids' => [],
            ];
        }
        foreach ($data as $item) {
            if (!is_array($item) || empty($item[$id_source]) || (int) $item[$id_source] <= 0) {
                continue;
            }
            $batches[$batchKey]['ids'][(int) $item[$id_source]] = (int) $item[$id_source];
        }
    }

    private function loadIdMappingBatch($table, $column, $ids)
    {
        if (!is_string($table) || !preg_match('/^[a-zA-Z0-9_]+$/', $table)
            || !is_string($column) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)
            || !is_array($ids) || !count($ids)
        ) {
            return;
        }
        $missing = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $mapKey = $this->buildIdMappingCacheKey($table, $column, $id);
            $nativeKey = $this->buildNativeExistsCacheKey($table, $column, $id);
            if (!array_key_exists($mapKey, $this->id_mapping_cache)
                || !array_key_exists($nativeKey, $this->id_mapping_cache)
            ) {
                $missing[$id] = $id;
            }
        }
        if (!count($missing)) {
            return;
        }
        foreach ($missing as $id) {
            $mapKey = $this->buildIdMappingCacheKey($table, $column, $id);
            $nativeKey = $this->buildNativeExistsCacheKey($table, $column, $id);
            if (!array_key_exists($mapKey, $this->id_mapping_cache)) {
                $this->id_mapping_cache[$mapKey] = 0;
            }
            if (!array_key_exists($nativeKey, $this->id_mapping_cache)) {
                $this->id_mapping_cache[$nativeKey] = 0;
            }
        }
        $idList = implode(',', array_map('intval', array_values($missing)));
        $hasOld = $this->hasOldId($column . '_ets_old', $table);
        if ($hasOld) {
            $sql = 'SELECT `' . bqSQL($column) . '` AS `pk`, `' . bqSQL($column) . '_ets_old` AS `old_pk`'
                . ' FROM `' . _DB_PREFIX_ . bqSQL($table) . '`'
                . ' WHERE `' . bqSQL($column) . '_ets_old` IN (' . $idList . ')'
                . ' OR `' . bqSQL($column) . '` IN (' . $idList . ')';
        } else {
            $sql = 'SELECT `' . bqSQL($column) . '` AS `pk`, NULL AS `old_pk`'
                . ' FROM `' . _DB_PREFIX_ . bqSQL($table) . '`'
                . ' WHERE `' . bqSQL($column) . '` IN (' . $idList . ')';
        }
        $rows = Db::getInstance()->executeS($sql);
        if ($rows === false) {
            self::writeLog('loadIdMappingBatch failed: ' . $table . '.' . $column . ': ' . Db::getInstance()->getMsgError(), 'ERROR');

            return;
        }
        foreach ($rows as $row) {
            $pk = (int) $row['pk'];
            $oldPk = isset($row['old_pk']) && $row['old_pk'] !== null && $row['old_pk'] !== '' ? (int) $row['old_pk'] : 0;
            if ($oldPk > 0) {
                $this->id_mapping_cache[$this->buildIdMappingCacheKey($table, $column, $oldPk)] = $pk;
            }
            if ($pk > 0 && isset($missing[$pk])) {
                $this->id_mapping_cache[$this->buildNativeExistsCacheKey($table, $column, $pk)] = 1;
                if (!$hasOld && $this->canUseNativeForeignId($table)) {
                    $this->id_mapping_cache[$this->buildIdMappingCacheKey($table, $column, $pk)] = $pk;
                }
            }
        }
    }

    private function prefetchForeignKeyMappings($data, $foreign_keys)
    {
        if (!is_array($data) || !count($data) || !is_array($foreign_keys) || !count($foreign_keys)) {
            return;
        }
        $batches = [];
        foreach ($foreign_keys as $foreign_table => $foreign_key) {
            if (trim($foreign_table) === 'shop') {
                continue;
            }
            if (is_array($foreign_key) && $foreign_key) {
                foreach ($foreign_key as $id_source => $id_target) {
                    if ($id_source === 'id_shop' || $id_source === 'id_shop_group' || is_array($id_target)) {
                        continue;
                    }
                    $this->collectForeignKeyPrefetchIds($batches, $data, $foreign_table, $id_source, $id_target);
                }
            } elseif (!is_array($foreign_key)) {
                $this->collectForeignKeyPrefetchIds($batches, $data, $foreign_table, $foreign_key, $foreign_key);
            }
        }
        foreach ($batches as $batch) {
            $this->loadIdMappingBatch($batch['table'], $batch['column'], $batch['ids']);
        }
    }

    public function getNewIdByOldId($table, $columnName, $value, $column_type = null)
    {
        if (!$table || !preg_match('/^[a-zA-Z0-9_]+$/', $table) || !$columnName || !preg_match('/^[a-zA-Z0-9_]+$/', $columnName)) {
            return 0;
        }
        $cacheKey = $this->buildIdMappingCacheKey($table, $columnName, $value);
        if (array_key_exists($cacheKey, $this->id_mapping_cache)) {
            $mappedId = $this->id_mapping_cache[$cacheKey];
            if (!$mappedId && trim($table) === 'lang' && trim($columnName) === 'id_lang') {
                $mappedId = $this->resolveLanguageByIsoCode((int) $value);
                if ($mappedId !== false) {
                    $this->id_mapping_cache[$cacheKey] = $mappedId;
                }
            }
            if (!$mappedId && ($default = $this->getFieldsDefault($columnName)) !== null) {
                return $default;
            }

            return $mappedId;
        }
        $dq = new DbQuery();
        $dq
            ->select($columnName)
            ->from($table);
        if ($this->hasOldId($columnName . '_ets_old', $table)) {
            $dq->where($columnName . '_ets_old=' . (int) $value);
            $raw = Db::getInstance()->getValue($dq);
            if ($raw === false) {
                $message = Db::getInstance()->getMsgError();
                if (is_string($message) && $message !== '') {
                    self::writeLog("getNewIdByOldId failed: {$table}.{$columnName}: {$message}", 'ERROR');

                    return false;
                }
                $mappedId = 0;
            } else {
                $mappedId = (int) $raw;
            }
        } elseif (!$this->canUseNativeForeignId($table)) {
            $mappedId = 0;
        } elseif ($column_type !== null) {
            if (preg_match('/(?:int|tinyint|mediumint)(\((\d+)\))?/i', $column_type)) {
                $dq->where($columnName . '=' . (int) $value);
                $raw = Db::getInstance()->getValue($dq);
                if ($raw === false) {
                    $message = Db::getInstance()->getMsgError();
                    if (is_string($message) && $message !== '') {
                        self::writeLog("getNewIdByOldId failed: {$table}.{$columnName}: {$message}", 'ERROR');

                        return false;
                    }
                    $mappedId = 0;
                } else {
                    $mappedId = (int) $raw;
                }
            } elseif (preg_match('/decimal\((\d+,\s*\d+)\)|float(\((\d+,\s*\d+)\))?/i', $column_type)) {
                $dq->where($columnName . '=' . (float) $value);
                $raw = Db::getInstance()->getValue($dq);
                if ($raw === false) {
                    $message = Db::getInstance()->getMsgError();
                    if (is_string($message) && $message !== '') {
                        self::writeLog("getNewIdByOldId failed: {$table}.{$columnName}: {$message}", 'ERROR');

                        return false;
                    }
                    $mappedId = 0;
                } else {
                    $mappedId = (float) $raw;
                }
            } else {
                // Non-integer column types (e.g. orders.reference): never cast the lookup value to int.
                $dq->where($columnName . '=\'' . pSQL((string) $value) . '\'');
                $raw = Db::getInstance()->getValue($dq);
                if ($raw === false) {
                    $message = Db::getInstance()->getMsgError();
                    if (is_string($message) && $message !== '') {
                        self::writeLog("getNewIdByOldId failed: {$table}.{$columnName}: {$message}", 'ERROR');

                        return false;
                    }
                    $mappedId = 0;
                } else {
                    $mappedId = '\'' . pSQL($raw) . '\'';
                }
            }
        } else {
            // Unknown column type: keep integer lookups for numeric IDs, preserve string lookups otherwise.
            if (is_numeric($value) && (string) (int) $value === (string) $value) {
                $dq->where($columnName . '=' . (int) $value);
                $raw = Db::getInstance()->getValue($dq);
                if ($raw === false) {
                    $message = Db::getInstance()->getMsgError();
                    if (is_string($message) && $message !== '') {
                        self::writeLog("getNewIdByOldId failed: {$table}.{$columnName}: {$message}", 'ERROR');

                        return false;
                    }
                    $mappedId = 0;
                } else {
                    $mappedId = is_numeric($raw) ? (0 + $raw) : $raw;
                }
            } else {
                $dq->where($columnName . '=\'' . pSQL((string) $value) . '\'');
                $raw = Db::getInstance()->getValue($dq);
                if ($raw === false) {
                    $message = Db::getInstance()->getMsgError();
                    if (is_string($message) && $message !== '') {
                        self::writeLog("getNewIdByOldId failed: {$table}.{$columnName}: {$message}", 'ERROR');

                        return false;
                    }
                    $mappedId = 0;
                } else {
                    $mappedId = '\'' . pSQL($raw) . '\'';
                }
            }
        }
        $this->id_mapping_cache[$cacheKey] = $mappedId;
        if (!$mappedId && trim($table) === 'lang' && trim($columnName) === 'id_lang') {
            $mappedId = $this->resolveLanguageByIsoCode((int) $value);
            if ($mappedId !== false) {
                $this->id_mapping_cache[$cacheKey] = $mappedId;
            }
        }

        if (!$mappedId && ($default = $this->getFieldsDefault($columnName)) !== null) {
            return $default;
        }

        return $mappedId;
    }

    private function resolveLanguageByIsoCode($sourceLanguageId)
    {
        $sourceLanguages = $this->getSourceMigrationMetadata()['languages'] ?? [];
        if (!is_array($sourceLanguages)) {
            return 0;
        }

        $isoCode = '';
        foreach ($sourceLanguages as $language) {
            if ((int) ($language['id_lang'] ?? 0) === $sourceLanguageId) {
                $isoCode = trim($language['iso_code'] ?? '');
                break;
            }
        }

        if (!$isoCode || !Validate::isLangIsoCode($isoCode)) {
            return 0;
        }

        $isoCode = self::$iso_code_mappings[$isoCode] ?? $isoCode;

        $rawLanguageId = Db::getInstance()->getValue('SELECT `id_lang` FROM `' . _DB_PREFIX_ . 'lang` WHERE `iso_code` = \'' . pSQL($isoCode) . '\'');
        if ($rawLanguageId === false) {
            $message = Db::getInstance()->getMsgError();
            if (is_string($message) && $message !== '') {
                self::writeLog('resolveLanguageByIsoCode failed: ' . $message, 'ERROR');

                return false;
            }

            return 0;
        }
        $targetLanguageId = (int) $rawLanguageId;

        if ($targetLanguageId > 0) {
            if (!$this->ensureMappingColumn('lang', 'id_lang', 'id_lang_ets_old')) {
                return 0;
            }
            if (!Db::getInstance()->update('lang', ['id_lang_ets_old' => $sourceLanguageId], '`id_lang` = ' . $targetLanguageId)) {
                return 0;
            }
        }

        return $targetLanguageId;
    }

    private function ensureMappingColumn($table, $primaryField, $mappingColumn, $addIndex = true)
    {
        if ($this->hasOldId($mappingColumn, $table)) {
            return true;
        }

        $schema = EtsMigrateTools::describeTable($table);
        if (!is_array($schema)) {
            return false;
        }
        $columnType = 'int(10) unsigned';
        foreach ($schema as $column) {
            if (trim($column['Field']) === $primaryField) {
                $columnType = $column['Type'];
                break;
            }
        }

        if (!$this->modifyTable($table, [$mappingColumn => $columnType . ' DEFAULT NULL'], $addIndex ? [$mappingColumn] : [])) {
            return false;
        }
        $this->recordOldId($table, $mappingColumn);

        return true;
    }

    private function buildInsertParts($table, $migrate_fields, $fields_value, $migrate_unique)
    {
        $fields = $values = [];
        if ($migrate_fields) {
            foreach ($migrate_fields as $field) {
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
                    continue;
                }
                if (isset($fields_value[$field])) {
                    $fields[] = $field;
                    $values[] = $fields_value[$field];
                }
            }
        }
        if (!$fields || !$values) {
            return false;
        }
        $insertKeyword = $this->shouldIgnoreDuplicate() ? 'INSERT IGNORE INTO' : 'INSERT INTO';
        $duplicate = '';
        $preservedLinkParent = '';
        if (preg_match('/^([a-zA-Z0-9_]+)_(shop|lang)$/', trim($table), $preservedLinkMatch) && isset(self::$ignore_tables[$preservedLinkMatch[1]])) {
            $preservedLinkParent = $preservedLinkMatch[1];
        }
        $allowDuplicateUpdate = $this->shouldOverwrite() && !in_array(trim($table), self::$ignore_keep_id) || $preservedLinkParent !== '';
        if ($allowDuplicateUpdate) {
            $duplicateQueries = [];
            $employeeSensitiveFields = ['passwd', 'id_profile', 'email'];
            foreach ($fields as $key) {
                if (trim($table) === 'employee' && in_array($key, $employeeSensitiveFields, true)) {
                    continue;
                }
                if (!$this->isFieldInUniqueIndex($key, $migrate_unique)) {
                    $duplicateQueries[] = '`' . bqSQL($key) . '`=VALUES(`' . bqSQL($key) . '`)';
                }
            }
            if ($duplicateQueries) {
                $duplicate = ' ON DUPLICATE KEY UPDATE ' . implode(',', $duplicateQueries);
            } elseif ($preservedLinkParent !== '' && !empty($fields[0]) && preg_match('/^[a-zA-Z0-9_]+$/', $fields[0])) {
                $duplicate = ' ON DUPLICATE KEY UPDATE `' . bqSQL($fields[0]) . '`=`' . bqSQL($fields[0]) . '`';
            }
        }

        return [
            'header' => $insertKeyword . ' `' . _DB_PREFIX_ . bqSQL($table) . '` (' . implode(',', $this->quoteColumnIdentifiers($fields)) . ')',
            'tuple' => '(' . implode(',', $values) . ')',
            'duplicate' => $duplicate,
        ];
    }

    private function buildInsertStatement($group, $tuples)
    {
        return $group['header'] . ' VALUES' . implode(',', $tuples) . $group['duplicate'];
    }

    private function addGeneratedRow(&$importBatch, $insertParts, $rowKey, $sourceId)
    {
        $groupKey = $insertParts['header'] . $insertParts['duplicate'];
        if (!isset($importBatch['groups'][$groupKey])) {
            $importBatch['groups'][$groupKey] = ['header' => $insertParts['header'], 'duplicate' => $insertParts['duplicate']];
        }
        $importBatch['rows'][] = ['group' => $groupKey, 'tuple' => $insertParts['tuple'], 'row' => $rowKey, 'source_id' => $sourceId];
    }

    public function quoteColumnIdentifiers($fields)
    {
        if (!$fields) {
            return [];
        }
        $safeFields = [];
        if (is_array($fields)) {
            foreach ($fields as $field) {
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
                    continue;
                }
                $safeFields[] = '`' . bqSQL($field) . '`';
            }
        }

        return $safeFields;
    }

    private $categoryThumbMigrationParamsCache;

    protected function getCategoryThumbMigrationParams()
    {
        if ($this->categoryThumbMigrationParamsCache !== null) {
            return $this->categoryThumbMigrationParamsCache;
        }
        $targetSupportsCategoryThumb = class_exists('PrestaShop\\PrestaShop\\Adapter\\Image\\Uploader\\CategoryThumbnailImageUploader');
        $imageFormats = ['jpg'];
        $configuredImageFormats = Configuration::get('PS_IMAGE_FORMAT');
        if ($configuredImageFormats !== false && $configuredImageFormats !== null && trim((string) $configuredImageFormats) !== '') {
            $supportedImageFormats = ['jpg', 'png', 'webp', 'avif'];
            $supportedFormatsConstant = 'PrestaShop\\PrestaShop\\Core\\Image\\ImageFormatConfiguration::SUPPORTED_FORMATS';
            if (class_exists('PrestaShop\\PrestaShop\\Core\\Image\\ImageFormatConfiguration') && defined($supportedFormatsConstant)) {
                $supportedImageFormats = constant($supportedFormatsConstant);
            }
            foreach (explode(',', (string) $configuredImageFormats) as $configuredFormat) {
                $imageFormat = trim($configuredFormat);
                if ($imageFormat !== '' && in_array($imageFormat, $supportedImageFormats, true) && !in_array($imageFormat, $imageFormats, true)) {
                    $imageFormats[] = $imageFormat;
                }
            }
        }
        $this->categoryThumbMigrationParamsCache = [
            'enabled' => $targetSupportsCategoryThumb,
            'formats' => $imageFormats,
        ];

        return $this->categoryThumbMigrationParamsCache;
    }

    protected function applyCategoryThumbMigration($dest_path, $id_new, $id_old, $src_link, $image_ext)
    {
        $params = $this->getCategoryThumbMigrationParams();
        if (empty($params['enabled'])) {
            return;
        }

        $targetPath = rtrim($dest_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $targetId = (int) $id_new;
        $sourceId = (int) $id_old;
        $imageExt = trim((string) $image_ext) !== '' ? trim((string) $image_ext) : '.jpg';
        if (isset($imageExt[0]) && $imageExt[0] !== '.') {
            $imageExt = '.' . $imageExt;
        }

        $targetImage = $targetPath . $targetId . $imageExt;
        if (!is_file($targetImage) || !filesize($targetImage)) {
            return;
        }

        $targetThumb = $targetPath . $targetId . '_thumb.jpg';
        $thumbCreated = false;
        if ($sourceId > 0) {
            $tempFile = tempnam(_PS_TMP_IMG_DIR_, 'ets_migrate_ct');
            if ($tempFile) {
                if (self::copy($src_link . $sourceId . '_thumb' . $imageExt, $tempFile)
                    && self::isValidImageFile($tempFile)
                    && ImageManager::checkImageMemoryLimit($tempFile)
                ) {
                    $error = 0;
                    $targetWidth = $targetHeight = $sourceWidth = $sourceHeight = 0;
                    $thumbCreated = ImageManager::resize($tempFile, $targetThumb, null, null, 'jpg', false, $error, $targetWidth, $targetHeight, 5, $sourceWidth, $sourceHeight) && is_file($targetThumb) && filesize($targetThumb);
                }
                EtsMigratePathSecurity::unlink($tempFile, _PS_TMP_IMG_DIR_);
            }
        }
        if (!$thumbCreated) {
            EtsMigratePathSecurity::unlink($targetThumb, $targetPath);
        }
        if (!$thumbCreated && ImageManager::checkImageMemoryLimit($targetImage)) {
            $error = 0;
            $targetWidth = $targetHeight = $sourceWidth = $sourceHeight = 0;
            $thumbCreated = ImageManager::resize($targetImage, $targetThumb, null, null, 'jpg', false, $error, $targetWidth, $targetHeight, 5, $sourceWidth, $sourceHeight) && is_file($targetThumb) && filesize($targetThumb);
        }

        if (!$thumbCreated) {
            return;
        }

        $imageTypes = ImageType::getImagesTypes('categories');
        if (!is_array($imageTypes) || !count($imageTypes)) {
            return;
        }

        foreach ($imageTypes as $imageType) {
            if (!isset($imageType['name']) || trim($imageType['name']) === '') {
                continue;
            }
            $typeName = stripslashes($imageType['name']);
            foreach ($params['formats'] as $imageFormat) {
                $error = 0;
                $targetWidth = $targetHeight = $sourceWidth = $sourceHeight = 0;
                ImageManager::resize($targetThumb, $targetPath . $targetId . '_thumb-' . $typeName . '.' . $imageFormat, (int) $imageType['width'], (int) $imageType['height'], $imageFormat, false, $error, $targetWidth, $targetHeight, 5, $sourceWidth, $sourceHeight);
            }
        }
    }

    public static function writeLog($message, $level = 'INFO')
    {
        $levelName = Tools::strtoupper(trim((string) $level));
        if ($levelName === '') {
            $levelName = 'INFO';
        }
        if ($levelName === 'ERROR') {
            self::flushLogBuffer();

            return self::writeLogImmediate($message, $levelName);
        }
        self::$log_buffer[] = [(string) $message, $levelName];
        if (count(self::$log_buffer) >= (int) self::$log_buffer_limit) {
            return self::flushLogBuffer();
        }

        return true;
    }

    public static function flushLogBuffer()
    {
        if (!self::$log_buffer) {
            return true;
        }
        $entries = self::$log_buffer;
        self::$log_buffer = [];
        $ok = true;
        foreach ($entries as $entry) {
            if (!self::writeLogImmediate($entry[0], $entry[1])) {
                $ok = false;
            }
        }

        return $ok;
    }

    private static function writeLogImmediate($message, $level)
    {
        $logDir = _PS_ROOT_DIR_ . '/var/logs';
        if (!is_dir($logDir) && !mkdir($logDir, 0755, true) && !is_dir($logDir)) {
            PrestaShopLogger::addLog('ets_migrate: cannot create log directory', 3);

            return false;
        }
        $logFile = $logDir . '/migrate-' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        if (file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX) === false) {
            PrestaShopLogger::addLog('ets_migrate: cannot write log file ' . $logFile, 3);

            return false;
        }

        return true;
    }

    private function resolveDefaultThemeName()
    {
        static $themeName = null;
        if ($themeName !== null) {
            return $themeName;
        }

        $themeClass = 'PrestaShop\\PrestaShop\\Core\\Addon\\Theme\\Theme';
        if (class_exists($themeClass, true) && method_exists($themeClass, 'getDefaultTheme')) {
            $themeName = (string) $themeClass::getDefaultTheme();

            return $themeName;
        }

        if (isset($this->context->shop) && Validate::isLoadedObject($this->context->shop)) {
            $shopTheme = (string) $this->context->shop->theme_name;
            if ($shopTheme !== '' && Validate::isThemeName($shopTheme)) {
                $themeName = $shopTheme;

                return $themeName;
            }
        }

        $themeName = 'classic';

        return $themeName;
    }

    public function __construct($context = null)
    {
        if (is_object($context)) {
            $this->context = $context;
        }
    }

    public function init()
    {
        if (!self::$_initialized) {
            self::$domain_url = rtrim(Configuration::getGlobalValue(self::$prefix . 'DOMAIN'), '/') . '/';
            if ($this->loadMappingShops() === false) {
                self::writeLog('Shop mapping unavailable during init', 'ERROR');
                self::$shop_mapping_load_failed = true;
            } else {
                self::$shop_mapping_load_failed = false;
                $this->applyMappedShopContext();
            }
            $this->supplier_default = (int) Configuration::getGlobalValue(self::$prefix . 'SUPPLIER_DEFAULT');
            $this->manufacturer_default = (int) Configuration::getGlobalValue(self::$prefix . 'MANUFACTURER_DEFAULT');
            $this->migration_mode = (int) Configuration::getGlobalValue(self::$prefix . 'MIGRATION_METHOD') ?: self::MIGRATE_MODE_CLEAN_KEEP_ID;
            $this->migrate_speed = (int) Configuration::getGlobalValue(self::$prefix . 'MIGRATE_SPEED');
            $this->getEffectiveMigrateSpeed();
            $this->source_version = trim(Configuration::getGlobalValue(self::$prefix . 'MIGRATE_VERSION'));
            $this->keep_passwd = 1;
            $fileSpeed = (int) Configuration::getGlobalValue(self::$prefix . 'ATTACHMENTS_FILES_SPEED');
            $this->migrate_file_speed = EtsMigrateConfigForm::clampMediaSpeed($fileSpeed > 0 ? $fileSpeed : 5);
            $imageSpeed = (int) Configuration::getGlobalValue(self::$prefix . 'MIGRATE_IMAGE_SPEED');
            $this->migrate_image_speed = EtsMigrateConfigForm::clampMediaSpeed($imageSpeed > 0 ? $imageSpeed : 5);
            $this->auto_migrate_image = !$this->shouldKeepOldId() || trim(Configuration::getGlobalValue(self::$prefix . 'MIGRATE_IMAGES')) == 'auto' ? 1 : 0;
            $this->auto_migrate_file = !$this->shouldKeepOldId() || trim(Configuration::getGlobalValue(self::$prefix . 'ATTACHMENTS_FILES')) == 'auto' ? 1 : 0;
            $this->auto_product_thumb = !$this->auto_migrate_image || trim((string) Configuration::getGlobalValue(self::$prefix . 'GENERATE_PRODUCT_THUMBNAIL')) == 'auto' ? 1 : 0;
            $this->getIgnoreFields();
            $this->getLanguages();
            $this->getDefaultLanguage();

            self::$_initialized = true;
        }

        return $this;
    }

    public static function getInstance($context)
    {
        if (!self::$_INSTANCE) {
            self::$_INSTANCE = new EtsMigrateDataImport($context);
            self::$_INSTANCE->context = $context;
        }

        return self::$_INSTANCE;
    }

    public function getIgnoreFields()
    {
        if (!self::$ignore_fields) {
            if (is_array(self::$ignore_tables) && count(self::$ignore_tables) > 0) {
                foreach (self::$ignore_tables as $table => $field) {
                    if (isset($field['pri']) && trim($field['pri']) !== '') {
                        self::$ignore_fields[$table] = trim($field['pri']);
                    }
                }
            }
        }

        return self::$ignore_fields;
    }

    public function importData($table, $data, $foreign_keys = [])
    {
        $executeSucceeded = 0;
        if (trim($table) === '' || !is_array($data)) {
            return [
                'ok' => 0,
                'error' => trim($table) === '' ? self::localizeAdminError('Table name is empty') : self::localizeAdminError('Invalid data'),
                'table' => $table,
            ];
        }
        if (count($data) <= 0) {
            return [
                'ok' => 1,
                'empty' => 1,
                'table' => $table,
            ];
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return [
                'ok' => 0,
                'error' => self::localizeAdminError('Invalid table name'),
                'table' => $table,
            ];
        }
        $deniedTables = [
            'configuration',
            'configuration_lang',
            'webservice_account',
            'webservice_account_shop',
            'webservice_permission',
            'access',
            'authorization_role',
            'module',
            'module_group',
            'module_history',
            'tab',
            'tab_lang',
            'hook',
            'hook_module',
            'hook_alias',
            'employee_session',
            'connections',
            'connections_source',
        ];
        if (in_array($table, $deniedTables, true)) {
            return [
                'ok' => 0,
                'error' => self::localizeAdminError('Table is not allowed for import'),
                'table' => $table,
            ];
        }
        $maxRows = max(1, min(9900, (int) $this->getEffectiveMigrateSpeed()));
        if (count($data) > $maxRows) {
            $data = array_slice($data, 0, $maxRows);
        }
        if (!EtsMigrateTools::tableExists($table)) {
            return [
                'ok' => 0,
                'error' => self::localizeAdminError('Table does not exist'),
                'table' => $table,
            ];
        }

        if (trim($this->getMigrateActive($table)) !== trim($table)) {
            $migrate_schema = EtsMigrateTools::describeTable($table);
            if (!is_array($migrate_schema)) {
                self::writeLog('DESCRIBE failed on table ' . $table . ': ' . Db::getInstance()->getMsgError(), 'ERROR');

                return [
                    'ok' => 0,
                    'error' => self::localizeAdminError('Failed to describe table schema'),
                    'table' => $table,
                ];
            }
            $this->setMigrateSchema($table, $migrate_schema);

            $primary_keys = [];
            $fields = [];
            $schema_columns = [];
            foreach ($migrate_schema as $schema) {
                $field = trim($schema['Field']);
                $schema_columns[$field] = $schema['Type'];
                if (!in_array($field, $fields)) {
                    $fields[] = $field;
                }
                if (isset($schema['Key']) && $schema['Key'] == 'PRI') {
                    if (!in_array($field, $primary_keys)) {
                        $primary_keys[] = $field;
                    }
                    if (
                        isset($schema['Extra'])
                        && trim($schema['Extra']) == 'auto_increment'
                        && !EtsMigrateForeignKeyCatalog::isForeignKeyColumn($table, $field)
                    ) {
                        if (!$this->ensureMappingColumn($table, $field, $field . '_ets_old', true)) {
                            return [
                                'ok' => 0,
                                'error' => self::localizeAdminError('Failed to add mapping column'),
                                'table' => $table,
                            ];
                        }
                        if (!in_array($field . '_ets_old', $fields)) {
                            $fields[] = $field . '_ets_old';
                        }
                    }
                }
                if (
                    $this->keep_passwd
                    && isset(self::$keep_passwd_tables[$table])
                    && $field == trim(self::$keep_passwd_tables[$table])
                ) {
                    if (!$this->modifyTable($table, [$field . '_old_wp' => $schema['Type'] . ' DEFAULT NULL'])) {
                        return [
                            'ok' => 0,
                            'error' => self::localizeAdminError('Failed to add password mapping column'),
                            'table' => $table,
                        ];
                    }
                    if (!in_array($field . '_old_wp', $fields)) {
                        $fields[] = $field . '_old_wp';
                    }
                }
            }

            if (($struct_images = $this->getMigrateImages($table))
                && is_array($struct_images)
            ) {
                if (trim($table) === 'category' && !in_array('category_thumb_img_exist', $fields)) {
                    if (EtsMigrateTools::columnExists($table, 'category_thumb_exists') && !EtsMigrateTools::columnExists($table, 'category_thumb_img_exist')) {
                        if (!Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . bqSQL($table) . '` CHANGE `category_thumb_exists` `category_thumb_img_exist` tinyint(1) unsigned DEFAULT NULL')) {
                            return [
                                'ok' => 0,
                                'error' => self::localizeAdminError('Failed to rename category thumb column'),
                                'table' => $table,
                            ];
                        }
                        EtsMigrateTools::clearSchemaCache();
                    } elseif (!$this->modifyTable($table, ['category_thumb_img_exist' => 'tinyint(1) unsigned DEFAULT NULL'])) {
                        return [
                            'ok' => 0,
                            'error' => self::localizeAdminError('Failed to add category thumb column'),
                            'table' => $table,
                        ];
                    }
                    $fields[] = 'category_thumb_img_exist';
                }
                foreach ($struct_images as $struct) {
                    $field = $this->resolveMediaEntityFieldName($table, $struct);
                    if ($field === '') {
                        continue;
                    }
                    if (!in_array($field . self::$image_field_unique, $fields)) {
                        if (!$this->modifyTable($table, [$field . self::$image_field_unique => 'tinyint(1) unsigned DEFAULT NULL'])) {
                            return [
                                'ok' => 0,
                                'error' => self::localizeAdminError('Failed to add image tracking column'),
                                'table' => $table,
                            ];
                        }
                        $fields[] = $field . self::$image_field_unique;
                    }
                    $this->recordOldId($table, $field . self::$image_field_unique);
                }
            }

            if (($struct_files = $this->getMigrateFiles($table))
                && is_array($struct_files)
            ) {
                foreach ($struct_files as $struct) {
                    $field = $this->resolveMediaEntityFieldName($table, $struct);
                    if ($field === '') {
                        continue;
                    }
                    if (!in_array($field . self::$file_field_unique, $fields)) {
                        if (!$this->modifyTable($table, [$field . self::$file_field_unique => 'tinyint(1) unsigned DEFAULT NULL'])) {
                            return [
                                'ok' => 0,
                                'error' => self::localizeAdminError('Failed to add file tracking column'),
                                'table' => $table,
                            ];
                        }
                        $fields[] = $field . self::$file_field_unique;
                    }
                    $this->recordOldId($table, $field . self::$file_field_unique);
                }
            }

            $this
                ->setMigrateFields($table, $fields)
                ->setMigratePrimary($table, $primary_keys);
            if ($this->loadUniqueIndexes($table) === false) {
                return [
                    'ok' => 0,
                    'error' => self::localizeAdminError('Failed to read table indexes'),
                    'table' => $table,
                ];
            }
            $this
                ->setSchemaColumn($table, $schema_columns)
                ->setMigrateActive($table);
        }

        $importBatch = $this->generateSQL($table, $data, $foreign_keys);
        if ($importBatch === false) {
            self::flushLogBuffer();

            return [
                'ok' => 0,
                'error' => self::localizeAdminError('Failed to generate SQL'),
                'table' => $table,
            ];
        }
        if ($importBatch['rows']) {
            $executeSucceeded = $this->executeImportBatch($table, $importBatch);
            self::flushLogBuffer();
            if (!$executeSucceeded) {
                self::writeLog('SQL execute failed on table ' . $table . ': ' . Db::getInstance()->getMsgError(), 'ERROR');

                return [
                    'ok' => 0,
                    'error' => self::localizeAdminError('SQL execute failed'),
                    'table' => $table,
                ];
            }
            if (
                trim($table) == 'currency'
                && version_compare(_PS_VERSION_, '1.7.6.0', '>=')
                && version_compare($this->source_version, '1.7.6.0', '<')
            ) {
                $ps_ver_1770 = version_compare(_PS_VERSION_, '1.7.7.7', '>=');
                $query = 'INSERT IGNORE INTO `' . _DB_PREFIX_ . bqSQL($table) . '_lang`(`id_currency`, `id_lang`, `name`, `symbol`' . ($ps_ver_1770 ? ', `pattern`' : '') . ') VALUES';
                $subQuery = '';
                $currencyOldIds = [];
                foreach ($data as $item) {
                    if (!empty($item['id_currency']) && (int) $item['id_currency'] > 0) {
                        $currencyOldIds[(int) $item['id_currency']] = (int) $item['id_currency'];
                    }
                }
                if ($currencyOldIds) {
                    $this->loadIdMappingBatch('currency', 'id_currency', $currencyOldIds);
                }
                if ($languages = Language::getLanguages(false)) {
                    foreach ($languages as $l) {
                        foreach ($data as $item) {
                            $mappedCurrencyId = $this->getNewIdByOldId('currency', 'id_currency', (int) $item['id_currency']);
                            if ($mappedCurrencyId === false) {
                                self::writeLog('currency_lang currency id lookup failed', 'ERROR');

                                return [
                                    'ok' => 0,
                                    'error' => self::localizeAdminError('currency_lang id lookup failed'),
                                    'table' => $table,
                                ];
                            }
                            $fields_value = [
                                'id_currency' => $mappedCurrencyId,
                                'id_lang' => (int) $l['id_lang'] ?: Configuration::get('PS_LANG_DEFAULT'),
                                'name' => '"' . pSQL($item['name']) . '"',
                                'symbol' => '"' . (isset($item['sign']) ? pSQL($item['sign']) : '') . '"',
                            ];
                            if ($ps_ver_1770) {
                                $fields_value['pattern'] = '"' . (isset($item['pattern']) ? pSQL($item['pattern']) : '') . '"';
                            }
                            $queryTmp = '(' . implode(',', $fields_value) . ')';
                            if ($this->shouldOverwrite()) {
                                $queryTmp .= ' ON DUPLICATE KEY UPDATE ';
                                foreach ($fields_value as $key => $value) {
                                    if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                                        continue;
                                    }
                                    $queryTmp .= '`' . bqSQL($key) . '`=' . $value . ',';
                                }
                            }
                            $subQuery .= $query . rtrim($queryTmp, ',') . ';';
                        }
                    }
                }
                if ($subQuery) {
                    if (!Db::getInstance()->execute($subQuery)) {
                        self::writeLog('currency_lang insert failed: ' . Db::getInstance()->getMsgError(), 'ERROR');

                        return [
                            'ok' => 0,
                            'error' => self::localizeAdminError('currency_lang insert failed'),
                            'table' => $table,
                        ];
                    }
                }
            }
            if (
                trim($table) == 'shop'
                && count(self::$mapping_shop) > 0
                && $this->hasOldId('id_shop_ets_old', $table)
            ) {
                $dq = new DbQuery();
                $dq
                    ->select('id_shop, id_shop_ets_old')
                    ->from('shop')
                    ->where('id_shop_ets_old is NOT NULL');
                $shops = Db::getInstance()->executeS($dq);
                if ($shops === false) {
                    self::writeLog('Shop remapping query failed: ' . Db::getInstance()->getMsgError(), 'ERROR');

                    return [
                        'ok' => 0,
                        'error' => self::localizeAdminError('Failed to remap imported shops'),
                        'table' => $table,
                    ];
                }
                if (count($shops)) {
                    $id_shop_default = (int) Configuration::get('PS_SHOP_DEFAULT');
                    if ($this->loadMappingShops() === false) {
                        return [
                            'ok' => 0,
                            'error' => self::localizeAdminError('Failed to load shop mapping'),
                            'table' => $table,
                        ];
                    }
                    foreach ($shops as $shop) {
                        $oldShopId = (int) $shop['id_shop_ets_old'];
                        if (isset(self::$mapping_shop[$oldShopId])) {
                            $mappedTarget = (int) self::$mapping_shop[$oldShopId];
                            self::$mapping_shop[$oldShopId] = (int) $shop['id_shop'];
                            if ($mappedTarget <= 0) {
                                if (EtsMigrateDataImport::addShop((int) $shop['id_shop'], $id_shop_default) === false) {
                                    return [
                                        'ok' => 0,
                                        'error' => self::localizeAdminError('Failed to clone shop module associations'),
                                        'table' => $table,
                                    ];
                                }
                            }
                        }
                    }
                    if (EtsMigrateDataImport::saveShopMapping(self::$mapping_shop) === false) {
                        return [
                            'ok' => 0,
                            'error' => self::localizeAdminError('Failed to persist shop mapping'),
                            'table' => $table,
                        ];
                    }
                    $this->applyMappedShopContext();
                }
            }
        } else {
            self::writeLog('No SQL generated for table ' . $table . ' — all rows skipped', 'INFO');

            return [
                'ok' => 1,
                'skipped' => count($data),
                'table' => $table,
            ];
        }

        return [
            'ok' => (int) $executeSucceeded,
            'table' => $table,
        ];
    }

    public static function addShop($id_shop, $id_shop_default = 0)
    {
        if ($id_shop_default <= 0) {
            $id_shop_default = (int) Configuration::get('PS_SHOP_DEFAULT');
        }

        $query = 'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'module_shop`(id_module, id_shop, enable_device) 
            SELECT id_module, ' . (int) $id_shop . ' as `id_shop`, enable_device 
            FROM `' . _DB_PREFIX_ . 'module_shop` 
            WHERE `id_shop` = ' . (int) $id_shop_default;

        $query .= ';INSERT IGNORE INTO `' . _DB_PREFIX_ . 'module_country`(id_module, id_shop, id_country) 
            SELECT id_module, ' . (int) $id_shop . ' as `id_shop`, id_country 
            FROM `' . _DB_PREFIX_ . 'module_country` 
            WHERE `id_shop` = ' . (int) $id_shop_default;

        $query .= ';INSERT IGNORE INTO `' . _DB_PREFIX_ . 'module_group`(id_module, id_shop, id_group) 
            SELECT id_module, ' . (int) $id_shop . ' as `id_shop`, id_group 
            FROM `' . _DB_PREFIX_ . 'module_group` 
            WHERE `id_shop` = ' . (int) $id_shop_default;

        $query .= ';INSERT IGNORE INTO `' . _DB_PREFIX_ . 'module_currency`(id_module, id_shop, id_currency) 
            SELECT id_module, ' . (int) $id_shop . ' as `id_shop`, id_currency 
            FROM `' . _DB_PREFIX_ . 'module_currency` 
            WHERE `id_shop` = ' . (int) $id_shop_default;

        $query .= ';INSERT IGNORE INTO `' . _DB_PREFIX_ . 'module_carrier`(id_module, id_shop, id_reference) 
            SELECT id_module, ' . (int) $id_shop . ' as `id_shop`, id_reference 
            FROM `' . _DB_PREFIX_ . 'module_carrier` 
            WHERE `id_shop` = ' . (int) $id_shop_default;

        return Db::getInstance()->execute($query);
    }

    public static function isRetryableDatabaseMessage($message)
    {
        if (!$message) {
            return false;
        }
        $message = strtolower($message);
        $nonRetryable = [
            'duplicate',
            'syntax',
            'constraint',
            'unknown column',
            'foreign key constraint',
            'cannot add or update a child row',
        ];
        foreach ($nonRetryable as $pattern) {
            if (strpos($message, $pattern) !== false) {
                return false;
            }
        }
        $retryable = [
            'gone away',
            'max_allowed_packet',
            'lock wait',
            'deadlock',
            'wait_timeout',
            'timed out',
            'too many connections',
            'server has gone away',
        ];
        foreach ($retryable as $pattern) {
            if (strpos($message, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    public function prepareImageMigrateQueue($task)
    {
        return $this->prepareMediaMigrateStructures($task, $this->getMigrateImages(), self::$image_field_unique);
    }

    public function prepareFileMigrateQueue($task)
    {
        return $this->prepareMediaMigrateStructures($task, $this->getMigrateFiles(), self::$file_field_unique);
    }

    public function resolveMediaEntityFieldName($table, $struct = [])
    {
        if (isset($struct['field']) && trim($struct['field']) !== '') {
            $field = trim($struct['field']);
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
                return '';
            }

            return $field;
        }
        $table = trim((string) $table);
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return '';
        }

        return 'id_' . $table;
    }

    protected function prepareMediaMigrateStructures($task, $media_structures, $field_unique_suffix)
    {
        $infos = $this->getSourceMigrationMetadata($task);

        if (
            is_array($media_structures)
            && count($media_structures) > 0
            && (!isset($infos['nb_group_table']) || !$this->getGroupTables() && !$this->getMigrated($task))
        ) {
            $infos['nb_group_table'] = 0;
            $tables = [];
            foreach ($media_structures as $table => $struct_media) {
                $fields = [];
                foreach ($struct_media as $st) {
                    $id_entity = $this->resolveMediaEntityFieldName($table, $st);
                    if ($id_entity === '') {
                        continue;
                    }
                    $id_entity .= $field_unique_suffix;
                    if ($this->hasOldId($id_entity, $table)) {
                        $fields[] = $id_entity;
                    }
                }
                if (count($fields) > 0) {
                    $rowCount = $this->fetchMigrateRows($table, '*', true, 0, 0, $fields);
                    if ($rowCount === false) {
                        self::writeLog('Media structure count failed on table ' . $table, 'ERROR');
                        continue;
                    }
                    $infos['nb_group_table'] += (int) $rowCount;
                    $tables[] = $table;
                }
            }
            $this
                ->setSourceMigrationMetadata($infos, $task)
                ->setGroupTables($tables)
                ->setOffset();
        }

        return $this;
    }

    public function fetchMigrateRows($table, $select = '*', $number_of_record = false, $offset = 0, $limit = 0, $fields = [])
    {
        if (!is_string($table) || !preg_match('/^[a-zA-Z0-9_]+$/', $table) || !is_string($select)) {
            return $number_of_record ? 0 : [];
        }
        $select = trim($select);
        if ($select !== '*' && $select !== 'a.*' && !preg_match('/^[a-zA-Z0-9_`,.\s*]+$/', $select)) {
            return $number_of_record ? 0 : [];
        }
        if (EtsMigrateTools::tableExists($table)) {
            $dq = new DbQuery();
            if ($number_of_record) {
                $dq->select('COUNT(' . ($select === '*' ? '*' : $select) . ')');
            } else {
                $dq
                    ->select($select);
                if (trim($table) == 'image') {
                    $dq
                        ->select('p.`id_product_ets_old`')
                        ->leftJoin('product', 'p', 'a.`id_product` = p.`id_product`')
                        ->orderBy('id_image');
                }
            }
            $dq
                ->from($table, 'a');
            if (
                is_array($fields)
                && count($fields) > 0
            ) {
                $queries = [];
                foreach ($fields as $field) {
                    if (is_string($field) && preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
                        $queries[] = 'a.`' . trim($field) . '` is NOT NULL';
                    }
                }
                if ($queries) {
                    $dq->where(implode(' OR ', $queries));
                }
            }
            if ($number_of_record) {
                $count = Db::getInstance()->getValue($dq);
                if ($count === false) {
                    $message = Db::getInstance()->getMsgError();
                    if (is_string($message) && $message !== '') {
                        self::writeLog('COUNT failed on table ' . $table . ': ' . $message, 'ERROR');

                        return false;
                    }

                    return 0;
                }

                return (int) $count;
            }
            if ($limit) {
                $dq->limit($limit, $offset);
            }
            $rows = Db::getInstance()->executeS($dq);
            if ($rows === false) {
                self::writeLog('fetchMigrateRows failed on table ' . $table . ': ' . Db::getInstance()->getMsgError(), 'ERROR');

                return false;
            }

            return $rows;
        }

        return $number_of_record ? 0 : [];
    }

    public static function getTargetShopGroup($id_shop)
    {
        if (!$id_shop) {
            return 0;
        }
        if (!isset(self::$target_shop_groups[$id_shop])) {
            $raw = Db::getInstance()->getValue('SELECT id_shop_group FROM `' . _DB_PREFIX_ . 'shop` WHERE id_shop=' . (int) $id_shop);
            if ($raw === false) {
                $message = Db::getInstance()->getMsgError();
                if (is_string($message) && $message !== '') {
                    self::writeLog('getTargetShopGroup failed for shop ' . (int) $id_shop . ': ' . $message, 'ERROR');

                    return false;
                }
                self::$target_shop_groups[$id_shop] = 0;
            } else {
                self::$target_shop_groups[$id_shop] = (int) $raw;
            }
        }

        return self::$target_shop_groups[$id_shop];
    }

    protected function mapSourceShopGroup($id_shop_group)
    {
        $id_shop_group = (int) $id_shop_group;
        if ($id_shop_group <= 0) {
            return 0;
        }
        if (isset(self::$mapping_shop_group[$id_shop_group])) {
            return (int) self::$mapping_shop_group[$id_shop_group];
        }
        $infos = $this->getSourceMigrationMetadata();
        $sourceShops = isset($infos['source_shops']) && is_array($infos['source_shops']) ? $infos['source_shops'] : [];
        $targetGroups = [];
        foreach ($sourceShops as $shop) {
            if (
                isset($shop['id_shop'], $shop['id_shop_group'])
                && (int) $shop['id_shop_group'] === $id_shop_group
                && isset(self::$mapping_shop[(int) $shop['id_shop']])
                && (int) self::$mapping_shop[(int) $shop['id_shop']] > 0
            ) {
                $targetGroupId = self::getTargetShopGroup((int) self::$mapping_shop[(int) $shop['id_shop']]);
                if ($targetGroupId === false) {
                    return false;
                }
                if ($targetGroupId > 0) {
                    $targetGroups[$targetGroupId] = $targetGroupId;
                }
            }
        }
        self::$mapping_shop_group[$id_shop_group] = 0;
        if (count($targetGroups) === 1) {
            self::$mapping_shop_group[$id_shop_group] = (int) reset($targetGroups);
        } elseif (count($targetGroups) > 1) {
            self::writeLog('Source shop group ' . $id_shop_group . ' maps to multiple target groups; rows using this group id will be skipped', 'ERROR');
        }

        return (int) self::$mapping_shop_group[$id_shop_group];
    }

    protected function resolveTargetShopIdFromFields($fields, $item = [])
    {
        $targetShopId = isset($fields['id_shop']) ? (int) $fields['id_shop'] : 0;
        if ($targetShopId <= 0 && isset($item['id_shop']) && (int) $item['id_shop'] > 0 && isset(self::$mapping_shop[(int) $item['id_shop']]) && (int) self::$mapping_shop[(int) $item['id_shop']] > 0) {
            $targetShopId = (int) self::$mapping_shop[(int) $item['id_shop']];
        }
        if ($targetShopId <= 0) {
            $targetShopId = isset($this->context->shop) ? (int) $this->context->shop->id : 0;
        }
        $shopContext = EtsMigrateTools::shopShare($targetShopId, $this->context);
        if ($shopContext === false || !is_array($shopContext) || !(int) $shopContext['id_shop']) {
            return null;
        }

        return $shopContext;
    }

    protected function normalizeStockShop(&$fields_value, $item = [])
    {
        $shopContext = $this->resolveTargetShopIdFromFields($fields_value, $item);
        if (!$shopContext) {
            return false;
        }
        if ((int) $shopContext['share_stock']) {
            $fields_value['id_shop'] = 0;
            $fields_value['id_shop_group'] = (int) $shopContext['id_shop_group'];
        } else {
            $fields_value['id_shop'] = (int) $shopContext['id_shop'];
            $fields_value['id_shop_group'] = 0;
        }

        return true;
    }

    protected function normalizeCustomerShop(&$fields_value, $item = [])
    {
        $shopContext = $this->resolveTargetShopIdFromFields($fields_value, $item);
        if (!$shopContext) {
            return false;
        }
        if (!isset($fields_value['id_shop']) || (int) $fields_value['id_shop'] <= 0) {
            $fields_value['id_shop'] = (int) $shopContext['id_shop'];
        }
        if (!isset($fields_value['id_shop_group']) || (int) $fields_value['id_shop_group'] <= 0 || (int) $shopContext['share_customer']) {
            $fields_value['id_shop_group'] = (int) $shopContext['id_shop_group'];
        }
        if ((int) $shopContext['share_customer'] && isset($item['email']) && is_string($item['email']) && trim($item['email']) !== '') {
            $emailLookup = trim($item['email']);
            $emailMapKey = (int) $shopContext['id_shop_group'] . '|' . Tools::strtolower($emailLookup);
            if (is_array($this->customer_email_exists_map)) {
                $existingCustomerId = isset($this->customer_email_exists_map[$emailMapKey])
                    ? (int) $this->customer_email_exists_map[$emailMapKey]
                    : 0;
            } else {
                $existingCustomerId = (int) Db::getInstance()->getValue('SELECT `id_customer` FROM `' . _DB_PREFIX_ . 'customer`
                    WHERE `email` = \'' . pSQL($emailLookup) . '\'
                        AND `id_shop_group` = ' . (int) $shopContext['id_shop_group'] . '
                    ORDER BY `id_customer` ASC');
            }
            if ($existingCustomerId > 0) {
                if (empty($item['id_customer']) || !$this->mapExistingSharedCustomer((int) $item['id_customer'], $existingCustomerId)) {
                    return false;
                }

                return $existingCustomerId;
            }
        }
        if (!(int) $shopContext['share_customer'] && empty($item['is_guest']) && isset($item['email']) && is_string($item['email']) && trim($item['email']) !== '') {
            $existingCustomerId = $this->findRegisteredCustomerInShop(trim($item['email']), (int) $shopContext['id_shop']);
            if ($existingCustomerId === false) {
                return false;
            }
            if ($existingCustomerId > 0) {
                if (empty($item['id_customer']) || !$this->mapExistingSharedCustomer((int) $item['id_customer'], $existingCustomerId)) {
                    return false;
                }

                return $existingCustomerId;
            }
        }

        return true;
    }

    private function findRegisteredCustomerInShop($email, $idShop)
    {
        $emailMapKey = 'shop:' . (int) $idShop . '|' . Tools::strtolower($email);
        if (is_array($this->customer_email_exists_map)) {
            return isset($this->customer_email_exists_map[$emailMapKey]) ? (int) $this->customer_email_exists_map[$emailMapKey] : 0;
        }
        $existingCustomerId = Db::getInstance()->getValue('SELECT `id_customer` FROM `' . _DB_PREFIX_ . 'customer`
            WHERE `email` = \'' . pSQL($email) . '\' AND `id_shop` = ' . (int) $idShop . ' AND `is_guest` = 0
            ORDER BY `id_customer` ASC');
        if ($existingCustomerId === false && Db::getInstance()->getMsgError() !== '') {
            self::writeLog('Customer e-mail lookup failed: ' . Db::getInstance()->getMsgError(), 'ERROR');

            return false;
        }

        return (int) $existingCustomerId;
    }

    public function getTargetDefaultGroupForSourceGroup($sourceGroupId)
    {
        $metadata = $this->getSourceMigrationMetadata();
        foreach (self::$default_group_configuration as $sourceKey => $targetKey) {
            if (!isset($metadata[$sourceKey]) || (int) $metadata[$sourceKey] !== (int) $sourceGroupId) {
                continue;
            }
            $targetGroupId = (int) Configuration::get($targetKey);
            if ($targetGroupId <= 0) {
                return 0;
            }

            return (int) Db::getInstance()->getValue('SELECT `id_group` FROM `' . _DB_PREFIX_ . 'group` WHERE `id_group` = ' . $targetGroupId);
        }

        return 0;
    }

    public function syncDefaultGroupConfiguration()
    {
        if ($this->shouldKeepOldId()) {
            return true;
        }
        $metadata = $this->getSourceMigrationMetadata();
        foreach (self::$default_group_configuration as $sourceKey => $targetKey) {
            if (empty($metadata[$sourceKey])) {
                continue;
            }
            $targetGroupId = $this->getNewIdByOldId('group', 'id_group', (int) $metadata[$sourceKey]);
            if ($targetGroupId === false) {
                return false;
            }
            if ((int) $targetGroupId > 0 && (int) $targetGroupId !== (int) Configuration::get($targetKey) && !Configuration::updateGlobalValue($targetKey, (int) $targetGroupId)) {
                self::writeLog('Failed to update ' . $targetKey . ' to migrated group ' . (int) $targetGroupId, 'ERROR');

                return false;
            }
        }

        return true;
    }

    private function mapExistingSharedCustomer($sourceCustomerId, $targetCustomerId)
    {
        if (!$this->ensureMappingColumn('customer', 'id_customer', 'id_customer_ets_old')) {
            self::writeLog('Shared-customer mapping column could not be prepared', 'ERROR');

            return false;
        }
        $existingSourceId = Db::getInstance()->getValue('SELECT COALESCE(`id_customer_ets_old`, 0) FROM `' . _DB_PREFIX_ . 'customer` WHERE `id_customer` = ' . (int) $targetCustomerId);
        if ($existingSourceId === false) {
            self::writeLog('Shared-customer mapping lookup failed: ' . Db::getInstance()->getMsgError(), 'ERROR');

            return false;
        }
        if ((int) $existingSourceId > 0 && (int) $existingSourceId !== (int) $sourceCustomerId) {
            self::writeLog('Shared-customer target is already mapped to a different source customer', 'ERROR');

            return false;
        }
        if ((int) $existingSourceId <= 0) {
            if (!Db::getInstance()->update('customer', ['id_customer_ets_old' => (int) $sourceCustomerId], '`id_customer` = ' . (int) $targetCustomerId)
                || Db::getInstance()->Affected_Rows() <= 0
            ) {
                self::writeLog('Shared-customer mapping update failed: ' . Db::getInstance()->getMsgError(), 'ERROR');

                return false;
            }
        }
        $this->id_mapping_cache[$this->buildIdMappingCacheKey('customer', 'id_customer', $sourceCustomerId)] = (int) $targetCustomerId;
        $this->id_mapping_cache[$this->buildNativeExistsCacheKey('customer', 'id_customer', $targetCustomerId)] = 1;

        return true;
    }

    public function shouldKeepOldId()
    {
        return in_array($this->migration_mode, [
            self::MIGRATE_MODE_CLEAN_KEEP_ID,
            self::MIGRATE_MODE_KEEP_IGNORE,
            self::MIGRATE_MODE_KEEP_OVERWRITE,
        ]);
    }

    public function shouldDeleteData()
    {
        return in_array($this->migration_mode, [
            self::MIGRATE_MODE_CLEAN_KEEP_ID,
            self::MIGRATE_MODE_CLEAN_NEW_ID,
        ]);
    }

    public function shouldOverwrite()
    {
        return in_array($this->migration_mode, [
            self::MIGRATE_MODE_CLEAN_KEEP_ID,
            self::MIGRATE_MODE_KEEP_OVERWRITE,
        ]);
    }

    public function shouldIgnoreDuplicate()
    {
        return in_array($this->migration_mode, [
            self::MIGRATE_MODE_KEEP_IGNORE,
            self::MIGRATE_MODE_APPEND_NEW_ID,
        ]);
    }

    public function commitMigrateOptions($module, &$errors, &$fieldErrors = null)
    {
        if (!is_array($fieldErrors)) {
            $fieldErrors = [];
        }
        $migrate_data = array_filter($this->getDataToMigrate(), function ($item) {
            return trim($item) !== '';
        });
        if (in_array('product', $migrate_data) && !in_array('category', $migrate_data)) {
            $migrate_data[] = 'category';
            $this->setDataToMigrate($migrate_data);
            $migrate_data = array_filter($this->getDataToMigrate(), function ($item) {
                return trim($item) !== '';
            });
        }
        if (!count($migrate_data)) {
            EtsMigrateConfigForm::addFieldError($errors, $fieldErrors, 'ETS_EM_DATA_TO_MIGRATE', $module->l('Select at least one data type to migrate.', 'AdminETSEMMigrateController'));
        }

        $resources = EtsMigrateResourceCatalog::getResources();
        if (
            is_array($migrate_data)
            && count($migrate_data) > 0
            && is_array($resources)
            && count($resources) > 0
        ) {
            $data_sort = [];
            foreach (array_keys($resources) as $task) {
                if (in_array($task, $migrate_data)) {
                    $data_sort[] = $task;
                }
            }
            $this->setDataToMigrate($data_sort);
        }

        if ($shops = trim(Tools::getValue('ETS_EM_SHOPS_MAPPING'))) {
            $shops = explode(',', $shops);
            $shops_mapping = [];
            $allowedTargetShops = [];
            foreach (Shop::getShops(false) as $targetShop) {
                if (isset($targetShop['id_shop'])) {
                    $allowedTargetShops[(int) $targetShop['id_shop']] = (int) $targetShop['id_shop'];
                }
            }
            $allowedSourceShops = [];
            $sourceMeta = $this->getSourceMigrationMetadata();
            if (isset($sourceMeta['source_shops']) && is_array($sourceMeta['source_shops'])) {
                foreach ($sourceMeta['source_shops'] as $sourceShop) {
                    if (isset($sourceShop['id_shop'])) {
                        $allowedSourceShops[(int) $sourceShop['id_shop']] = (int) $sourceShop['id_shop'];
                    }
                }
            }
            foreach ($shops as $shop) {
                $id_source_shop = (int) Tools::getValue('id_shop_source_' . $shop);
                $id_target_shop = Tools::getValue('id_shop_target_' . $shop);
                if (trim($id_target_shop) === '' || $id_target_shop < 0) {
                    continue;
                }
                $id_target_shop = (int) $id_target_shop;
                if ($id_source_shop <= 0 || ($allowedSourceShops && !isset($allowedSourceShops[$id_source_shop]))) {
                    EtsMigrateConfigForm::addFieldError($errors, $fieldErrors, 'ETS_EM_SHOPS_MAPPING', $module->l('Shop mapping is invalid. Source shop is not allowed.', 'AdminETSEMMigrateController'));
                    break;
                }
                if ($id_target_shop > 0 && !isset($allowedTargetShops[$id_target_shop])) {
                    EtsMigrateConfigForm::addFieldError($errors, $fieldErrors, 'ETS_EM_SHOPS_MAPPING', $module->l('Shop mapping is invalid. Target shop does not exist.', 'AdminETSEMMigrateController'));
                    break;
                }
                $shops_mapping[$id_source_shop] = $id_target_shop;
            }
            if (!count($errors) && !count($shops_mapping)) {
                EtsMigrateConfigForm::addFieldError($errors, $fieldErrors, 'ETS_EM_SHOPS_MAPPING', $module->l('Shop mapping is invalid. Please select target shop or create new shop.', 'AdminETSEMMigrateController'));
            } elseif (!count($errors)) {
                $mapped_target_shops = [];
                foreach ($shops_mapping as $id_target_shop) {
                    $id_target_shop = (int) $id_target_shop;
                    if ($id_target_shop > 0) {
                        $mapped_target_shops[] = $id_target_shop;
                    }
                }
                if (count($mapped_target_shops) !== count(array_unique($mapped_target_shops))) {
                    EtsMigrateConfigForm::addFieldError($errors, $fieldErrors, 'ETS_EM_SHOPS_MAPPING', $module->l('Shop mapping is invalid. Each target shop can be selected only once.', 'AdminETSEMMigrateController'));
                } else {
                    if (EtsMigrateDataImport::saveShopMapping($shops_mapping) === false) {
                        EtsMigrateConfigForm::addFieldError($errors, $fieldErrors, 'ETS_EM_SHOPS_MAPPING', $module->l('Could not save shop mapping. Check database permissions and try again.', 'AdminETSEMMigrateController'));
                    }
                }
            }
        } else {
            $shops_mapped = false;
            $savedMapping = $this->loadMappingShops();
            if (is_array($savedMapping) && count($savedMapping) > 0) {
                $shops_mapped = true;
            } else {
                $target_shops = Shop::getShops();
                if (count($target_shops) === 1) {
                    $data = $this->getSourceMigrationMetadata();
                    if (isset($data['source_shops']) && count($data['source_shops']) === 1) {
                        $source_shop = reset($data['source_shops']);
                        $target_shop = reset($target_shops);
                        if (isset($source_shop['id_shop'], $target_shop['id_shop'])) {
                            if (EtsMigrateDataImport::saveShopMapping([(int) $source_shop['id_shop'] => (int) $target_shop['id_shop']]) === false) {
                                EtsMigrateConfigForm::addFieldError($errors, $fieldErrors, 'ETS_EM_SHOPS_MAPPING', $module->l('Could not save shop mapping. Check database permissions and try again.', 'AdminETSEMMigrateController'));
                            } else {
                                $shops_mapped = true;
                            }
                        }
                    }
                }
            }
            if (!$shops_mapped) {
                EtsMigrateDataImport::cleanShopsMapping();
                $sourceCount = 0;
                $data = $this->getSourceMigrationMetadata();
                if (isset($data['source_shops']) && is_array($data['source_shops'])) {
                    $sourceCount = count($data['source_shops']);
                }
                $target_shops = Shop::getShops();
                if ($sourceCount > 1 || count($target_shops) > 1) {
                    EtsMigrateConfigForm::addFieldError($errors, $fieldErrors, 'ETS_EM_SHOPS_MAPPING', $module->l('Shop mapping is required when source or target has more than one shop.', 'AdminETSEMMigrateController'));
                }
            }
        }

        if (!count($errors)) {
            self::$mapping_shop = [];
            self::$source_shops = [];
            self::$target_shops = [];
            if ($this->loadMappingShops() === false) {
                EtsMigrateConfigForm::addFieldError($errors, $fieldErrors, 'ETS_EM_SHOPS_MAPPING', $module->l('Could not load shop mapping. Save the mapping again and retry.', 'AdminETSEMMigrateController'));
            }
        }

        $migrate_data = $this->getDataToMigrate();
        $infos = $this->getSourceMigrationMetadata();
        $images = [];
        $files = [];
        if (
            is_array($migrate_data)
            && count($migrate_data) > 0
        ) {
            foreach ($migrate_data as $data) {
                $resourceDefinition = EtsMigrateResourceCatalog::getResources($data);
                if (
                    isset($resourceDefinition['images'])
                    && count($resourceDefinition['images']) > 0
                ) {
                    $images += $resourceDefinition['images'];
                }
                if (
                    isset($resourceDefinition['files'])
                    && count($resourceDefinition['files']) > 0
                ) {
                    $files += $resourceDefinition['files'];
                }
            }
        }
        $this
            ->setMigrateImages($images)
            ->setMigrateFiles($files);

        $em_keep_old_id = in_array((int) Configuration::getGlobalValue('ETS_EM_MIGRATION_METHOD'), [
            EtsMigrateDataImport::MIGRATE_MODE_CLEAN_KEEP_ID,
            EtsMigrateDataImport::MIGRATE_MODE_KEEP_IGNORE,
            EtsMigrateDataImport::MIGRATE_MODE_KEEP_OVERWRITE,
        ]);
        if (count($images) > 0
            && isset($infos['images'])
            && (int) $infos['images'] > 0
            && (!$em_keep_old_id || trim(Configuration::getGlobalValue('ETS_EM_MIGRATE_IMAGES')) === 'auto')
        ) {
            $this->setDataToMigrate('images');
        }
        if (count($files) > 0
            && isset($infos['files'])
            && (int) $infos['files'] > 0
            && (!$em_keep_old_id || trim(Configuration::getGlobalValue('ETS_EM_ATTACHMENTS_FILES')) === 'auto')
        ) {
            $this->setDataToMigrate('files');
        }

        if (!count($errors)) {
            $info_name = 'finished';
            $info = $this->getSourceMigrationMetadata($info_name);
            $info['nb'] = $info['nb_group_table'] = (int) EtsMigrateTools::queryTable('product');
            $this
                ->setDataToMigrate($info_name)
                ->setSourceMigrationMetadata($info, $info_name);
        }
    }

    public function getUnique($table = null)
    {
        if ($table === null) {
            return [];
        }
        if (
            !$this->migrate_unique
            || !isset($this->migrate_unique[$table])
        ) {
            $this->migrate_unique = json_decode(trim((string) Configuration::getGlobalValue(self::$prefix . 'MIGRATE_UNIQUE')), true);
        }
        if (empty($this->migrate_unique[$table]) && EtsMigrateTools::tableExists($table)) {
            $this->loadUniqueIndexes($table);
        }

        return isset($this->migrate_unique[$table]) && is_array($this->migrate_unique[$table]) && count($this->migrate_unique[$table]) > 0 ? $this->migrate_unique[$table] : [];
    }

    public function cleanUnique()
    {
        if ($this->migrate_unique) {
            $this->migrate_unique = [];
        }
        Configuration::updateGlobalValue(self::$prefix . 'MIGRATE_UNIQUE', json_encode($this->migrate_unique), true);

        return $this;
    }

    public function ignoreTable($table)
    {
        if (isset(self::$ignore_tables[$table])) {
            if (isset(self::$ignore_tables[$table]['delete']) && self::$ignore_tables[$table]['delete']) {
                return false;
            }

            return true;
        } elseif (preg_match('/^([a-z0-9A-Z\_]+)\_(shop|lang)$/', $table, $m) && !empty($m[1])) {
            return $this->ignoreTable(trim($m[1]));
        }

        return false;
    }

    private function executeImportBatch($table, $importBatch)
    {
        $rows = $this->skipRowsImportedBeforeResume($table, $importBatch['rows'], $importBatch['source_column']);
        if ($rows === false) {
            return false;
        }
        if (!$rows) {
            return true;
        }
        if (!$this->isTransactionalTable($table)) {
            return $this->executeRowsResumingAfterFailure($table, $rows, $importBatch['groups']);
        }
        if (!$this->beginImportTransaction($table)) {
            return false;
        }
        $batchError = $this->executeMultiRowInserts($table, $rows, $importBatch['groups']);
        if (!$batchError) {
            return $this->commitImportTransaction($table);
        }
        $this->executeImportSql('ROLLBACK');
        if (!$this->isRowDataError($batchError)) {
            self::writeLog('Import stopped on table ' . $table . ': ' . $batchError['message'], 'ERROR');

            return false;
        }
        self::writeLog('Batch insert failed on table ' . $table . ': ' . $batchError['message'] . '; locating the failing rows.', 'WARNING');

        return $this->retryRowsInTransaction($table, $rows, $importBatch['groups']);
    }

    private function retryRowsInTransaction($table, $rows, $groups)
    {
        if (!$this->beginImportTransaction($table)) {
            return false;
        }
        if (!$this->executeRowsResumingAfterFailure($table, $rows, $groups)) {
            $this->executeImportSql('ROLLBACK');

            return false;
        }

        return $this->commitImportTransaction($table);
    }

    private function executeMultiRowInserts($table, $rows, $groups)
    {
        $runs = [];
        foreach ($rows as $row) {
            $lastRun = count($runs) - 1;
            if ($lastRun < 0 || $runs[$lastRun][0]['group'] !== $row['group']) {
                $runs[] = [];
                ++$lastRun;
            }
            $runs[$lastRun][] = $row;
        }
        foreach ($runs as $runRows) {
            $result = $this->executeImportSql($this->buildInsertStatement($groups[$runRows[0]['group']], array_column($runRows, 'tuple')));
            if ($result['error']) {
                return $result['error'];
            }
            $this->logImportWarnings($table, $runRows);
        }

        return null;
    }

    private function logImportWarnings($table, $rows)
    {
        $db = Db::getInstance();
        $warnings = $db->executeS('SHOW WARNINGS', true, false);
        if (!$warnings) {
            return;
        }
        $notInserted = [];
        $logged = 0;
        foreach ($warnings as $warning) {
            $code = (int) $warning['Code'];
            if (in_array($code, self::$ignored_warning_numbers, true)) {
                continue;
            }
            ++$logged;
            if (in_array($code, self::$row_not_inserted_warning_numbers, true)) {
                $notInserted[] = $warning['Message'];
                continue;
            }
            $rowIndex = preg_match('/ at row (\d+)/', $warning['Message'], $match) ? (int) $match[1] - 1 : -1;
            $rowKey = isset($rows[$rowIndex]) ? ' [' . $rows[$rowIndex]['row'] . ']' : '';
            if (in_array($code, self::$value_adjusted_warning_numbers, true)) {
                self::writeLog('Row stored with an adjusted value on table ' . $table . $rowKey . ': ' . $warning['Message'], 'WARNING');
            } else {
                self::writeLog('MySQL warning on table ' . $table . $rowKey . ': ' . $warning['Message'], 'INFO');
            }
        }
        if ($notInserted) {
            self::writeLog('Table ' . $table . ': row(s) not inserted by INSERT IGNORE [' . implode('; ', array_slice($notInserted, 0, 50)) . ']', 'INFO');
        }
        if ($logged === 0) {
            return;
        }
        $countRows = $db->executeS('SHOW COUNT(*) WARNINGS', true, false);
        $warningTotal = $countRows ? (int) reset($countRows[0]) : count($warnings);
        if ($warningTotal > count($warnings)) {
            self::writeLog('Table ' . $table . ': ' . $warningTotal . ' MySQL warning(s) in this batch, ' . count($warnings) . ' listed.', 'INFO');
        }
    }

    private function executeRowsResumingAfterFailure($table, $rows, $groups)
    {
        $statements = [];
        foreach ($rows as $row) {
            $statements[] = $this->buildInsertStatement($groups[$row['group']], [$row['tuple']]) . ';';
        }
        $multiStatement = $this->supportsMultiStatementImport();
        $total = count($statements);
        $offset = 0;
        $skipped = 0;
        while ($offset < $total) {
            $pending = $multiStatement ? array_slice($statements, $offset) : [$statements[$offset]];
            $result = $this->executeImportSql(implode('', $pending));
            if (!$result['error']) {
                $offset += count($pending);
                continue;
            }
            $failedIndex = min($offset + $result['completed'], $total - 1);
            if (!$this->isRowDataError($result['error'])) {
                self::writeLog('Import stopped on table ' . $table . ' [' . $rows[$failedIndex]['row'] . ']: ' . $result['error']['message'], 'ERROR');

                return false;
            }
            ++$skipped;
            self::writeLog('Row skipped on table ' . $table . ' [' . $rows[$failedIndex]['row'] . ']: ' . $result['error']['message'], 'ERROR');
            $offset = $failedIndex + 1;
        }
        if ($skipped > 0) {
            self::writeLog('Table ' . $table . ': ' . ($total - $skipped) . ' row(s) imported, ' . $skipped . ' row(s) skipped.', 'WARNING');
        }

        return true;
    }

    private function skipRowsImportedBeforeResume($table, $rows, $sourceColumn)
    {
        $sourceIds = [];
        foreach ($rows as $row) {
            if ($row['source_id'] > 0) {
                $sourceIds[$row['source_id']] = (int) $row['source_id'];
            }
        }
        if (!$sourceIds || $sourceColumn === '') {
            return $rows;
        }
        $column = bqSQL($sourceColumn);
        $db = Db::getInstance();
        $importedRows = $db->executeS('SELECT `' . $column . '` AS `source_id` FROM `' . _DB_PREFIX_ . bqSQL($table) . '` WHERE `' . $column . '` IN (' . implode(',', $sourceIds) . ')', true, false);
        if ($importedRows === false && $db->getMsgError() !== '') {
            self::writeLog('Resume check failed on table ' . $table . ': ' . $db->getMsgError(), 'ERROR');

            return false;
        }
        if (!$importedRows) {
            return $rows;
        }
        $importedIds = array_flip(array_map('intval', array_column($importedRows, 'source_id')));
        $remainingRows = [];
        $skippedRows = [];
        foreach ($rows as $row) {
            if ($row['source_id'] > 0 && isset($importedIds[$row['source_id']])) {
                $skippedRows[] = $row['row'];
                continue;
            }
            $remainingRows[] = $row;
        }
        self::writeLog('Table ' . $table . ': ' . count($skippedRows) . ' row(s) already imported by an interrupted run, not inserted again [' . implode('; ', array_slice($skippedRows, 0, 50)) . ']', 'INFO');

        return $remainingRows;
    }

    private function isTransactionalTable($table)
    {
        if (!isset(self::$transactional_tables[$table])) {
            $engine = Db::getInstance()->getValue('SELECT `ENGINE` FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = \'' . pSQL(_DB_PREFIX_ . $table) . '\'', false);
            self::$transactional_tables[$table] = is_string($engine) && Tools::strtolower($engine) === 'innodb';
        }

        return self::$transactional_tables[$table];
    }

    private function beginImportTransaction($table)
    {
        $result = $this->executeImportSql('START TRANSACTION');
        if ($result['error']) {
            self::writeLog('Import stopped on table ' . $table . ': ' . $result['error']['message'], 'ERROR');

            return false;
        }

        return true;
    }

    private function commitImportTransaction($table)
    {
        $result = $this->executeImportSql('COMMIT');
        if ($result['error']) {
            self::writeLog('Commit failed on table ' . $table . ': ' . $result['error']['message'], 'ERROR');

            return false;
        }

        return true;
    }

    private function isRowDataError($error)
    {
        return in_array((int) $error['number'], self::$row_data_error_numbers, true);
    }

    private function supportsMultiStatementImport()
    {
        $link = $this->getImportLink();
        if ($link instanceof PDO) {
            return !defined('_PS_ALLOW_MULTI_STATEMENTS_QUERIES_') || EtsMigrateMultiStatementConfig::isEnabledInRuntime();
        }

        return $link instanceof mysqli;
    }

    private function getImportLink()
    {
        $db = Db::getInstance();

        return method_exists($db, 'getLink') ? $db->getLink() : null;
    }

    private function executeImportSql($sql)
    {
        $link = $this->getImportLink();
        if ($link instanceof PDO) {
            return $this->executeImportSqlWithPdo($link, $sql);
        }
        if ($link instanceof mysqli) {
            return $this->executeImportSqlWithMysqli($link, $sql);
        }
        $db = Db::getInstance();
        try {
            $executed = $db->execute($sql, false);
        } catch (PrestaShopDatabaseException $exception) {
            $executed = false;
        }

        return $executed
            ? ['completed' => 1, 'error' => null]
            : ['completed' => 0, 'error' => ['number' => (int) $db->getNumberError(), 'message' => (string) $db->getMsgError()]];
    }

    private function executeImportSqlWithPdo($link, $sql)
    {
        $errorMode = $link->getAttribute(PDO::ATTR_ERRMODE);
        $link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $completed = 0;
        $errorInfo = null;
        try {
            $statement = $link->query($sql);
            if ($statement === false) {
                $errorInfo = $link->errorInfo();
            } else {
                $completed = 1;
                while ($statement->nextRowset()) {
                    ++$completed;
                }
                if ($statement->errorCode() !== '00000') {
                    $errorInfo = $statement->errorInfo();
                }
                $statement->closeCursor();
            }
        } catch (PDOException $exception) {
            $errorInfo = is_array($exception->errorInfo) ? $exception->errorInfo : [$exception->getCode(), 0, $exception->getMessage()];
        } finally {
            $link->setAttribute(PDO::ATTR_ERRMODE, $errorMode);
        }

        return [
            'completed' => $completed,
            'error' => $errorInfo ? ['number' => (int) $errorInfo[1], 'message' => (string) $errorInfo[2]] : null,
        ];
    }

    private function executeImportSqlWithMysqli($link, $sql)
    {
        $completed = 0;
        $error = null;
        try {
            if ($link->multi_query($sql)) {
                $completed = 1;
                while ($link->more_results() && $link->next_result()) {
                    ++$completed;
                }
            }
            if ($link->errno) {
                $error = ['number' => (int) $link->errno, 'message' => (string) $link->error];
            }
        } catch (mysqli_sql_exception $exception) {
            $error = ['number' => (int) $exception->getCode(), 'message' => $exception->getMessage()];
        }

        return ['completed' => $completed, 'error' => $error];
    }

    public function generateSQL($table, $data, $foreign_keys = [])
    {
        $this->time_execution = time();
        $importBatch = ['rows' => [], 'groups' => [], 'source_column' => ''];
        if ($data) {
            $migrate_ps_1770 = version_compare(_PS_VERSION_, '1.7.7.0', '>=') || in_array(trim($table), self::$ignore_null_field_1770) ? 1 : 0;

            $migrate_ps_15010 = version_compare($this->source_version, '1.5.0.10', '<') && version_compare(_PS_VERSION_, '1.5.0.10', '>') ? 1 : 0;

            $migrate_ps_1504 = version_compare($this->source_version, '1.5.0.4', '>=') ? 1 : 0;

            $keep_id_ignore_table = isset(self::$ignore_tables[$table]) && (!isset(self::$ignore_tables[$table]['delete']) || !self::$ignore_tables[$table]['delete']) ? 1 : 0;

            $prefetchKeys = is_array($foreign_keys) ? $foreign_keys : [];
            if (isset(self::$ignore_tables[$table])) {
                $ignoreForeignKeys = EtsMigrateForeignKeyCatalog::getForeignKeyMap($table);
                if (is_array($ignoreForeignKeys)) {
                    foreach ($ignoreForeignKeys as $foreignTable => $foreignKey) {
                        if (!isset($prefetchKeys[$foreignTable])) {
                            $prefetchKeys[$foreignTable] = $foreignKey;
                        }
                    }
                }
            }
            $this->prefetchForeignKeyMappings($data, $prefetchKeys);

            if (count($this->id_mapping_cache) > (int) self::$id_mapping_cache_bound) {
                $this->id_mapping_cache = [];
            }

            $tableName = trim($table);
            $schema_columns = $this->getSchemaColumn($table);
            $migrate_schema = $this->getMigrateSchema($table);
            $migrate_primary = $this->getMigratePrimary($table);
            $migrate_fields = $this->getMigrateFields($table);
            $migrate_unique = $this->getUnique($table);
            $migrate_primary_set = is_array($migrate_primary) ? array_flip($migrate_primary) : [];
            $foreign_auto_increment = [];
            foreach (array_keys($migrate_primary_set) as $primaryField) {
                if (EtsMigrateForeignKeyCatalog::isForeignKeyColumn($tableName, $primaryField)) {
                    $foreign_auto_increment[$primaryField] = true;
                }
            }
            $migrate_fields_set = is_array($migrate_fields) ? array_flip($migrate_fields) : [];
            $unique_field_set = [];
            if (is_array($migrate_unique)) {
                foreach ($migrate_unique as $uniqueColumns) {
                    if (!is_array($uniqueColumns)) {
                        continue;
                    }
                    foreach ($uniqueColumns as $uniqueColumn) {
                        $unique_field_set[$uniqueColumn] = true;
                    }
                }
            }
            $defaultCurrencyId = null;
            $ignore_keep_id_set = array_flip(self::$ignore_keep_id);

            $existingShopGroups = null;
            if ($tableName === 'shop_group') {
                $shopGroupIds = [];
                foreach ($data as $prefetchItem) {
                    if (isset($prefetchItem['id_shop_group']) && (int) $prefetchItem['id_shop_group'] > 0) {
                        $shopGroupIds[(int) $prefetchItem['id_shop_group']] = true;
                    }
                }
                $existingShopGroups = [];
                if ($shopGroupIds) {
                    $shopGroupRows = Db::getInstance()->executeS('SELECT `id_shop_group` FROM `' . _DB_PREFIX_ . 'shop_group` WHERE `id_shop_group` IN (' . implode(',', array_map('intval', array_keys($shopGroupIds))) . ')');
                    if ($shopGroupRows === false) {
                        $message = Db::getInstance()->getMsgError();
                        if (is_string($message) && $message !== '') {
                            self::writeLog('Shop group exists lookup failed: ' . $message, 'ERROR');
                            self::flushLogBuffer();

                            return false;
                        }
                    } else {
                        foreach ($shopGroupRows as $shopGroupRow) {
                            $existingShopGroups[(int) $shopGroupRow['id_shop_group']] = true;
                        }
                    }
                }
            }

            $existingShops = null;
            if ($tableName === 'shop') {
                $shopTargetIds = [];
                foreach ($data as $prefetchItem) {
                    if (!isset($prefetchItem['id_shop']) || (int) $prefetchItem['id_shop'] <= 0) {
                        continue;
                    }
                    $sourceShopId = (int) $prefetchItem['id_shop'];
                    if (isset(self::$mapping_shop[$sourceShopId]) && (int) self::$mapping_shop[$sourceShopId] > 0) {
                        $shopTargetIds[(int) self::$mapping_shop[$sourceShopId]] = true;
                    }
                }
                $existingShops = [];
                if ($shopTargetIds) {
                    $shopRows = Db::getInstance()->executeS('SELECT `id_shop` FROM `' . _DB_PREFIX_ . 'shop` WHERE `id_shop` IN (' . implode(',', array_map('intval', array_keys($shopTargetIds))) . ')');
                    if ($shopRows === false) {
                        $message = Db::getInstance()->getMsgError();
                        if (is_string($message) && $message !== '') {
                            self::writeLog('Shop exists lookup failed: ' . $message, 'ERROR');
                            self::flushLogBuffer();

                            return false;
                        }
                    } else {
                        foreach ($shopRows as $shopRow) {
                            $existingShops[(int) $shopRow['id_shop']] = true;
                        }
                    }
                }
            }

            $this->customer_email_exists_map = null;
            if ($tableName === 'customer') {
                $customerEmails = [];
                foreach ($data as $prefetchItem) {
                    if (isset($prefetchItem['email']) && trim($prefetchItem['email']) !== '') {
                        $customerEmails[trim($prefetchItem['email'])] = true;
                    }
                }
                $this->customer_email_exists_map = [];
                if ($customerEmails) {
                    $emailInList = [];
                    foreach (array_keys($customerEmails) as $customerEmail) {
                        $emailInList[] = '\'' . pSQL($customerEmail) . '\'';
                    }
                    $customerRows = Db::getInstance()->executeS('SELECT `email`, `id_customer`, `id_shop_group`, `id_shop`, `is_guest` FROM `' . _DB_PREFIX_ . 'customer`
                        WHERE `email` IN (' . implode(',', $emailInList) . ')
                        ORDER BY `id_customer` ASC');
                    if ($customerRows === false) {
                        $message = Db::getInstance()->getMsgError();
                        if (is_string($message) && $message !== '') {
                            self::writeLog('Customer email lookup failed: ' . $message, 'ERROR');
                            self::flushLogBuffer();

                            return false;
                        }
                    } else {
                        foreach ($customerRows as $customerRow) {
                            $emailMapKey = (int) $customerRow['id_shop_group'] . '|' . Tools::strtolower(trim($customerRow['email']));
                            if (!isset($this->customer_email_exists_map[$emailMapKey])) {
                                $this->customer_email_exists_map[$emailMapKey] = (int) $customerRow['id_customer'];
                            }
                            $shopEmailMapKey = 'shop:' . (int) $customerRow['id_shop'] . '|' . Tools::strtolower(trim($customerRow['email']));
                            if (!(int) $customerRow['is_guest'] && !isset($this->customer_email_exists_map[$shopEmailMapKey])) {
                                $this->customer_email_exists_map[$shopEmailMapKey] = (int) $customerRow['id_customer'];
                            }
                        }
                    }
                }
            }

            $ignoreTableNaturalKeyRows = null;
            $ignoreTableField = null;
            $ignoreTableFks = null;
            $ignoreTableSelectReady = false;
            if (
                isset(self::$ignore_tables[$table])
                && ($ignoreTableField = self::$ignore_tables[$table])
                && isset($ignoreTableField['field'], $ignoreTableField['pri'])
                && preg_match('/^[a-zA-Z0-9_]+$/', $ignoreTableField['field'])
                && preg_match('/^[a-zA-Z0-9_]+$/', $ignoreTableField['pri'])
            ) {
                $ignoreTableFks = EtsMigrateForeignKeyCatalog::getForeignKeyMap($table);
                $ignoreSelectColumns = [
                    '`' . bqSQL($ignoreTableField['pri']) . '`',
                    '`' . bqSQL($ignoreTableField['field']) . '`',
                ];
                if (is_array($ignoreTableFks) && count($ignoreTableFks) > 0) {
                    foreach ($ignoreTableFks as $fk_table => $fk_key) {
                        if (is_array($fk_key)) {
                            foreach ($fk_key as $id_source => $id_target) {
                                if (!is_array($id_target) && preg_match('/^[a-zA-Z0-9_]+$/', $id_source)) {
                                    $ignoreSelectColumns[] = '`' . bqSQL($id_source) . '`';
                                }
                            }
                        } elseif (preg_match('/^[a-zA-Z0-9_]+$/', $fk_key)) {
                            $ignoreSelectColumns[] = '`' . bqSQL($fk_key) . '`';
                        }
                    }
                }
                if (isset($schema_columns['active'])) {
                    $ignoreSelectColumns[] = '`active`';
                }
                $ignoreSelectColumns = array_values(array_unique($ignoreSelectColumns));
                $ignoreTableSelectReady = true;
                $naturalKeyValues = [];
                foreach ($data as $prefetchItem) {
                    if (!isset($prefetchItem[$ignoreTableField['field']]) || !trim($prefetchItem[$ignoreTableField['field']])) {
                        continue;
                    }
                    $naturalKeyValue = ($tableName !== 'lang'
                        || !isset(self::$iso_code_mappings[$prefetchItem[$ignoreTableField['field']]])
                        || !Validate::isLangIsoCode(self::$iso_code_mappings[$prefetchItem[$ignoreTableField['field']]])
                            ? trim($prefetchItem[$ignoreTableField['field']])
                            : trim(self::$iso_code_mappings[$prefetchItem[$ignoreTableField['field']]]));
                    $naturalKeyValues[$naturalKeyValue] = true;
                }
                $ignoreTableNaturalKeyRows = [];
                if ($naturalKeyValues) {
                    $naturalKeyInList = [];
                    foreach (array_keys($naturalKeyValues) as $naturalKeyValue) {
                        $naturalKeyInList[] = '\'' . pSQL($naturalKeyValue) . '\'';
                    }
                    $ignoreQuery = (new DbQuery())
                        ->select(implode(',', $ignoreSelectColumns))
                        ->from($table)
                        ->where('`' . bqSQL($ignoreTableField['field']) . '` IN (' . implode(',', $naturalKeyInList) . ')');
                    if ($tableName === 'customer') {
                        $ignoreQuery->where('`is_guest`=0');
                    }
                    $ignoreRows = Db::getInstance()->executeS($ignoreQuery);
                    if ($ignoreRows === false) {
                        $message = Db::getInstance()->getMsgError();
                        if (is_string($message) && $message !== '') {
                            self::writeLog('Mapping lookup failed: ' . $table . '.' . $ignoreTableField['field'] . ' IN(...): ' . $message, 'ERROR');
                            self::flushLogBuffer();

                            return false;
                        }
                    } else {
                        foreach ($ignoreRows as $ignoreRow) {
                            if (!isset($ignoreRow[$ignoreTableField['field']])) {
                                continue;
                            }
                            $ignoreTableNaturalKeyRows[$this->getNaturalKeyIndex($tableName, $ignoreRow[$ignoreTableField['field']])] = $ignoreRow;
                        }
                    }
                }
                $mappingColumn = $ignoreTableField['pri'] . '_ets_old';
                if (!$this->ensureMappingColumn($table, $ignoreTableField['pri'], $mappingColumn)) {
                    self::writeLog("Mapping column ensure failed: {$table}.{$mappingColumn}", 'ERROR');
                    self::flushLogBuffer();

                    return false;
                }
            }

            $id_root_category = null;
            foreach ($data as $item) {
                if ($tableName == 'shop_group' && isset($item['id_shop_group']) && ($idGroupSource = (int) $item['id_shop_group']) > 0) {
                    if (is_array($existingShopGroups) && isset($existingShopGroups[$idGroupSource])) {
                        $shareCustomer = isset($item['share_customer']) ? (int) $item['share_customer'] : 0;
                        $shareStock = isset($item['share_stock']) ? (int) $item['share_stock'] : 0;
                        $shareOrder = isset($item['share_order']) ? (int) $item['share_order'] : 0;
                        if (!$shareCustomer || !$shareStock) {
                            $shareOrder = 0;
                        }
                        if (!Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'shop_group`
                            SET `share_customer` = ' . (int) $shareCustomer . ',
                                `share_stock` = ' . (int) $shareStock . ',
                                `share_order` = ' . (int) $shareOrder . '
                            WHERE `id_shop_group` = ' . (int) $idGroupSource)) {
                            self::writeLog('Failed to sync shop_group share flags for group ' . (int) $idGroupSource . ': ' . Db::getInstance()->getMsgError(), 'ERROR');
                            self::flushLogBuffer();

                            return false;
                        }
                        if (method_exists('EtsMigrateTools', 'clearShopShareContextCache')) {
                            EtsMigrateTools::clearShopShareContextCache();
                        }
                        continue;
                    }

                    $sourceShops = isset($this->getSourceMigrationMetadata()['source_shops']) ? $this->getSourceMigrationMetadata()['source_shops'] : [];
                    $needImport = false;

                    foreach ($sourceShops as $shop) {
                        if (isset($shop['id_shop_group']) && (int) $shop['id_shop_group'] === $idGroupSource) {
                            $targetShopId = isset(self::$mapping_shop[(int) $shop['id_shop']]) ? (int) self::$mapping_shop[(int) $shop['id_shop']] : null;

                            if ($targetShopId === 0) {
                                $needImport = true;
                                break;
                            }
                        }
                    }

                    if (!$needImport) {
                        continue;
                    }
                } elseif (
                    $tableName == 'shop'
                    && isset($item['id_shop']) && $item['id_shop'] > 0
                ) {
                    $sourceId = (int) $item['id_shop'];

                    if (!isset(self::$mapping_shop[$sourceId])) {
                        continue;
                    }

                    $targetId = (int) self::$mapping_shop[$sourceId];

                    if ($targetId > 0 && is_array($existingShops) && isset($existingShops[$targetId])) {
                        continue;
                    }
                } elseif ($tableName == 'group' && !$this->shouldKeepOldId() && isset($item['id_group']) && ($targetDefaultGroupId = $this->getTargetDefaultGroupForSourceGroup((int) $item['id_group'])) > 0) {
                    if (!Db::getInstance()->update('group', ['id_group_ets_old' => (int) $item['id_group']], '`id_group` = ' . (int) $targetDefaultGroupId)) {
                        self::writeLog('Failed to map default customer group id_group_ets_old=' . (int) $item['id_group'] . ': ' . Db::getInstance()->getMsgError(), 'ERROR');
                        self::flushLogBuffer();

                        return false;
                    }
                    $this->id_mapping_cache[$this->buildIdMappingCacheKey('group', 'id_group', (int) $item['id_group'])] = $targetDefaultGroupId;
                    self::writeLog('Default customer group source id_group=' . (int) $item['id_group'] . ' mapped to target id_group=' . $targetDefaultGroupId . '.', 'INFO');
                    continue;
                } elseif (
                    $tableName == 'category'
                    && !$this->shouldKeepOldId()
                    && isset($item['id_category'])
                    && (int) $item['id_category'] > 0
                ) {
                    $isParentZero = isset($item['id_parent']) && (int) $item['id_parent'] <= 0;
                    $isRootFlag = $migrate_ps_1504 && isset($item['is_root_category']) && (int) $item['is_root_category'] > 0;
                    if ($isParentZero || $isRootFlag || !$migrate_ps_1504) {
                        if (!isset($id_root_category)) {
                            $id_root_category = EtsMigrateTools::queryTable($tableName, false, 'id_category', 0, 0, ['is_root_category=1'], true);
                            if ($id_root_category === false) {
                                self::writeLog('Failed to lookup root category', 'ERROR');
                                self::flushLogBuffer();

                                return false;
                            }
                            $id_root_category = (int) $id_root_category;
                        }
                    }
                    $isRootCandidate = $isParentZero
                        || $isRootFlag
                        || (!$migrate_ps_1504 && isset($id_root_category) && (int) $item['id_category'] == $id_root_category);
                    if ($isRootCandidate) {
                        if ($isParentZero) {
                            $id_category = EtsMigrateTools::queryTable($tableName, false, 'id_category', 0, 0, ['id_parent=0'], true);
                            if ($id_category === false) {
                                self::writeLog('Failed to lookup parent-zero category', 'ERROR');
                                self::flushLogBuffer();

                                return false;
                            }
                            $id_category = (int) $id_category;
                        } else {
                            $id_category = (int) $id_root_category;
                        }
                        if ($id_category <= 0) {
                            if (!Db::getInstance()->update('configuration', ['value' => (int) $item['id_category']], 'name=\'' . (isset($id_root_category) ? 'PS_ROOT_CATEGORY' : 'PS_HOME_CATEGORY') . '\'')) {
                                self::writeLog('Failed to update root/home category configuration', 'ERROR');
                                self::flushLogBuffer();

                                return false;
                            }
                        } else {
                            if (!Db::getInstance()->update($table, ['id_category_ets_old' => (int) $item['id_category']], 'id_category=' . (int) $id_category)) {
                                self::writeLog('Failed to map root category id_category_ets_old=' . (int) $item['id_category'], 'ERROR');
                                self::flushLogBuffer();

                                return false;
                            }
                            continue;
                        }
                    }
                }

                if (
                    $ignoreTableSelectReady
                    && is_array($ignoreTableField)
                    && isset($item[$ignoreTableField['field']])
                    && trim($item[$ignoreTableField['field']])
                ) {
                    $field = $ignoreTableField;
                    $item_field_value = ($tableName !== 'lang'
                        || !isset(self::$iso_code_mappings[$item[$field['field']]])
                        || !Validate::isLangIsoCode(self::$iso_code_mappings[$item[$field['field']]])
                            ? trim($item[$field['field']])
                            : trim(self::$iso_code_mappings[$item[$field['field']]]));
                    $fks = $ignoreTableFks;
                    $naturalKeyIndex = $this->getNaturalKeyIndex($tableName, $item_field_value);
                    $row = (is_array($ignoreTableNaturalKeyRows) && isset($ignoreTableNaturalKeyRows[$naturalKeyIndex]))
                        ? $ignoreTableNaturalKeyRows[$naturalKeyIndex]
                        : null;
                    if ($row) {
                        if (isset($item['deleted']) && (int) $item['deleted'] > 0) {
                            continue;
                        }
                        $fk_data = [];
                        if (is_array($fks) && count($fks) > 0) {
                            foreach ($fks as $fk_table => $fk_key) {
                                if (is_array($fk_key)) {
                                    continue;
                                }
                                if (trim($fk_table) !== 'shop' && isset($row[$fk_key]) && isset($item[$fk_key]) && $item[$fk_key]) {
                                    $mappedId = $this->getNewIdByOldId($fk_table, $fk_key, $item[$fk_key], !empty($schema_columns[$fk_key]) ? $schema_columns[$fk_key] : null);
                                    if ($mappedId === false) {
                                        self::writeLog("Mapping FK lookup failed: {$table}.{$fk_key}", 'ERROR');
                                        self::flushLogBuffer();

                                        return false;
                                    }
                                    $fk_data[$fk_key] = $mappedId;
                                }
                            }
                        }
                        if ($tableName === 'employee') {
                            $fk_data = [];
                            self::writeLog("Employee source id_employee={$item[$field['pri']]} matched target id_employee={$row[$field['pri']]} by email; target account kept unchanged.", 'INFO');
                        }
                        $mappingColumn = $field['pri'] . '_ets_old';
                        $fk_data[$mappingColumn] = (int) $item[$field['pri']];
                        if ($tableName !== 'employee' && array_key_exists('active', $item) && array_key_exists('active', $row)) {
                            $fk_data['active'] = (int) $item['active'];
                        }
                        if (!Db::getInstance()->update($table, $fk_data, '`' . bqSQL($field['field']) . '`=\'' . pSQL($item_field_value) . '\'')) {
                            self::writeLog("Mapping update failed: {$table}.{$mappingColumn}={$fk_data[$mappingColumn]} WHERE {$field['field']}={$item_field_value}", 'ERROR');
                            self::flushLogBuffer();

                            return false;
                        }
                        $mappedPrimaryId = isset($row[$field['pri']]) ? (int) $row[$field['pri']] : 0;
                        if ($mappedPrimaryId > 0) {
                            $this->id_mapping_cache[$this->buildIdMappingCacheKey($table, $field['pri'], (int) $item[$field['pri']])] = $mappedPrimaryId;
                            $this->id_mapping_cache[$this->buildNativeExistsCacheKey($table, $field['pri'], $mappedPrimaryId)] = 1;
                        }
                        // Existing natural-key row was mapped; do not INSERT a duplicate.
                        continue;
                    }
                    // Natural key not found on target: fall through to INSERT so new zones/profiles/etc. are created.
                }

                if (
                    is_array($migrate_schema)
                ) {
                    $fields_value = [];
                    $skip_item = false;
                    $rowSourceId = 0;
                    foreach ($migrate_schema as $schema) {
                        $field = trim($schema['Field']);

                        if (isset($migrate_primary_set[$field]) && isset($schema['Extra']) && trim($schema['Extra']) == 'auto_increment' && !isset($foreign_auto_increment[$field])) {
                            if ($tableName === 'stock_available') {
                                continue;
                            }
                            if ($this->shouldKeepOldId() && !$keep_id_ignore_table) {
                                $fields_value[$field] = !isset($ignore_keep_id_set[$tableName]) && isset($item[$field]) && (int) $item[$field] > 0 ? (int) $item[$field] : (isset($schema['Null']) && Tools::strtoupper(trim($schema['Null'])) !== 'YES' || $migrate_ps_1770 ? '\'\'' : 'NULL');
                            }
                            $fields_value[$field . '_ets_old'] = (isset($item[$field]) && Validate::isUnsignedInt($item[$field])) ? (int) $item[$field] : 'NULL';
                            if (isset($fields_value[$field]) && trim($fields_value[$field]) === '\'\'') {
                                unset($fields_value[$field]);
                            }
                            if (!isset($fields_value[$field]) && is_int($fields_value[$field . '_ets_old'])) {
                                $rowSourceId = $fields_value[$field . '_ets_old'];
                                $importBatch['source_column'] = $field . '_ets_old';
                            }
                            continue;
                        } elseif (!preg_match('/^(id_[0-9a-zA-Z\_]+_ets_old|[0-9a-zA-Z\_]+_old_wp)$/i', $field) && isset($migrate_fields_set[$field])) {
                            if ($tableName == 'shop' && $field == 'theme_name') {
                                $fields_value[$field] = '\'' . pSQL($this->resolveDefaultThemeName()) . '\'';
                            } elseif (preg_match('/(?:int|tinyint|mediumint)(?:\((\d+)\))?/i', $schema['Type'])) {
                                $fields_value[$field] = isset($item[$field]) && trim($item[$field]) != '' && Validate::isInt($item[$field]) ? (int) $item[$field] : (isset($schema['Default']) && trim($schema['Default']) !== '' ? (int) $schema['Default'] : (isset($schema['Null']) && Tools::strtoupper(trim($schema['Null'])) === 'YES' && !$migrate_ps_1770 || $table == 'product_attribute' && $field == 'default_on' ? 'NULL' : 0));
                            } elseif (preg_match('/(?:char|varchar)(?:\((\d+)\))?|text|mediumtext|longtext/i', $schema['Type'])) {
                                $fields_value[$field] = (isset($item[$field]) && trim($item[$field]) !== '' ? '\'' . pSQL($item[$field], true) . '\'' : (isset($schema['Default']) && trim($schema['Default']) !== '' ? '\'' . pSQL($schema['Default'], true) . '\'' : (isset($schema['Null']) && Tools::strtoupper(trim($schema['Null'])) === 'YES' && !$migrate_ps_1770 || $table == 'product_attribute' && $field == 'default_on' ? 'NULL' : '\'\'')));
                            } elseif (preg_match('/decimal\((\d+,\s*\d+)\)?/i', $schema['Type'])) {
                                $fields_value[$field] = isset($item[$field]) && trim($item[$field]) !== '' && Validate::isNegativePrice($item[$field]) ? (float) $item[$field] : (isset($schema['Default']) && trim($schema['Default']) !== '' ? (float) $schema['Default'] : (isset($schema['Null']) && Tools::strtoupper(trim($schema['Null'])) === 'YES' && !$migrate_ps_1770 || $table == 'product_attribute' && $field == 'default_on' ? 'NULL' : 0));
                            } elseif (preg_match('/float(?:\((\d+,\s*\d+)\))?/i', $schema['Type'])) {
                                $fields_value[$field] = isset($item[$field]) && trim($item[$field]) !== '' && Validate::isFloat($item[$field]) ? (float) $item[$field] : (isset($schema['Default']) && trim($schema['Default']) !== '' ? (float) $schema['Default'] : (isset($schema['Null']) && Tools::strtoupper(trim($schema['Null'])) === 'YES' && !$migrate_ps_1770 || $table == 'product_attribute' && $field == 'default_on' ? 'NULL' : 0));
                            } elseif (preg_match('/date|datetime/i', $schema['Type'])) {
                                if (isset($item[$field]) && Validate::isDate(trim($item[$field])) && (strtotime($item[$field]) > 0 || Tools::strtoupper(trim($schema['Null'])) !== 'YES' && (trim($item[$field]) == '0000-00-00' || trim($item[$field]) == '0000-00-00 00:00:00'))) {
                                    $fields_value[$field] = '\'' . pSQL($item[$field]) . '\'';
                                } elseif (isset($schema['Default']) && Validate::isDate($schema['Default']) && strtotime($schema['Default']) > 0) {
                                    $fields_value[$field] = '\'' . pSQL($schema['Default']) . '\'';
                                } else {
                                    $fields_value[$field] = isset($schema['Null']) && Tools::strtoupper(trim($schema['Null'])) === 'NO' ? '\'' . (trim($schema['Type']) !== 'datetime' ? '0000-00-00' : '0000-00-00 00:00:00') . '\'' : 'NULL';
                                }
                            } else {
                                $fields_value[$field] = (isset($item[$field]) && trim($item[$field]) != '' ? '\'' . pSQL($item[$field]) . '\'' : (isset($schema['Default']) && trim($schema['Default']) !== '' ? '\'' . pSQL($schema['Default']) . '\'' : (isset($schema['Null']) && Tools::strtoupper(trim($schema['Null'])) === 'YES' && !$migrate_ps_1770 || $table == 'product_attribute' && $field == 'default_on' ? 'NULL' : '\'\'')));
                            }
                        }

                        if (
                            $tableName === 'carrier'
                            && (isset($fields_value['is_module']) && (int) $fields_value['is_module'] == 1 || isset($fields_value['shipping_external']) && (int) $fields_value['shipping_external'] == 1)
                        ) {
                            $externalModuleName = isset($item['external_module_name']) ? trim($item['external_module_name']) : '';
                            $moduleExists = false;
                            if ($externalModuleName !== '') {
                                $moduleExistsRaw = Db::getInstance()->getValue('SELECT `id_module` FROM `' . _DB_PREFIX_ . 'module` WHERE `name` = \'' . pSQL($externalModuleName) . '\' AND `active`=1');
                                if ($moduleExistsRaw === false) {
                                    $message = Db::getInstance()->getMsgError();
                                    if (is_string($message) && $message !== '') {
                                        self::writeLog('Carrier module lookup failed: ' . $message, 'ERROR');
                                        self::flushLogBuffer();

                                        return false;
                                    }
                                } else {
                                    $moduleExists = (bool) $moduleExistsRaw;
                                }
                            }
                            if ($externalModuleName === '' || !$moduleExists) {
                                $fields_value['is_module'] = 0;
                                $fields_value['shipping_external'] = 0;
                            }
                        }

                        if (
                            $this->keep_passwd
                            && isset(self::$keep_passwd_tables[$table])
                            && $field == trim(self::$keep_passwd_tables[$table])
                        ) {
                            $fields_value[$field . '_old_wp'] = '\'' . pSQL($item[$field]) . '\'';
                        }

                        if (
                            trim($field) == 'id_shop_group'
                            && $migrate_ps_15010
                            && isset($item['id_group_shop'])
                        ) {
                            $fields_value[$field] = (int) $item['id_group_shop'];
                        }
                        if (
                            $tableName !== 'shop'
                            && $tableName !== 'shop_group'
                            && trim($field) === 'id_shop'
                            && isset($fields_value[$field])
                        ) {
                            if (EtsMigrateCmsLegacyPostImport::isLegacyLang($this, $table) && !array_key_exists($field, $item)) {
                                $fields_value[$field] = EtsMigrateTools::resolveDefaultShopId($this);
                            } else {
                                $sourceShopId = isset($item[$field]) ? (int) $item[$field] : 0;
                                if (
                                    $sourceShopId > 0
                                    && (int) $fields_value[$field] > 0
                                    && isset(self::$mapping_shop[$sourceShopId])
                                    && self::$mapping_shop[$sourceShopId] > 0
                                ) {
                                    $fields_value[$field] = self::$mapping_shop[$sourceShopId];
                                    $target_group_id = self::getTargetShopGroup($fields_value[$field]);
                                    if ($target_group_id === false) {
                                        self::writeLog("Shop group lookup failed for shop {$fields_value[$field]}", 'ERROR');
                                        self::flushLogBuffer();

                                        return false;
                                    }
                                    if ($target_group_id && isset($fields_value['id_shop_group'])) {
                                        $fields_value['id_shop_group'] = $target_group_id;
                                    }
                                } elseif (
                                    $sourceShopId > 0
                                ) {
                                    $skip_item = true;
                                    self::writeLog("Data mapping ERROR: Table '{$table}' row [" . $this->describeSourceRow($table, $item) . "] - Skipping item due to missing target shop mapping for source shop '{$sourceShopId}'.", 'ERROR');
                                    break;
                                } elseif (
                                    isset($item[$field])
                                    && (int) $item[$field] == 0
                                    && (int) $fields_value[$field] == 0
                                ) {
                                    $fields_value[$field] = 0;
                                } elseif (
                                    array_key_exists($field, $item)
                                    && ($item[$field] === null || Tools::strtoupper(trim($item[$field])) === 'NULL')
                                ) {
                                    $fields_value[$field] = 'NULL';
                                } else {
                                    $skip_item = true;
                                    self::writeLog("Data mapping ERROR: Table '{$table}' row [" . $this->describeSourceRow($table, $item) . "] - Skipping item due to missing shop mapping for {$field}.", 'ERROR');
                                    break;
                                }
                            }
                        }
                        if (
                            $tableName === 'stock_available'
                            && (trim($field) === 'id_shop' || trim($field) === 'id_shop_group')
                        ) {
                            if ($this->normalizeStockShop($fields_value, $item) === false) {
                                $skip_item = true;
                                self::writeLog("Data mapping ERROR: Table '{$table}' row [" . $this->describeSourceRow($table, $item) . '] - Skipping item due to unresolved stock shop context.', 'ERROR');
                                break;
                            }
                        } elseif (
                            $tableName === 'customer'
                            && (trim($field) === 'id_shop' || trim($field) === 'id_shop_group')
                        ) {
                            $customerShopStatus = $this->normalizeCustomerShop($fields_value, $item);
                            if ($customerShopStatus === false) {
                                $skip_item = true;
                                self::writeLog("Data mapping ERROR: Table '{$table}' row [" . $this->describeSourceRow($table, $item) . '] - Skipping item due to unresolved customer shop context.', 'ERROR');
                                break;
                            }
                            if (is_int($customerShopStatus) && $customerShopStatus > 0) {
                                $skip_item = true;
                                self::writeLog("Data mapping: Table '{$table}' - Existing shared customer retained and linked to source customer.", 'WARNING');
                                break;
                            }
                        } elseif (
                            $tableName !== 'shop'
                            && $tableName !== 'shop_group'
                            && trim($field) === 'id_shop_group'
                            && $tableName !== 'stock_available'
                            && $tableName !== 'customer'
                            && isset($fields_value[$field])
                            && (int) $fields_value[$field] > 0
                            && (!isset($fields_value['id_shop']) || (int) $fields_value['id_shop'] <= 0)
                            && (!array_key_exists('id_shop', $item) || $item['id_shop'] === null || Tools::strtoupper(trim($item['id_shop'])) === 'NULL' || (int) $item['id_shop'] <= 0)
                        ) {
                            $sourceShopGroupId = isset($item[$field]) ? (int) $item[$field] : 0;
                            if ($sourceShopGroupId > 0) {
                                $target_group_id = $this->mapSourceShopGroup($sourceShopGroupId);
                                if ($target_group_id === false) {
                                    self::writeLog("Shop group lookup failed for source group {$sourceShopGroupId}", 'ERROR');
                                    self::flushLogBuffer();

                                    return false;
                                }
                                if ($target_group_id) {
                                    $fields_value[$field] = $target_group_id;
                                } else {
                                    $skip_item = true;
                                    self::writeLog("Data mapping ERROR: Table '{$table}' row [" . $this->describeSourceRow($table, $item) . "] - Skipping item due to missing target shop group mapping for source shop group '{$sourceShopGroupId}'.", 'ERROR');
                                    break;
                                }
                            } else {
                                $skip_item = true;
                                self::writeLog("Data mapping ERROR: Table '{$table}' row [" . $this->describeSourceRow($table, $item) . "] - Skipping item due to missing target shop group mapping for source shop group '{$sourceShopGroupId}'.", 'ERROR');
                                break;
                            }
                        } elseif (
                            $tableName !== 'shop'
                            && $tableName !== 'shop_group'
                            && trim($field) === 'id_shop_group'
                            && $tableName !== 'stock_available'
                            && isset($fields_value[$field])
                            && isset($fields_value['id_shop'])
                            && (int) $fields_value['id_shop'] > 0
                        ) {
                            $target_group_id = self::getTargetShopGroup((int) $fields_value['id_shop']);
                            if ($target_group_id === false) {
                                self::writeLog("Shop group lookup failed for shop {$fields_value['id_shop']}", 'ERROR');
                                self::flushLogBuffer();

                                return false;
                            }
                            if ($target_group_id) {
                                $fields_value[$field] = $target_group_id;
                            }
                        }

                        if (
                            isset($unique_field_set[$field])
                            && isset($fields_value[$field])
                            && (trim($fields_value[$field]) == '' || preg_match('/(?:int|tinyint)(\((\d+)\))?/i', $schema['Type']) && $fields_value[$field] <= 0)
                            && isset($schema['Null'])
                            && Tools::strtoupper(trim($schema['Null'])) === 'YES'
                        ) {
                            $fields_value[$field] = 'NULL';
                        }
                    }
                    if ($tableName === 'shop_group') {
                        $shareCustomer = isset($fields_value['share_customer']) ? (int) $fields_value['share_customer'] : 0;
                        $shareStock = isset($fields_value['share_stock']) ? (int) $fields_value['share_stock'] : 0;
                        if (!$shareCustomer || !$shareStock) {
                            $fields_value['share_order'] = 0;
                        }
                    }
                    if ($skip_item) {
                        continue;
                    }
                    if ($tableName === 'stock_available') {
                        if ($this->normalizeStockShop($fields_value, $item) === false) {
                            continue;
                        }
                    }
                    [$fields_value, $skip_item, $foreign_error] = $this->applyForeignKeyRemappingForRow($table, $item, $fields_value, $foreign_keys, $schema_columns);
                    if ($foreign_error) {
                        self::flushLogBuffer();

                        return false;
                    }
                    if ($skip_item) {
                        continue;
                    }
                    if ($tableName == 'cart_product') {
                        if (isset($fields_value['id_cart']) && (int) $fields_value['id_cart'] <= 0
                            || isset($fields_value['id_product']) && (int) $fields_value['id_product'] <= 0) {
                            continue;
                        }
                    }

                    if (($struct_images = $this->getMigrateImages($table))
                        && is_array($struct_images)
                    ) {
                        foreach ($struct_images as $struct) {
                            if (is_array($struct)) {
                                $field = $this->resolveMediaEntityFieldName($table, $struct);
                                if ($field !== '' && isset($migrate_fields_set[$field . self::$image_field_unique])) {
                                    $fields_value[$field . self::$image_field_unique] = isset($item[$field . self::$image_field_unique]) && trim($item[$field . self::$image_field_unique]) !== '' ? (int) $item[$field . self::$image_field_unique] : 'NULL';
                                }
                            }
                        }
                    }

                    if (($struct_files = $this->getMigrateFiles($table))
                        && is_array($struct_files)
                    ) {
                        foreach ($struct_files as $struct) {
                            if (
                                is_array($struct)
                                && !empty($struct)
                            ) {
                                $field = $this->resolveMediaEntityFieldName($table, $struct);
                                if ($field !== '' && isset($migrate_fields_set[$field . self::$file_field_unique])) {
                                    $fields_value[$field . self::$file_field_unique] = isset($item[$field . self::$file_field_unique]) && trim($item[$field . self::$file_field_unique]) != '' ? (int) $item[$field . self::$file_field_unique] : 'NULL';
                                }
                            }
                        }
                    }

                    if ($fields_value) {
                        if (
                            $tableName == 'currency'
                            && version_compare($this->source_version, '1.7.0.0', '<')
                            && version_compare(_PS_VERSION_, '1.7.6.0', '>=')
                        ) {
                            $fields_value['numeric_iso_code'] = isset($item['iso_code_num']) ? '\'' . pSQL(trim($item['iso_code_num'])) . '\'' : 'NULL';
                            if ($fields_value['numeric_iso_code'] !== 'NULL' && Tools::strlen($fields_value['numeric_iso_code']) < 3 && !Validate::isNumericIsoCode($fields_value['numeric_iso_code'])) {
                                $fields_value['numeric_iso_code'] = '0' . $fields_value['numeric_iso_code'];
                            }
                            $fields_value['precision'] = isset($item['format']) ? (int) $item['format'] : 0;
                        }
                        if (
                            version_compare($this->source_version, '1.6.1.0', '<')
                            && version_compare(_PS_VERSION_, '1.6.1.0', '>=')
                        ) {
                            switch ($tableName) {
                                case 'image_shop':
                                    if (isset($fields_value['id_image']) && trim($fields_value['id_image']) !== '') {
                                        $productId = EtsMigrateTools::queryTable('image', false, 'id_product', 0, 0, ['id_image=' . (int) $fields_value['id_image']], true);
                                        if ($productId === false) {
                                            self::writeLog('Failed to lookup image_shop id_product', 'ERROR');
                                            self::flushLogBuffer();

                                            return false;
                                        }
                                        $fields_value['id_product'] = (int) $productId;
                                    } else {
                                        $fields_value['id_product'] = 0;
                                    }
                                    break;
                                case 'product_attribute_shop':
                                    if (isset($fields_value['id_product_attribute']) && trim($fields_value['id_product_attribute']) !== '') {
                                        $productId = EtsMigrateTools::queryTable('product_attribute', false, 'id_product', 0, 0, ['id_product_attribute=' . (int) $fields_value['id_product_attribute']], true);
                                        if ($productId === false) {
                                            self::writeLog('Failed to lookup product_attribute_shop id_product', 'ERROR');
                                            self::flushLogBuffer();

                                            return false;
                                        }
                                        $fields_value['id_product'] = (int) $productId;
                                    } else {
                                        $fields_value['id_product'] = 0;
                                    }
                                    break;
                            }
                        }
                        if (version_compare($this->source_version, '1.7.6.0', '<') && version_compare(_PS_VERSION_, '1.7.6.0', '>=')) {
                            switch ($tableName) {
                                case 'product_supplier':
                                    if (isset($fields_value['id_currency']) && $fields_value['id_currency'] <= 0) {
                                        if ($defaultCurrencyId === null) {
                                            $defaultCurrencyId = (int) Configuration::get('PS_CURRENCY_DEFAULT');
                                        }
                                        $fields_value['id_currency'] = (int) $defaultCurrencyId;
                                    }
                                    break;
                            }
                        }
                        if ($tableName == 'product' || $tableName == 'product_shop') {
                            if (isset($fields_value['id_supplier']) && (int) $fields_value['id_supplier'] <= 0) {
                                if ($this->supplier_default > 0) {
                                    $fields_value['id_supplier'] = (int) $this->supplier_default;
                                } else {
                                    $fields_value['id_supplier'] = 'NULL';
                                }
                            }
                            if (isset($fields_value['id_manufacturer']) && (int) $fields_value['id_manufacturer'] <= 0) {
                                if ($this->manufacturer_default > 0) {
                                    $fields_value['id_manufacturer'] = (int) $this->manufacturer_default;
                                } else {
                                    $fields_value['id_manufacturer'] = 'NULL';
                                }
                            }
                        }
                        if (($tableName == 'product' || $tableName == 'product_shop') && isset($fields_value['minimal_quantity']) && (int) $fields_value['minimal_quantity'] < 1) {
                            $fields_value['minimal_quantity'] = 1;
                        }

                        if ($insertParts = $this->buildInsertParts($table, $migrate_fields, $fields_value, $migrate_unique)) {
                            $this->addGeneratedRow($importBatch, $insertParts, $this->describeSourceRow($table, $item), $rowSourceId);
                            if (
                                preg_match('/^[a-zA-Z0-9\_]+_lang$/', $table)
                                && is_array(self::$languages)
                                && count(self::$languages) > 0
                                && isset($fields_value['id_lang'])
                                && (int) $fields_value['id_lang'] > 0
                                && (int) self::$default_language == (int) $fields_value['id_lang']
                            ) {
                                foreach (self::$languages as $id_lang) {
                                    if ((int) $id_lang <= 0) {
                                        continue;
                                    }
                                    $fields_value['id_lang'] = (int) $id_lang;
                                    if ($insertParts = $this->buildInsertParts($table, $migrate_fields, $fields_value, $migrate_unique)) {
                                        $this->addGeneratedRow($importBatch, $insertParts, $this->describeSourceRow($table, $item), $rowSourceId);
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $this->customer_email_exists_map = null;
        }
        $this->time_execution = time() - $this->time_execution;
        self::flushLogBuffer();

        return $importBatch;
    }

    private function remapForeignKeyColumn($table, $foreign_table, $id_source, $id_target, $item, &$fields_value, $schema_columns, $fail_unmapped_id_lang)
    {
        if (!isset($fields_value[$id_source]) || !isset($item[$id_source]) || !$item[$id_source]) {
            return ['skip_item' => false, 'foreign_error' => false];
        }
        $new_id = $this->getNewIdByOldId($foreign_table, $id_target, $item[$id_source], !empty($schema_columns[$id_source]) ? $schema_columns[$id_source] : null);
        if ($new_id === false) {
            self::writeLog("FK Mapping ERROR: Table '{$table}' - lookup failed for '{$id_source}' -> '{$foreign_table}'.", 'ERROR');

            return ['skip_item' => false, 'foreign_error' => true];
        }
        if (!$new_id && $item[$id_source] > 0 && $this->canUseNativeForeignId($foreign_table)) {
            $nativeKey = $this->buildNativeExistsCacheKey($foreign_table, $id_target, (int) $item[$id_source]);
            if (array_key_exists($nativeKey, $this->id_mapping_cache)) {
                if ($this->id_mapping_cache[$nativeKey]) {
                    $new_id = $item[$id_source];
                }
            } elseif (EtsMigrateTools::tableExists($foreign_table) && preg_match('/^[a-zA-Z0-9_]+$/', $foreign_table) && preg_match('/^[a-zA-Z0-9_]+$/', $id_target)) {
                $nativeRaw = Db::getInstance()->getValue('SELECT `' . bqSQL($id_target) . '` FROM `' . _DB_PREFIX_ . bqSQL($foreign_table) . '` WHERE `' . bqSQL($id_target) . '` = ' . (int) $item[$id_source]);
                if ($nativeRaw === false) {
                    $message = Db::getInstance()->getMsgError();
                    if (is_string($message) && $message !== '') {
                        self::writeLog("FK native exists lookup failed: {$foreign_table}.{$id_target}: {$message}", 'ERROR');

                        return ['skip_item' => false, 'foreign_error' => true];
                    }
                    $this->id_mapping_cache[$nativeKey] = 0;
                } elseif ((int) $nativeRaw) {
                    $this->id_mapping_cache[$nativeKey] = 1;
                    $new_id = $item[$id_source];
                } else {
                    $this->id_mapping_cache[$nativeKey] = 0;
                }
            }
        }
        if ($new_id) {
            $fields_value[$id_source] = $new_id;

            return ['skip_item' => false, 'foreign_error' => false];
        }
        $isKeyColumn = in_array($id_source, $this->getMigratePrimary($table), true);
        if ($fail_unmapped_id_lang && trim($id_source) === 'id_lang' || $isKeyColumn && $item[$id_source] > 0) {
            self::writeLog("FK Mapping WARNING: Table '{$table}' row [" . $this->describeSourceRow($table, $item) . "] - Field '{$id_source}' with value '{$item[$id_source]}' could not be mapped to '{$foreign_table}'. Skipping row.", 'WARNING');

            return ['skip_item' => true, 'foreign_error' => false];
        }
        if ($item[$id_source] > 0) {
            self::writeLog("FK Mapping WARNING: Table '{$table}' row [" . $this->describeSourceRow($table, $item) . "] - Field '{$id_source}' with value '{$item[$id_source]}' could not be mapped to '{$foreign_table}'. Result is 0.", 'WARNING');
        }
        $fields_value[$id_source] = 0;

        return ['skip_item' => false, 'foreign_error' => false];
    }

    public function describeSourceRow($table, $item)
    {
        $columns = $this->getMigratePrimary($table);
        if (!$columns) {
            $columns = array_slice(array_values(array_filter(array_keys($item), function ($column) {
                return strpos($column, 'id_') === 0;
            })), 0, 3);
        }
        $parts = [];
        foreach ($columns as $column) {
            if (isset($item[$column]) && is_scalar($item[$column])) {
                $parts[] = $column . '=' . $item[$column];
            }
        }

        return $parts ? implode(', ', $parts) : 'unknown key';
    }

    private function getNaturalKeyIndex($tableName, $value)
    {
        $value = trim($value);

        return $tableName === 'employee' ? Tools::strtolower($value) : $value;
    }

    private function canUseNativeForeignId($table)
    {
        $table = trim($table);

        return $this->shouldKeepOldId() || isset(self::$ignore_tables[$table]) || in_array($table, self::$native_reference_tables, true);
    }

    private function applyForeignKeyRemappingForRow($table, $item, $fields_value, $foreign_keys, $schema_columns)
    {
        $foreign_error = false;
        $skip_item = false;
        if (is_array($foreign_keys) && count($foreign_keys) > 0) {
            foreach ($foreign_keys as $foreign_table => $foreign_key) {
                if (trim($foreign_table) === 'shop') {
                    if (is_array($foreign_key)) {
                        foreach ($foreign_key as $id_source => $id_target) {
                            if ($id_source !== 'id_shop_default' && $id_target !== 'id_shop_default') {
                                continue;
                            }
                            $sourceField = 'id_shop_default';
                            if (!isset($fields_value[$sourceField]) || !isset($item[$sourceField]) || !(int) $item[$sourceField]) {
                                continue;
                            }
                            $sourceShopId = (int) $item[$sourceField];
                            if (isset(self::$mapping_shop[$sourceShopId]) && (int) self::$mapping_shop[$sourceShopId] > 0) {
                                $fields_value[$sourceField] = (int) self::$mapping_shop[$sourceShopId];
                            } else {
                                $defaultShopId = (int) Configuration::get('PS_SHOP_DEFAULT');
                                if ($defaultShopId <= 0 && isset($this->context->shop->id)) {
                                    $defaultShopId = (int) $this->context->shop->id;
                                }
                                $fields_value[$sourceField] = $defaultShopId > 0 ? $defaultShopId : 1;
                            }
                        }
                    }
                    continue;
                }
                if (is_array($foreign_key) && $foreign_key) {
                    foreach ($foreign_key as $id_source => $id_target) {
                        if ($id_source === 'id_shop' || $id_source === 'id_shop_group') {
                            continue;
                        }

                        $status = $this->remapForeignKeyColumn($table, $foreign_table, $id_source, $id_target, $item, $fields_value, $schema_columns, false);
                        if (!empty($status['foreign_error'])) {
                            $foreign_error = true;
                            break 2;
                        }
                        if ($status['skip_item']) {
                            $skip_item = true;
                            break 2;
                        }
                    }
                } elseif (!is_array($foreign_key)) {
                    if (trim($foreign_table) === 'shop_group') {
                        continue;
                    }
                    $status = $this->remapForeignKeyColumn($table, $foreign_table, $foreign_key, $foreign_key, $item, $fields_value, $schema_columns, true);
                    if ($status['foreign_error']) {
                        $foreign_error = true;
                        break;
                    }
                    if ($status['skip_item']) {
                        $skip_item = true;
                        break;
                    }
                }
            }
        }

        return [$fields_value, $skip_item, $foreign_error];
    }

    public function isFieldInUniqueIndex($field, $migrate_unique)
    {
        if (is_array($migrate_unique) && count($migrate_unique) > 0) {
            foreach ($migrate_unique as $unique) {
                if (in_array($field, $unique)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function importImage($entity, $data)
    {
        if (
            trim($entity) === ''
            || !is_array($data)
            || count($data) <= 0
        ) {
            return false;
        }
        if ($image = $this->getMigrateImages($entity)) {
            $watermarkTypesRaw = explode(',', (string) Configuration::get('WATERMARK_TYPES'));
            $watermark_types = [];
            foreach ($watermarkTypesRaw as $watermarkTypeId) {
                $watermarkTypeId = trim($watermarkTypeId);
                if ($watermarkTypeId !== '') {
                    $watermark_types[$watermarkTypeId] = true;
                }
            }
            $generateProductThumbnail = trim((string) Configuration::getGlobalValue(self::$prefix . 'GENERATE_PRODUCT_THUMBNAIL'));
            $loadImagesTypes = (trim($entity) !== 'image' || !$this->shouldKeepOldId() || $generateProductThumbnail === 'auto');
            $imagesTypesByStructType = [];
            if (is_array($image)) {
                foreach ($image as $struct) {
                    if (!is_array($struct) || !isset($struct['type']) || trim($struct['type']) === '') {
                        continue;
                    }
                    $structType = trim($struct['type']);
                    if (!isset($imagesTypesByStructType[$structType])) {
                        $imagesTypesByStructType[$structType] = $loadImagesTypes ? ImageType::getImagesTypes($structType) : [];
                    }
                }
            }
            $success_count = 0;
            $failedMediaRows = [];
            foreach ($data as $item) {
                if (is_array($image)) {
                    foreach ($image as $struct) {
                        $id_entity = $this->resolveMediaEntityFieldName($entity, $struct);
                        if ($id_entity === '') {
                            $failedMediaRows[] = $this->describeSourceRow($entity, $item);
                            continue;
                        }
                        $id_entity_old = isset($struct['field']) && trim($struct['field']) !== '' ? $id_entity : $id_entity . '_ets_old';
                        $structType = isset($struct['type']) && trim($struct['type']) !== '' ? trim($struct['type']) : '';
                        $images_types = ($structType !== '' && isset($imagesTypesByStructType[$structType])) ? $imagesTypesByStructType[$structType] : [];
                        $regenerate = count($images_types) > 0 ? 1 : 0;
                        $extension = isset($struct['ext']) && trim($struct['ext']) !== '' ? trim($struct['ext']) : '';

                        $structRelative = trim($struct['path'], DIRECTORY_SEPARATOR . '/');
                        if ($structRelative === '' || strpos($structRelative, '..') !== false) {
                            $failedMediaRows[] = $this->describeSourceRow($entity, $item);
                            continue;
                        }
                        $psRootResolved = EtsMigratePathSecurity::resolveAllowedRoot(_PS_ROOT_DIR_);
                        $destRoot = realpath(_PS_ROOT_DIR_ . '/' . $structRelative);
                        if (
                            $psRootResolved === false
                            || $destRoot === false
                            || !is_dir($destRoot)
                            || !EtsMigratePathSecurity::isPathInsideResolvedRoot($destRoot, $psRootResolved)
                        ) {
                            $failedMediaRows[] = $this->describeSourceRow($entity, $item);
                            continue;
                        }
                        $destRootResolved = EtsMigratePathSecurity::resolveAllowedRoot($destRoot);
                        if ($destRootResolved === false) {
                            $failedMediaRows[] = $this->describeSourceRow($entity, $item);
                            continue;
                        }
                        $dest_path = $destRoot . DIRECTORY_SEPARATOR;
                        $src_link = self::$domain_url . trim($struct['path'], '/') . '/';

                        if (
                            isset($item[$id_entity_old])
                            && trim($item[$id_entity_old]) !== ''
                            && isset($item[$id_entity])
                            && trim($item[$id_entity]) !== ''
                            && isset($item[$id_entity . self::$image_field_unique])
                            && trim($item[$id_entity . self::$image_field_unique]) !== ''
                        ) {
                            if (trim($entity) !== 'image') {
                                $entityIdValue = $item[$id_entity];
                                if (Validate::isUnsignedInt($entityIdValue)) {
                                    $entityIdValue = (int) $entityIdValue;
                                }
                                $sources = [
                                    $entityIdValue => [
                                        'path_creator' => '',
                                        'src' => $item[$id_entity_old],
                                    ],
                                ];
                            } else {
                                $path_creator = Image::getImgFolderStatic($item[$id_entity]);
                                $imageId = Validate::isUnsignedInt($item[$id_entity]) ? (int) $item[$id_entity] : basename((string) $item[$id_entity]);
                                $sources = [
                                    $path_creator . $imageId => [
                                        'path_creator' => $path_creator,
                                        'src' => ((int) $item[$id_entity . self::$image_field_unique] > 0 ? Image::getImgFolderStatic($item[$id_entity_old]) : $item['id_product_ets_old'] . '-') . $item[$id_entity_old],
                                    ],
                                ];
                            }
                            if (is_array($sources)) {
                                $type = Tools::strtolower(Tools::substr(strrchr(trim($item[$id_entity]), '.'), 1));
                                foreach ($sources as $dest => $source) {
                                    $pathCreator = '';
                                    if (is_array($source)) {
                                        $pathCreator = !empty($source['path_creator']) ? $source['path_creator'] : '';
                                        if (
                                            $pathCreator !== ''
                                            && !is_dir($dest_path . $pathCreator)
                                        ) {
                                            if (strpos($pathCreator, '..') !== false) {
                                                $failedMediaRows[] = $this->describeSourceRow($entity, $item);
                                                continue;
                                            }
                                            $created_path = $dest_path . $pathCreator;
                                            if (!mkdir($created_path, self::$access_rights, true) && !is_dir($created_path)) {
                                                $failedMediaRows[] = $this->describeSourceRow($entity, $item);
                                                continue;
                                            }
                                            chmod($created_path, self::$access_rights);
                                            if (
                                                !file_exists($created_path . 'index.php')
                                                && file_exists($dest_path . 'index.php')
                                            ) {
                                                copy($dest_path . 'index.php', $created_path . 'index.php');
                                            }
                                        }
                                        $source = !empty($source['src']) ? trim($source['src']) : '';
                                    }
                                    $destFileName = basename((string) $dest);
                                    if (Validate::isUnsignedInt($destFileName)) {
                                        $destFileName = (string) (int) $destFileName;
                                    } elseif (!EtsMigratePathSecurity::isSafeFileName($destFileName)) {
                                        $failedMediaRows[] = $this->describeSourceRow($entity, $item);
                                        continue;
                                    }
                                    $relativeDest = ($pathCreator !== '' ? $pathCreator : '') . $destFileName . $extension;
                                    if (strpos($relativeDest, '..') !== false) {
                                        $failedMediaRows[] = $this->describeSourceRow($entity, $item);
                                        continue;
                                    }
                                    $fullDestPath = $dest_path . $relativeDest;
                                    if (
                                        strpos($fullDestPath, '..') !== false
                                        || !EtsMigratePathSecurity::isPathInsideResolvedRoot(dirname($fullDestPath), $destRootResolved)
                                    ) {
                                        $failedMediaRows[] = $this->describeSourceRow($entity, $item);
                                        continue;
                                    }
                                    $tmp_file = tempnam(_PS_TMP_IMG_DIR_, uniqid('ets_migrate', true));
                                    if (trim($source) === '') {
                                        EtsMigratePathSecurity::unlink($tmp_file, _PS_TMP_IMG_DIR_);
                                        continue;
                                    }
                                    if (self::copy($src_link . $source . $extension, $tmp_file)
                                        && self::isValidImageFile($tmp_file)
                                    ) {
                                        if (!ImageManager::checkImageMemoryLimit($tmp_file)) {
                                            EtsMigratePathSecurity::unlink($tmp_file, _PS_TMP_IMG_DIR_);
                                            $failedMediaRows[] = $this->describeSourceRow($entity, $item);
                                        } else {
                                            $tgt_width = $tgt_height = 0;
                                            $src_width = $src_height = 0;
                                            $error = 0;
                                            $resizeOk = ImageManager::resize($tmp_file, $fullDestPath, null, null, trim($extension) !== '' ? trim($extension, '.') : $type, false, $error, $tgt_width, $tgt_height, 5, $src_width, $src_height);
                                            if (!$resizeOk) {
                                                $failedMediaRows[] = $this->describeSourceRow($entity, $item);
                                            } else {
                                                if ($regenerate) {
                                                    $path_infos = [];
                                                    $path_infos[] = [$tgt_width, $tgt_height, $fullDestPath];
                                                    foreach ($images_types as $image_type) {
                                                        $best_path = self::getBestPath($image_type['width'], $image_type['height'], $path_infos);
                                                        $thumbRelative = ($pathCreator !== '' ? $pathCreator : '') . $destFileName . '-' . $image_type['name'] . $extension;
                                                        if (strpos($thumbRelative, '..') !== false) {
                                                            continue;
                                                        }
                                                        $thumbDestPath = $dest_path . $thumbRelative;
                                                        if (ImageManager::resize($best_path, $thumbDestPath, $image_type['width'], $image_type['height'], trim($extension) !== '' ? trim($extension, '.') : $type, false, $error, $tgt_width, $tgt_height, 5, $src_width, $src_height)) {
                                                            if ($tgt_width <= $src_width && $tgt_height <= $src_height) {
                                                                $path_infos[] = [$tgt_width, $tgt_height, $thumbDestPath];
                                                            }
                                                            if (trim($entity) == 'image') {
                                                                $miniBase = _PS_TMP_IMG_DIR_ . 'product_mini_' . (int) $item[$id_entity] . $extension;
                                                                if (is_file($miniBase)) {
                                                                    EtsMigratePathSecurity::unlink($miniBase, _PS_TMP_IMG_DIR_);
                                                                }
                                                                if (self::$target_shops) {
                                                                    foreach (self::$target_shops as $id_shop) {
                                                                        $miniShop = _PS_TMP_IMG_DIR_ . 'product_mini_' . (int) $item[$id_entity] . '_' . (int) $id_shop . $extension;
                                                                        if (is_file($miniShop)) {
                                                                            EtsMigratePathSecurity::unlink($miniShop, _PS_TMP_IMG_DIR_);
                                                                        }
                                                                    }
                                                                } elseif (is_file(_PS_TMP_IMG_DIR_ . 'product_mini_' . (int) $item[$id_entity] . '_' . (int) $this->context->shop->id . $extension)) {
                                                                    EtsMigratePathSecurity::unlink(_PS_TMP_IMG_DIR_ . 'product_mini_' . (int) $item[$id_entity] . '_' . (int) $this->context->shop->id . $extension, _PS_TMP_IMG_DIR_);
                                                                }
                                                            }
                                                        }
                                                        if (trim($entity) == 'image' && isset($watermark_types[(string) $image_type['id_image_type']])) {
                                                            Hook::exec('actionWatermark', ['id_image' => $item[$id_entity], 'id_product' => $item['id_product']]);
                                                        }
                                                    }
                                                }
                                                if (trim($entity) === 'category') {
                                                    $this->applyCategoryThumbMigration($dest_path, $item[$id_entity], !empty($item['category_thumb_img_exist']) || !empty($item['category_thumb_exists']) ? $item[$id_entity_old] : 0, $src_link, $extension);
                                                }
                                                ++$success_count;
                                            }
                                        }
                                    } else {
                                        $failedMediaRows[] = $this->describeSourceRow($entity, $item);
                                    }
                                    EtsMigratePathSecurity::unlink($tmp_file, _PS_TMP_IMG_DIR_);
                                }
                            }
                        }
                    }
                }
            }

            $failed_count = count($failedMediaRows);
            if ($failedMediaRows) {
                self::writeLog('Media table ' . $entity . ': ' . $failed_count . ' file(s) not copied [' . implode('; ', array_slice(array_unique($failedMediaRows), 0, 50)) . ']', 'WARNING');
            }
            $processed = $success_count + $failed_count;
            self::flushLogBuffer();

            return [
                'ok' => 1,
                'success' => $success_count,
                'failed' => $failed_count,
                'table' => $entity,
            ];
        }

        return false;
    }

    public function importFiles($entity, $data)
    {
        if (
            trim($entity) === ''
            || !is_array($data)
            || count($data) <= 0
        ) {
            return false;
        }
        $file = $this->getMigrateFiles($entity);
        if (
            is_array($file)
            && count($file) > 0
        ) {
            $success_count = 0;
            $failedMediaRows = [];
            foreach ($file as $struct) {
                if (
                    isset($struct['path'])
                    && trim($struct['path']) !== ''
                ) {
                    $field = $this->resolveMediaEntityFieldName($entity, $struct);
                    if ($field === '') {
                        $failedMediaRows[] = 'path ' . ($struct['path'] ?? '');
                        continue;
                    }
                    $relativePath = trim(str_replace('\\', '/', $struct['path']), '/');
                    if ($relativePath === '' || strpos($relativePath, '..') !== false) {
                        $failedMediaRows[] = 'path ' . ($struct['path'] ?? '');
                        continue;
                    }
                    $destDir = _PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
                    if (!is_dir($destDir)) {
                        if (!mkdir($destDir, self::$access_rights, true) && !is_dir($destDir)) {
                            $failedMediaRows[] = 'path ' . ($struct['path'] ?? '');
                            continue;
                        }
                    }
                    $destDir = realpath($destDir);
                    if ($destDir === false || !is_dir($destDir) || !EtsMigratePathSecurity::isPathInAllowedDirectory($destDir, _PS_ROOT_DIR_)) {
                        $failedMediaRows[] = 'path ' . ($struct['path'] ?? '');
                        continue;
                    }
                    foreach ($data as $item) {
                        if (
                            isset($item[$field])
                            && trim($item[$field]) !== ''
                            && isset($item[$field . self::$file_field_unique])
                            && $item[$field . self::$file_field_unique] !== ''
                        ) {
                            $fileName = basename(trim($item[$field]));
                            if (trim($entity) === 'customized_data') {
                                if (!EtsMigratePathSecurity::isCustomizationFileName($fileName)) {
                                    $failedMediaRows[] = $this->describeSourceRow($entity, $item);
                                    continue;
                                }
                            } elseif (!EtsMigratePathSecurity::isSafeFileName($fileName) || EtsMigratePathSecurity::isBlockedUploadExtension($fileName)) {
                                $failedMediaRows[] = $this->describeSourceRow($entity, $item);
                                continue;
                            }
                            if ($this->downloadFile($destDir . '/' . $fileName, ['filename' => $fileName, 'field' => trim($field), 'entity' => trim($entity)], $destDir)) {
                                ++$success_count;
                            } else {
                                $failedMediaRows[] = $this->describeSourceRow($entity, $item);
                            }
                            if (trim($entity) == 'customized_data') {
                                $smallFileName = $fileName . '_small';
                                if (!EtsMigratePathSecurity::isCustomizationFileName($smallFileName)) {
                                    $failedMediaRows[] = $this->describeSourceRow($entity, $item);
                                    continue;
                                }
                                if ($this->downloadFile($destDir . '/' . $smallFileName, ['filename' => $smallFileName, 'field' => trim($field), 'entity' => trim($entity)], $destDir)) {
                                    ++$success_count;
                                } else {
                                    $failedMediaRows[] = $this->describeSourceRow($entity, $item);
                                }
                            }
                        }
                    }
                }
            }
            $failed_count = count($failedMediaRows);
            if ($failedMediaRows) {
                self::writeLog('Media table ' . $entity . ': ' . $failed_count . ' file(s) not copied [' . implode('; ', array_slice(array_unique($failedMediaRows), 0, 50)) . ']', 'WARNING');
            }
            $processed = $success_count + $failed_count;

            return [
                'ok' => 1,
                'success' => $success_count,
                'failed' => $failed_count,
                'failed_count' => $failed_count,
                'table' => $entity,
            ];
        }

        return false;
    }

    protected static function isValidImageFile($file)
    {
        if (!is_file($file) || !filesize($file)) {
            return false;
        }
        $image_size = getimagesize($file);
        if ($image_size === false || !is_array($image_size)) {
            return false;
        }

        return true;
    }

    protected static function getBestPath($tgt_width, $tgt_height, $path_infos)
    {
        $path_infos = array_reverse($path_infos);
        $path = '';
        foreach ($path_infos as $path_info) {
            [$width, $height, $path] = $path_info;
            if ($width >= $tgt_width && $height >= $tgt_height) {
                return $path;
            }
        }

        return $path;
    }

    public static function copy($source, $destination, $stream_context = null)
    {
        $allowedDirectory = defined('_PS_TMP_IMG_DIR_') && strpos($destination, _PS_TMP_IMG_DIR_) === 0
            ? _PS_TMP_IMG_DIR_
            : dirname($destination);
        if (null === $stream_context && !preg_match('/^https?:\/\//', $source)) {
            if (!is_file($source)) {
                return false;
            }
            $sourceAllowedDirectory = defined('_PS_TMP_IMG_DIR_') && strpos($source, _PS_TMP_IMG_DIR_) === 0
                ? _PS_TMP_IMG_DIR_
                : _PS_ROOT_DIR_;
            if (!EtsMigratePathSecurity::isPathInAllowedDirectory($source, $sourceAllowedDirectory)) {
                return false;
            }
            $content = Tools::file_get_contents($source);
            if ($content === false || $content === '') {
                return false;
            }

            return EtsMigratePathSecurity::file_put_contents($destination, $content, $allowedDirectory);
        }
        if (preg_match('/^https?:\/\//', $source)) {
            if (!EtsMigrateUrlSecurity::isPublicHttpUrl($source) || !EtsMigrateUrlSecurity::isAllowedDomain($source)) {
                return false;
            }
            $content = EtsMigrateHttpTransport::fetchRemoteContents($source, false, null, 300);
            if ($content === false || $content === '') {
                self::writeLog('Remote media download failed or returned an empty response', 'ERROR');

                return false;
            }
            if (preg_match('/<head/i', $content)) {
                self::writeLog('Remote media download returned an HTML response', 'ERROR');

                return false;
            }

            if (!EtsMigratePathSecurity::file_put_contents($destination, $content, $allowedDirectory)) {
                self::writeLog('Remote media could not be written to the approved destination', 'ERROR');

                return false;
            }

            return true;
        }
        $content = EtsMigrateHttpTransport::fetchRemoteContents($source, false, $stream_context);
        if ($content === false || $content === '') {
            self::writeLog('Remote media download failed or returned an empty response', 'ERROR');

            return false;
        }
        if (preg_match('/<head/i', $content)) {
            self::writeLog('Remote media download returned an HTML response', 'ERROR');

            return false;
        }
        if (!EtsMigratePathSecurity::file_put_contents($destination, $content, $allowedDirectory)) {
            self::writeLog('Remote media could not be written to the approved destination', 'ERROR');

            return false;
        }

        return true;
    }

    public function downloadFile($destination, $params, $allowedDirectory = null)
    {
        if (!is_string($allowedDirectory) || $allowedDirectory === '') {
            return false;
        }
        $http_build_query = ['download' => 1];
        if ($params) {
            foreach ($params as $key => $param) {
                if (trim($param) !== '') {
                    $http_build_query[$key] = $param;
                }
            }
        }
        $content_file = EtsMigrateHttpTransport::fetchRemoteContents(EtsMigrateApiEndpoint::getInstance()->getRequestApi(), false, null, 300, $http_build_query);
        if ($content_file === false || $content_file === '') {
            self::writeLog('Migration file download failed or returned an empty response', 'ERROR');

            return false;
        }
        if (strpos($content_file, '**(error)**') !== false) {
            self::writeLog('Source connector rejected the migration file request', 'ERROR');

            return false;
        }
        if (stripos($content_file, '<html') !== false) {
            self::writeLog('Migration file download returned an HTML response', 'ERROR');

            return false;
        }
        $decoded_file = json_decode($content_file, true);
        if (is_array($decoded_file) && array_key_exists('error', $decoded_file)) {
            self::writeLog('Source connector returned an error for the migration file request', 'ERROR');

            return false;
        }
        if (!EtsMigratePathSecurity::file_put_contents($destination, $content_file, $allowedDirectory)) {
            self::writeLog('Migration file could not be written to the approved destination', 'ERROR');

            return false;
        }

        return true;
    }

    public function getLanguageMigrate($language = null)
    {
        if (!$this->language_migrate) {
            $this->language_migrate = (trim($res = Configuration::getGlobalValue(self::$prefix . 'LANGUAGE_MIGRATE')) !== '' ? explode(',', $res) : []);
        }

        return $language !== null && trim($language) !== '' ? (isset($this->language_migrate[$language]) && trim($this->language_migrate[$language]) !== '' ? $this->language_migrate[$language] : '') : $this->language_migrate;
    }

    public function setLanguageMigrate($language = null)
    {
        if ($language !== null && trim($language) == '') {
            $this->language_migrate = [];
        } elseif (is_array($language)) {
            $this->language_migrate = $language;
        } else {
            $this->language_migrate[] = trim($language);
        }
        Configuration::updateGlobalValue(self::$prefix . 'LANGUAGE_MIGRATE', count($this->language_migrate) > 0 ? implode(',', $this->language_migrate) : '', true);

        return $this;
    }

    public static function saveShopMapping($mapping)
    {
        if (!is_array($mapping)) {
            $mapping = [$mapping];
        }
        self::$mapping_shop_group = [];
        if ($mapping) {
            if (EtsMigrateDataImport::truncateShopMappingTable() === false) {
                self::writeLog('truncateShopMappingTable failed: ' . Db::getInstance()->getMsgError(), 'ERROR');

                return false;
            }
            $query = 'INSERT INTO `' . _DB_PREFIX_ . 'ets_em_shop_mapping`(id_shop_source, id_shop_target) VALUES';
            foreach ($mapping as $id_shop1 => $id_shop2) {
                $query .= '(' . (int) $id_shop1 . ', ' . (int) $id_shop2 . '),';
            }
            if (!Db::getInstance()->execute(rtrim($query, ','))) {
                return false;
            }
        }

        return true;
    }

    public function loadMappingShops()
    {
        if (!self::$mapping_shop) {
            $qd = new DbQuery();
            $qd
                ->select('*')
                ->from('ets_em_shop_mapping');
            $mapping = Db::getInstance()->executeS($qd);
            if ($mapping === false) {
                self::writeLog('Shop mapping load failed: ' . Db::getInstance()->getMsgError(), 'ERROR');

                return false;
            }
            foreach ($mapping as $item) {
                if (isset($item['id_shop_source']) && (int) $item['id_shop_source']) {
                    self::$mapping_shop[(int) $item['id_shop_source']] = (int) $item['id_shop_target'];
                    if ((int) $item['id_shop_source']) {
                        self::$source_shops[] = (int) $item['id_shop_source'];
                    }
                    if ((int) $item['id_shop_target']) {
                        self::$target_shops[] = (int) $item['id_shop_target'];
                    }
                }
            }
        }

        return self::$mapping_shop;
    }

    protected function applyMappedShopContext()
    {
        if (!class_exists('Shop') || !method_exists('Shop', 'setContext')) {
            return;
        }
        if (method_exists('Shop', 'resetStaticCache')) {
            Shop::resetStaticCache();
        }
        if (!Shop::isFeatureActive()) {
            return;
        }
        if (!self::$shop_context_saved) {
            self::$shop_context_previous = method_exists('Shop', 'getContext') ? Shop::getContext() : null;
            self::$shop_context_previous_id = method_exists('Shop', 'getContextShopID') ? Shop::getContextShopID(false) : null;
            if (!(int) self::$shop_context_previous_id && !empty($this->context->shop->id)) {
                self::$shop_context_previous_id = (int) $this->context->shop->id;
            }
            self::$shop_object_previous = $this->context->shop ?? null;
            self::$shop_context_saved = true;
        }
        $unique_target_shops = [];
        if (is_array(self::$mapping_shop)) {
            foreach (self::$mapping_shop as $target_shop_id) {
                $target_shop_id = (int) $target_shop_id;
                if ($target_shop_id > 0) {
                    $unique_target_shops[$target_shop_id] = $target_shop_id;
                }
            }
        }
        $unique_target_shop_count = count($unique_target_shops);
        if ($unique_target_shop_count < 1 || !defined('Shop::CONTEXT_SHOP')) {
            return;
        }
        $contextShopId = (int) reset($unique_target_shops);
        Shop::setContext(Shop::CONTEXT_SHOP, $contextShopId);
        $shop = new Shop($contextShopId);
        if (Validate::isLoadedObject($shop)) {
            $this->context->shop = $shop;
        }
    }

    public static function restoreMappedShopContext($context = null)
    {
        if (!self::$shop_context_saved || !class_exists('Shop') || !method_exists('Shop', 'setContext')) {
            return;
        }
        if (self::$shop_context_previous !== null) {
            $previousContext = (int) self::$shop_context_previous;
            $shopContext = defined('Shop::CONTEXT_SHOP') ? (int) Shop::CONTEXT_SHOP : 1;
            if ($previousContext === $shopContext) {
                $shopId = (int) self::$shop_context_previous_id;
                if ($shopId < 1 && self::$shop_object_previous) {
                    $shopId = (int) self::$shop_object_previous->id;
                }
                if ($shopId < 1) {
                    $shopId = (int) Configuration::get('PS_SHOP_DEFAULT');
                }
                if ($shopId > 0) {
                    Shop::setContext($previousContext, $shopId);
                }
            } else {
                Shop::setContext(self::$shop_context_previous);
            }
        }
        if ($context && self::$shop_object_previous) {
            $context->shop = self::$shop_object_previous;
        }
        self::$shop_context_saved = false;
        self::$shop_context_previous = null;
        self::$shop_context_previous_id = null;
        self::$shop_object_previous = null;
    }

    public function getRecordsSource($migrating = '')
    {
        $infos = $this->getSourceMigrationMetadata();
        if ($infos && isset($infos['nb']) && is_array($infos['nb']) && count($infos['nb']) > 0) {
            $total_record = 0;
            if ($migrating) {
                $diff = $this->getPendingMigrateTasks();
                if (count($diff) > 0) {
                    foreach ($diff as $resource) {
                        if (isset($infos['nb'][$resource]) && count($infos['nb'][$resource]) > 0 && isset($infos['nb'][$resource]['nb_group_table']) && $infos['nb'][$resource]['nb_group_table'] > 0) {
                            $total_record += (int) $infos['nb'][$resource]['nb_group_table'];
                        }
                    }
                }

                return $total_record;
            }
            foreach ($infos['nb'] as $resource => $info) {
                if (isset($info['nb_group_table']) && $info['nb_group_table'] > 0) {
                    $total_record += (int) $info['nb_group_table'];
                }
            }

            return $total_record;
        }

        return 0;
    }

    public function getSourceMigrationMetadata($task = null)
    {
        if (!$this->data_infos) {
            $this->data_infos = json_decode(Configuration::getGlobalValue(self::$prefix . 'DATA_INFO_SOURCE'), true);
        }
        if ($task) {
            return $this->data_infos['nb'][$task] ?? [];
        }

        return $this->data_infos;
    }

    public function setSourceMigrationMetadata($data_infos, $task = null)
    {
        if (!$this->data_infos) {
            $raw = Configuration::getGlobalValue(self::$prefix . 'DATA_INFO_SOURCE');
            $this->data_infos = $raw ? json_decode($raw, true) : [];
        }
        if (!is_array($this->data_infos)) {
            $this->data_infos = [];
        }
        if (is_array($data_infos)) {
            unset($data_infos['cookie_key'], $data_infos['access_token'], $data_infos['token'], $data_infos['ps_root_dir']);
        }
        if ($task !== null) {
            if (!isset($this->data_infos['nb']) || !is_array($this->data_infos['nb'])) {
                $this->data_infos['nb'] = [];
            }
            $this->data_infos['nb'][$task] = $data_infos;
        } else {
            if (is_array($data_infos)) {
                $this->data_infos = array_merge($this->data_infos, $data_infos);
            } else {
                $this->data_infos = $data_infos;
            }
        }
        if (is_array($this->data_infos)) {
            unset($this->data_infos['cookie_key'], $this->data_infos['access_token'], $this->data_infos['token'], $this->data_infos['ps_root_dir']);
        }
        Configuration::updateGlobalValue(self::$prefix . 'DATA_INFO_SOURCE', $this->data_infos ? json_encode($this->data_infos) : '', true);

        return $this;
    }

    public function setMigrated($task)
    {
        if (!in_array($task, $this->data_migrated)) {
            $this->data_migrated[] = $task;
            Configuration::updateGlobalValue(self::$prefix . 'DATA_MIGRATED', $this->data_migrated ? implode(',', $this->data_migrated) : '', true);
        }

        return $this;
    }

    public function getMigrated($task = null)
    {
        if (!$this->data_migrated) {
            $this->data_migrated = ($tasks = trim(Configuration::getGlobalValue(self::$prefix . 'DATA_MIGRATED'))) ? explode(',', $tasks) : [];
        }

        return $task !== null ? in_array($task, $this->data_migrated) : $this->data_migrated;
    }

    public function getPendingMigrateTasks()
    {
        return array_values(array_diff($this->getDataToMigrate(), $this->getMigrated()));
    }

    public function cleanMigrated()
    {
        $this->data_migrated = [];
        Configuration::updateGlobalValue(self::$prefix . 'DATA_MIGRATED', '');

        return $this;
    }

    public function setGroupTables($tables = [])
    {
        $this->group_tables = $tables;
        Configuration::updateGlobalValue(self::$prefix . 'GROUP_TABLES', $tables ? implode(',', $tables) : '', true);

        return $this;
    }

    public function getGroupTables()
    {
        if (!$this->group_tables) {
            $this->group_tables = ($tables = trim(Configuration::getGlobalValue(self::$prefix . 'GROUP_TABLES'))) ? explode(',', $tables) : [];
        }

        return $this->group_tables;
    }

    public function setOffset($offset = 0)
    {
        $previous = (int) $this->getOffset();
        $this->offset = $offset;
        Configuration::updateGlobalValue(self::$prefix . 'MIGRATE_OFFSET', $this->offset, true);
        if ((int) $offset > $previous) {
            $this->resetAdaptiveRetryCount();
        }

        return $this;
    }

    public function getOffset()
    {
        if (!$this->offset) {
            $this->offset = (int) Configuration::getGlobalValue(self::$prefix . 'MIGRATE_OFFSET');
        }

        return $this->offset;
    }

    public function getBaselineMigrateSpeed()
    {
        $baseline = (int) Configuration::getGlobalValue(self::$prefix . 'MIGRATE_SPEED');
        if ($baseline <= 0) {
            $baseline = 200;
        }

        return EtsMigrateConfigForm::clampMigrateSpeed($baseline);
    }

    public function getEffectiveMigrateSpeed()
    {
        $baseline = $this->getBaselineMigrateSpeed();
        $runtime = (int) Configuration::getGlobalValue(self::$prefix . 'MIGRATE_SPEED_RUNTIME');
        if ($runtime <= 0) {
            $runtime = $baseline;
            $this->setRuntimeMigrateSpeed($runtime);
        }
        $speed = max(self::MIGRATE_SPEED_MIN, min($runtime, $baseline));
        $this->migrate_speed = $speed;

        return $speed;
    }

    public function setRuntimeMigrateSpeed($speed)
    {
        $baseline = $this->getBaselineMigrateSpeed();
        $speed = max(self::MIGRATE_SPEED_MIN, min((int) $speed, $baseline));
        $this->migrate_speed = $speed;
        Configuration::updateGlobalValue(self::$prefix . 'MIGRATE_SPEED_RUNTIME', $speed, true);

        return $this;
    }

    public function resetRuntimeMigrateSpeed()
    {
        Configuration::deleteByName(self::$prefix . 'MIGRATE_SPEED_RUNTIME');
        Configuration::deleteByName(self::$prefix . 'ADAPTIVE_RETRY_COUNT');
        $this->migrate_speed = $this->getBaselineMigrateSpeed();

        return $this;
    }

    public function reduceRuntimeMigrateSpeed()
    {
        $current = $this->getEffectiveMigrateSpeed();
        if ($current <= self::MIGRATE_SPEED_MIN) {
            return false;
        }
        $this->setRuntimeMigrateSpeed((int) floor($current / 2));

        return true;
    }

    public function tuneRuntimeMigrateSpeed($elapsedSeconds, $recordCount)
    {
        $elapsedSeconds = (int) $elapsedSeconds;
        $recordCount = (int) $recordCount;
        $current = $this->getEffectiveMigrateSpeed();
        $baseline = $this->getBaselineMigrateSpeed();
        $next = $current;
        if ($elapsedSeconds >= self::MIGRATE_SPEED_SLOW_SECONDS) {
            $next = max(self::MIGRATE_SPEED_MIN, (int) floor($current * 0.7));
        } elseif ($elapsedSeconds <= self::MIGRATE_SPEED_FAST_SECONDS && $recordCount > 0 && $recordCount >= $current * 0.8) {
            $next = min($baseline, (int) ceil($current * 1.15));
        }
        if ($next !== $current) {
            $this->setRuntimeMigrateSpeed($next);
        }
    }

    public function isRetryableImportFailure($elapsedSeconds)
    {
        if ((int) $elapsedSeconds >= self::MIGRATE_SPEED_TIMEOUT_SECONDS) {
            return true;
        }

        return self::isRetryableDatabaseMessage(Db::getInstance()->getMsgError());
    }

    public function canAdaptiveRetry()
    {
        return (int) Configuration::getGlobalValue(self::$prefix . 'ADAPTIVE_RETRY_COUNT') < self::ADAPTIVE_RETRY_MAX;
    }

    public function registerAdaptiveRetry()
    {
        $count = (int) Configuration::getGlobalValue(self::$prefix . 'ADAPTIVE_RETRY_COUNT');
        Configuration::updateGlobalValue(self::$prefix . 'ADAPTIVE_RETRY_COUNT', $count + 1, true);
    }

    public function resetAdaptiveRetryCount()
    {
        Configuration::updateGlobalValue(self::$prefix . 'ADAPTIVE_RETRY_COUNT', 0, true);

        return $this;
    }

    public function canAdaptiveRetryImport($elapsedSeconds)
    {
        if (!$this->isRetryableImportFailure($elapsedSeconds)) {
            return false;
        }
        if (!$this->canAdaptiveRetry()) {
            return false;
        }

        return $this->reduceRuntimeMigrateSpeed();
    }

    public function setMigrateActive($table = null)
    {
        if ($table === null) {
            $this->migrate_active = [];
        } else {
            if (is_array($this->migrate_active) && count($this->migrate_active) > 1) {
                $this->migrate_active = [];
            }
            $this->migrate_active[$table] = $table;
        }
        Configuration::updateGlobalValue(self::$prefix . 'MIGRATE_ACTIVE', json_encode($this->migrate_active), true);

        return $this;
    }

    public function getMigrateActive($table = null)
    {
        if (
            !$this->migrate_active
            || $table !== null && !isset($this->migrate_active[$table])
        ) {
            $this->migrate_active = json_decode(Configuration::getGlobalValue(self::$prefix . 'MIGRATE_ACTIVE'), true);
        }

        return $table !== null && isset($this->migrate_active[$table]) && trim($this->migrate_active[$table]) !== '' ? $this->migrate_active[$table] : (is_array($this->migrate_active) ? array_shift($this->migrate_active) : []);
    }

    public function setSchemaColumn($table = null, $schema_column = [])
    {
        if ($table === null) {
            $this->schema_column = [];
        } else {
            if (is_array($this->schema_column) && count($this->schema_column) > 1) {
                $this->schema_column = [];
            }
            $this->schema_column[$table] = $schema_column;
        }
        Configuration::updateGlobalValue(self::$prefix . 'SCHEMA_COLUMN', json_encode($this->schema_column), true);

        return $this;
    }

    public function getSchemaColumn($table = null)
    {
        if (
            !$this->schema_column
            || $table !== null && !isset($this->schema_column[$table])
        ) {
            $schemaColumnJson = self::getMigrationGlobalValue(self::$prefix . 'SCHEMA_COLUMN', self::$prefix . '_SCHEMA_COLUMN');
            $this->schema_column = json_decode($schemaColumnJson !== false && $schemaColumnJson !== null ? $schemaColumnJson : '', true);
        }

        return $table && isset($this->schema_column[$table]) && is_array($this->schema_column[$table]) ? $this->schema_column[$table] : [];
    }

    public function getMigrateFields($table = null)
    {
        if ($table === null) {
            return [];
        }
        if (
            !$this->migrate_fields
            || !isset($this->migrate_fields[$table])
        ) {
            $this->migrate_fields = json_decode(Configuration::getGlobalValue(self::$prefix . 'MIGRATE_FIELDS'), true);
        }

        return isset($this->migrate_fields[$table]) && is_array($this->migrate_fields[$table]) && count($this->migrate_fields[$table]) > 0 ? $this->migrate_fields[$table] : [];
    }

    public function setMigrateFields($table = null, $fields = [])
    {
        if ($table === null) {
            $this->migrate_fields = [];
        } else {
            if (is_array($this->migrate_fields) && count($this->migrate_fields) > 1) {
                $this->migrate_fields = [];
            }
            $this->migrate_fields[$table] = $fields;
        }
        Configuration::updateGlobalValue(self::$prefix . 'MIGRATE_FIELDS', json_encode($this->migrate_fields), true);

        return $this;
    }

    public function getMigratePrimary($table = null)
    {
        if ($table === null) {
            return [];
        }
        if (
            !$this->migrate_primary
            || !isset($this->migrate_primary[$table])
        ) {
            $this->migrate_primary = json_decode(Configuration::getGlobalValue(self::$prefix . 'MIGRATE_PRIMARY'), true);
        }

        return isset($this->migrate_primary[$table]) && is_array($this->migrate_primary[$table]) && count($this->migrate_primary[$table]) > 0 ? $this->migrate_primary[$table] : [];
    }

    public function setMigratePrimary($table = null, $primary = [])
    {
        if ($table === null) {
            $this->migrate_primary = [];
        } else {
            if (is_array($this->migrate_primary) && count($this->migrate_primary) > 1) {
                $this->migrate_primary = [];
            }
            $this->migrate_primary[$table] = $primary;
        }
        Configuration::updateGlobalValue(self::$prefix . 'MIGRATE_PRIMARY', json_encode($this->migrate_primary), true);

        return $this;
    }

    public function getMigrateSchema($table = null)
    {
        if ($table === null) {
            return [];
        }
        if (
            !$this->migrate_schema
            || !isset($this->migrate_schema[$table])
        ) {
            $this->migrate_schema = json_decode(Configuration::getGlobalValue(self::$prefix . 'MIGRATE_SCHEMA'), true);
        }

        return isset($this->migrate_schema[$table]) && is_array($this->migrate_schema[$table]) && count($this->migrate_schema[$table]) > 0 ? $this->migrate_schema[$table] : [];
    }

    public function setMigrateSchema($table = null, $schema = [])
    {
        if ($table === null) {
            $this->migrate_schema = [];
        } else {
            if (is_array($this->migrate_schema) && count($this->migrate_schema) > 1) {
                $this->migrate_schema = [];
            }
            $this->migrate_schema[$table] = $schema;
        }
        Configuration::updateGlobalValue(self::$prefix . 'MIGRATE_SCHEMA', json_encode($this->migrate_schema), true);

        return $this;
    }

    public function hasOldId($id, $table = null)
    {
        if ($this->old_id_exist === null) {
            $this->getOldIdMap();
        }
        $key = trim((string) $id);

        return $key !== '' && isset($this->old_id_exist[$table]) && is_array($this->old_id_exist[$table]) && isset($this->old_id_exist[$table][$key]);
    }

    public function getOldIdMap()
    {
        if ($this->old_id_exist === null) {
            $decoded = json_decode(trim((string) Configuration::getGlobalValue(self::$prefix . 'OLD_ID_EXIST')), true);
            $this->old_id_exist = [];
            if (is_array($decoded)) {
                foreach ($decoded as $tableName => $ids) {
                    if (!is_array($ids)) {
                        continue;
                    }
                    $set = [];
                    foreach ($ids as $key => $value) {
                        if (is_string($key) && trim($key) !== '') {
                            $set[trim($key)] = true;
                        } elseif ($value !== null && trim((string) $value) !== '') {
                            $set[trim((string) $value)] = true;
                        }
                    }
                    $this->old_id_exist[$tableName] = $set;
                }
            }
        }

        return $this->old_id_exist;
    }

    public function recordOldId($table, $id)
    {
        if (!$this->hasOldId($id, $table)) {
            $key = trim((string) $id);
            if (!isset($this->old_id_exist[$table]) || !is_array($this->old_id_exist[$table])) {
                $this->old_id_exist[$table] = [];
            }
            $this->old_id_exist[$table][$key] = true;
            $toStore = [];
            foreach ($this->old_id_exist as $tableName => $set) {
                $toStore[$tableName] = is_array($set) ? array_keys($set) : [];
            }
            Configuration::updateGlobalValue(self::$prefix . 'OLD_ID_EXIST', json_encode($toStore), true);
        }

        return $this;
    }

    public function cleanOldId()
    {
        $this->old_id_exist = [];
        Configuration::updateGlobalValue(self::$prefix . 'OLD_ID_EXIST', '');

        return $this;
    }

    public function getDataToMigrate()
    {
        if (!$this->data_to_migrate) {
            $this->data_to_migrate = explode(',', trim(Configuration::getGlobalValue(self::$prefix . 'DATA_TO_MIGRATE')));
        }

        return $this->data_to_migrate;
    }

    public function setDataToMigrate($data)
    {
        if (is_array($data)) {
            $this->data_to_migrate = $data;
        } elseif (!in_array($data, $this->data_to_migrate)) {
            $this->data_to_migrate[] = trim($data);
        }
        Configuration::updateGlobalValue(self::$prefix . 'DATA_TO_MIGRATE', $this->data_to_migrate ? implode(',', $this->data_to_migrate) : '');

        return $this;
    }

    public function getCount()
    {
        if (!$this->count) {
            $this->count = (int) Configuration::getGlobalValue(self::$prefix . 'RESOURCE_COUNT');
        }

        return (int) $this->count;
    }

    public function setCount($count = 0)
    {
        $this->count = (int) $count;
        Configuration::updateGlobalValue(self::$prefix . 'RESOURCE_COUNT', (int) $this->count);

        return $this;
    }

    public function getFieldsDefault($field = '')
    {
        if (trim($field) !== '') {
            return $this->fields_default[$field] ?? null;
        }

        return $this->fields_default;
    }

    public function setFieldsDefault($fields)
    {
        $this->fields_default = $fields;

        return $this;
    }

    public function getMigrateImages($table = null)
    {
        if (!$this->migrate_images) {
            $this->migrate_images = json_decode(Configuration::getGlobalValue(self::$prefix . 'RESOURCE_IMAGES'), true);
        }

        return $this->migrate_images[$table] ?? $this->migrate_images;
    }

    public function setMigrateImages($migrate_images = [])
    {
        $this->migrate_images = $migrate_images;
        Configuration::updateGlobalValue(self::$prefix . 'RESOURCE_IMAGES', json_encode($this->migrate_images));

        return $this;
    }

    public function getTableImages()
    {
        if (!$this->table_images) {
            $this->table_images = json_decode(Configuration::getGlobalValue(self::$prefix . 'TABLE_IMAGES'), true);
        }

        return $this->table_images;
    }

    public function setTableImages($table_images = [])
    {
        $this->table_images = $table_images;
        Configuration::updateGlobalValue(self::$prefix . 'TABLE_IMAGES', json_encode($this->table_images));

        return $this;
    }

    public function getMigrateFiles($table = null)
    {
        if (!$this->migrate_files) {
            $this->migrate_files = json_decode(Configuration::getGlobalValue(self::$prefix . 'RESOURCE_FILES'), true);
        }

        return $this->migrate_files[$table] ?? $this->migrate_files;
    }

    public function setMigrateFiles($migrate_files = [])
    {
        $this->migrate_files = $migrate_files;
        Configuration::updateGlobalValue(self::$prefix . 'RESOURCE_FILES', json_encode($this->migrate_files));

        return $this;
    }

    public function getTableFiles()
    {
        if (!$this->table_files) {
            $this->table_files = json_decode(Configuration::getGlobalValue(self::$prefix . 'TABLE_FILES'), true);
        }

        return $this->table_files;
    }

    public function setTableFiles($table_files = [])
    {
        $this->table_files = $table_files;
        Configuration::updateGlobalValue(self::$prefix . 'TABLE_FILES', json_encode($this->table_files));

        return $this;
    }

    public function getLanguages()
    {
        if (!self::$languages) {
            self::$languages = EtsMigrateTools::toPositiveIntList(Configuration::getGlobalValue(self::$prefix . 'LANGUAGES'));
        }

        return self::$languages;
    }

    public function setLanguages($languages = [])
    {
        self::$languages = EtsMigrateTools::toPositiveIntList($languages);
        Configuration::updateGlobalValue(self::$prefix . 'LANGUAGES', count(self::$languages) > 0 ? implode(',', self::$languages) : '');

        return $this;
    }

    public function getDefaultLanguage()
    {
        if (!self::$default_language) {
            self::$default_language = (int) Configuration::getGlobalValue(self::$prefix . 'LANG_DEFAULT');
        }

        return self::$default_language;
    }

    public function setDefaultLanguage($default_language = 0)
    {
        self::$default_language = (int) $default_language;
        Configuration::updateGlobalValue(self::$prefix . 'LANG_DEFAULT', self::$default_language > 0 ? self::$default_language : '');

        return $this;
    }

    public function getMigratedLang($iso_code = null)
    {
        $iso_code = $iso_code != null ? trim(Tools::strtolower($iso_code)) : '';
        if (!$this->migrated_lang) {
            $this->migrated_lang = ($res = trim(Configuration::getGlobalValue(self::$prefix . 'MIGRATED_LANG'))) !== '' ? explode(',', $res) : [];
        }

        return $iso_code !== '' ? (in_array($iso_code, $this->migrated_lang) ? $iso_code : false) : $this->migrated_lang;
    }

    public function setMigratedLang($iso_code = null)
    {
        if ($iso_code !== null && trim($iso_code) == '') {
            $this->migrated_lang = [];
        } elseif (!in_array($iso_code, $this->migrated_lang)) {
            $this->migrated_lang[] = $iso_code;
        }
        Configuration::updateGlobalValue(self::$prefix . 'MIGRATED_LANG', count($this->migrated_lang) > 0 ? implode(',', $this->migrated_lang) : '');

        return $this;
    }

    public function getLanguagePack()
    {
        if ($this->language_pack === null) {
            $this->language_pack = (int) Configuration::getGlobalValue(self::$prefix . 'LANGUAGE_PACK') ? 1 : 0;
        }

        return $this->language_pack;
    }

    public function setLanguagePack($language_pack = 0)
    {
        $this->language_pack = $language_pack > 0 ? 1 : 0;
        Configuration::updateGlobalValue(self::$prefix . 'LANGUAGE_PACK', $this->language_pack > 0 ? 1 : 0);

        return $this;
    }

    private function loadCleanMediaState()
    {
        $raw = (string) Configuration::getGlobalValue(self::$prefix . 'CLEAN_MEDIA_STATE');
        if (trim($raw) === '') {
            return [
                'phase' => 'media',
                'job_index' => 0,
                'offset' => 0,
            ];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            self::writeLog('Invalid clean media state JSON; resetting', 'ERROR');

            return [
                'phase' => 'media',
                'job_index' => 0,
                'offset' => 0,
            ];
        }

        $phase = isset($decoded['phase']) && in_array($decoded['phase'], ['media', 'files'], true) ? $decoded['phase'] : 'media';

        return [
            'phase' => $phase,
            'job_index' => isset($decoded['job_index']) ? max(0, (int) $decoded['job_index']) : 0,
            'offset' => isset($decoded['offset']) ? max(0, (int) $decoded['offset']) : 0,
        ];
    }

    private function saveCleanMediaState($state)
    {
        if (!is_array($state)) {
            self::writeLog('Cannot persist clean media state: invalid payload', 'ERROR');

            return false;
        }
        if (!Configuration::updateGlobalValue(self::$prefix . 'CLEAN_MEDIA_STATE', json_encode($state))) {
            self::writeLog('Failed to persist clean media state', 'ERROR');

            return false;
        }

        return true;
    }

    public function isCleanMediaPending()
    {
        $raw = (string) Configuration::getGlobalValue(self::$prefix . 'CLEAN_MEDIA_STATE');

        return trim($raw) !== '';
    }

    private function clearCleanMediaState()
    {
        $key = self::$prefix . 'CLEAN_MEDIA_STATE';
        $existing = (string) Configuration::getGlobalValue($key);
        if (trim($existing) === '') {
            return true;
        }
        if (!Configuration::deleteByName($key)) {
            self::writeLog('Failed to clear clean media state', 'ERROR');

            return false;
        }

        return true;
    }

    private function buildCleanMediaJobs($resource, $kind)
    {
        $jobs = [];
        $psRootResolved = EtsMigratePathSecurity::resolveAllowedRoot(_PS_ROOT_DIR_);
        if ($psRootResolved === false) {
            return $jobs;
        }
        $entries = isset($resource[$kind]) && is_array($resource[$kind]) ? $resource[$kind] : [];
        foreach ($entries as $table => $structs) {
            if (!is_array($structs) || !count($structs) || !EtsMigrateTools::tableExists($table)) {
                continue;
            }
            foreach ($structs as $struct) {
                if (!is_array($struct) || !isset($struct['path']) || trim($struct['path']) === '') {
                    continue;
                }
                $relativePath = trim($struct['path'], '/');
                if ($relativePath === '' || strpos($relativePath, '..') !== false) {
                    continue;
                }
                $path = _PS_ROOT_DIR_ . '/' . $relativePath . '/';
                if (!EtsMigratePathSecurity::isPathInsideResolvedRoot($path, $psRootResolved)) {
                    continue;
                }
                $pathResolved = EtsMigratePathSecurity::resolveAllowedRoot($path);
                if ($pathResolved === false) {
                    continue;
                }
                $field = $this->resolveMediaEntityFieldName($table, $struct);
                if ($field === '') {
                    continue;
                }
                $jobs[] = [
                    'table' => $table,
                    'struct' => $struct,
                    'path' => $path,
                    'pathResolved' => $pathResolved,
                    'field' => $field,
                ];
            }
        }

        return $jobs;
    }

    private function unlinkCleanMediaEntityRow($table, $path, $id_entity, $item, $image_types, $extension, $thumbParams, $pathResolved = null)
    {
        if (!isset($item[$id_entity]) || trim($item[$id_entity]) === '') {
            return;
        }
        if ($pathResolved === null) {
            $pathResolved = EtsMigratePathSecurity::resolveAllowedRoot($path);
        }
        if ($pathResolved === false) {
            return;
        }
        $tmpResolved = EtsMigratePathSecurity::resolveAllowedRoot(_PS_TMP_IMG_DIR_);
        if ($tmpResolved === false) {
            return;
        }
        $entityFileName = basename((string) $item[$id_entity]);
        if (Validate::isUnsignedInt($entityFileName)) {
            $entityFileName = (string) (int) $entityFileName;
        }
        $path_create = [
            $path . $entityFileName,
        ];
        if (trim($table) === 'image') {
            $path_create[] = $path . Image::getImgFolderStatic($item[$id_entity]) . $entityFileName;
        }
        $has_image_type = count($image_types) > 0 ? 1 : 0;
        foreach ($path_create as $path_item) {
            if (strpos($path_item, '..') !== false) {
                continue;
            }
            $files = [
                $path_item,
                _PS_TMP_IMG_DIR_ . ($table !== 'image' ? $table : 'product') . '_mini_' . (int) $item[$id_entity],
            ];
            if ($has_image_type) {
                foreach ($image_types as $type) {
                    if (isset($type['name']) && trim($type['name']) !== '') {
                        $files[] = $path_item . '-' . $type['name'];
                    }
                }
            }
            foreach ($files as $file) {
                $resolvedRoot = (strpos($file, _PS_TMP_IMG_DIR_) === 0) ? $tmpResolved : $pathResolved;
                if (is_file($file . $extension)) {
                    EtsMigratePathSecurity::unlinkInsideResolvedRoot($file . $extension, $resolvedRoot);
                }
            }
            if (trim($table) === 'category' && $thumbParams && !empty($thumbParams['enabled'])) {
                $id_cat = (int) $item[$id_entity];
                $thumb_stem = $path . $id_cat . '_thumb';
                foreach ($thumbParams['formats'] as $fmt) {
                    $p = $thumb_stem . '.' . $fmt;
                    if (is_file($p)) {
                        EtsMigratePathSecurity::unlinkInsideResolvedRoot($p, $pathResolved);
                    }
                }
                if ($has_image_type) {
                    foreach ($image_types as $type) {
                        if (!isset($type['name']) || trim($type['name']) === '') {
                            continue;
                        }
                        $tn = stripslashes(trim($type['name']));
                        foreach ($thumbParams['formats'] as $fmt) {
                            $p = $path . $id_cat . '_thumb-' . $tn . '.' . $fmt;
                            if (is_file($p)) {
                                EtsMigratePathSecurity::unlinkInsideResolvedRoot($p, $pathResolved);
                            }
                        }
                    }
                }
                $tmp_thumb = _PS_TMP_IMG_DIR_ . 'category_' . $id_cat . '-thumb.jpg';
                if (is_file($tmp_thumb)) {
                    EtsMigratePathSecurity::unlinkInsideResolvedRoot($tmp_thumb, $tmpResolved);
                }
            }
        }
    }

    private function unlinkCleanFileEntityRow($table, $struct, $path, $field, $item)
    {
        if (!isset($item[$field]) || trim($item[$field]) === '') {
            return;
        }
        $extension = isset($struct['ext']) && trim($struct['ext']) !== '' ? trim($struct['ext']) : '';
        $fileBaseDir = $path;
        if (defined('_PS_UPLOAD_DIR_') && strpos($path, _PS_UPLOAD_DIR_) === 0) {
            $fileBaseDir = _PS_UPLOAD_DIR_;
        } elseif (defined('_PS_DOWNLOAD_DIR_') && strpos($path, _PS_DOWNLOAD_DIR_) === 0) {
            $fileBaseDir = _PS_DOWNLOAD_DIR_;
        }
        $fileName = basename(trim($item[$field]));
        if (trim($table) === 'customized_data') {
            if (!EtsMigratePathSecurity::isCustomizationFileName($fileName)) {
                return;
            }
        } elseif (!EtsMigratePathSecurity::isSafeFileName($fileName) || EtsMigratePathSecurity::isBlockedUploadExtension($fileName)) {
            return;
        }
        EtsMigratePathSecurity::unlink($path . $fileName . $extension, $fileBaseDir);
        if (trim($table) == 'customized_data') {
            $smallFileName = $fileName . '_small';
            if (EtsMigratePathSecurity::isCustomizationFileName($smallFileName)) {
                EtsMigratePathSecurity::unlink($path . $smallFileName . $extension, $fileBaseDir);
            }
        }
    }

    public function getTargetShopIdsToClean()
    {
        $targetShopIds = [];
        if (is_array(self::$mapping_shop)) {
            foreach (self::$mapping_shop as $idTarget) {
                if ((int) $idTarget > 0) {
                    $targetShopIds[(int) $idTarget] = (int) $idTarget;
                }
            }
        }

        return array_values($targetShopIds);
    }

    public function isPartialMultistoreClean()
    {
        if (!class_exists('Shop') || !method_exists('Shop', 'isFeatureActive') || !Shop::isFeatureActive()) {
            return false;
        }
        $allShops = Shop::getShops(false);
        if (count($allShops) <= 1) {
            return false;
        }
        $mapped = $this->getTargetShopIdsToClean();

        return count($mapped) > 0 && count($mapped) < count($allShops);
    }

    public function ensureTargetShopsCreated()
    {
        if (!class_exists('Shop') || !is_array(self::$mapping_shop) || empty(self::$mapping_shop)) {
            return true;
        }
        $sourceMeta = $this->getSourceMigrationMetadata();
        $sourceShops = isset($sourceMeta['source_shops']) && is_array($sourceMeta['source_shops']) ? $sourceMeta['source_shops'] : [];
        $sourceShopNames = [];
        foreach ($sourceShops as $sShop) {
            if (isset($sShop['id_shop'], $sShop['name'])) {
                $sourceShopNames[(int) $sShop['id_shop']] = (string) $sShop['name'];
            }
        }
        $updated = false;
        foreach (self::$mapping_shop as $sourceId => $targetId) {
            if ((int) $targetId === 0) {
                if (method_exists('Shop', 'resetStaticCache')) {
                    Shop::resetStaticCache();
                }
                if (method_exists('Shop', 'isFeatureActive') && !Shop::isFeatureActive()) {
                    Configuration::updateGlobalValue('PS_MULTISHOP_FEATURE_ACTIVE', 1);
                    if (method_exists('Shop', 'resetStaticCache')) {
                        Shop::resetStaticCache();
                    }
                }
                $shopName = isset($sourceShopNames[(int) $sourceId]) && trim($sourceShopNames[(int) $sourceId]) !== ''
                    ? $sourceShopNames[(int) $sourceId]
                    : 'Shop ' . (int) $sourceId;
                $newShop = new Shop();
                $newShop->name = $shopName;
                $newShop->id_shop_group = 1;
                $newShop->id_category = (int) Configuration::get('PS_ROOT_CATEGORY') ?: 2;
                $newShop->theme_name = $this->resolveDefaultThemeName();
                $newShop->active = true;
                if ($newShop->add()) {
                    self::$mapping_shop[$sourceId] = (int) $newShop->id;
                    Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'ets_em_shop_mapping` SET `id_shop_target` = ' . (int) $newShop->id . ' WHERE `id_shop_source` = ' . (int) $sourceId);
                    $updated = true;
                }
            }
        }
        if ($updated) {
            $this->applyMappedShopContext();
        }

        return true;
    }

    private function fetchCleanMediaJobRows($job, $offset, $limit)
    {
        $table = $job['table'];
        $field = $job['field'];
        $dq = new DbQuery();
        $dq
            ->select('`' . bqSQL($field) . '`')
            ->from($table, 'a')
            ->orderBy('a.`' . bqSQL($field) . '` ASC')
            ->limit((int) $limit, (int) $offset);
        if ($table == 'category') {
            $dq
                ->where('a.id_parent!=0')
                ->where('a.is_root_category!=1');
        }
        if ($this->isPartialMultistoreClean()) {
            $shopIds = $this->getTargetShopIdsToClean();
            $inShops = implode(',', array_map('intval', $shopIds));
            if ($table === 'category' && EtsMigrateTools::tableExists('category_shop')) {
                $dq->where('NOT EXISTS (SELECT 1 FROM `' . _DB_PREFIX_ . 'category_shop` cs WHERE cs.`id_category` = a.`id_category` AND cs.`id_shop` NOT IN (' . $inShops . '))');
            } elseif ($table === 'image' && EtsMigrateTools::tableExists('image_shop')) {
                $dq->where('NOT EXISTS (SELECT 1 FROM `' . _DB_PREFIX_ . 'image_shop` ishop WHERE ishop.`id_image` = a.`id_image` AND ishop.`id_shop` NOT IN (' . $inShops . '))');
            }
        }
        $rows = Db::getInstance()->executeS($dq);
        if ($rows === false) {
            self::writeLog('Media cleanup query failed on table ' . $table . ': ' . Db::getInstance()->getMsgError(), 'ERROR');

            return false;
        }

        return $rows;
    }

    private function fetchCleanFileJobRows($job, $offset, $limit)
    {
        $table = $job['table'];
        $field = $job['field'];
        $dq = new DbQuery();
        $dq
            ->select('`' . bqSQL($field) . '`')
            ->from($table)
            ->orderBy('`' . bqSQL($field) . '` ASC')
            ->limit((int) $limit, (int) $offset);
        $rows = Db::getInstance()->executeS($dq);
        if ($rows === false) {
            self::writeLog('File cleanup query failed on table ' . $table . ': ' . Db::getInstance()->getMsgError(), 'ERROR');

            return false;
        }

        return $rows;
    }

    public function cleanBeforeMigrateData($resource)
    {
        if (
            !is_array($resource)
            || !count($resource)
            || !isset($resource['tables'])
            || !count($resource['tables'])
        ) {
            return false;
        }
        $tables = $resource['tables'];
        $batchLimit = max(50, (int) $this->migrate_image_speed * 20);
        $state = $this->loadCleanMediaState();
        $processed = 0;

        if ($state['phase'] === 'media') {
            $mediaJobs = $this->buildCleanMediaJobs($resource, 'images');
            $jobIndex = (int) $state['job_index'];
            $offset = (int) $state['offset'];
            while ($jobIndex < count($mediaJobs) && $processed < $batchLimit) {
                $job = $mediaJobs[$jobIndex];
                $struct = $job['struct'];
                $image_types = isset($struct['type']) && trim($struct['type']) !== '' ? ImageType::getImagesTypes(trim($struct['type'])) : [];
                $extension = isset($struct['ext']) && trim($struct['ext']) !== '' ? trim($struct['ext']) : '';
                $thumbParams = trim($job['table']) === 'category' ? $this->getCategoryThumbMigrationParams() : null;
                $remaining = $batchLimit - $processed;
                $rows = $this->fetchCleanMediaJobRows($job, $offset, $remaining);
                if ($rows === false) {
                    return false;
                }
                foreach ($rows as $item) {
                    $this->unlinkCleanMediaEntityRow($job['table'], $job['path'], $job['field'], $item, $image_types, $extension, $thumbParams, $job['pathResolved'] ?? null);
                }
                $rowCount = count($rows);
                $processed += $rowCount;
                if ($rowCount < $remaining) {
                    ++$jobIndex;
                    $offset = 0;
                } else {
                    $offset += $rowCount;
                }
            }
            if ($jobIndex < count($mediaJobs)) {
                if (!$this->saveCleanMediaState([
                    'phase' => 'media',
                    'job_index' => $jobIndex,
                    'offset' => $offset,
                ])) {
                    return false;
                }

                return 'continue';
            }
            $state = [
                'phase' => 'files',
                'job_index' => 0,
                'offset' => 0,
            ];
            if ($processed >= $batchLimit) {
                if (!$this->saveCleanMediaState($state)) {
                    return false;
                }

                return 'continue';
            }
        }

        if ($state['phase'] === 'files') {
            $fileJobs = $this->buildCleanMediaJobs($resource, 'files');
            $jobIndex = (int) $state['job_index'];
            $offset = (int) $state['offset'];
            while ($jobIndex < count($fileJobs) && $processed < $batchLimit) {
                $job = $fileJobs[$jobIndex];
                $remaining = $batchLimit - $processed;
                $rows = $this->fetchCleanFileJobRows($job, $offset, $remaining);
                if ($rows === false) {
                    return false;
                }
                foreach ($rows as $item) {
                    $this->unlinkCleanFileEntityRow($job['table'], $job['struct'], $job['path'], $job['field'], $item);
                }
                $rowCount = count($rows);
                $processed += $rowCount;
                if ($rowCount < $remaining) {
                    ++$jobIndex;
                    $offset = 0;
                } else {
                    $offset += $rowCount;
                }
            }
            if ($jobIndex < count($fileJobs)) {
                if (!$this->saveCleanMediaState([
                    'phase' => 'files',
                    'job_index' => $jobIndex,
                    'offset' => $offset,
                ])) {
                    return false;
                }

                return 'continue';
            }
        }

        if ($this->isPartialMultistoreClean()) {
            $shopIds = $this->getTargetShopIdsToClean();
            $inShops = implode(',', array_map('intval', $shopIds));
            $queries = ['SET FOREIGN_KEY_CHECKS = 0'];
            foreach ($tables as $table) {
                if (preg_match('/^shop([0-9a-zA-Z\_]+)?$/', $table) || !EtsMigrateTools::tableExists($table)) {
                    continue;
                }
                $schema = EtsMigrateTools::describeTable($table);
                if (!is_array($schema)) {
                    continue;
                }
                $fields = [];
                foreach ($schema as $column) {
                    if (isset($column['Field'])) {
                        $fields[] = $column['Field'];
                    }
                }
                if (in_array('id_shop', $fields, true)) {
                    $queries[] = 'DELETE FROM `' . _DB_PREFIX_ . bqSQL($table) . '` WHERE `id_shop` IN (' . $inShops . ')';
                }
                if ($table === 'stock_available') {
                    $sharedShopGroupIds = [];
                    foreach ($shopIds as $idShopClean) {
                        $shareInfo = EtsMigrateTools::shopShare($idShopClean, $this->context);
                        if (is_array($shareInfo) && !empty($shareInfo['share_stock']) && !empty($shareInfo['id_shop_group'])) {
                            $sharedShopGroupIds[(int) $shareInfo['id_shop_group']] = (int) $shareInfo['id_shop_group'];
                        }
                    }
                    if ($sharedShopGroupIds) {
                        $queries[] = 'DELETE FROM `' . _DB_PREFIX_ . 'stock_available` WHERE `id_shop` = 0 AND `id_shop_group` IN (' . implode(',', $sharedShopGroupIds) . ')';
                    }
                }
                if ($table === 'product' && EtsMigrateTools::tableExists('product_shop')) {
                    $queries[] = 'DELETE a FROM `' . _DB_PREFIX_ . 'product` a LEFT JOIN `' . _DB_PREFIX_ . 'product_shop` b ON (a.`id_product` = b.`id_product`) WHERE b.`id_product` IS NULL';
                    if (EtsMigrateTools::tableExists('product_attribute')) {
                        $queries[] = 'DELETE a FROM `' . _DB_PREFIX_ . 'product_attribute` a LEFT JOIN `' . _DB_PREFIX_ . 'product` b ON (a.`id_product` = b.`id_product`) WHERE b.`id_product` IS NULL';
                    }
                    if (EtsMigrateTools::tableExists('product_attribute_combination') && EtsMigrateTools::tableExists('product_attribute')) {
                        $queries[] = 'DELETE a FROM `' . _DB_PREFIX_ . 'product_attribute_combination` a LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` b ON (a.`id_product_attribute` = b.`id_product_attribute`) WHERE b.`id_product_attribute` IS NULL';
                    }
                } elseif ($table === 'category' && EtsMigrateTools::tableExists('category_shop')) {
                    $queries[] = 'DELETE a FROM `' . _DB_PREFIX_ . 'category` a LEFT JOIN `' . _DB_PREFIX_ . 'category_shop` b ON (a.`id_category` = b.`id_category`) WHERE b.`id_category` IS NULL AND a.`id_category` > 2';
                } elseif ($table === 'image' && EtsMigrateTools::tableExists('image_shop')) {
                    $queries[] = 'DELETE a FROM `' . _DB_PREFIX_ . 'image` a LEFT JOIN `' . _DB_PREFIX_ . 'image_shop` b ON (a.`id_image` = b.`id_image`) WHERE b.`id_image` IS NULL';
                } elseif ($table === 'carrier' && EtsMigrateTools::tableExists('carrier_shop')) {
                    $queries[] = 'DELETE a FROM `' . _DB_PREFIX_ . 'carrier` a LEFT JOIN `' . _DB_PREFIX_ . 'carrier_shop` b ON (a.`id_carrier` = b.`id_carrier`) WHERE b.`id_carrier` IS NULL';
                } elseif ($table === 'manufacturer' && EtsMigrateTools::tableExists('manufacturer_shop')) {
                    $queries[] = 'DELETE a FROM `' . _DB_PREFIX_ . 'manufacturer` a LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer_shop` b ON (a.`id_manufacturer` = b.`id_manufacturer`) WHERE b.`id_manufacturer` IS NULL';
                } elseif ($table === 'supplier' && EtsMigrateTools::tableExists('supplier_shop')) {
                    $queries[] = 'DELETE a FROM `' . _DB_PREFIX_ . 'supplier` a LEFT JOIN `' . _DB_PREFIX_ . 'supplier_shop` b ON (a.`id_supplier` = b.`id_supplier`) WHERE b.`id_supplier` IS NULL';
                }
            }
            $queries[] = 'SET FOREIGN_KEY_CHECKS = 1';
            $cleanResult = Db::getInstance()->execute(implode(';', $queries));
            if ($cleanResult === false) {
                self::writeLog('cleanPartialMultistoreData failed: ' . Db::getInstance()->getMsgError(), 'ERROR');

                return false;
            }
            if (!$this->clearCleanMediaState()) {
                return false;
            }

            return true;
        }

        $queries = ['SET FOREIGN_KEY_CHECKS = 0'];
        foreach ($tables as $table) {
            if (
                !preg_match('/^shop([0-9a-zA-Z\_]+)?$/', $table)
                && EtsMigrateTools::tableExists($table)
            ) {
                if (!$this->ignoreTable($table)) {
                    $queries[] = 'TRUNCATE TABLE `' . _DB_PREFIX_ . bqSQL($table) . '`';
                } elseif (preg_match('/^([0-9a-zA-Z\_]+?)_(lang|shop)$/', $table, $m)) {
                    $dq = new DbQuery();
                    $dq
                        ->from($table, 'a')
                        ->leftJoin($m[2], 'b', 'a.id_' . $m[2] . ' = b.id_' . $m[2])
                        ->where('b.id_' . $m[2] . ' is NULL OR b.id_' . $m[2] . ' <= 0');
                    $queries[] = preg_replace('/DELETE|SELECT\s+\*/i', 'DELETE a', $dq->build());
                }
            }
        }
        $queries[] = 'SET FOREIGN_KEY_CHECKS = 1';
        $cleanResult = Db::getInstance()->execute(implode(';', $queries));
        if ($cleanResult === false) {
            self::writeLog('cleanBeforeMigrateData failed: ' . Db::getInstance()->getMsgError(), 'ERROR');

            return false;
        }
        if (!$this->clearCleanMediaState()) {
            return false;
        }

        return true;
    }

    public static function deleteAllMigrationConfiguration()
    {
        $keys = [
            'ETS_EM_DOMAIN',
            'ETS_EM_ACCESS_TOKEN',
            'ETS_EM_DATA_TO_MIGRATE',
            'ETS_EM_MIGRATION_METHOD',
            'ETS_EM_MIGRATE_IMAGES',
            'ETS_EM_MIGRATE_IMAGE_SPEED',
            'ETS_EM_ATTACHMENTS_FILES',
            'ETS_EM_ATTACHMENTS_FILES_SPEED',
            'ETS_EM_MIGRATE_EMPTY_CART',
            'ETS_EM_SUPPLIER_DEFAULT',
            'ETS_EM_MANUFACTURER_DEFAULT',
            'ETS_EM_MIGRATE_SPEED',
            'ETS_EM_MIGRATE_VERSION',
            'ETS_EM_MIGRATE_LOCK',
            'ETS_EM_CLEAN_MEDIA_STATE',
            'ETS_EM_SEARCH_INDEX_PENDING',
            'ETS_EM_ORDER_PATCH_LAST_ID',
            'ETS_EM_GENERATE_PRODUCT_THUMBNAIL',
            'ETS_EM_SOURCE_COOKIE_KEY',
            'ETS_EM_PROCESS_MIGRATION',
            'ETS_EM_MIGRATION_DONE',
            self::$prefix . 'DATA_MIGRATED',
            self::$prefix . 'DATA_TO_MIGRATE',
            self::$prefix . 'DATA_INFO_SOURCE',
            self::$prefix . 'MIGRATE_OFFSET',
            self::$prefix . 'GROUP_TABLES',
            self::$prefix . 'MIGRATE_ACTIVE',
            self::$prefix . 'MIGRATE_FIELDS',
            self::$prefix . 'MIGRATE_SCHEMA',
            self::$prefix . 'MIGRATE_PRIMARY',
            self::$prefix . 'MIGRATE_UNIQUE',
            self::$prefix . 'OLD_ID_EXIST',
            self::$prefix . 'RESOURCE_COUNT',
            self::$prefix . 'RESOURCE_IMAGES',
            self::$prefix . 'RESOURCE_FILES',
            self::$prefix . 'TABLE_IMAGES',
            self::$prefix . 'TABLE_FILES',
            self::$prefix . 'LANGUAGES',
            self::$prefix . 'LANG_DEFAULT',
            self::$prefix . 'MIGRATED_LANG',
            self::$prefix . 'LANGUAGE_PACK',
            self::$prefix . 'LANGUAGE_MIGRATE',
            self::$prefix . 'ADAPTIVE_RETRY_COUNT',
            self::$prefix . 'MIGRATE_SPEED_RUNTIME',
            self::$prefix . 'SCHEMA_COLUMN',
            self::$prefix . '_SCHEMA_COLUMN',
        ];
        $deleted = true;
        foreach ($keys as $key) {
            if (!Configuration::deleteByName($key)) {
                $deleted = false;
            }
        }

        return $deleted;
    }

    public static function cleanDb()
    {
        $imageSuffix = self::$image_field_unique;
        $fileSuffix = self::$file_field_unique;
        $preservePasswordFallback = (bool) Module::isInstalled('ets_passwordkeeper')
            || (bool) Module::isEnabled('ets_passwordkeeper');
        $query = '
            SELECT TABLE_NAME, COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = \'' . pSQL(_DB_NAME_) . '\'
                AND TABLE_NAME LIKE \'' . pSQL(_DB_PREFIX_) . '%\'
                AND (
                    COLUMN_NAME LIKE \'id\\_%\\_ets\\_old\' ESCAPE \'\\\\\'
                    OR COLUMN_NAME LIKE \'' . pSQL('%' . $imageSuffix) . '\'
                    OR COLUMN_NAME LIKE \'' . pSQL('%' . $fileSuffix) . '\'
                    OR COLUMN_NAME LIKE \'%\\_old\\_wp\' ESCAPE \'\\\\\'
                    OR COLUMN_NAME = \'category_thumb_exists\'
                )
        ';

        $collections = Db::getInstance()->executeS($query);
        if ($collections === false) {
            self::writeLog('cleanDb INFORMATION_SCHEMA failed: ' . Db::getInstance()->getMsgError(), 'ERROR');

            return false;
        }

        if ($collections) {
            $dropQueries = [];

            foreach ($collections as $schema) {
                $tableName = isset($schema['TABLE_NAME']) ? (string) $schema['TABLE_NAME'] : '';
                $columnName = isset($schema['COLUMN_NAME']) ? (string) $schema['COLUMN_NAME'] : '';

                if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName) || !preg_match('/^[a-zA-Z0-9_]+$/', $columnName)) {
                    continue;
                }
                // Password Keeper authenticates legacy hashes via passwd_old_wp + ETS_NEW_COOKIE_KEY.
                // Dropping the column during finalize permanently breaks source password login.
                if (
                    $preservePasswordFallback
                    && preg_match('/^[a-zA-Z0-9_]+_old_wp$/', $columnName)
                    && (substr($tableName, -strlen('customer')) === 'customer'
                        || substr($tableName, -strlen('employee')) === 'employee')
                ) {
                    continue;
                }
                $isMigrateColumn = (bool) preg_match('/^id_[a-zA-Z0-9_]+_ets_old$/', $columnName)
                    || (bool) preg_match('/^[a-zA-Z0-9_]+' . preg_quote($imageSuffix, '/') . '$/', $columnName)
                    || (bool) preg_match('/^[a-zA-Z0-9_]+' . preg_quote($fileSuffix, '/') . '$/', $columnName)
                    || (bool) preg_match('/^[a-zA-Z0-9_]+_old_wp$/', $columnName)
                    || $columnName === 'category_thumb_exists';
                if (!$isMigrateColumn) {
                    continue;
                }
                $dropQueries[] = 'ALTER TABLE `' . bqSQL($tableName) . '` DROP COLUMN `' . bqSQL($columnName) . '`';
            }

            if (!empty($dropQueries)) {
                if (!Db::getInstance()->execute("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'")
                    || !Db::getInstance()->execute("SET time_zone = '+00:00'")
                ) {
                    self::writeLog('cleanDb session setup failed: ' . Db::getInstance()->getMsgError(), 'ERROR');

                    return false;
                }
                foreach ($dropQueries as $dropQuery) {
                    if (!Db::getInstance()->execute($dropQuery)) {
                        self::writeLog('cleanDb DROP COLUMN failed: ' . Db::getInstance()->getMsgError(), 'ERROR');

                        return false;
                    }
                }
                EtsMigrateTools::clearSchemaCache();
            }
        }

        return true;
    }

    public static function truncateShopMappingTable()
    {
        if (!Db::getInstance()->execute('SET FOREIGN_KEY_CHECKS = 0')) {
            self::writeLog('truncateShopMappingTable FK off failed: ' . Db::getInstance()->getMsgError(), 'ERROR');

            return false;
        }
        $truncatedOk = Db::getInstance()->execute('TRUNCATE TABLE `' . _DB_PREFIX_ . 'ets_em_shop_mapping`');
        if ($truncatedOk === false) {
            self::writeLog('truncateShopMappingTable failed: ' . Db::getInstance()->getMsgError(), 'ERROR');
        }
        if (!Db::getInstance()->execute('SET FOREIGN_KEY_CHECKS = 1')) {
            self::writeLog('truncateShopMappingTable FK on failed: ' . Db::getInstance()->getMsgError(), 'ERROR');

            return false;
        }

        return $truncatedOk;
    }

    public static function cleanShopsMapping()
    {
        self::$mapping_shop_group = [];

        return self::truncateShopMappingTable();
    }

    public static function cleanAll($context, $cleanDb = true)
    {
        $import = self::getInstance($context);
        $import
            ->cleanMigrated()
            ->resetRuntimeMigrateSpeed()
            ->setOffset()
            ->setCount()
            ->setGroupTables()
            ->setMigrateFields()
            ->setMigratePrimary()
            ->cleanOldId()
            ->setMigrateActive()
            ->setMigrateSchema()
            ->setMigrateImages()
            ->setTableImages()
            ->setMigrateFiles()
            ->setTableFiles()
            ->setDefaultLanguage()
            ->setLanguages()
            ->setMigratedLang()
            ->setLanguagePack()
            ->setLanguageMigrate()
            ->cleanUnique();
        if (!self::cleanShopsMapping()) {
            return false;
        }
        self::restoreMappedShopContext($context);
        if ($cleanDb && !self::cleanDb()) {
            return false;
        }

        return true;
    }
}
