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

class AdminETSEMMigrateController extends ModuleAdminController
{
    /**
     * @var Ets_migrate
     */
    public $module;
    public $migrateLockHeld = false;

    /**
     * @var EtsMigrateWorkflow|null
     */
    protected $workflow;

    protected $ajaxAccessAsserted = false;

    public function postProcess()
    {
        try {
            return parent::postProcess();
        } catch (Throwable $exception) {
            if ($this->ajax) {
                EtsMigrateDataImport::writeLog('AdminETSEMMigrateController: ' . $exception->getMessage(), 'ERROR');
                $this->sendJsonAndExit([
                    'error' => $this->module->l('An unexpected error occurred. Please try again.', 'AdminETSEMMigrateController'),
                ]);
            }
            throw $exception;
        }
    }

    public function displayAjax()
    {
        $payload = [];
        if (count($this->errors)) {
            $payload['errors'] = Tools::nl2br(implode(PHP_EOL, array_unique($this->errors)));
        }
        if (count($this->warnings)) {
            $payload['warning'] = Tools::nl2br(implode(PHP_EOL, array_unique($this->warnings)));
        }
        $this->sendJsonAndExit($payload ?: [
            'error' => $this->module->l('Invalid AJAX action.', 'AdminETSEMMigrateController'),
        ]);
    }

    public function ajaxProcessSettingForm()
    {
        $this->assertAjaxAccess();
        $this->sendJsonAndExit([
            'form' => EtsMigrateConfigForm::renderForm($this->module, $this->context, 'speed'),
        ]);
    }

    public function ajaxProcessNewMigrate()
    {
        $this->assertAjaxAccess();
        $this->ensureMultiStatementsReady(false);
        if (!EtsMigrateDataImport::cleanAll($this->context)) {
            $this->sendJsonAndExit([
                'error' => $this->module->l('Could not reset migration data. Please try again.', 'AdminETSEMMigrateController'),
            ]);
        }
        $this->sendJsonAndExit([
            'new_migrate' => 1,
        ]);
    }

    public function ajaxProcessFinalizeMigration()
    {
        $this->assertAjaxAccess();
        $this->sendJsonAndExit($this->getWorkflow()->finalizeMigration());
    }

    public function ajaxProcessMigrate()
    {
        $this->assertAjaxAccess();
        $workflow = $this->getWorkflow();
        $step = (int) Tools::getValue('current_step');
        $newSetting = (int) Tools::getValue('new_setting', 0);
        $migrateResume = (int) Tools::getValue('migrate_resume');
        $needsMultiStatements = $step === 3 || $newSetting > 0 || $migrateResume === 1;
        $this->ensureMultiStatementsReady($needsMultiStatements);
        if ($newSetting > 0) {
            $fieldErrors = [];
            $postErrors = EtsMigrateConfigForm::saveConfiguration($this->module, $this->context, $fieldErrors);
            if (count($postErrors)) {
                $payload = [
                    'errors' => Tools::nl2br(implode(PHP_EOL, $postErrors)),
                ];
                if (count($fieldErrors)) {
                    $payload['field_errors'] = $fieldErrors;
                }
                $this->sendJsonAndExit($payload);
            }

            return $this->ajaxProcessMigrationQueue();
        }
        if ($migrateResume === 1) {
            if ($step === 3) {
                $jsonData = $workflow->processConnectorQueue();
                $this->migrateLockHeld = $workflow->migrateLockHeld;
                $this->sendJsonAndExit($jsonData);
            } else {
                $this->sendJsonAndExit($workflow->resumeMigration($step));
            }
        }
        if ($step < 3) {
            $fieldErrors = [];
            $postErrors = EtsMigrateConfigForm::saveConfiguration($this->module, $this->context, $fieldErrors);
            if (count($postErrors)) {
                $payload = [
                    'errors' => Tools::nl2br(implode(PHP_EOL, $postErrors)),
                ];
                if (count($fieldErrors)) {
                    $payload['field_errors'] = $fieldErrors;
                }
                $this->sendJsonAndExit($payload);
            }
        }
        switch ($step) {
            case '1':
                $this->sendJsonAndExit($workflow->guardOrStartSourceConnection());
                break;
            case '2':
                $jsonData = [
                    'ok' => 1,
                ];
                if (!Tools::getValue('migrate_option')) {
                    $jsonData += EtsMigrateConfigForm::renderMigrateTemplates($this->module, $this->context);
                }
                $this->sendJsonAndExit($jsonData);
                break;
            case '3':
                $this->ajaxProcessMigrationQueue();
                break;
            default:
                $this->sendJsonAndExit([
                    'error' => $this->module->l('Invalid migration step.', 'AdminETSEMMigrateController'),
                ]);
                break;
        }
    }

    public function ajaxProcessMigrationQueue()
    {
        $this->assertAjaxAccess();
        $this->ensureMultiStatementsReady(true);
        $workflow = $this->getWorkflow();
        $jsonData = $workflow->processConnectorQueue();
        $this->migrateLockHeld = $workflow->migrateLockHeld;
        $this->sendJsonAndExit($jsonData);
    }

    public function sendJsonAndExit($jsonData)
    {
        EtsMigrateDataImport::restoreMappedShopContext($this->context);
        if ($this->migrateLockHeld) {
            if (!$this->releaseMigrateLock()) {
                if (!is_array($jsonData)) {
                    $jsonData = [];
                }
                $jsonData['warning'] = $this->module->l('Could not release migration lock.', 'AdminETSEMMigrateController');
            }
            $this->migrateLockHeld = false;
        }
        if (is_array($jsonData)) {
            if (isset($jsonData['error']) && is_string($jsonData['error'])) {
                $jsonData['error'] = Tools::htmlentitiesUTF8(strip_tags($jsonData['error']));
            }
            if (isset($jsonData['errors']) && is_string($jsonData['errors'])) {
                $jsonData['errors'] = Tools::htmlentitiesUTF8(strip_tags($jsonData['errors']));
            }
            if (isset($jsonData['warning']) && is_string($jsonData['warning'])) {
                $jsonData['warning'] = Tools::htmlentitiesUTF8(strip_tags($jsonData['warning']));
            }
            if (isset($jsonData['field_errors']) && is_array($jsonData['field_errors'])) {
                foreach ($jsonData['field_errors'] as $fieldName => $fieldMessage) {
                    if (!is_string($fieldMessage)) {
                        unset($jsonData['field_errors'][$fieldName]);
                        continue;
                    }
                    $jsonData['field_errors'][$fieldName] = Tools::htmlentitiesUTF8(strip_tags($fieldMessage));
                }
            }
        }
        $flags = 0;
        if (defined('JSON_HEX_TAG')) {
            $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        }
        exit(json_encode($jsonData, $flags));
    }

    protected function getWorkflow()
    {
        if (!$this->workflow instanceof EtsMigrateWorkflow) {
            $this->workflow = new EtsMigrateWorkflow($this->module, $this, $this->context);
        }

        return $this->workflow;
    }

    protected function releaseMigrateLock()
    {
        $lockKey = 'ets_em_migrate_queue';
        $released = Db::getInstance()->getValue("SELECT RELEASE_LOCK('" . pSQL($lockKey) . "')", false);
        if ($released === null || $released === false || (int) $released === 0) {
            return false;
        }
        if ($this->workflow instanceof EtsMigrateWorkflow) {
            $this->workflow->migrateLockHeld = false;
        }

        return true;
    }

    protected function ensureMultiStatementsReady($waitForRuntime)
    {
        if (!defined('_PS_ALLOW_MULTI_STATEMENTS_QUERIES_')) {
            return;
        }
        if (!EtsMigrateMultiStatementConfig::setMultiStatementsStatus(true)) {
            $this->sendJsonAndExit([
                'error' => $this->module->l('Could not update database settings. Check file permissions.', 'AdminETSEMMigrateController'),
            ]);
        }
        if ($waitForRuntime && !EtsMigrateMultiStatementConfig::isEnabledInRuntime()) {
            $this->sendJsonAndExit([
                'ok' => 1,
                'adaptive_retry' => 1,
            ]);
        }
    }

    protected function assertAjaxAccess()
    {
        if ($this->ajaxAccessAsserted) {
            return;
        }
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJsonAndExit([
                'error' => $this->module->l('Invalid request method.', 'AdminETSEMMigrateController'),
            ]);
        }
        if (!Module::isEnabled('ets_migrate')) {
            $this->sendJsonAndExit([
                'error' => $this->module->l('The migration module is disabled.', 'AdminETSEMMigrateController'),
            ]);
        }
        if (!EtsMigrateConfigForm::canConfigureModule($this->module)) {
            $this->sendJsonAndExit([
                'error' => $this->module->l('Access denied.', 'AdminETSEMMigrateController'),
            ]);
        }
        $this->ajaxAccessAsserted = true;
    }
}
