<?php
/** PS9 adds an empty query marker when the legacy route omits id_product_attribute.
 * Keep native combination query/anchor behavior; remove only a trailing empty marker.
 */
class Link extends LinkCore
{
    public function getProductLink(
        $product, $alias = null, $category = null, $ean13 = null, $idLang = null, $idShop = null,
        $idProductAttribute = null, $force_routes = false, $relativeProtocol = false,
        $withIdInAnchor = false, $extraParams = [], bool $addAnchor = true
    ) {
        $url = parent::getProductLink($product, $alias, $category, $ean13, $idLang, $idShop,
            $idProductAttribute, $force_routes, $relativeProtocol, $withIdInAnchor, $extraParams, $addAnchor);
        // Empty parameters may precede an anchor. Nonempty combination parameters must be preserved.
        return preg_replace('/\?(?=#|$)/', '', $url);
    }
}
