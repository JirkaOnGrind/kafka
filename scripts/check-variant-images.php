<?php
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['HTTP_HOST'] = 'localhost:8083';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require '/var/www/html/config/config.inc.php';
if (Configuration::get('PS_SHOP_DOMAIN') !== 'localhost:8083' || _DB_SERVER_ !== 'target-db') { throw new RuntimeException('Isolated target only.'); }
$kernel = new AdminKernel('prod', false);
$kernel->boot();
$options = getopt('', ['map:']);
$map = json_decode(file_get_contents($options['map']), true, 512, JSON_THROW_ON_ERROR);
$context = Context::getContext();
$context->cart = new Cart(); // Unsaved guest context; the audit creates no cart/customer records.
$context->customer = new Customer();
$context->currency = new Currency((int) Configuration::get('PS_CURRENCY_DEFAULT'));
$context->country = new Country((int) Configuration::get('PS_COUNTRY_DEFAULT'));
$context->controller = new FrontController();
$factory = new ProductPresenterFactory($context);
$settings = $factory->getPresentationSettings();
$presenter = $factory->getPresenter();
$cartPresenter = new PrestaShop\PrestaShop\Adapter\Presenter\Cart\CartProductPresenter(
    new PrestaShop\PrestaShop\Adapter\Image\ImageRetriever($context->link),
    $context->link,
    new PrestaShop\PrestaShop\Adapter\Product\PriceFormatter(),
    new PrestaShop\PrestaShop\Adapter\Product\ProductColorsRetriever(),
    $context->getTranslator()
);
$assembler = new ProductAssembler($context);
$checked = 0;
$presented = 0;
$failures = [];
$samples = [];
foreach ($map['products'] as $product) {
    $id = (int) $product['id_product'];
    $rows = Db::getInstance()->executeS('SELECT pa.id_product_attribute,a.id_attribute FROM `' . _DB_PREFIX_ . 'product_attribute` pa'
        . ' JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac ON pac.id_product_attribute=pa.id_product_attribute'
        . ' JOIN `' . _DB_PREFIX_ . 'attribute` a ON a.id_attribute=pac.id_attribute'
        . ' WHERE pa.id_product=' . $id . ' AND a.id_attribute_group=' . (int) $map['color_group']);
    $colors = [];
    foreach ($rows as $row) {
        $combination = (int) $row['id_product_attribute'];
        $color = (int) $row['id_attribute'];
        $wanted = $product['colors'][$color];
        $actual = array_map('intval', array_column(Db::getInstance()->executeS('SELECT id_image FROM `' . _DB_PREFIX_ . 'product_attribute_image` WHERE id_product_attribute=' . $combination), 'id_image'));
        sort($wanted); sort($actual);
        if ($wanted !== $actual) { $failures[] = ['combination' => $combination, 'reason' => 'Association mismatch']; }
        ++$checked;
        if (isset($colors[$color])) { continue; }
        $colors[$color] = true;
        $raw = $assembler->assembleProduct(['id_product' => $id, 'id_product_attribute' => $combination]);
        foreach (['detail' => $presenter, 'cart' => $cartPresenter] as $surface => $native) {
            $view = $native->present($settings, $raw, $context->language);
            $imageId = (int) ($view['default_image']['id_image'] ?? 0);
            if ($wanted ? !in_array($imageId, $wanted, true) : ($imageId !== 0 || count($view['images']) !== 0)) {
                $failures[] = ['product' => $id, 'combination' => $combination, 'surface' => $surface, 'reason' => 'Wrong rendered image', 'image' => $imageId];
            }
            ++$presented;
        }
        $samples[] = ['product' => $id, 'combination' => $combination, 'color' => $color, 'images' => $wanted,
            'url' => $context->link->getProductLink($id, null, null, null, (int) $context->language->id, 1, $combination)];
    }
}
echo json_encode(['products' => count($map['products']), 'combinations_checked' => $checked, 'native_presentations_checked' => $presented,
    'failures' => $failures, 'samples' => $samples], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
exit($failures ? 1 : 0);
