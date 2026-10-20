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

class EtsMigrateHttpTransport
{
    private static $caBundlePath;
    private static $caBundleResolved = false;
    private static $caCertRefreshed = false;
    private static $redirectDepth = 0;

    public static function getCaBundlePath()
    {
        if (self::$caBundleResolved) {
            return self::$caBundlePath;
        }

        $candidate_paths = [];
        $candidate_paths[] = ini_get('curl.cainfo');
        $candidate_paths[] = ini_get('openssl.cafile');
        if (defined('_PS_CACHE_CA_CERT_FILE_')) {
            $candidate_paths[] = _PS_CACHE_CA_CERT_FILE_;
        }
        $candidate_paths[] = _PS_CACHE_DIR_ . 'cacert.pem';
        if (class_exists('Composer\CaBundle\CaBundle') && method_exists('Composer\CaBundle\CaBundle', 'getBundledCaBundlePath')) {
            $candidate_paths[] = Composer\CaBundle\CaBundle::getBundledCaBundlePath();
        }
        foreach ($candidate_paths as $candidate_path) {
            if ($candidate_path && is_file($candidate_path) && is_readable($candidate_path)) {
                self::$caBundlePath = $candidate_path;
                self::$caBundleResolved = true;

                return self::$caBundlePath;
            }
        }

        self::$caBundlePath = null;
        self::$caBundleResolved = true;

        return null;
    }

    public static function applyCurlHostResolve(&$curl_options, $url)
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return false;
        }

        $host = $parts['host'];
        if ($host !== '' && $host[0] === '[' && substr($host, -1) === ']') {
            $host = substr($host, 1, -1);
        }
        if ($host === '') {
            return false;
        }

        $scheme = isset($parts['scheme']) ? Tools::strtolower($parts['scheme']) : 'http';
        $port = !empty($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);

        $candidate_ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $candidate_ips[] = $host;
        } else {
            $candidate_ips = EtsMigrateUrlSecurity::resolveHostIps($host);
        }

        if (!$candidate_ips) {
            self::logCurl('ERROR', 'Cannot resolve host ' . $host);

            return false;
        }

        $allowLocalIp = EtsMigrateUrlSecurity::isLocalEnvironment()
            && EtsMigrateUrlSecurity::isLocalNetworkHost($host);
        $pinned_ip = null;
        foreach ($candidate_ips as $candidate_ip) {
            if (!filter_var($candidate_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                continue;
            }
            if (!$allowLocalIp && EtsMigrateUrlSecurity::isBlockedIp($candidate_ip)) {
                continue;
            }
            $pinned_ip = $candidate_ip;
            break;
        }
        if ($pinned_ip === null) {
            foreach ($candidate_ips as $candidate_ip) {
                if (!filter_var($candidate_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    continue;
                }
                if (!$allowLocalIp && EtsMigrateUrlSecurity::isBlockedIp($candidate_ip)) {
                    continue;
                }
                $pinned_ip = $candidate_ip;
                break;
            }
        }
        if ($pinned_ip === null) {
            self::logCurl('ERROR', 'Host ' . $host . ' resolves only to blocked IPs');

            return false;
        }

        if ($allowLocalIp && ($pinned_ip === '127.0.0.1' || $pinned_ip === '::1')) {
            $dockerHostIp = gethostbyname('host.docker.internal');
            if ($dockerHostIp !== 'host.docker.internal' && filter_var($dockerHostIp, FILTER_VALIDATE_IP)) {
                $testSocket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
                if ($testSocket) {
                    fclose($testSocket);
                } else {
                    $dockerSocket = @fsockopen($dockerHostIp, $port, $errno, $errstr, 0.5);
                    if ($dockerSocket) {
                        fclose($dockerSocket);
                        $pinned_ip = $dockerHostIp;
                    }
                }
            }
        }

        $resolve_target = filter_var($pinned_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            ? $host . ':' . $port . ':[' . $pinned_ip . ']'
            : $host . ':' . $port . ':' . $pinned_ip;
        $curl_options[CURLOPT_RESOLVE] = [$resolve_target];

        return true;
    }

    private static function redactSecrets($value)
    {
        if (is_array($value)) {
            $redacted = [];
            foreach ($value as $key => $item) {
                $key_name = (string) $key;
                if (Tools::strtolower($key_name) === 'cookie_key') {
                    continue;
                }
                if (preg_match('/^(token|secret|password|api_key)$/i', $key_name)) {
                    $redacted[$key] = '***';
                    continue;
                }
                $redacted[$key] = self::redactSecrets($item);
            }

            return $redacted;
        }

        if (!is_string($value)) {
            return $value;
        }

        $value = preg_replace('/([?&])(token|secret|password|api_key)=[^&]*/i', '$1$2=***', $value);
        $value = preg_replace('/\b(token|secret|password|api_key)\b\s*[:=]\s*["\']?[^&"\'\s,}]+/i', '$1=***', $value);
        if (stripos($value, 'cookie_key') !== false) {
            return '[redacted]';
        }

        return $value;
    }

    private static function getAccessToken()
    {
        $tokenFromRequest = trim((string) Tools::getValue('ETS_EM_ACCESS_TOKEN'));
        if ($tokenFromRequest !== '') {
            return $tokenFromRequest;
        }

        return trim((string) Configuration::getGlobalValue('ETS_EM_ACCESS_TOKEN'));
    }

    private static function ensureCaBundleReady()
    {
        $ca_bundle_path = self::getCaBundlePath();
        if (self::$caCertRefreshed) {
            return $ca_bundle_path;
        }

        if (!$ca_bundle_path && method_exists('Tools', 'refreshCACertFile')) {
            Tools::refreshCACertFile();
            self::$caBundleResolved = false;
            self::$caBundlePath = null;
            $ca_bundle_path = self::getCaBundlePath();
        }
        self::$caCertRefreshed = true;

        return $ca_bundle_path;
    }

    public static function fetchRemoteContents($url, $use_include_path = false, $stream_context = null, $curl_timeout = 10, $opts = [])
    {
        $request_options = $opts;
        $is_url_file = preg_match('/^https?:\/\//', $url);
        $is_connector = strpos($url, '/modules/ets_migrate_connector/server.php') !== false;
        $access_token = $is_connector ? self::getAccessToken() : '';
        if ($is_connector && is_array($opts) && count($opts) > 0 && !isset($opts['token']) && $access_token !== '') {
            $opts['token'] = $access_token;
        }
        $log_url = self::redactSecrets($url);
        $request_lines = ['--- REQUEST ---', 'URL: ' . $log_url];
        if (is_array($opts) && count($opts) > 0) {
            $log_opts = self::redactSecrets($opts);
            $request_lines[] = 'Method: POST';
            $request_lines[] = 'Body: ' . substr(http_build_query($log_opts), 0, 500);
        } else {
            $request_lines[] = 'Method: GET';
        }
        self::logCurl('INFO', implode("\n", $request_lines));
        if ($is_url_file) {
            $url = filter_var($url, FILTER_SANITIZE_URL);
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                self::logCurl('ERROR', 'Invalid URL: ' . self::redactSecrets($url));

                return false;
            }
            if (!EtsMigrateUrlSecurity::isPublicHttpUrl($url)) {
                self::logCurl('ERROR', 'URL is not an allowed HTTP(S) URL: ' . self::redactSecrets($url));

                return false;
            }
            if (!EtsMigrateUrlSecurity::isAllowedDomain($url)) {
                self::logCurl('ERROR', 'Domain not allowed: ' . self::redactSecrets($url));

                return false;
            }
        } else {
            $url = EtsMigratePathSecurity::sanitizePath($url);
            if (!$url || !EtsMigratePathSecurity::isPathInAllowedDirectory($url, _PS_ROOT_DIR_)) {
                self::logCurl('ERROR', 'Unauthorized path');

                return false;
            }
            if (!file_exists($url)) {
                self::logCurl('ERROR', 'Local file not found');

                return false;
            }
        }

        $ca_bundle_path = self::ensureCaBundleReady();
        $post = is_array($opts) && count($opts) > 0 ? 1 : 0;
        if ($post) {
            $opts = http_build_query($opts);
        }
        if ($stream_context == null && $is_url_file) {
            $ssl_options = [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ];
            if ($ca_bundle_path) {
                $ssl_options['cafile'] = $ca_bundle_path;
            }
            $http_header = 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.3865.90 Safari/537.36';
            if ($access_token !== '') {
                $http_header .= "\r\n" . 'X-ETS-EM-Token: ' . $access_token;
            }
            $stream_context = stream_context_create([
                'http' => [
                    'method' => $post ? 'POST' : 'GET',
                    'timeout' => $curl_timeout,
                    'max_redirects' => 0,
                    'header' => $http_header,
                    'content' => $opts,
                ],
                'ssl' => $ssl_options,
            ]);
        }
        if (function_exists('curl_init')) {
            self::logCurl('INFO', 'Transport: cURL');
            $curl = curl_init();
            $http_protocol = defined('CURLPROTO_HTTP') ? CURLPROTO_HTTP : 1;
            $https_protocol = defined('CURLPROTO_HTTPS') ? CURLPROTO_HTTPS : 2;

            $curl_options = [
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => html_entity_decode($url),
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_USERAGENT => 'PostmanRuntime/7.49.0',
                CURLOPT_TIMEOUT => $is_connector ? max(300, (int) $curl_timeout) : max(60, (int) $curl_timeout),
                CURLOPT_CONNECTTIMEOUT => min(10, max(1, (int) $curl_timeout)),
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_POST => $post,
                CURLOPT_POSTFIELDS => $opts,
                CURLOPT_PROTOCOLS => $http_protocol | $https_protocol,
                CURLOPT_REDIR_PROTOCOLS => $http_protocol | $https_protocol,
            ];
            $curl_options[CURLOPT_SSL_VERIFYHOST] = 2;
            $curl_options[CURLOPT_SSL_VERIFYPEER] = true;
            if ($ca_bundle_path) {
                $curl_options[CURLOPT_CAINFO] = $ca_bundle_path;
            }
            if ($access_token !== '') {
                $curl_options[CURLOPT_HTTPHEADER] = [
                    'X-ETS-EM-Token: ' . $access_token,
                ];
            }

            if ($is_url_file) {
                if (!self::applyCurlHostResolve($curl_options, html_entity_decode($url))) {
                    return false;
                }
            }

            $response_headers = [];
            $curl_options[CURLOPT_HEADERFUNCTION] = function ($curl_handle, $header_line) use (&$response_headers) {
                $separator = strpos($header_line, ':');
                if ($separator !== false) {
                    $name = Tools::strtolower(trim(substr($header_line, 0, $separator)));
                    $response_headers[$name] = trim(substr($header_line, $separator + 1));
                }

                return strlen($header_line);
            };
            curl_setopt_array($curl, $curl_options);
            $content = curl_exec($curl);
            $http_code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $effective_url = (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
            $redirect_count = (int) curl_getinfo($curl, CURLINFO_REDIRECT_COUNT);
            $content_type = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
            $primary_ip = (string) curl_getinfo($curl, CURLINFO_PRIMARY_IP);
            if (curl_errno($curl)) {
                $err_code = curl_errno($curl);
                $err_msg = curl_error($curl);
                self::logCurl('ERROR', 'cURL failed (' . $err_code . '): ' . $err_msg);
                curl_close($curl);

                return false;
            }
            $request_host = Tools::strtolower((string) parse_url($url, PHP_URL_HOST));
            $allow_local_ip = EtsMigrateUrlSecurity::isLocalEnvironment()
                && EtsMigrateUrlSecurity::isLocalNetworkHost($request_host);
            if ($is_url_file && ($primary_ip === '' || EtsMigrateUrlSecurity::isMetadataOrProhibitedIp($primary_ip) || (!$allow_local_ip && EtsMigrateUrlSecurity::isBlockedIp($primary_ip)))) {
                self::logCurl('ERROR', 'Blocked or empty IP after connect' . ($primary_ip !== '' ? ': ' . $primary_ip : ''));
                curl_close($curl);

                return false;
            }
            $decoded_url = html_entity_decode($url);
            $response_level = $http_code >= 400 ? 'ERROR' : (stripos($content_type, 'text/html') !== false ? 'WARN' : 'OK');
            $response_lines = [
                '--- RESPONSE ---',
                'HTTP: ' . $http_code . ' | IP: ' . $primary_ip . ' | Redirects: ' . $redirect_count,
            ];
            if ($effective_url !== '' && $effective_url !== $decoded_url) {
                $response_lines[] = 'Effective URL: ' . self::redactSecrets($effective_url);
            }
            if ($content_type !== '') {
                $response_lines[] = 'Content-Type: ' . $content_type;
            }
            $response_lines[] = 'Size: ' . strlen((string) $content) . ' bytes';
            $response_lines[] = 'Body: ' . self::redactSecrets(substr((string) $content, 0, 1000));
            self::logCurl($response_level, implode("\n", $response_lines));
            if ($is_url_file) {
                if ($http_code >= 300 && $http_code <= 399 && !empty($response_headers['location'])) {
                    $redirect_url = self::resolveRedirectUrl($decoded_url, $response_headers['location']);
                    curl_close($curl);
                    if (
                        self::$redirectDepth >= 3
                        || !$redirect_url
                        || !self::isSecureRedirect($decoded_url, $redirect_url)
                        || !EtsMigrateUrlSecurity::isPublicHttpUrl($redirect_url)
                        || !EtsMigrateUrlSecurity::isAllowedDomain($redirect_url)
                    ) {
                        self::logCurl('ERROR', 'Redirect URL not allowed');

                        return false;
                    }
                    ++self::$redirectDepth;
                    $redirect_content = self::fetchRemoteContents($redirect_url, $use_include_path, null, $curl_timeout, $request_options);
                    --self::$redirectDepth;

                    return $redirect_content;
                }
                if ($http_code < 200 || $http_code > 299) {
                    curl_close($curl);

                    return false;
                }
                $request_host = (string) parse_url($decoded_url, PHP_URL_HOST);
                $effective_host = (string) parse_url($effective_url, PHP_URL_HOST);
                if ($redirect_count > 0 || ($effective_url !== '' && !EtsMigrateUrlSecurity::isSameHttpHost($effective_host, $request_host))) {
                    self::logCurl('ERROR', 'Redirect or host change detected');
                    curl_close($curl);

                    return false;
                }
                if ($effective_url !== '' && $effective_url !== $decoded_url) {
                    if (!EtsMigrateUrlSecurity::isPublicHttpUrl($effective_url) || !EtsMigrateUrlSecurity::isAllowedDomain($effective_url)) {
                        self::logCurl('ERROR', 'Effective URL not allowed: ' . self::redactSecrets($effective_url));
                        curl_close($curl);

                        return false;
                    }
                }
                if ($is_connector) {
                    $decoded_content = json_decode((string) $content, true);
                    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded_content)) {
                        self::logCurl('ERROR', 'Invalid JSON connector response');
                        curl_close($curl);

                        return false;
                    }
                }
            }
            curl_close($curl);

            return $content;
        } elseif (!$is_url_file) {
            self::logCurl('INFO', 'Transport: file_get_contents');
            $res = Tools::file_get_contents($url, $use_include_path, $stream_context);
            if ($res === false) {
                self::logCurl('ERROR', 'file_get_contents failed');
            } else {
                self::logCurl('OK', 'file_get_contents completed, size ' . strlen((string) $res) . ' bytes');
            }

            return $res;
        }
        self::logCurl('ERROR', 'cURL required for remote HTTP(S) requests');

        return false;
    }

    private static function resolveRedirectUrl($request_url, $location)
    {
        $location = trim(html_entity_decode((string) $location));
        if ($location === '') {
            return false;
        }
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($request_url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        if (strpos($location, '//') === 0) {
            return $parts['scheme'] . ':' . $location;
        }

        $authority = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $authority .= ':' . (int) $parts['port'];
        }
        if ($location[0] === '/') {
            return $authority . $location;
        }

        $path = $parts['path'] ?? '/';

        return $authority . rtrim(str_replace('\\', '/', dirname($path)), '/') . '/' . $location;
    }

    private static function isSecureRedirect($request_url, $redirect_url)
    {
        $request_scheme = Tools::strtolower((string) parse_url($request_url, PHP_URL_SCHEME));
        $redirect_scheme = Tools::strtolower((string) parse_url($redirect_url, PHP_URL_SCHEME));

        return $request_scheme !== 'https' || $redirect_scheme === 'https';
    }

    public static function logCurl($level, $message)
    {
        static $enabled = null;
        static $log_file = null;
        if ($enabled === null) {
            $enabled = (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_)
                || file_exists(_PS_ROOT_DIR_ . '/var/logs/ets_migrate_curl.debug');
        }
        if (!$enabled || trim($message) === '') {
            return;
        }
        if ($log_file === null) {
            $var_directory = _PS_ROOT_DIR_ . '/var/logs';
            if (!is_dir($var_directory) && !mkdir($var_directory, 0755, true)) {
                return;
            }
            if (!is_writable($var_directory)) {
                return;
            }
            $log_file = $var_directory . '/ets_migrate_curl.log';
        }
        file_put_contents($log_file, sprintf("[%s] %-5s %s\n", date('Y-m-d H:i:s'), strtoupper($level), trim($message)), FILE_APPEND);
    }
}
