CREATE TABLE IF NOT EXISTS `PREFIX_ets_em_shop_mapping` (
    `id_ets_em_shop_mapping` int(10) unsigned NOT NULL auto_increment,
    `id_shop_source` int(10) unsigned NOT NULL DEFAULT '0',
    `id_shop_target` int(10) unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id_ets_em_shop_mapping`),
    UNIQUE KEY `uniq_id_shop_source` (`id_shop_source`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4;
