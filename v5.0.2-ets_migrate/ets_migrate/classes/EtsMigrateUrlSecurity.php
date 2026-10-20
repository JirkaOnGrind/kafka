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

class EtsMigrateUrlSecurity
{
    private static $domainConfigHash;
    private static $publicUrlMemo = [];
    private static $allowedDomainMemo = [];
    private static $hostIpsMemo = [];

    public static function isAllowedDomain($url, $allowed_domains = [])
    {
        self::syncDomainConfigMemo();

        if (!self::isPublicHttpUrl($url)) {
            return false;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return false;
        }

        $scheme = isset($parts['scheme']) ? Tools::strtolower($parts['scheme']) : '';
        $host = self::normalizeHost($parts['host']);
        $usesConfigDomains = empty($allowed_domains);
        $memoKey = $scheme . '|' . $host;

        if ($usesConfigDomains && array_key_exists($memoKey, self::$allowedDomainMemo)) {
            return self::$allowedDomainMemo[$memoKey];
        }

        if ($usesConfigDomains) {
            $configuredHost = parse_url(Configuration::getGlobalValue('ETS_EM_DOMAIN'), PHP_URL_HOST);
            $allowed_domains = $configuredHost ? [$configuredHost] : [];
        }

        $allowed = false;
        foreach ($allowed_domains as $allowedDomain) {
            if (self::isSameHttpHost($host, (string) $allowedDomain)) {
                $allowed = true;
                break;
            }
        }

        if ($usesConfigDomains) {
            self::$allowedDomainMemo[$memoKey] = $allowed;
        }

        return $allowed;
    }

    public static function isLocalEnvironment()
    {
        if (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_) {
            return true;
        }

        $currentHost = (string) Tools::getHttpHost(false, false);
        if ($currentHost !== '' && self::isLocalNetworkHost($currentHost)) {
            return true;
        }

        $serverName = isset($_SERVER['SERVER_NAME']) ? (string) $_SERVER['SERVER_NAME'] : '';
        if ($serverName !== '' && self::isLocalNetworkHost($serverName)) {
            return true;
        }

        $serverAddr = isset($_SERVER['SERVER_ADDR']) ? (string) $_SERVER['SERVER_ADDR'] : '';
        if ($serverAddr !== '' && self::isLocalDevIp($serverAddr)) {
            return true;
        }

        return false;
    }

    public static function isLocalHostname($host)
    {
        $host = self::normalizeHost((string) $host);
        if (self::isLoopbackHostname($host)) {
            return true;
        }

        foreach (['.test', '.local', '.localhost', '.invalid', '.example', '.internal', '.lan'] as $suffix) {
            if ($host !== '' && substr($host, -strlen($suffix)) === $suffix) {
                return true;
            }
        }

        return false;
    }

    public static function isLocalNetworkHost($host)
    {
        $host = self::normalizeHost((string) $host);
        if ($host === '') {
            return false;
        }
        if (self::isLocalHostname($host)) {
            return true;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::isLocalDevIp($host);
        }
        $resolvedIps = self::resolveHostIps($host);
        if (!empty($resolvedIps)) {
            foreach ($resolvedIps as $resolvedIp) {
                if (self::isLocalDevIp($resolvedIp)) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function isSameHttpHost($host, $otherHost)
    {
        $host = self::normalizeHost((string) $host);
        $otherHost = self::normalizeHost((string) $otherHost);
        if ($host === '' || $otherHost === '') {
            return false;
        }
        if ($host === $otherHost) {
            return true;
        }
        if (self::isLoopbackHostname($host) && self::isLoopbackHostname($otherHost)) {
            return true;
        }
        if (self::isCanonicalDomainPair($host, $otherHost)) {
            return true;
        }
        if (self::isLocalEnvironment() && self::isLocalNetworkHost($host) && self::isLocalNetworkHost($otherHost)) {
            return true;
        }

        $ipsA = self::resolveHostIps($host);
        $ipsB = self::resolveHostIps($otherHost);
        if (!empty($ipsA) && !empty($ipsB) && !empty(array_intersect($ipsA, $ipsB))) {
            return true;
        }

        return false;
    }

    public static function isBlockedIp($ip)
    {
        $ip = self::normalizeHost((string) $ip);
        if ($ip === '') {
            return true;
        }

        if (preg_match('/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $ip, $mapped)) {
            return self::isBlockedIp($mapped[1]);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return self::isBlockedIpv4($ip);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return self::isBlockedIpv6($ip);
        }

        return true;
    }

    public static function isPublicHttpUrl($url)
    {
        if (!is_string($url) || $url === '') {
            return false;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        $scheme = Tools::strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $host = self::normalizeHost($parts['host']);
        if ($host === '') {
            return false;
        }

        $memoKey = $scheme . '|' . $host;
        if (array_key_exists($memoKey, self::$publicUrlMemo)) {
            return self::$publicUrlMemo[$memoKey];
        }

        if ($host === 'metadata.google.internal' || $host === 'metadata') {
            return self::$publicUrlMemo[$memoKey] = false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (self::isMetadataOrProhibitedIp($host)) {
                return self::$publicUrlMemo[$memoKey] = false;
            }
            if (self::isLocalEnvironment()) {
                return self::$publicUrlMemo[$memoKey] = true;
            }

            return self::$publicUrlMemo[$memoKey] = !self::isBlockedIp($host);
        }

        $resolvedIps = self::resolveHostIps($host);
        if (empty($resolvedIps)) {
            if (self::isLocalEnvironment() && self::isLocalHostname($host)) {
                return self::$publicUrlMemo[$memoKey] = true;
            }

            return self::$publicUrlMemo[$memoKey] = false;
        }

        foreach ($resolvedIps as $resolvedIp) {
            if (self::isMetadataOrProhibitedIp($resolvedIp)) {
                return self::$publicUrlMemo[$memoKey] = false;
            }
        }

        if (self::isLocalEnvironment()) {
            return self::$publicUrlMemo[$memoKey] = true;
        }

        foreach ($resolvedIps as $resolvedIp) {
            if (self::isBlockedIp($resolvedIp)) {
                return self::$publicUrlMemo[$memoKey] = false;
            }
        }

        return self::$publicUrlMemo[$memoKey] = true;
    }

    public static function resolveHostIps($host)
    {
        $host = Tools::strtolower((string) $host);
        if ($host === '') {
            return [];
        }

        if (array_key_exists($host, self::$hostIpsMemo)) {
            return self::$hostIpsMemo[$host];
        }

        $resolvedIps = [];

        $ipv4List = gethostbynamel($host);
        if (is_array($ipv4List)) {
            foreach ($ipv4List as $ipv4) {
                $resolvedIps[] = $ipv4;
            }
        } else {
            $ipv4 = gethostbyname($host);
            if ($ipv4 !== $host && filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $resolvedIps[] = $ipv4;
            }
        }

        if (function_exists('dns_get_record')) {
            $aRecords = dns_get_record($host, DNS_A);
            if (is_array($aRecords)) {
                foreach ($aRecords as $record) {
                    if (!empty($record['ip'])) {
                        $resolvedIps[] = $record['ip'];
                    }
                }
            }

            $aaaaRecords = dns_get_record($host, DNS_AAAA);
            if (is_array($aaaaRecords)) {
                foreach ($aaaaRecords as $record) {
                    if (!empty($record['ipv6'])) {
                        $resolvedIps[] = $record['ipv6'];
                    }
                }
            }
        }

        $uniqueIps = [];
        $seen = [];
        foreach ($resolvedIps as $resolvedIp) {
            if (isset($seen[$resolvedIp])) {
                continue;
            }
            $seen[$resolvedIp] = true;
            $uniqueIps[] = $resolvedIp;
        }

        return self::$hostIpsMemo[$host] = $uniqueIps;
    }

    private static function syncDomainConfigMemo()
    {
        $domainConfigHash = md5((string) Configuration::getGlobalValue('ETS_EM_DOMAIN'));
        if (self::$domainConfigHash === $domainConfigHash) {
            return;
        }

        self::$domainConfigHash = $domainConfigHash;
        self::$allowedDomainMemo = [];
        self::$publicUrlMemo = [];
    }

    private static function normalizeHost($host)
    {
        $host = Tools::strtolower(trim((string) $host));
        if ($host !== '' && $host[0] === '[' && substr($host, -1) === ']') {
            $host = substr($host, 1, -1);
        }

        return $host;
    }

    private static function isLoopbackHostname($host)
    {
        $host = self::normalizeHost($host);

        return $host === 'localhost' || $host === '127.0.0.1' || $host === '::1';
    }

    public static function isLocalDevIp($ip)
    {
        $ip = self::normalizeHost($ip);
        if (preg_match('/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $ip, $mapped)) {
            return self::isLocalDevIp($mapped[1]);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return self::isLocalDevIpv4($ip);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return self::isLocalDevIpv6($ip);
        }

        return false;
    }

    public static function isMetadataOrProhibitedIp($ip)
    {
        $ip = self::normalizeHost((string) $ip);
        if ($ip === '') {
            return true;
        }
        if (preg_match('/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $ip, $mapped)) {
            return self::isMetadataOrProhibitedIp($mapped[1]);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return self::isMetadataOrProhibitedIpv4($ip);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return self::isMetadataOrProhibitedIpv6($ip);
        }

        return true;
    }

    private static function isCanonicalDomainPair($host, $allowedHost)
    {
        $hostWithoutWww = strpos($host, 'www.') === 0 ? substr($host, 4) : $host;
        $allowedHostWithoutWww = strpos($allowedHost, 'www.') === 0 ? substr($allowedHost, 4) : $allowedHost;

        return $hostWithoutWww === $allowedHostWithoutWww
            && $hostWithoutWww !== 'localhost'
            && !filter_var($hostWithoutWww, FILTER_VALIDATE_IP);
    }

    private static function isBlockedIpv4($ip)
    {
        return self::isMetadataOrProhibitedIpv4($ip) || self::isLocalDevIpv4($ip);
    }

    private static function isBlockedIpv6($ip)
    {
        return self::isMetadataOrProhibitedIpv6($ip) || self::isLocalDevIpv6($ip);
    }

    private static function isMetadataOrProhibitedIpv4($ip)
    {
        $long = ip2long($ip);
        if ($long === false) {
            return true;
        }
        $long = $long & 0xFFFFFFFF;

        if ($ip === '0.250.250.254') {
            return false;
        }
        if (($long & 0xFF000000) === 0x00000000) {
            return true;
        }
        if (($long & 0xFFFF0000) === 0xA9FE0000) {
            return true;
        }
        if ($long === 0x646464C8) {
            return true;
        }
        if ($ip === '192.0.0.192') {
            return true;
        }
        if (($long & 0xF0000000) === 0xE0000000) {
            return true;
        }
        if (($long & 0xF0000000) === 0xF0000000) {
            return true;
        }

        return false;
    }

    private static function isLocalDevIpv4($ip)
    {
        $long = ip2long($ip);
        if ($long === false) {
            return false;
        }
        $long = $long & 0xFFFFFFFF;

        if ($ip === '0.250.250.254') {
            return true;
        }

        return (($long & 0xFF000000) === 0x7F000000)
            || (($long & 0xFF000000) === 0x0A000000)
            || (($long & 0xFFF00000) === 0xAC100000)
            || (($long & 0xFFFF0000) === 0xC0A80000)
            || (($long & 0xFFC00000) === 0x64400000);
    }

    private static function isMetadataOrProhibitedIpv6($ip)
    {
        $binary = inet_pton($ip);
        if ($binary === false) {
            return true;
        }
        if ($binary === inet_pton('::')) {
            return true;
        }
        if ($binary === inet_pton('fd00:ec2::254')) {
            return true;
        }
        $first = unpack('n', substr($binary, 0, 2));
        if ($first === false || !isset($first[1])) {
            return true;
        }
        $prefix = $first[1];
        if (($prefix & 0xFF00) === 0xFF00) {
            return true;
        }
        if (($prefix & 0xFFC0) === 0xFE80) {
            return true;
        }
        if ($prefix === 0x2002) {
            $embeddedIpv4 = sprintf('%d.%d.%d.%d', ord($binary[2]), ord($binary[3]), ord($binary[4]), ord($binary[5]));
            if (self::isMetadataOrProhibitedIpv4($embeddedIpv4)) {
                return true;
            }
        }

        return false;
    }

    private static function isLocalDevIpv6($ip)
    {
        $binary = inet_pton($ip);
        if ($binary === false) {
            return false;
        }
        if ($binary === inet_pton('::1')) {
            return true;
        }
        $first = unpack('n', substr($binary, 0, 2));
        if ($first === false || !isset($first[1])) {
            return false;
        }
        $prefix = $first[1];
        if (($prefix & 0xFE00) === 0xFC00) {
            return true;
        }
        if ($prefix === 0x2002) {
            $embeddedIpv4 = sprintf('%d.%d.%d.%d', ord($binary[2]), ord($binary[3]), ord($binary[4]), ord($binary[5]));
            if (self::isLocalDevIpv4($embeddedIpv4)) {
                return true;
            }
        }

        return false;
    }
}
