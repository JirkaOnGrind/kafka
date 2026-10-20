# Dotaz před nákupem MigrationPro Ultimate

Text je připravený, nebyl odeslaný. Vložit do soukromé podpory PrestaHero; neposílat zálohy, zákaznická data ani přístupová hesla.

Hello, we plan to purchase MigrationPro Ultimate for one store, kafka.monster.

Our source is PrestaShop 1.6.1.13, currently on PHP 7.1. The target is PrestaShop 9.1.5 on PHP 8.3 with a custom child theme based on Hummingbird 2.0. We have approximately 3,296 products, 11,786 combinations and 574 orders; languages CS/SK/DE and currencies CZK/EUR.

We must not install any connector or modify the live source store during development. We will restore a private source clone from database and file backups into Docker on our PC, then install your connector only on that clone. Both source and target will be locally reachable from each other's containers, with separate databases. Before final migration, we will refresh the source clone from a final production backup.

Please confirm:

1. Is this fully local clone-to-clone migration supported? What exact module/connector releases and source PHP extensions are required for 1.6.1.13 → 9.1.5? Can it work without inbound public internet access and with production integrations disabled?
2. Does one license allow development on localhost and subsequent relocation of the same target store to kafka.monster? What domain should we select when purchasing, and is any activation transfer required?
3. Does Mode 1 preserve source IDs, customer passwords, product combinations, prices, stock, images, attachments, order history, payment and invoice records? Can we retain our target administrator and custom theme/module without importing old modules or overrides?
4. Does Mode 4 update existing orders, their statuses/payments, catalogue prices and stock, in addition to adding new records? How does it handle source deletions and repeat runs without duplicates? Which target configuration or carrier settings can it overwrite?
5. The source contains two orphan combinations with product ID 0 and historical order lines for products that have been deleted. Can the orphan combinations be excluded while preserving those historical order lines?

We understand that custom Comgate, Packeta and DPD module tables may require separate handling. Please also confirm the total purchase price and any ongoing license/support costs.
