<?php
if (!defined('_PS_VERSION_')) { exit; }

/** Migration compatibility only. Catalog records and native cart controllers stay untouched. */
class Kafkaseo extends Module
{
    public function __construct()
    {
        $this->name = 'kafkaseo';
        $this->version = '1.0.0';
        $this->author = 'Kafka Monster';
        $this->tab = 'seo';
        $this->ps_versions_compliancy = ['min' => '9.1.0', 'max' => '9.1.99'];
        parent::__construct();
        $this->displayName = 'Kafka: SEO migration compatibility';
        $this->description = 'Legacy product aliases, pagination and rendered title formatting.';
    }

    public function install()
    {
        return parent::install() && $this->registerHook('moduleRoutes')
            && $this->registerHook('actionFrontControllerInitBefore')
            && $this->registerHook('actionFrontControllerSetVariables');
    }

    public function hookModuleRoutes($params): array
    {
        // Accept the temporary PS9 flat URLs too. ProductController performs the single native 301.
        // The primary rule (including optional EAN) remains a managed native configuration value.
        return ['kafka-flat-product' => [
            'controller' => 'product',
            'rule' => '{id}{-:id_product_attribute}-{rewrite}.html',
            'keywords' => [
                'id' => ['regexp' => '[0-9]+', 'param' => 'id_product'],
                'id_product_attribute' => ['regexp' => '[0-9]*+', 'param' => 'id_product_attribute'],
                'rewrite' => ['regexp' => Dispatcher::REWRITE_PATTERN, 'param' => 'rewrite'],
            ],
        ]];
    }

    public function hookActionFrontControllerInitBefore($params): void
    {
        // Redirect only read-only listing requests. Never intercept cart, AJAX, language selection or POST.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || Tools::getValue('ajax')) { return; }
        $controller = $params['controller']->php_self;
        parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY) ?? '', $query);
        if (in_array(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), ['/', '/index.php'], true)
            && isset($query['id_lang']) && is_scalar($query['id_lang'])) {
            // PS9's dispatcher assigns the default ISO to an unprefixed legacy query URL.
            // Restore its explicit active language before native controllers build the one-hop canonical.
            $language = new Language((int) $query['id_lang']);
            if (Validate::isLoadedObject($language) && $language->active && $language->isAssociatedToShop()) {
                $this->context->language = $language;
                $this->context->cookie->id_lang = (int) $language->id;
                $_GET['id_lang'] = (int) $language->id;
                $_GET['isolang'] = $language->iso_code;
            }
        }
        if ($controller === 'product') {
            parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY) ?? '', $query);
            // The broad legacy product rule also recognizes flat PS9 paths. Recover a path combination only
            // if it really belongs to this product; an explicit query selection always takes precedence.
            if (!array_key_exists('id_product_attribute', $query)
                && preg_match('#^/(?:[a-z]{2}/)?([0-9]+)-([0-9]+)-[^/]+\.html$#u', rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)), $matches)
                && (int) Tools::getValue('id_product') === (int) $matches[1]
                && Db::getInstance()->getValue('SELECT 1 FROM `' . _DB_PREFIX_ . 'product_attribute` WHERE id_product=' . (int) $matches[1]
                    . ' AND id_product_attribute=' . (int) $matches[2])) {
                $_GET['id_product_attribute'] = (int) $matches[2];
            }
            return;
        }
        if ($controller === 'index' && parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/') {
            // This is a visitor language choice, not a permanent move of the root URL.
            // Dispatcher already sets id_lang from its default prefix. Read an explicit legacy choice from the original query.
            parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY) ?? '', $query);
            $choice = $query['id_lang'] ?? $this->context->language->id;
            $idLang = is_scalar($choice) ? (int) $choice : (int) Configuration::get('PS_LANG_DEFAULT');
            $language = new Language($idLang);
            if (!Validate::isLoadedObject($language) || !$language->active) { $idLang = (int) Configuration::get('PS_LANG_DEFAULT'); }
            unset($query['id_lang'], $query['isolang'], $query['controller']);
            $url = $this->context->link->getPageLink('index', null, $idLang, $query);
            header('Cache-Control: private, no-store');
            header('Vary: Cookie, Accept-Language');
            header('Location: ' . $url, true, 302);
            exit;
        }
        if (!in_array($controller, ['category', 'new-products', 'best-sales', 'prices-drop', 'manufacturer', 'supplier', 'search'], true)) { return; }
        parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY) ?? '', $query);
        if (!isset($query['p']) || !is_string($query['p']) || !ctype_digit($query['p']) || (int) $query['p'] < 1) { return; }
        $page = $query['page'] ?? $query['p']; // Explicit native page wins if both are present.
        if (!is_string($page) || !ctype_digit($page) || (int) $page < 1) { return; }
        unset($query['p'], $query['page']);
        if ((int) $page > 1) { $query['page'] = (int) $page; }
        $idLang = (int) Tools::getValue('id_lang', Configuration::get('PS_LANG_DEFAULT'));
        $link = $this->context->link;
        if ($controller === 'category') {
            $category = new Category((int) Tools::getValue('id_category'), $idLang);
            if (!Validate::isLoadedObject($category) || !$category->active) { return; }
            $url = $link->getCategoryLink($category, null, $idLang);
        } elseif ($controller === 'manufacturer' && Tools::getValue('id_manufacturer')) {
            $url = $link->getManufacturerLink((int) Tools::getValue('id_manufacturer'), null, $idLang);
        } elseif ($controller === 'supplier' && Tools::getValue('id_supplier')) {
            $url = $link->getSupplierLink((int) Tools::getValue('id_supplier'), null, $idLang);
        } else {
            $url = $link->getPageLink($controller, null, $idLang);
        }
        // Routing fields have been incorporated into the native target. Preserve other query parameters.
        foreach (['controller', 'id_lang', 'isolang', 'id_category', 'id_manufacturer', 'id_supplier'] as $key) { unset($query[$key]); }
        if ($query) { $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query, '', '&', PHP_QUERY_RFC3986); }
        header('Cache-Control: no-cache');
        header('Location: ' . $url, true, 301);
        exit;
    }

    public function hookActionFrontControllerSetVariables($params): void
    {
        $vars = &$params['templateVars'];
        if (($vars['page']['page_name'] ?? '') === 'cart') {
            $vars['page']['meta']['robots'] = 'noindex, nofollow';
        }
        $title = trim((string) ($vars['page']['meta']['title'] ?? ''));
        // Keep custom metadata verbatim. Format the rendered title once, shared by title, OG and JSON-LD.
        if (!preg_match('/kafka\s+monster/iu', $title)) {
            $vars['page']['meta']['title'] = $title === '' ? 'Kafka Monster' : $title . ' - Kafka Monster';
        }
    }
}
