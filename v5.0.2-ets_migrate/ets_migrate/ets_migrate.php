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
require_once dirname(__FILE__) . '/classes/EtsMigrateAutoload.php';

class Ets_migrate extends Module
{
    public function __construct()
    {
        $this->name = 'ets_migrate';
        $this->tab = 'front_office_features';
        $this->version = '5.0.2';
        $this->author = 'PrestaHero';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->module_key = '44fa773de0e2ee30ecce1925be276a02';
        parent::__construct();

        $this->displayName = $this->l('MigrationPro Ultimate');
        $this->description = $this->l('The perfect PrestaShop migration tool for data transfer to migrate your website from old versions to PrestaShop 9 or migrate data between any PrestaShop website (any version)');
$this->refs = 'https://prestahero.com/';
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        return EtsMigrateInstall::executeSQL($this, EtsMigrateInstall::_INSTALL_SQL_FILE)
            && parent::install()
            && EtsMigrateInstall::installAfterParent($this);
    }

    public function uninstall()
    {
        return EtsMigrateInstall::uninstallBeforeParent($this)
            && parent::uninstall();
    }

    public function getContent()
    {
        return EtsMigrateConfigForm::renderConfigurePage($this, $this->context);
    }

    public function assignTemplateVars($templateVars)
    {
        $this->smarty->assign($templateVars);
    }
}
