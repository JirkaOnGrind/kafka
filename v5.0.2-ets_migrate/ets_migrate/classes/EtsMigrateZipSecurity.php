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

class EtsMigrateZipSecurity
{
    public static function extractZipSafely($zipPath, $destinationDirectory)
    {
        if (!class_exists('ZipArchive') || !is_string($zipPath) || !is_file($zipPath)) {
            return false;
        }

        $resolvedZipPath = realpath($zipPath);
        if ($resolvedZipPath === false) {
            return false;
        }

        $sanitizedZipPath = EtsMigratePathSecurity::sanitizePath($resolvedZipPath);
        $sanitizedDestination = EtsMigratePathSecurity::sanitizePath($destinationDirectory);
        if ($sanitizedZipPath === false || $sanitizedDestination === false) {
            return false;
        }

        $realDestination = realpath($sanitizedDestination);
        if ($realDestination === false || !is_dir($realDestination)) {
            return false;
        }

        if (EtsMigratePathSecurity::pathContainsSymlink($realDestination)) {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($sanitizedZipPath) !== true) {
            return false;
        }

        $fileCount = (int) $zip->numFiles;
        $safeEntryNames = [];
        for ($index = 0; $index < $fileCount; ++$index) {
            $entryName = $zip->getNameIndex($index);
            if (!self::isSafeZipEntryName($entryName)) {
                $zip->close();

                return false;
            }
            $safeEntryNames[] = $entryName;
        }

        $parentMemo = [
            $realDestination => $realDestination,
        ];

        foreach ($safeEntryNames as $entryName) {
            $destinationPath = $realDestination . '/' . $entryName;
            $destinationParent = dirname($destinationPath);
            if (isset($parentMemo[$destinationParent])) {
                $realParent = $parentMemo[$destinationParent];
            } else {
                $realParent = EtsMigratePathSecurity::resolveExistingDirectory($destinationParent);
                if ($realParent === false || !EtsMigratePathSecurity::isResolvedPathInsideDirectory($realParent, $realDestination)) {
                    $zip->close();

                    return false;
                }
                if (is_dir($destinationParent) && !is_link($destinationParent)) {
                    $resolvedParent = realpath($destinationParent);
                    if ($resolvedParent !== false
                        && EtsMigratePathSecurity::isResolvedPathInsideDirectory($resolvedParent, $realDestination)
                    ) {
                        $parentMemo[$destinationParent] = $resolvedParent;
                    }
                }
            }

            if (!$zip->extractTo($realDestination, $entryName)) {
                $zip->close();

                return false;
            }

            if (file_exists($destinationPath) && is_link($destinationPath)) {
                unlink($destinationPath);
                $zip->close();

                return false;
            }

            if (is_dir($destinationPath) && !is_link($destinationPath)) {
                $resolvedDirectory = realpath($destinationPath);
                if ($resolvedDirectory !== false
                    && EtsMigratePathSecurity::isResolvedPathInsideDirectory($resolvedDirectory, $realDestination)
                ) {
                    $normalizedDirectory = rtrim($destinationPath, '/');
                    $parentMemo[$destinationPath] = $resolvedDirectory;
                    $parentMemo[$normalizedDirectory] = $resolvedDirectory;
                }
            }

            if (!isset($parentMemo[$destinationParent])
                && is_dir($destinationParent)
                && !is_link($destinationParent)
            ) {
                $resolvedParent = realpath($destinationParent);
                if ($resolvedParent !== false
                    && EtsMigratePathSecurity::isResolvedPathInsideDirectory($resolvedParent, $realDestination)
                ) {
                    $parentMemo[$destinationParent] = $resolvedParent;
                }
            }
        }

        $zip->close();

        return true;
    }

    private static function isSafeZipEntryName($entryName)
    {
        if (!is_string($entryName) || $entryName === '') {
            return false;
        }

        if (strpos($entryName, "\0") !== false || strpos($entryName, '\\') !== false) {
            return false;
        }

        $normalizedName = $entryName;
        if ($normalizedName === '.') {
            return false;
        }

        if ($normalizedName[0] === '/' || strpos($normalizedName, '://') !== false) {
            return false;
        }

        if (preg_match('#^[A-Za-z]:/#', $normalizedName)) {
            return false;
        }

        $segments = explode('/', $normalizedName);
        foreach ($segments as $segment) {
            if ($segment === '..') {
                return false;
            }
        }

        return true;
    }
}
