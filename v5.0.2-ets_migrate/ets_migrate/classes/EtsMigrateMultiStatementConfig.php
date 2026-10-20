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

class EtsMigrateMultiStatementConfig
{
    public const DEFINES_FILE = _PS_ROOT_DIR_ . '/config/defines.inc.php';
    public const CUSTOM_DEFINES_FILE = _PS_ROOT_DIR_ . '/config/defines_custom.inc.php';
    public const PATTERN = '/(define\(\'_PS_ALLOW_MULTI_STATEMENTS_QUERIES_\', )([a-zA-Z]+)(\);)/Ui';

    public static function setMultiStatementsStatus($status)
    {
        $desired = ((bool) $status) ? 'true' : 'false';
        $content = null;
        $matchSource = false;
        $file = self::CUSTOM_DEFINES_FILE;
        if (is_readable(self::CUSTOM_DEFINES_FILE)) {
            $content = Tools::file_get_contents(self::CUSTOM_DEFINES_FILE);
            $matchSource = php_strip_whitespace(self::CUSTOM_DEFINES_FILE);
        }
        if (!$matchSource || !preg_match(self::PATTERN, $matchSource)) {
            $content = Tools::file_get_contents(self::DEFINES_FILE);
            $matchSource = php_strip_whitespace(self::DEFINES_FILE);
            $file = self::DEFINES_FILE;
            if (!$matchSource || !preg_match(self::PATTERN, $matchSource)) {
                return false;
            }
        }
        if (!$content || !preg_match(self::PATTERN, $matchSource, $matches)) {
            return false;
        }
        if (Tools::strtolower($matches[2]) === $desired) {
            return true;
        }
        $bytesWritten = file_put_contents($file, preg_replace(self::PATTERN, '$1' . $desired . '$3', $content));
        if ($bytesWritten === false) {
            return false;
        }
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file);
        }

        return true;
    }

    public static function isEnabledInRuntime()
    {
        if (!defined('_PS_ALLOW_MULTI_STATEMENTS_QUERIES_')) {
            return false;
        }

        return (bool) constant('_PS_ALLOW_MULTI_STATEMENTS_QUERIES_');
    }

    public static function restoreDisabledStatus()
    {
        if (!defined('_PS_ALLOW_MULTI_STATEMENTS_QUERIES_')) {
            return true;
        }

        return self::setMultiStatementsStatus(false);
    }
}
