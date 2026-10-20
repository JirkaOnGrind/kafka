<?php
class KafkahomeInfoModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        $information = $this->module->informationLinks();
        $key = Tools::getValue('page');
        if (!is_string($key) || !isset($information[$key])) {
            Tools::redirect($this->context->link->getPageLink('pagenotfound'));
        }
        $pageInfo = $information[$key];
        if ($pageInfo['cms_url']) {
            Tools::redirect($pageInfo['cms_url']);
        }
        $page = $this->context->smarty->getTemplateVars('page');
        $page['meta']['title'] = $pageInfo['name'] . ' — Kafka Monster';
        $this->context->smarty->assign(['kafka_information_page' => $pageInfo, 'page' => $page]);
        $this->setTemplate('module:kafkahome/views/templates/front/info.tpl');
    }
}
