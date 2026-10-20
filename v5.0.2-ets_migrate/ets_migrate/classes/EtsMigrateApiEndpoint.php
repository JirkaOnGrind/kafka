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

class EtsMigrateApiEndpoint
{
    protected static $_INSTANCE;
    private $request_api;
    private $api_link;
    private $domainConfigHash;

    public function __construct()
    {
        $this->init();
    }

    private function init()
    {
        $this->api_link = '/modules/ets_migrate_connector/server.php';
        $this->request_api = '';
        $domain = trim((string) Configuration::getGlobalValue('ETS_EM_DOMAIN'));
        $this->domainConfigHash = md5($domain);
        if ($domain === '') {
            return;
        }
        $requestApi = rtrim($domain, '/') . $this->api_link;
        if (
            !EtsMigrateUrlSecurity::isPublicHttpUrl($requestApi)
            || !EtsMigrateUrlSecurity::isAllowedDomain($requestApi)
        ) {
            return;
        }
        $this->request_api = $requestApi;
    }

    public static function getInstance()
    {
        if (!static::$_INSTANCE) {
            static::$_INSTANCE = new self();
        }

        return static::$_INSTANCE;
    }

    public function getRequestApi()
    {
        $domainConfigHash = md5(trim((string) Configuration::getGlobalValue('ETS_EM_DOMAIN')));
        if ($this->domainConfigHash !== $domainConfigHash) {
            $this->init();
        }

        return $this->request_api;
    }
}
