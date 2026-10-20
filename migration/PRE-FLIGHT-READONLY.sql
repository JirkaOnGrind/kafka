-- Kafka Monster: read-only checks for the SOURCE PrestaShop 1.6 DB.
-- Select the actual shop database in phpMyAdmin first. Do not use information_schema.
-- Prefix ejf6_ is verified in the supplied dump. No SQL is executed by this file.
-- These queries deliberately avoid passwords, tokens, contact data and payment secrets.

SELECT name, value FROM ejf6_configuration
WHERE name IN ('PS_VERSION_DB','PS_MULTISHOP_FEATURE_ACTIVE','PS_LANG_DEFAULT',
 'PS_CURRENCY_DEFAULT','PS_TAX','PS_ORDER_OUT_OF_STOCK','PS_ADVANCED_STOCK_MANAGEMENT',
 'PS_SHIPPING_FREE_PRICE','PS_SHIPPING_HANDLING','PS_ORDER_PROCESS_TYPE');

SELECT 'product' AS entity, COUNT(*) AS rows_count FROM ejf6_product
UNION ALL SELECT 'product_attribute', COUNT(*) FROM ejf6_product_attribute
UNION ALL SELECT 'category', COUNT(*) FROM ejf6_category
UNION ALL SELECT 'image', COUNT(*) FROM ejf6_image
UNION ALL SELECT 'customer', COUNT(*) FROM ejf6_customer
UNION ALL SELECT 'address', COUNT(*) FROM ejf6_address
UNION ALL SELECT 'orders', COUNT(*) FROM ejf6_orders
UNION ALL SELECT 'order_detail', COUNT(*) FROM ejf6_order_detail
UNION ALL SELECT 'order_invoice', COUNT(*) FROM ejf6_order_invoice
UNION ALL SELECT 'order_payment', COUNT(*) FROM ejf6_order_payment
UNION ALL SELECT 'stock_available', COUNT(*) FROM ejf6_stock_available
UNION ALL SELECT 'specific_price', COUNT(*) FROM ejf6_specific_price
UNION ALL SELECT 'cart_rule', COUNT(*) FROM ejf6_cart_rule
UNION ALL SELECT 'feature', COUNT(*) FROM ejf6_feature
UNION ALL SELECT 'product_attribute_image', COUNT(*) FROM ejf6_product_attribute_image;

SELECT active, COUNT(*) AS products FROM ejf6_product GROUP BY active;
SELECT is_guest, active, deleted, COUNT(*) AS customers
FROM ejf6_customer GROUP BY is_guest, active, deleted;

SELECT m.id_module, m.name, m.version, m.active,
 EXISTS(SELECT 1 FROM ejf6_module_shop ms
        WHERE ms.id_module=m.id_module AND ms.id_shop=1) AS assigned_to_shop
FROM ejf6_module m ORDER BY m.name;

SELECT id_lang, iso_code, active FROM ejf6_lang;
SELECT id_currency, iso_code, active, deleted, conversion_rate FROM ejf6_currency;
SELECT module, id_currency, current_state, COUNT(*) AS orders,
 MIN(date_add) AS earliest, MAX(date_add) AS latest
FROM ejf6_orders GROUP BY module, id_currency, current_state;

-- Counts are comparable even if source catalog changes.
SELECT COUNT(*) AS orphan_combinations
FROM ejf6_product_attribute pa LEFT JOIN ejf6_product p ON p.id_product=pa.id_product
WHERE p.id_product IS NULL;
SELECT COUNT(*) AS orphan_stock_products
FROM ejf6_stock_available s LEFT JOIN ejf6_product p ON p.id_product=s.id_product
WHERE p.id_product IS NULL;
SELECT COUNT(*) AS orphan_stock_combinations
FROM ejf6_stock_available s LEFT JOIN ejf6_product_attribute pa
 ON pa.id_product_attribute=s.id_product_attribute
WHERE s.id_product_attribute<>0 AND pa.id_product_attribute IS NULL;
SELECT COUNT(*) AS order_details_with_deleted_product
FROM ejf6_order_detail od LEFT JOIN ejf6_product p ON p.id_product=od.product_id
WHERE p.id_product IS NULL;
SELECT COUNT(*) AS orders_without_customer
FROM ejf6_orders o LEFT JOIN ejf6_customer c ON c.id_customer=o.id_customer
WHERE c.id_customer IS NULL;

SELECT id_product, id_product_attribute, quantity, out_of_stock, depends_on_stock
FROM ejf6_stock_available WHERE id_product IN (3415,3454,3501)
ORDER BY id_product,id_product_attribute;

SELECT c.id_carrier,c.id_reference,c.name,c.shipping_handling,c.shipping_method,
 c.is_module,c.external_module_name,d.id_zone,d.price,
 rp.delimiter1 AS price_from,rp.delimiter2 AS price_to
FROM ejf6_carrier c JOIN ejf6_delivery d ON d.id_carrier=c.id_carrier
LEFT JOIN ejf6_range_price rp ON rp.id_range_price=d.id_range_price
WHERE c.active=1 AND c.deleted=0 ORDER BY c.id_carrier,d.id_zone,price_from;

-- A protected source archive is needed for module-specific history.
SELECT 'packetery_order' AS entity,COUNT(*) AS rows_count FROM ejf6_packetery_order
UNION ALL SELECT 'dm_comgate_payment',COUNT(*) FROM ejf6_dm_comgate_payment
UNION ALL SELECT 'shaim_dpdparcelshop_data',COUNT(*) FROM ejf6_shaim_dpdparcelshop_data;
