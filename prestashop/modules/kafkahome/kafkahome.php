<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class Kafkahome extends Module
{
    private ?array $categoryRows = null;

    public function __construct()
    {
        $this->name = 'kafkahome';
        $this->version = '1.0.2';
        $this->author = 'Kafka Monster';
        $this->tab = 'front_office_features';
        $this->bootstrap = true;
        $this->controllers = ['section', 'info'];
        $this->ps_versions_compliancy = ['min' => '9.1.0', 'max' => '9.1.99'];
        parent::__construct();
        $this->displayName = 'Kafka: vlastní kategoriální vstupy';
        $this->description = 'Ikonové karty a navigace podle schválené předlohy.';
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('displayKafkaCategories')
            && $this->registerHook('displayKafkaNavigation')
            && $this->registerHook('displayKafkaTrust')
            && $this->registerHook('displayKafkaFooter')
            && $this->registerHook('displayKafkaCustomProduction')
            && $this->registerHook('actionPresentProduct')
            && $this->registerHook('actionPresentProductListing')
            && $this->registerHook('actionPresentCartProduct')
            && $this->registerHook('actionFrontControllerInitBefore')
            && $this->registerHook('additionalCustomerFormFields')
            && $this->registerHook('additionalCustomerAddressFields');
    }

    public function hookAdditionalCustomerFormFields($params): array
    {
        // Remove from the native formatter, not just from the visible template.
        // Existing customer values remain intact when updating an account.
        unset($params['fields']['id_gender'], $params['fields']['optin']);
        if (isset($params['fields']['birthday'])) {
            $params['fields']['birthday']->setRequired(false)
                ->addAvailableValue('date_format', $this->context->language->date_format_lite);
        }
        return [];
    }

    public function hookAdditionalCustomerAddressFields($params): array
    {
        $fields = &$params['fields'];
        if (isset($fields['phone_mobile'])) {
            if (!isset($fields['phone'])) {
                $fields['phone'] = (new FormField())->setName('phone')->setType('tel')
                    ->setLabel($this->trans('Phone', [], 'Shop.Forms.Labels'));
            }
            $fields['phone']->setRequired($fields['phone']->isRequired() || $fields['phone_mobile']->isRequired());
            // Preserve imported secondary numbers while asking for only one contact number.
            $fields['phone_mobile']->setType('hidden')->setRequired(false);
        }
        if (isset($fields['phone'])) {
            $fields['phone']->setType('tel')->setAutocompleteAttribute('tel');
        }
        return [];
    }

    public function hookActionPresentProduct($params): void
    {
        require_once __DIR__ . '/classes/VariantImages.php';
        KafkaVariantImages::protectMissingPhoto($params['presentedProduct'], $this->context);
    }

    public function hookActionPresentProductListing($params): void
    {
        $this->hookActionPresentProduct($params);
    }

    public function hookActionPresentCartProduct($params): void
    {
        $this->hookActionPresentProduct($params);
    }

    public function hookActionFrontControllerInitBefore($params): void
    {
        // Product Comments 8.0.1 keeps gaps in array_unique() keys, then uses those keys to place SQL
        // commas. Cross-listed/related product cards can submit duplicate IDs and trigger HTTP 500.
        // Normalize this one read-only endpoint; leave the vendor module and review records intact.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET'
            || !is_a($params['controller'] ?? null, 'ProductCommentsCommentGradeModuleFrontController')) { return; }
        $ids = Tools::getValue('id_products');
        if (is_array($ids)) { $_GET['id_products'] = array_values(array_unique(array_map('intval', $ids))); }
    }

    public function definitions(): array
    {
        return json_decode(file_get_contents(__DIR__ . '/data/sections.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    private function catalogCategories(): array
    {
        if ($this->categoryRows === null) {
            $this->categoryRows = Db::getInstance()->executeS(
                'SELECT c.id_category, c.id_parent, cl.name FROM `' . _DB_PREFIX_ . 'category` c'
                . ' INNER JOIN `' . _DB_PREFIX_ . 'category_shop` cs ON cs.id_category=c.id_category'
                . ' INNER JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.id_category=c.id_category'
                . ' AND cl.id_shop=cs.id_shop'
                . ' WHERE c.active=1 AND cs.id_shop=' . (int) $this->context->shop->id
                . ' AND cl.id_lang IN (' . (int) $this->context->language->id . ',' . (int) Language::getIdByIso('cs') . ')'
                . ' ORDER BY cs.position, c.id_category, (cl.id_lang=' . (int) $this->context->language->id . ') DESC'
            );
        }
        return $this->categoryRows;
    }

    private function categoryMatch(array $aliases, int $parent): ?array
    {
        foreach ($aliases as $alias) {
            $key = Tools::str2url($alias);
            foreach ($this->catalogCategories() as $row) {
                if ((int) $row['id_parent'] !== $parent || Tools::str2url($row['name']) !== $key) {
                    continue;
                }
                $category = new Category((int) $row['id_category'], (int) $this->context->language->id);
                if ($category->checkAccess((int) $this->context->customer->id)) {
                    return ['id' => (int) $category->id, 'url' => $this->context->link->getCategoryLink($category)];
                }
            }
        }
        return null;
    }

    public function sections(): array
    {
        $sections = [];
        $currentCategory = $this->context->controller->php_self === 'category' ? (int) Tools::getValue('id_category') : 0;
        $parents = array_column($this->catalogCategories(), 'id_parent', 'id_category');
        $activePath = [];
        for ($id = $currentCategory; $id > 0 && !isset($activePath[$id]); $id = (int) ($parents[$id] ?? 0)) {
            $activePath[$id] = true;
        }
        foreach ($this->definitions() as $definition) {
            $section = $this->resolveNode($definition, (int) $this->context->shop->id_category, $definition['key'], '');
            $section['landing_url'] = $this->context->link->getModuleLink($this->name, 'section', ['section' => $section['key']]);
            $section['is_active'] = isset($activePath[$section['category_id'] ?? 0]);
            $section['is_current'] = $currentCategory > 0 && $currentCategory === $section['category_id'];
            $sections[] = $section;
        }
        return $sections;
    }

    private function resolveNode(array $node, int $parent, string $section, string $path): array
    {
        $match = $parent > 0 ? $this->categoryMatch(array_merge([$node['name']], $node['aliases']), $parent) : null;
        $node['key'] = $path ?: $section;
        $node['category_url'] = $match['url'] ?? null;
        $node['category_id'] = $match['id'] ?? null;
        $node['url'] = $node['category_url'] ?? $this->context->link->getModuleLink(
            $this->name, 'section', array_filter(['section' => $section, 'item' => $path])
        );
        foreach ($node['items'] as &$child) {
            $childPath = ($path ? $path . '/' : '') . Tools::str2url($child['name']);
            $child = $this->resolveNode($child, $match['id'] ?? 0, $section, $childPath);
        }
        unset($child);
        // Keep the approved labels/order, then complete every branch from the live catalog.
        // The same resolved tree feeds homepage cards, mobile navigation and section routes.
        if ($match) {
            $seen = array_fill_keys(array_filter(array_column($node['items'], 'category_id')), true);
            foreach ($this->catalogCategories() as $row) {
                $id = (int) $row['id_category'];
                if ((int) $row['id_parent'] !== $match['id'] || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $category = new Category($id, (int) $this->context->language->id);
                if (!$category->checkAccess((int) $this->context->customer->id)) {
                    continue;
                }
                $childPath = ($path ? $path . '/' : '') . Tools::str2url($row['name']);
                $node['items'][] = $this->resolveNode(
                    ['name' => $row['name'], 'aliases' => [], 'items' => []],
                    $match['id'], $section, $childPath
                );
            }
        }
        return $node;
    }

    private function renderSections(string $template): string
    {
        $this->context->smarty->assign('kafka_sections', $this->sections());
        return $this->fetch('module:kafkahome/views/templates/hook/' . $template);
    }

    public function hookDisplayKafkaCategories($params)
    {
        $this->context->smarty->assign('kafka_partners', json_decode(
            file_get_contents(__DIR__ . '/data/partners.json'), true, 512, JSON_THROW_ON_ERROR
        ));
        return $this->renderSections('categories.tpl')
            . $this->fetch('module:kafkahome/views/templates/hook/partners.tpl');
    }

    public function hookDisplayKafkaNavigation($params)
    {
        return $this->renderSections('navigation.tpl');
    }

    public function hookDisplayKafkaCustomProduction($params)
    {
        require_once __DIR__ . '/classes/CustomProduction.php';
        return (new KafkaCustomProduction($this))->render((int) ($params['id_category'] ?? 0));
    }

    public function hookDisplayKafkaTrust($params)
    {
        return $this->fetch('module:kafkahome/views/templates/hook/trust.tpl');
    }

    public function informationLinks(): array
    {
        $definitions = [
            'shipping' => ['name' => 'Doprava a platba', 'aliases' => ['Doprava a platba', 'Dodání']],
            'returns' => ['name' => 'Vrácení a reklamace', 'aliases' => ['Vrácení a reklamace', 'Reklamace']],
            'terms' => ['name' => 'Obchodní podmínky', 'aliases' => ['Obchodní podmínky']],
            'about' => ['name' => 'O Kafka Monster', 'aliases' => ['O Kafka Monster', 'O nás']],
            'faq' => ['name' => 'Časté dotazy', 'aliases' => ['Časté dotazy', 'FAQ']],
        ];
        $pages = Db::getInstance()->executeS(
            'SELECT c.id_cms, cl.meta_title FROM `' . _DB_PREFIX_ . 'cms` c'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'cms_shop` cs ON cs.id_cms=c.id_cms'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'cms_lang` cl ON cl.id_cms=c.id_cms AND cl.id_shop=cs.id_shop'
            . ' WHERE c.active=1 AND cs.id_shop=' . (int) $this->context->shop->id
            . ' AND cl.id_lang IN (' . (int) $this->context->language->id . ',' . (int) Language::getIdByIso('cs') . ')'
            . ' ORDER BY c.id_cms'
        );
        foreach ($definitions as $key => &$definition) {
            $definition['key'] = $key;
            $definition['cms_url'] = null;
            foreach ($pages as $page) {
                foreach ($definition['aliases'] as $alias) {
                    if (Tools::str2url($page['meta_title']) === Tools::str2url($alias)) {
                        $definition['cms_url'] = $this->context->link->getCMSLink((int) $page['id_cms']);
                        break 2;
                    }
                }
            }
            $definition['url'] = $definition['cms_url'] ?? $this->context->link->getModuleLink($this->name, 'info', ['page' => $key]);
        }
        unset($definition);
        return $definitions;
    }

    public function hookDisplayKafkaFooter($params)
    {
        $this->context->smarty->assign('kafka_information', $this->informationLinks());
        return $this->fetch('module:kafkahome/views/templates/hook/footer.tpl');
    }
}
