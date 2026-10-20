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

class EtsMigrateApiResponse
{
    public static function normalizeToUtf8($mixed)
    {
        if (is_array($mixed)) {
            foreach ($mixed as $key => $value) {
                $mixed[$key] = self::normalizeToUtf8($value);
            }
        } elseif (is_string($mixed)) {
            return self::utf8ToCharset($mixed);
        }

        return $mixed;
    }

    private static function utf8ToCharset($content, $encoding = null)
    {
        if ($encoding == null && function_exists('mb_detect_encoding')) {
            $encoding = mb_detect_encoding($content);
        }
        if ('UTF-8' !== $encoding && !empty($encoding) && function_exists('mb_convert_encoding')) {
            $converted = mb_convert_encoding($content, 'UTF-8', $encoding);

            return $converted === false ? $content : $converted;
        }

        return $content;
    }

    public static function isResponseMetaKey($key)
    {
        return in_array($key, [
            'error',
            'errors',
            'ok',
            'end',
            'json_error_utf8',
            'limit',
            'empty',
            'offset',
            'speed',
            'ps_version',
            'total',
        ], true);
    }

    public static function isDeniedSecretTable($table)
    {
        $table = trim((string) $table);
        if ($table === '') {
            return true;
        }
        $denied = [
            'configuration',
            'configuration_kpi',
            'configuration_lang',
            'configuration_kpi_lang',
            'employee_session',
            'customer_session',
            'access',
            'authorization_role',
            'module',
            'module_shop',
            'module_group',
            'module_country',
            'module_currency',
            'module_carrier',
            'module_history',
            'tab',
            'tab_lang',
            'tab_module_preference',
            'quick_access',
            'quick_access_lang',
        ];
        if (in_array($table, $denied, true)) {
            return true;
        }
        if (strpos($table, 'webservice_') === 0) {
            return true;
        }
        if (strpos($table, 'hook') === 0) {
            return true;
        }

        return false;
    }
}
