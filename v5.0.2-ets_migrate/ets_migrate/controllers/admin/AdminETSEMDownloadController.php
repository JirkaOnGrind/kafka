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

class AdminETSEMDownloadController extends ModuleAdminController
{
    /**
     * @var Ets_migrate
     */
    public $module;

    protected $allowedFiles = [
        'ets_migrate_connector.zip' => 'plugins',
        'ets_passwordkeeper.zip' => 'plugins',
        'readme_de.pdf' => 'docs',
        'readme_en.pdf' => 'docs',
        'readme_es.pdf' => 'docs',
        'readme_fr.pdf' => 'docs',
        'readme_it.pdf' => 'docs',
        'readme_pl.pdf' => 'docs',
    ];

    public function initProcess()
    {
        parent::initProcess();
        if (Tools::getValue('file') !== false && Tools::getValue('file') !== '') {
            $this->action = 'download';
        }
    }

    public function postProcess()
    {
        parent::postProcess();
        exit;
    }

    public function processDownload()
    {
        if (!Module::isEnabled('ets_migrate')) {
            $this->abortDownload('disabled');
        }
        if (!EtsMigrateConfigForm::canConfigureModule($this->module)) {
            $this->abortDownload('access_denied');
        }

        $fileName = Tools::getValue('file');
        $filePath = EtsMigrateDownloadResolver::resolveFilePath($this->module->getLocalPath(), $fileName, $this->allowedFiles);
        if (!$filePath) {
            $this->abortDownload('invalid_file');
        }

        $fileSize = filesize($filePath);
        if (!$fileSize || !is_readable($filePath)) {
            $this->abortDownload('file_not_found');
        }

        $fileName = basename($filePath);
        if (ob_get_level() && ob_get_length() > 0) {
            ob_end_clean();
        }

        header('Content-Transfer-Encoding: binary');
        header('Content-Type: ' . EtsMigrateDownloadResolver::resolveMimeType($fileName));
        header('Content-Length: ' . (int) $fileSize);
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        readfile($filePath);
        exit;
    }

    protected function abortDownload($errorCode)
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true, [], [
            'configure' => $this->module->name,
            'tab_module' => $this->module->tab,
            'module_name' => $this->module->name,
            'download_error' => (string) $errorCode,
        ]));
    }
}
