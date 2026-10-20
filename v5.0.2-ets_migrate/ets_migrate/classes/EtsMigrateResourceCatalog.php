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

class EtsMigrateResourceCatalog
{
    public static $resource;

    private static $resourceSorted;

    public static function getResources($group = null, $sort = false)
    {
        if (!self::$resource) {
            self::$resource = array_merge([
                'minor_data' => [
                    'name' => self::l('Minor data'),
                    'tables' => [
                        'shop_group',
                        'shop',
                        'lang',
                        'lang_shop',
                        'currency',
                        'currency_lang',
                        'currency_shop',
                        'zone',
                        'zone_shop',
                        'country',
                        'country_lang',
                        'country_shop',
                        'state',
                        'gender',
                        'gender_lang',
                        'tax',
                        'tax_lang',
                        'tax_rules_group',
                        'tax_rules_group_shop',
                        'tax_rule',
                    ],
                    'position' => -1,
                ],
                'employee' => [
                    'name' => self::l('Employees'),
                    'tables' => [
                        'profile',
                        'profile_lang',
                        'employee',
                        'employee_shop',
                    ],
                    'position' => 4,
                ],
                'category' => [
                    'name' => self::l('Product categories'),
                    'images' => [
                        'category' => [
                            [
                                'path' => 'img/c',
                                'ext' => '.jpg',
                                'type' => 'categories',
                            ],
                        ],
                    ],
                    'tables' => [
                        'group',
                        'group_lang',
                        'group_shop',
                        'category',
                        'category_lang',
                        'category_shop',
                        'category_group',
                    ],
                    'position' => 1,
                ],
                'manufacturer' => [
                    'name' => self::l('Manufacturers'),
                    'images' => [
                        'manufacturer' => [
                            [
                                'path' => 'img/m',
                                'ext' => '.jpg',
                                'type' => 'manufacturers',
                            ],
                        ],
                    ],
                    'tables' => [
                        'manufacturer',
                        'manufacturer_lang',
                        'manufacturer_shop',
                    ],
                    'position' => 12,
                ],
                'supplier' => [
                    'name' => self::l('Suppliers'),
                    'images' => [
                        'supplier' => [
                            [
                                'path' => 'img/su',
                                'ext' => '.jpg',
                                'type' => 'suppliers',
                            ],
                        ],
                    ],
                    'tables' => [
                        'supplier',
                        'supplier_lang',
                        'supplier_shop',
                    ],
                    'position' => 11,
                ],
                'customer' => [
                    'name' => self::l('Customers & addresses'),
                    'tables' => [
                        'customer',
                        'customer_group',
                        'address',
                        'address_format',
                    ],
                    'position' => 3,
                ],
                'carrier' => [
                    'name' => self::l('Carriers & shipping'),
                    'images' => [
                        'carrier' => [
                            [
                                'path' => 'img/s',
                                'ext' => '.jpg',
                            ],
                        ],
                    ],
                    'tables' => [
                        'carrier',
                        'carrier_lang',
                        'carrier_shop',
                        'carrier_group',
                        'carrier_zone',
                        'range_price',
                        'range_weight',
                        'delivery',
                        'carrier_tax_rules_group_shop',
                    ],
                    'position' => 6,
                ],
                'product' => [
                    'name' => self::l('Products & SEO'),
                    'images' => [
                        'image' => [
                            [
                                'path' => 'img/p',
                                'ext' => '.jpg',
                                'type' => 'products',
                            ],
                        ],
                        'attribute' => [
                            [
                                'path' => 'img/co',
                                'ext' => '.jpg',
                            ],
                        ],
                    ],
                    'files' => [
                        'attachment' => [
                            [
                                'path' => 'download',
                                'field' => 'file',
                            ],
                        ],
                        'product_download' => [
                            [
                                'path' => 'download',
                                'field' => 'filename',
                            ],
                        ],
                    ],
                    'tables' => [
                        'tag',
                        'tag_count',
                        'attachment',
                        'attachment_lang',
                        'product',
                        'product_lang',
                        'product_shop',
                        'category_product',
                        'accessory',
                        'product_tag',
                        'feature',
                        'feature_lang',
                        'feature_shop',
                        'feature_value',
                        'feature_value_lang',
                        'feature_product',
                        'attribute_group',
                        'attribute_group_lang',
                        'attribute_group_shop',
                        'attribute',
                        'attribute_lang',
                        'attribute_shop',
                        'attribute_impact',
                        'product_attribute',
                        'product_attribute_lang',
                        'product_attribute_shop',
                        'product_attribute_combination',
                        'product_supplier',
                        'pack',
                        'product_sale',
                        'product_group_reduction_cache',
                        'product_download',
                        'product_country_tax',
                        'product_carrier',
                        'product_attachment',
                        'customization_field',
                        'customization_field_lang',
                        'image',
                        'image_lang',
                        'image_shop',
                        'product_attribute_image',
                        'stock_available',
                        'warehouse',
                        'warehouse_shop',
                        'warehouse_carrier',
                        'warehouse_product_location',
                        'stock',
                    ],
                    'position' => 2,
                ],
            ], EtsMigrateTools::tableExists('product_comment') ? [
                'product_comment' => [
                    'name' => self::l('Product comments'),
                    'tables' => [
                        'product_comment_criterion',
                        'product_comment_criterion_lang',
                        'product_comment_criterion_product',
                        'product_comment_criterion_category',
                        'product_comment',
                        'product_comment_grade',
                        'product_comment_usefulness',
                        'product_comment_report',
                    ],
                    'position' => 15,
                ],
            ] : [], [
                'cart_rule' => [
                    'name' => self::l('Cart rules'),
                    'tables' => [
                        'cart_rule',
                        'cart_rule_lang',
                        'cart_rule_shop',
                        'cart_rule_carrier',
                        'cart_rule_combination',
                        'cart_rule_country',
                        'cart_rule_group',
                        'cart_rule_product_rule_group',
                        'cart_rule_product_rule',
                        'cart_rule_product_rule_value',
                    ],
                    'position' => 7,
                ],
                'specific_price' => [
                    'name' => self::l('Catalog price rules'),
                    'tables' => [
                        'specific_price_rule',
                        'specific_price_rule_condition_group',
                        'specific_price_rule_condition',
                        'specific_price',
                    ],
                    'position' => 8,
                ],
                'orders' => [
                    'name' => self::l('Orders & shopping carts'),
                    'files' => [
                        'customized_data' => [
                            [
                                'path' => 'upload',
                                'field' => 'value',
                            ],
                        ],
                    ],
                    'tables' => [
                        'order_state',
                        'order_state_lang',
                        'guest',
                        'cart',
                        'cart_cart_rule',
                        'customization',
                        'customized_data',
                        'cart_product',
                        'orders',
                        'order_payment',
                        'order_invoice',
                        'order_invoice_tax',
                        'order_invoice_payment',
                        'order_detail',
                        'order_detail_tax',
                        'order_slip',
                        'order_slip_detail',
                        'order_slip_detail_tax',
                        'order_carrier',
                        'order_cart_rule',
                        'order_history',
                        'order_message',
                        'order_message_lang',
                        'order_return',
                        'order_return_detail',
                        'message',
                        'message_readed',
                    ],
                    'position' => 5,
                ],
                'cms_category' => [
                    'name' => self::l('CMS categories'),
                    'tables' => [
                        'cms_category',
                        'cms_category_lang',
                        'cms_category_shop',
                    ],
                    'position' => 9,
                ],
                'cms' => [
                    'name' => self::l('CMSs'),
                    'tables' => [
                        'cms',
                        'cms_lang',
                        'cms_shop',
                    ],
                    'position' => 10,
                ],
                'contact' => [
                    'name' => self::l('Contact form messages'),
                    'tables' => [
                        'contact',
                        'contact_lang',
                        'contact_shop',
                        'customer_thread',
                        'customer_message',
                    ],
                    'position' => 13,
                ],
                'meta' => [
                    'name' => self::l('Meta data & SEO'),
                    'tables' => [
                        'meta',
                        'meta_lang',
                    ],
                    'position' => 14,
                ],
                /*'connections' => [
                        'name' => self::l('Connections'),
                        'tables' => [
                            'page_type',
                            'page',
                            'connections',
                            'connections_page',
                            'connections_source',
                        ],
                        'position' => 15,
                    ],*/
            ], (EtsMigrateTools::isModuleEnabled('ybc_blog') && EtsMigrateTools::tableExists('ybc_blog_post')) ? [
                'ybc_blog_post' => [
                    'name' => self::l('BLOG'),
                    'images' => [
                        'ybc_blog_gallery_lang' => [
                            [
                                'path' => 'img/ybc_blog/gallery',
                                'field' => 'image',
                            ],
                            [
                                'path' => 'img/ybc_blog/gallery/thumb',
                                'field' => 'thumb',
                            ],
                        ],
                        'ybc_blog_category_lang' => [
                            [
                                'path' => 'img/ybc_blog/category',
                                'field' => 'image',
                            ],
                            [
                                'path' => 'img/ybc_blog/category/thumb',
                                'field' => 'thumb',
                            ],
                        ],
                        'ybc_blog_post_lang' => [
                            [
                                'path' => 'img/ybc_blog/post',
                                'field' => 'image',
                            ],
                            [
                                'path' => 'img/ybc_blog/post/thumb',
                                'field' => 'thumb',
                            ],
                        ],
                        'ybc_blog_slide_lang' => [
                            [
                                'path' => 'img/ybc_blog/slide',
                                'field' => 'image',
                            ],
                        ],
                        'ybc_blog_employee' => [
                            [
                                'path' => 'img/ybc_blog/avata',
                                'field' => 'avata',
                            ],
                        ],
                    ],
                    'tables' => [
                        'ybc_blog_category',
                        'ybc_blog_category_lang',
                        'ybc_blog_category_shop',
                        'ybc_blog_gallery',
                        'ybc_blog_gallery_lang',
                        'ybc_blog_gallery_shop',
                        'ybc_blog_slide',
                        'ybc_blog_slide_lang',
                        'ybc_blog_slide_shop',
                        'ybc_blog_post',
                        'ybc_blog_post_lang',
                        'ybc_blog_post_shop',
                        'ybc_blog_post_category',
                        'ybc_blog_post_related_categories',
                        'ybc_blog_tag',
                        'ybc_blog_polls',
                        'ybc_blog_comment',
                        'ybc_blog_reply',
                        'ybc_blog_log_like',
                        'ybc_blog_log_view',
                        'ybc_blog_employee',
                        'ybc_blog_employee_lang',
                    ],
                    'position' => 16,
                ],
            ] : [], (EtsMigrateTools::isModuleEnabled('ets_blog') && EtsMigrateTools::tableExists('ets_blog_post')) ? [
                'ets_blog_post' => [
                    'name' => self::l('Simple Blog'),
                    'images' => [
                        'ets_blog_category_lang' => [
                            [
                                'path' => 'img/ets_blog/category',
                                'field' => 'image',
                            ],
                            [
                                'path' => 'img/ets_blog/category/thumb',
                                'field' => 'thumb',
                            ],
                        ],
                        'ets_blog_post_lang' => [
                            [
                                'path' => 'img/ets_blog/post',
                                'field' => 'image',
                            ],
                            [
                                'path' => 'img/ets_blog/post/thumb',
                                'field' => 'thumb',
                            ],
                        ],
                    ],
                    'tables' => [
                        'ets_blog_category',
                        'ets_blog_category_lang',
                        'ets_blog_post',
                        'ets_blog_post_lang',
                        'ets_blog_post_category',
                        'ets_blog_comment',
                        'ets_blog_reply',
                    ],
                    'position' => 16,
                ],
            ] : [], (EtsMigrateTools::isModuleEnabled('csblog') && EtsMigrateTools::isModuleEnabled('csblogcategory') && EtsMigrateTools::tableExists('cs_blog_category') && EtsMigrateTools::tableExists('cs_blog_post')) ? [
                'cs_blog' => [
                    'name' => self::l('CS Blog'),
                    'parent' => [
                        'cs_blog_category',
                        'cs_blog_post',
                    ],
                    'images' => [
                        'cs_blog_category' => [
                            [
                                'path' => 'modules/csblog/media/categories/src',
                                'ext' => '.jpg',
                            ],
                        ],
                        'cs_blog_post' => [
                            [
                                'path' => 'modules/csblog/media/posts/src',
                                'ext' => '.jpg',
                            ],
                        ],
                    ],
                    'tables' => [
                        'cs_blog_category',
                        'cs_blog_category_shop',
                        'cs_blog_category_lang',
                        'cs_blog_tag',
                        'cs_blog_post',
                        'cs_blog_post_shop',
                        'cs_blog_post_lang',
                        'cs_blog_category_post',
                        'cs_blog_post_tag',
                        'cs_blog_comment',
                        'cs_blog_comment_lang',
                    ],
                    'position' => 18,
                ],
            ] : [], EtsMigrateTools::tableExists('linksmenutop') ? [
                'linksmenutop' => [
                    'name' => self::l('Top menus'),
                    'parent' => [
                        'linksmenutop',
                    ],
                    'tables' => [
                        'linksmenutop',
                        'linksmenutop_lang',
                    ],
                    'position' => 17,
                ],
            ] : [], [
                'images' => [
                    'name' => self::l('Images & thumbnails'),
                    'tables' => [],
                    'position' => -1,
                ],
                'files' => [
                    'name' => self::l('Attachments & files'),
                    'tables' => [],
                    'position' => -1,
                ],
                'finished' => [
                    'name' => self::l('Finalization'),
                    'tables' => [],
                    'position' => -1,
                ],
            ]);
        }

        $resources = self::$resource;
        if ($sort) {
            if (self::$resourceSorted === null) {
                self::$resourceSorted = EtsMigrateTools::quickSort(self::$resource);
            }
            $resources = self::$resourceSorted;
        }

        if ($group !== null && $group !== '' && !isset($resources[$group])) {
            return [
                'tables' => null,
                'error' => self::l('Unknown resource'),
            ];
        }

        return $group && isset($resources[$group]) ? $resources[$group] : $resources;
    }

    protected static function l($string)
    {
        return Translate::getModuleTranslation('ets_migrate', $string, 'EtsMigrateApi');
    }
}
