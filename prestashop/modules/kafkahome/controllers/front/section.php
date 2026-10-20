<?php
class KafkahomeSectionModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        $section = null;
        foreach ($this->module->sections() as $candidate) {
            if ($candidate['key'] === Tools::getValue('section')) {
                $section = $candidate;
                break;
            }
        }
        if ($section === null) {
            Tools::redirect($this->context->link->getPageLink('pagenotfound'));
        }
        $itemKey = Tools::getValue('item');
        if ($itemKey) {
            $item = null;
            $pending = $section['items'];
            while ($pending) {
                $candidate = array_shift($pending);
                if ($candidate['key'] === $itemKey) {
                    $item = $candidate;
                    break;
                }
                $pending = array_merge($pending, $candidate['items']);
            }
            if ($item === null) {
                Tools::redirect($this->context->link->getPageLink('pagenotfound'));
            }
            if ($item['category_url']) {
                Tools::redirect($item['category_url']);
            }
            $section['name'] = $item['name'];
            $section['items'] = $item['items'];
        } elseif ($section['kind'] !== 'information' && $section['url'] !== $section['landing_url']) {
            Tools::redirect($section['url']);
        }
        $page = $this->context->smarty->getTemplateVars('page');
        $page['meta']['title'] = $section['name'] . ' — Kafka Monster';
        $this->context->smarty->assign(['kafka_section' => $section, 'page' => $page]);
        $this->setTemplate('module:kafkahome/views/templates/front/section.tpl');
    }
}
