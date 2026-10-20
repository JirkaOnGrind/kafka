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

class EtsMigratePathSecurity
{
    private static $allowedRootMemo = [];

    public static function file_put_contents($path, $data, $allowed_directory = null, $allowedExtensions = [], $flags = 0, $context = null)
    {
        $realAllowed = self::resolveAllowedRoot($allowed_directory);
        if ($realAllowed === false) {
            return false;
        }

        $sanitizedPath = self::sanitizePath($path);
        if ($sanitizedPath === false) {
            return false;
        }

        if (!self::isPathInsideResolvedRoot($sanitizedPath, $realAllowed)) {
            return false;
        }

        if (!empty($allowedExtensions) && !self::checkFileExtension($sanitizedPath, $allowedExtensions)) {
            return false;
        }

        $parentDirectory = dirname($sanitizedPath);
        $realParent = realpath($parentDirectory);
        if ($realParent === false || !is_dir($realParent) || !is_writable($realParent)) {
            return false;
        }

        if (!self::isResolvedPathInsideDirectory($realParent, $realAllowed)) {
            return false;
        }

        $fileName = basename($sanitizedPath);
        if (!self::isSafeFileName($fileName)) {
            return false;
        }

        $targetPath = $realParent . DIRECTORY_SEPARATOR . $fileName;
        if ($context !== null) {
            $result = file_put_contents($targetPath, $data, $flags, $context);
        } else {
            $result = file_put_contents($targetPath, $data, $flags);
        }

        return $result;
    }

    public static function unlink($filePath, $baseDir = null)
    {
        $realAllowed = self::resolveAllowedRoot($baseDir);
        if ($realAllowed === false) {
            return false;
        }

        return self::unlinkInsideResolvedRoot($filePath, $realAllowed);
    }

    public static function unlinkInsideResolvedRoot($filePath, $realAllowedRoot)
    {
        if (!is_string($realAllowedRoot) || $realAllowedRoot === '') {
            return false;
        }

        $sanitizedPath = self::sanitizePath($filePath);
        if ($sanitizedPath === false) {
            return false;
        }

        if (!self::isPathInsideResolvedRoot($sanitizedPath, $realAllowedRoot)) {
            return false;
        }

        if (is_link($sanitizedPath) || !is_file($sanitizedPath)) {
            return false;
        }

        $realPath = realpath($sanitizedPath);
        if ($realPath === false || !is_file($realPath) || is_link($realPath)) {
            return false;
        }

        if (!self::isResolvedPathInsideDirectory($realPath, $realAllowedRoot)) {
            return false;
        }

        return unlink($realPath);
    }

    public static function sanitizePath($path)
    {
        if (!is_string($path) || $path === '') {
            return false;
        }

        if (strpos($path, "\0") !== false) {
            return false;
        }

        if (strpos($path, '\\') !== false) {
            return false;
        }

        if (strpos($path, '://') !== false) {
            return false;
        }

        $sanitizedPath = preg_replace('#[^A-Za-z0-9._/\-]#', '', $path);
        if ($sanitizedPath !== $path) {
            return false;
        }

        $segments = explode('/', $sanitizedPath);
        foreach ($segments as $segment) {
            if ($segment === '..') {
                return false;
            }
        }

        return $sanitizedPath;
    }

    public static function resolveAllowedRoot($allowed_directory)
    {
        if (!is_string($allowed_directory) || $allowed_directory === '') {
            return false;
        }

        $sanitizedAllowed = self::sanitizePath($allowed_directory);
        if ($sanitizedAllowed === false) {
            return false;
        }

        if (array_key_exists($sanitizedAllowed, self::$allowedRootMemo)) {
            return self::$allowedRootMemo[$sanitizedAllowed];
        }

        if (self::pathContainsSymlink($sanitizedAllowed)) {
            self::$allowedRootMemo[$sanitizedAllowed] = false;

            return false;
        }

        $realAllowed = realpath($sanitizedAllowed);
        if ($realAllowed === false || !is_dir($realAllowed)) {
            self::$allowedRootMemo[$sanitizedAllowed] = false;

            return false;
        }

        self::$allowedRootMemo[$sanitizedAllowed] = $realAllowed;

        return $realAllowed;
    }

    public static function isPathInAllowedDirectory($path, $allowed_directory = null)
    {
        $realAllowed = self::resolveAllowedRoot($allowed_directory);
        if ($realAllowed === false) {
            return false;
        }

        return self::isPathInsideResolvedRoot($path, $realAllowed);
    }

    public static function isPathInsideResolvedRoot($path, $realAllowedRoot)
    {
        if (!is_string($path) || $path === '' || !is_string($realAllowedRoot) || $realAllowedRoot === '') {
            return false;
        }

        $sanitizedPath = self::sanitizePath($path);
        if ($sanitizedPath === false) {
            return false;
        }

        if (self::pathContainsSymlink($sanitizedPath)) {
            return false;
        }

        $realDir = self::resolveExistingDirectory($sanitizedPath);
        if ($realDir === false) {
            return false;
        }

        return self::isResolvedPathInsideDirectory($realDir, $realAllowedRoot);
    }

    public static function checkFileExtension($path, $allowedExtensions)
    {
        if (!is_array($allowedExtensions)) {
            return false;
        }

        $fileExtension = pathinfo($path, PATHINFO_EXTENSION);

        return in_array($fileExtension, $allowedExtensions, true);
    }

    public static function isSafeFileName($name)
    {
        if (!is_string($name) || $name === '') {
            return false;
        }

        if (strpos($name, '/') !== false || strpos($name, '\\') !== false || strpos($name, '..') !== false) {
            return false;
        }

        return Validate::isFileName($name);
    }

    public static function isCustomizationFileName($name)
    {
        if (!is_string($name) || $name === '') {
            return false;
        }

        return (bool) preg_match('/^[0-9a-f]{40}(_small)?$/', $name);
    }

    public static function isBlockedUploadExtension($name)
    {
        if (!is_string($name) || $name === '') {
            return false;
        }

        $baseName = Tools::strtolower(basename($name));
        if ($baseName === '.htaccess' || $baseName === 'htaccess') {
            return true;
        }

        $extension = Tools::strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $blockedExtensions = [
            'php',
            'phtml',
            'phar',
            'php3',
            'php4',
            'php5',
            'php6',
            'php7',
            'php8',
            'htaccess',
            'cgi',
            'exe',
            'js',
        ];

        return in_array($extension, $blockedExtensions, true);
    }

    public static function resolveExistingDirectory($path)
    {
        if (file_exists($path)) {
            if (is_link($path)) {
                return false;
            }
            $realPath = realpath($path);
            if ($realPath === false) {
                return false;
            }

            return is_dir($realPath) ? $realPath : dirname($realPath);
        }

        $ancestor = dirname($path);
        while ($ancestor !== '' && $ancestor !== '.' && $ancestor !== '/' && !file_exists($ancestor)) {
            $nextAncestor = dirname($ancestor);
            if ($nextAncestor === $ancestor) {
                return false;
            }
            $ancestor = $nextAncestor;
        }

        if (!file_exists($ancestor) || is_link($ancestor)) {
            return false;
        }

        $realAncestor = realpath($ancestor);
        if ($realAncestor === false || !is_dir($realAncestor)) {
            return false;
        }

        return $realAncestor;
    }

    public static function isResolvedPathInsideDirectory($realPath, $realAllowed)
    {
        if (!is_string($realPath) || $realPath === '' || !is_string($realAllowed) || $realAllowed === '') {
            return false;
        }

        return $realPath === $realAllowed || strpos($realPath, $realAllowed . DIRECTORY_SEPARATOR) === 0;
    }

    public static function pathContainsSymlink($path)
    {
        if (!is_string($path) || $path === '') {
            return true;
        }

        $parts = explode('/', $path);
        $current = '';
        foreach ($parts as $index => $part) {
            if ($index === 0 && $part === '') {
                $current = '/';
                continue;
            }
            if ($part === '') {
                continue;
            }
            if ($current === '/' || $current === '') {
                $current = $current === '/' ? '/' . $part : $part;
            } else {
                $current .= '/' . $part;
            }
            if (file_exists($current) && is_link($current)) {
                return true;
            }
        }

        return false;
    }
}
