<?php
if (!defined('_PS_VERSION_')) { exit; }

final class KafkaVariantImages
{
    private static ?array $missing = null;

    public static function protectMissingPhoto($product, Context $context): void
    {
        $combination = (int) ($product['id_product_attribute'] ?? 0);
        if (!$combination) { return; }
        if (self::$missing === null) {
            // A missing relation is harmless for sizes of a single-color product. Only multicolor
            // products need protection from PrestaShop's fallback to another color's cover.
            $prefix = _DB_PREFIX_;
            $colorGroup = 'SELECT DISTINCT ag.id_attribute_group FROM `' . $prefix . 'attribute_group` ag'
                . ' LEFT JOIN `' . $prefix . 'attribute_group_lang` agl ON agl.id_attribute_group=ag.id_attribute_group'
                . " WHERE ag.is_color_group=1 OR LOWER(agl.name) IN ('barva','color','colour')";
            $multi = 'SELECT pa.id_product FROM `' . $prefix . 'product_attribute` pa'
                . ' JOIN `' . $prefix . 'product_attribute_combination` pac ON pac.id_product_attribute=pa.id_product_attribute'
                . ' JOIN `' . $prefix . 'attribute` a ON a.id_attribute=pac.id_attribute'
                . ' WHERE a.id_attribute_group IN (' . $colorGroup . ') GROUP BY pa.id_product HAVING COUNT(DISTINCT a.id_attribute)>1';
            $rows = Db::getInstance()->executeS('SELECT pa.id_product_attribute FROM `' . $prefix . 'product_attribute` pa'
                . ' LEFT JOIN `' . $prefix . 'product_attribute_image` pai ON pai.id_product_attribute=pa.id_product_attribute'
                . ' WHERE pai.id_image IS NULL AND pa.id_product IN (' . $multi . ')');
            self::$missing = array_fill_keys(array_column($rows, 'id_product_attribute'), true);
        }
        if (!isset(self::$missing[$combination])) { return; }
        $retriever = new PrestaShop\PrestaShop\Adapter\Image\ImageRetriever($context->link);
        $placeholder = $retriever->getNoPictureImage($context->language);
        // The same neutral placeholder reaches product refreshes, listings, cart and checkout.
        // Never recolor a photograph or silently show another variant as the selected color.
        $product->offsetSet('images', [], true);
        $product->offsetSet('default_image', $placeholder, true);
        $product->offsetSet('cover', $placeholder, true);
    }
}
