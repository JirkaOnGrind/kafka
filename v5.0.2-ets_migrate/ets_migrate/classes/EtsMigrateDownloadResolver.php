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

class EtsMigrateDownloadResolver
{
    public static function resolveFilePath($moduleLocalPath, $fileName, $allowedFilesMap)
    {
        if (!is_string($fileName)) {
            return false;
        }

        $fileName = basename($fileName);
        if (!Validate::isFileName($fileName) || !isset($allowedFilesMap[$fileName])) {
            return false;
        }

        $modulePath = realpath($moduleLocalPath);
        if ($modulePath === false) {
            return false;
        }

        $directory = $allowedFilesMap[$fileName];
        $directoryPath = realpath($modulePath . DIRECTORY_SEPARATOR . $directory);
        if ($directoryPath === false
            || dirname($directoryPath) !== $modulePath
            || basename($directoryPath) !== $directory
        ) {
            return false;
        }

        $filePath = realpath($directoryPath . DIRECTORY_SEPARATOR . $fileName);
        if ($filePath === false
            || !is_file($filePath)
            || dirname($filePath) !== $directoryPath
            || basename($filePath) !== $fileName
        ) {
            return false;
        }

        return $filePath;
    }

    public static function resolveMimeType($fileName)
    {
        switch (Tools::strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) {
            case 'pdf':
                return 'application/pdf';
            case 'zip':
                return 'application/zip';
            default:
                return 'application/octet-stream';
        }
    }
}
