# Práce na Kafka Monster

## Navazující produktové informace — 20. 10. 2026

Popis a detaily tvoří souvislý panel, komentáře navazují pod popisem v levém sloupci. Bez popisu se nadále vykreslují vlevo jen komentáře. Na mobilu zůstává nativní sklápění. Detail nevolá prohlédnuté produkty ani příslušenství; jediná doporučení poskytuje nativní `ps_categoryproducts` s limitem čtyř v přepisu jeho šablony (běžící modul už měl nastavení 4). Při menším počtu se zobrazí jen dostupné produkty stejné kategorie. Ostatní neproduktové hooky a přílohy zůstávají.

Nasazeno pouze do izolované 8083. Soubory: `templates/catalog/product.tpl`, `templates/_partials/head.tpl` (CSS `20261020-22-product-flow`), `assets/css/kafka.css` a `modules/ps_categoryproducts/views/templates/hook/ps_categoryproducts.tpl` uvnitř tématu Kafka. Po kopírování vyčistit soubory Smarty compile cache. Záloha tématu před změnou: `/lab/product-flow-20261020/before.tar.gz` uvnitř cílového kontejneru. Bez importu SQL, změny katalogu, volumes či produkce.

Chrome/Playwright (Browser plugin není dostupný), desktop 1536 × 1024 a mobil 390 × 844: HTTP 200, ochrana X-Robots-Tag, jeden blok komentářů, čtyři kategoriální karty, bez prohlédnutých produktů i po návštěvě více detailů, bez přetékání a zachycených JS chyb. Ověřeno mobilní sbalení/rozbalení popisu a nativní změna na Červená / 164, vložení za 299 Kč do košíku a odstranění testovací položky. Objednávka se neodesílala. Vizuální kontrola vůči aktuálně vykreslenému původnímu produktu na 8085; souvislý panel je nově schválená odchylka. Stav bez popisu nebyl v tomto běhu samostatně vykreslen.

## Modernizovaný košík — 20. 10. 2026

Prázdný i naplněný košík používají tmavé panely, přehlednější ceny a větší ovládání množství. Výchozí Ujištění zákazníka s modrými ikonami a instalačními texty je odstraněné přímo blokem Smarty v obou stránkách. Nativní produktové řádky, varianty, ceny, promo kódy a aktualizace košíku zůstávají; nový prázdný stav je v dílčí šabloně `checkout/_partials/cart-detailed.tpl` se zachovaným AJAX kontejnerem.

Nasazeno pouze do izolované 8083. Při dalším nasazení zahrnout `assets/css/kafka.css`, `templates/_partials/head.tpl` (CSS `20261020-20`), `templates/checkout/cart.tpl`, `templates/checkout/cart-empty.tpl` a `templates/checkout/_partials/cart-detailed.tpl`; vytvořit chybějící adresář `_partials` a vyčistit Smarty cache. Soukromá záloha před změnou je v cílovém kontejneru `/lab/cart-style-20261020-20/theme-before.tar.gz`. Databáze, volumes ani produkce se touto úpravou nemění.

Chrome/Playwright (Browser plugin není dostupný), desktop 1536 × 1024 a mobil 390 × 844: ověřen prázdný košík, návrat do obchodu, vložení varianty Červená / 164, zvýšení množství 1 → 2 s přepočtem 299 → 598 Kč, snížení zpět, přechod do objednávky a odebrání položky s návratem do prázdného stavu i po obnovení. Kontrola zahrnuje identitu stránky, ochranu noindex, nepřítomnost modrého bloku, vykreslení bez chyb a vodorovné přetékání na dalších šířkách. Objednávka se neodesílá. Vizuálně porovnáno s aktuálně vykresleným prototypem: zachované barvy, fonty, obrysy, šířka a červený akcent; vlastní rozložení košíku je uživatelem požadovaná úprava.

## Spojenci na scéně — 20. 10. 2026

Homepage má pod šesticí kategoriálních karet nový blok partnerů The Punkavec a Punkhudba, podle externích partnerů na původní `https://kafka.monster/cs/`. Průhledná loga jsou stažena přímo z partnerských webů, uložená v `prestashop/themes/kafka/assets/img/partners/` a sjednocená do bílé pomocí CSS. Návštěvník nenačítá loga z cizích serverů. Odkazy otevírají nový panel s `noopener noreferrer` a viditelným klávesnicovým fokusem.

Seznam, popisky a původní zdrojové URL log spravuje `prestashop/modules/kafkahome/data/partners.json`. Existující homepage hook `displayKafkaCategories` vykresluje po kategoriích novou `views/templates/hook/partners.tpl`; registrace nového hooku ani změna databáze není potřeba. Při nasazení zahrnout `kafkahome.php`, JSON, novou šablonu, složku log, `kafka.css` a `head.tpl` (verze při této změně `20261020-21-partners`), potom vyčistit Smarty cache. Nasazeno do izolované místní 8083; předchozí soubory jsou zálohovány mimo webroot v `/lab/partners-20261020/before.tar.gz`. Zachována interní izolace obou sítí a ochrana `X-Robots-Tag: noindex`. Produkce se nemění.

Ověřeno přes existující Chrome/Playwright (Browser plugin není dostupný), desktop 1536 × 1024 a mobil 390 × 844: správná stránka HTTP 200, šest kategorií a dva partneři, načtená lokální loga, bez chybové vrstvy a chyb konzole/JavaScriptu. Klávesnice Enter otevřela skutečný web každého partnera v nové kartě a zachovala shop; tlačítko v hero posouvá na viditelné kategorie. Bez vodorovného přetékání také při 320, 767, 768, 991 a 992 px. Porovnáno s čerstvě vykresleným původním prototypem: nový blok je schválený přídavek, existující vizuál zůstává. Košík ani objednávka nebyly součástí této změny a znovu se netestovaly. Přímý náhled sekce: `http://localhost:8083/cs/#spojenci`.

## Mobilní výpis produktů — 20. 10. 2026

Do 767 px se nezobrazují obrázkové bannery kategorií (`.category__cover`) a nákupní tlačítka produktových karet mají pouze vystředěnou ikonu košíku, minimálně 48 px na výšku. Nativní přístupný název, množství a košík zůstávají zachované. Desktop ani prototyp se nemění. Nasazeno do izolované 8083; soukromá záloha původních souborů je `/lab/mobile-listing-20261020-before.tar.gz`. Při dalším nasazení kopírovat `kafka.css` a `templates/_partials/head.tpl` (CSS verze `20261020-17`) a vyčistit kompilované Smarty šablony. Ověřeno v Chrome/Playwright (Browser plugin není dostupný) na mobilu 390 × 844, šířkách 360, 767 a 768 px a desktopu 1536 × 1024: skrytí banneru/textu, zachování desktopového banneru, bez vodorovného přetékání a chyb JavaScriptu, skutečné přidání produktu do košíku.

## Mobilní homepage — 20. 10. 2026

Do 767 px je skrytá trojice servisních boxů, hlavní fotka má 500 px vysoký hero a výraznější horní část. Tlačítko do obchodu je přes šířku textového bloku, minimálně 64 px vysoké, s větší šipkou a odezvou při stisku. Odkaz dál vede na `#kategorie`. Desktop i původní prototyp zůstávají stejné. CSS verze `20261020-15`, soubory `prestashop/themes/kafka/assets/css/kafka.css` a `templates/_partials/head.tpl`; při nasazení kopírovat oba a vyčistit kompilované Smarty šablony.

Nasazeno do izolované místní 8083 po soukromé záloze `/lab/mobile-hero-20261020-15/theme-before.tar.gz` v cílovém kontejneru. Kontrolní snímky a výsledky jsou v `local/qa/mobile-hero-20261020/`. Browser plugin není dostupný, používá se existující Chrome/Playwright.

Ověřeno na šířkách 320, 390, 767, 768 a 1536 px: funkční posun ke kategoriím, HTTP 200, zachované noindex, žádné vodorovné přetékání ani chyby konzole/JavaScriptu. Snímky na 768 a 1536 px jsou bajtově shodné s původním stavem. Porovnáno také s vykresleným prototypem; mobilní hlavička, prodloužená fotka a tlačítko jsou schválené odchylky.

## Hover názvu v hlavičce — 20. 10. 2026

Aktuální chování: KAFKA MONSTER i PUNK ROCK SHOP zachovávají výchozí barvy na všech zařízeních také při najetí či klepnutí. Odkaz má pouze kurzor ručičky (`cursor: pointer`), bez barevného hoveru. CSS verze `20261020-14`; nasazení do izolované 8083 se zálohou obou předchozích souborů v `local/private/wordmark-nohover-*`.

Pravidlo je v `prestashop/themes/kafka/assets/css/kafka.css`. Kontrola přes Chrome/Playwright na 1536 × 1024 a dotykové emulaci 390 × 844 zahrnuje hover, návrat barev a klepnutí na domovský odkaz. Browser plugin není dostupný, použita existující instalace Playwright. Předchozí varianty jsou zálohované v `local/private/wordmark-hover-20261020/` a `local/private/tagline-hover-*`.

## Hover navigace — 20. 10. 2026

Horní a boční kategoriální navigace používá při hoveru pouze rudý text, bez podbarvení, barevných čar a náklonu. Aktuální kategorie zůstává rudá i po odjetí myši. Modul `kafkahome` při vykreslení určuje aktivní sekci podle skutečného stromu kategorií; horní odkazy mají `aria-current="page"` na kořeni sekce a `aria-current="location"` v jejích podkategoriích. Stejné označení používá mobilní menu. Nativní rozbalování, fokus a stav vybraných filtrů zůstávají zachované. Změny jsou v CSS a `head.tpl` šablony Kafka (verze CSS `20261020-10`) a v `kafkahome.php` a `navigation.tpl` modulu. Nasazeno do izolované 8083 po záloze `local/private/migration-lab-20261018/simple-navigation-20261020-104759/before.tar.gz`. Při dalším nasazení zahrnout oba soubory modulu, CSS a head a vyčistit kompilované Smarty šablony.

## Detail produktu — 20. 10. 2026

Nadpisy Popis a Detaily produktu mají nyní odsazení 16 × 20 px a obsah 20 px. Desktop používá statické nadpisy bez šipek, obě sekce jsou stále viditelné. Mobilní tlačítka a nativní sklápění zůstávají. CSS od 768 px zobrazí obsah i při příchodu z mobilu se zavřeným panelem. Změna je v obou produktových šablonách a CSS, aktuální verze načítání je `20261020-11`. Záloha před změnou: `local/private/migration-lab-20261018/product-static-panels-20261020-105650-theme-before.tar.gz`.

Ověřeno přes Chrome/Playwright na 8083, desktop 1536 × 1024 a mobil 390 × 844, produkty s popisem a bez popisu: na desktopu statické nadpisy bez viditelných tlačítek, klik obsah nezavírá, shodná výška obou dvojic karet (spodní 214 px). Na mobilu funguje zavření i otevření popisu/detailů. Zavřený mobilní panel se při rozšíření na desktop zobrazí a po návratu na mobil zachová svůj stav. Změna varianty zachovává jedny detaily, jeden seznam komentářů a shodnou výšku karet. Správné neprázdné stránky HTTP 200, bez přetékání, chybové vrstvy a chyb konzole/JavaScriptu. Košík se při této úpravě znovu netestoval.

Navazující úprava: pokud popis chybí nebo obsahuje jen prázdné HTML odstavce, šablona přesune nativní `productcomments` z `displayFooterProduct` do levé spodní karty a z původního místa vyloučí pouze tento modul. Ostatní moduly/přílohy zůstávají. Popisy obsahující média se zachovávají. Spodní karty mají společnou výšku podle vyššího obsahu na desktopu a vlastní pozadí s obrysem po celé výšce; mobil zůstává jednosloupcový. Aktuální verze CSS je `20261020-8`. Záloha před touto úpravou: `local/private/migration-lab-20261018/product-fallback-20261020-theme-before.tar.gz`. Používá se stejný lokální postup nasazení jako níže.

Ověřeno na skutečných produktech Pin Punks Not Dead (s popisem) a Tričko Neparkovat Vjezd (bez popisu): Chrome/Playwright, desktop 1536 × 1024 a mobil 390 × 844. Všechny čtyři kombinace: HTTP 200, správný titulek a obsah, právě jeden seznam komentářů a jedny detaily, správné umístění komentářů, funkční zavření/otevření detailů, bez přetékání, chybové vrstvy a chyb konzole/JavaScriptu. Na desktopu mají oba spodní panely 212 px; oba horní panely rovněž shodnou výšku. Referenční podoba prototypu je zachována, změna spodních karet odpovídá novému zadání. Odesílání recenze ani objednávky se netestovalo.

Produktová šablona `prestashop/themes/kafka/templates/catalog/product.tpl` dědí nativní Hummingbird a nahrazuje výchozí Ujištění zákazníka skutečnými detaily v pravém spodním sloupci vedle popisu. Přepis `catalog/_partials/product-details.tpl` zachovává nativní produktová data, DOM pro aktualizace variant a rozbalování, pouze otevírá detaily ve výchozím stavu. Galerie a nákupní karta mají na desktopu shodnou výšku; mobil používá jeden sloupec a přirozené výšky. Styly jsou v `assets/css/kafka.css`, verze načítání v `_partials/head.tpl` je `20261020-6`.

Nasazeno do izolovaného místního shopu 8083 po záloze `local/private/migration-lab-20261018/product-layout-20261020-103354-theme-before.tar.gz`. Při dalším nasazení kopírovat oba produktové přepisy, CSS a head a vyčistit kompilované Smarty šablony pomocí `scripts/clear-local-seo-cache.php` uvnitř cíle. Bez importu nebo změny katalogu. Chrome/Playwright (Browser plugin není dostupný), desktop 1536 × 1024 a mobil 390 × 844: HTTP 200, správná stránka, stejná výška horních karet na desktopu (707,95 px), detaily jednou a vedle popisu, odstraněný modrý blok, rozbalování, galerie, změny variant s cenou 269 → 299 Kč a skutečné vložení odpovídající barvy/velikosti do košíku. Testovací položky odstraněny. Bez přetékání, chybové vrstvy a chyb konzole/JavaScriptu. Porovnáno s vykresleným původním prototypem na portu 4173; jiné údaje produktu a nové rozložení detailů jsou záměrné.

## Štítek Výprodej — 20. 10. 2026

Nativní příznak `on_sale` se ve výpisu i detailu zobrazuje jako „Výprodej“, slovensky „Výpredaj“, německy „Abverkauf“. Nevyžaduje aktivní cenovou slevu. Přepis `prestashop/themes/kafka/templates/catalog/_partials/product-flags.tpl` mění pouze popisek `on-sale`; skutečné procentní/částkové slevy a ostatní štítky zachovává. Nasazeno do izolované 8083 po soukromé záloze `local/private/clearance-label-20261020-103757/theme-before.tar.gz`. Při dalším nasazení šablony zahrnout tento přepis a vyčistit kompilované šablony. Ceny ani katalogová data se nemění.

## Zarovnání desktopové hlavičky — 20. 10. 2026

Účet a košík používají v `kafka.css` společné flexové zarovnání `.kafka-header-tools .header-block`. Odstraňuje třípixelový rozdíl způsobený mezerou pod řádkou přihlašovacího odkazu. Nasazeno do izolované kopie 8083; soukromá záloha původního CSS a `head.tpl` je v laboratoři `header-alignment-20261020-before.tar.gz`.

## Filtry v Oblečení — 20. 10. 2026

Nová nadřazená kategorie Oblečení chyběla v přiřazení `ps_facetedsearch`. Na izolovaném cíli 8083 je nyní přidaná do stejné nativní filtrační šablony jako její podkategorie. Dostupné volby vycházejí z katalogu (kategorie, cena, dostupnost, velikosti, barvy a další existující atributy); řazení i filtrování používají původní nativní AJAX. Šablona ani produktové údaje se nemění.

Opakovatelný postup po importu: zkopírovat `scripts/configure-clothing-filters.php` do `/tmp/configure-clothing-filters.php` aplikačního kontejneru cíle a spustit přes PHP CLI. Bez parametrů vypíše návrh, s `--apply --backup=/lab/novy-jedinecny-soubor.json` nejprve uloží soukromou zálohu tabulek nastavení filtrů mimo webroot, přidá jedinou kategorii a zavolá nativní obnovu přiřazení. Kategorie a existující šablona se hledají z živých dat, nikoli podle pevných ID. Opakované spuštění už nic nemění. Skript povoluje pouze `localhost:8083` / `target-db`; nejde o import katalogu. Záloha před změnou je `local/private/migration-lab-20261018/clothing-filters-before-20261020.json`.

Ověřeno přes Chrome/Playwright (Browser plugin není dostupný), desktop 1536 × 1024 a mobil 390 × 844: načtení správné neprázdné stránky, rozbalení Kategorie a výběr Pánské, nativní AJAX změna počtu 1 583 → 39, aktivní zaškrtnutí i zachování volby po obnovení stránky. Na mobilu ověřeno otevření panelu tlačítkem Filtrovat. Bez chyb JavaScriptu/konzole, chybové vrstvy a vodorovného přetékání; černý vzhled a rozložení odpovídají stávající šabloně. Ověřena syntaxe PHP a opakované spuštění beze změn. Varianty a košík se touto opravou nemění a nebyly znovu testovány.

## Řazení produktů — 20. 10. 2026

Nabídka řazení má pět možností: „Výchozí pořadí“, „Od nejlevnějšího“, „Od nejdražšího“, „Název A–Z“, „Název Z–A“. Řazení podle kódu a prodejů se nenabízí. Přepis `prestashop/themes/kafka/templates/catalog/_partials/sort-orders.tpl` zachovává nativní odkazy poskytovatele, AJAX, aktivní volbu i parametry filtrů; výchozí pořadí používá nativní pozici, případně vlastní výchozí řazení daného poskytovatele. Popisky jsou připravené také slovensky a německy.

Nasazeno do izolované kopie 8083 po soukromé záloze šablony `local/private/migration-lab-20261018/sort-ux-20261020-102155-theme-before.tar.gz` a vyčištění kompilovaných šablon. Při dalším nasazení šablony kopírovat i tento přepis. Ověřeno přes místní Chrome/Playwright (Browser plugin není dostupný), desktop 1536 × 1024 a mobil 390 × 844: všech pět voleb, skutečné pořadí cen/názvů, návrat původních produktů, obnovení stránky, stejná nabídka při vyhledávání, bez přetékání a chyb JavaScriptu. Abecední řazení zachovává nativní databázovou kolaci.

## Mobilní hlavička — 20. 10. 2026

Do 767 px je logo nad názvem a sloganem, pod nimi samostatná řada čtyř ikon přes šířku hlavičky. Opakovaný kategoriální panel nad produkty je skrytý; navigaci otevírá hamburger. Přehled podkategorií „Vyber si“ má na mobilu dva kompaktní sloupce místo vysokých karet pod sebou. Změny jsou v `prestashop/themes/kafka/assets/css/kafka.css`, verze CSS v `_partials/head.tpl` je `20261020-4`. Nasazeno do izolované kopie 8083; desktopové rozložení zůstává původní.

## Názvy podkategorií — 20. 10. 2026

Na migrační kopii 8083 mají pánské a dámské podkategorie běžné psaní („Pánská tílka“, „Dámská trička“, „Mikiny na zip pánské“, „Sukně“). Opraveno je devět přesně vyjmenovaných katalogových názvů ve všech záznamech, které obsahovaly původní český text velkými písmeny; menu i přehledy čtou stejné nativní názvy. URL, SEO metadata a vlastní překlady se nemění. Soukromá záloha původních hodnot: `local/private/migration-lab-20261018/category-names-before-20261020.json`.

Opakovatelná oprava po novém importu: `scripts/normalize-category-names.php` přes PHP CLI uvnitř izolovaného cíle. Bez parametrů ukáže návrh; `--apply --backup=/lab/novy-soubor.json` nejprve uloží nové původní hodnoty mimo webroot, pak změní pouze přesné shody a vyčistí cache včetně samostatně uložených popisků filtrů `ps_facetedsearch`. Skript povoluje jen migrační cíl 8083 s databází `target-db`. Ověřeno v Chrome přes Playwright na desktopu 1536 × 1024 a mobilu 390 × 844: rozbalení menu, opravené názvy v přehledu a přechod na obě kategorie tílek; bez chyb JavaScriptu.

## Navigace — 20. 10. 2026

Veřejná šablona Kafka nevykresluje drobečkovou navigaci (`templates/_partials/breadcrumb.tpl`). Boční strom kategorií nemá nadbytečný nadpis kořenové kategorie „Home“ (`modules/ps_categorytree/views/templates/hook/ps_categorytree.tpl`); odkazy kategorií a rozbalování zůstávají nativní. Kliknutí na logo nebo nápis Kafka Monster vrací zákazníka na úvod aktuálního jazyka. Nadpis přehledu podkategorií zní „Vyber si“. Změny jsou v projektové šabloně a nasazené na migrační kopii 8083; rodičovská šablona, prototyp a katalogové názvy/URL se nemění. Před nasazením byla uložena soukromá záloha šablony `local/private/navigation-ux-20261020-095057/theme-before.tar.gz`.

## Aktuální migrační verze a objednávkový formulář — 20. 10. 2026

**Pro aktuální migrační shop spouštěj `start-migrace.bat` a používej http://localhost:8083/cs/.** Původní `start.bat` nadále spouští samostatný starší vývojový shop na 8080; není to migrační cíl. Návod ke staršímu prostředí níže se týká 8080.

`start-migrace.bat` volá `scripts/start-migration.ps1`: spustí pouze existující kontejnery cíle, jeho databáze a náhledu, ověří interní sítě a HTTP/noindex ochranu a otevře 8083. Nevytváří nový shop, neimportuje SQL ani nenasazuje kód. Před spuštěním musí běžet Docker Desktop. Pro kontrolu bez otevření prohlížeče použij `start-migrace.bat -NoBrowser`.

Objednávka, registrace a úprava osobních údajů používají nativní formuláře s hooky modulu `kafkahome`: bez oslovení/pohlaví a bez nabídek partnerů. Vlastní odběr novinek zůstává samostatný, volitelný a předem nezaškrtnutý. Datum narození je volitelné, na konci osobních údajů; vybírá se den, měsíc a rok, kontroluje se úplnost, platnost i budoucí datum. Odesílá se nativní pole `birthday`, bez JavaScriptu zůstává původní textový vstup. Adresa nabízí jediný telefon s mobilní klávesnicí a automatickým vyplněním; historické druhé číslo se zachovává, u adresy pouze s mobilem se tento údaj předvyplní do viditelného telefonu.

Opakovatelné nasazení do aktuální 8083: `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/deploy-checkout-ux.ps1` vypíše plán; s `-Apply` nejprve uloží soukromou zálohu databáze a frontendu do `local/private/migration-lab-20261018/import-runs/checkout-ux-*/`, nasadí projektový modul a šablonu, registruje hooky, vypne `PS_CUSTOMER_OPTIN` a vyčistí cache. Žádný katalog se neimportuje. Po dalším importu či obnově checkpointu znovu nasadit a ověřit tento krok; povinná SEO přejímka importu zůstává beze změny. Na produkci se tento lokální skript nepouští.

Ověřeno na 8083 přes Chrome/Playwright (Browser plugin není dostupný), desktop 1536 × 1024 a mobil 390 × 844: nativní přidání produktu do košíku, osobní údaje, neplatné i platné datum, uložení data `2006-06-12`, adresa s jediným telefonem, pokračování k dopravě a vyprázdnění testovacího košíku. Bez chyb JavaScriptu či konzole, bez vodorovného přetékání formuláře. Žádná objednávka nevznikla; platba ani doručení e-mailu nejsou součástí této kontroly. Ověřena také syntaxe PHP/JS/PowerShellu a nový spouštěč s `-NoBrowser`.


## Obrázky barevných variant — 19. 10. 2026

Zdrojová záloha neměla žádné vazby kombinací na fotografie. Na izolovaném shopu 8083 bylo podle vizuálně zkontrolovaných existujících fotografií doplněno 4 131 vazeb u 3 680 kombinací 267 produktů s více barvami. Nativní detail, změny variant, potvrzovací okno košíku, košík i pokladna používají stejné vazby. Jednobarevné produkty a varianty pouze podle velikosti zůstávají s původní galerií.

Opakovatelný postup: `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/repair-variant-images.ps1` ověří návrh bez změny katalogu; `-Apply` nejprve soukromě zálohuje databázi a modul, doplní pouze chybějící vazby, zaregistruje ochranu chybějících fotografií a zkontroluje všechny vazby i nativní detail/košík pro každou barvu. Mapa `scripts/variant-image-map.json` obsahuje kontrolní součty fotografií; změněná média, nové barvy či odlišné ruční vazby zastaví opravu k novému posouzení. Po novém importu se tento postup musí znovu ověřit a spustit; mapa není pravidlo odvozující barvu z pořadí obrázků.

U variant bez ověřené fotografie chrání `kafkahome/classes/VariantImages.php` zákazníka před automatickým zobrazením jiné barvy: v detailu, kartách a košíku použije nativní neutrální obrázek. Aktuálně jde o bílou u Tričko Punk Art (300), zlatou u Náušnice Vrut (546), bílou u Tričko Valentýn (1421) a černou u Náušnice Řetízek klips (2014). U části doplňků jsou dostupné pouze společné snímky více barev; ty zůstávají společnými skutečnými fotografiemi. Nové fotografie ani barvy se nevyrábějí. Zdrojové obrázky, ceny, sklad a kombinace se nemění.

Záloha před opravou: `local/private/migration-lab-20261018/import-runs/import-variant-images-20261019-210611-322/`. Obsahuje úplnou databázi, původní modul, kontrolní součty a `links-before.json` se seznamem přidaných vazeb. Cílený návrat vazeb poskytuje `scripts/apply-variant-images.php --restore=/lab/.../links-before.json` (návrh), s `--apply` odstraní pouze vazby přidané tímto během; ochrana chybějících fotografií je součást samostatně zálohovaného modulu.

Ověřeno: 3 680 kombinací, 1 150 nativních prezentací detailu/košíku a 204 prohlížečových kontrol. Desktop 1536 × 1024 a mobil 390 × 844: přepínání barev, všech osm velikostí uvedeného trička, odkazy na kombinace, obnovení stránky, dvě barvy stejného produktu v košíku, potvrzovací okno a další druhy zboží. Souhrn pokladny má správné obrázky obou barev a čitelné názvy barev/velikostí na obou rozlišeních; opravený kontrast je v `kafka.css`, načítání stylů má novou verzi v `head.tpl`. Náhradní obrázek chybějící fotografie v košíku ověřen navíc na desktopu. Testovací položky byly odstraněny; objednávka nebyla vytvořena. Opakovaný návrh oprav obsahuje nula nových vazeb.

## Rychlý náhled

Rychlý náhled produktů je v šabloně Kafka vypnutý. Přepisy `templates/catalog/_partials/miniatures/product-quickview.tpl` a `templates/catalog/_partials/quickview.tpl` odstraňují tlačítka pro desktop i dotykové ovládání a obsah AJAX náhledu. Obrázek a název produktu dál vedou na nativní detail. Změny těchto šablon do izolovaného shopu 8083 vyžadují zkopírování do jeho volume a vyčištění kompilovaných šablon; původní prototyp zůstává pro porovnání.

Všechny úpravy zadávej Codexu v projektu `C:\Users\Utrh\Documents\ChatGPT\KAFKA`. Původní frontend je závazná předloha. Převod na PrestaShop probíhá po jednotlivých komponentách; spuštěný základ není hotový výsledný design.

## Aktuální migrační pravidla — 14. 10. 2026

Platí [migration/ODDELENA-MIGRACE.md](migration/ODDELENA-MIGRACE.md). Ostrý web během přípravy pouze zálohujeme; konektor ani změny na něm neprovádíme. Migrační zdroj je izolovaná kopie na PC. Nový shop i zdroj mají vlastní databáze; kopie pro nácvik mají interní síť bez odchozího přístupu, náhled jde přes místní reverse proxy.

`backup.bat` nyní zahrnuje také `project.tar.gz` s celým projektem a Git historií, včetně dosud neverzované práce. Vylučuje existující zálohy, soukromé obnovovací klíče a pracovní zdrojovou kopii, která se zálohuje zvlášť. Zálohy se automaticky nemažou. Na výslovný pokyn uživatele byly 14. 10. jednorázově odstraněny nadbytečné a nahrazené pracovní archivy; zachované obnovovací sady a rozsah úklidu uvádí [migration/UKLID-14-10.md](migration/UKLID-14-10.md). Všechny klíče a přihlašovací soubory zůstaly zachované.

Vlastní kontrola obnovených obchodů: dvojklik `zkontrolovat-zalohy.bat`. Nový obnovený shop je na http://localhost:8081/, starý zdroj na http://localhost:8082/. Podrobný návod, přihlášení a pravidla šifrované zálohy jsou v [migration/OBNOVA-A-ZALOHY.md](migration/OBNOVA-A-ZALOHY.md). `zastavit-zkousky.bat` zastaví pouze tyto zkoušky, zachová jejich data. Běžný `stop.bat` ovládá jen `kafka-local`.

První obnova: `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/restore-rehearsal.ps1 -BackupPath local/backups/NAZEV-ZALOHY`. Každá obnova vyžaduje volný port 8081 a vytváří nové svazky, nikdy nepřepisuje vývojový shop. Přípravu starého zdroje a případné další nácviky provádí Codex; restart hotových kopií dělá `scripts/rehearsals.ps1` a nic znovu neimportuje.

## Každodenní spuštění

### Vlastní výroba — ceníky a poptávka, 19. 10. 2026

Koncové kategorie pod Merch → Vlastní potisk (včetně původní adresy `/cs/63-vyroba-naramku-s-vlastnim-motivem`) zobrazují službu: rozbalovací provedení s původními množstevními ceníky z nativních popisů a formulář pro motiv. Nemají produktový feed, třídění ani nákupní tlačítka. Identifikace vychází ze stromu `kafkahome` a skutečných kategorií, nikoli z pevných ID. Mateřský přehled deseti služeb zůstává navigací; ostatní katalog funguje dál běžným způsobem. Katalog, původní URL ani původní prototyp se nemění.

Formulář přijímá PDF/PNG/JPG do 10 MB, kontaktní e-mail a volitelnou poznámku. Server kontroluje token, velikost, příponu i skutečný MIME typ a omezuje opakovaná podání. Příloha se nezveřejňuje ve webových souborech. Pro připravené skutečné odesílání používá nativní PrestaShop `Mail::Send`, příjemce `PS_SHOP_EMAIL`, kontaktní e-mail jako Reply-To a šablony `kafkahome/mails/`. Při vypnuté poště mimo místní náhled formulář ohlásí neúspěch, nikoli falešné odeslání.

Na `localhost:8083` i `localhost:8080` se žádná pošta neodesílá: testovací zprávy včetně příloh se ukládají pouze uvnitř aplikačního kontejneru do `/tmp/kafka-custom-quotes/*.eml` (adresář 0700, soubory 0600). Potvrzení výslovně uvádí místní test. Jde o dočasnou testovací schránku, nikoli trvalé úložiště poptávek; před ostrým spuštěním se samostatně ověří skutečné doručení e-mailu na schváleném hostingu.

Nasazení do oddělené kopie: `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/deploy-custom-production.ps1` ukáže plán; s `-Apply` ověří interní síť, soukromě zálohuje modul/šablonu, zkopíruje změny, zaregistruje hook a obnoví šablony. Neimportuje SQL ani nepřepisuje katalog. První záloha před změnou je `local/private/custom-production-20261019-203854/frontend-before.tar.gz`. Implementace je v `prestashop/modules/kafkahome/classes/CustomProduction.php`, `views/templates/hook/custom-production.tpl` a šabloně/stylu Kafka.

### Klientské členění kategorií — 19. 10. 2026

Od 20. 10. 2026 mají homepage karty a otevřené podkategorie jemný rudý aktivní stav (nadpis/podbarvení, zvětšení 0,8 %, náklon 0,2–0,25°). Obvodový rámeček homepage karet zůstává šedý i při hoveru a rozbalení. Stejný styl používají boční kategorie i nativní filtry včetně mobilního panelu. Aktuální kategorie má serverové `aria-current="page"`; projektový JavaScript otevře její předky a zachová nativní Bootstrap rozbalování. Filtry se zvýrazňují podle skutečného `checked` stavu i po AJAX aktualizaci. Při omezeném pohybu jsou náklony a přechody vypnuté. Implementace je v `assets/css/kafka.css`, `assets/js/kafka-ui.js` a přepisu `ps_categorytree.tpl` šablony Kafka; nasazení do 8083 má soukromou zálohu `local/private/category-active-20261020-101313/theme-before.tar.gz`.

Nová navigace má přesně pořadí Oblečení, Módní doplňky, Stylové doplňky, Merch, Hudba a Výprodej. Merch obsahuje Kapely a Vlastní potisk; menší šipka vpravo u názvu rozbaluje další úroveň na homepage i v mobilním menu. Schválené názvy a pořadí spravuje `prestashop/modules/kafkahome/data/sections.json`. Od 20. 10. 2026 modul každou nalezenou větev automaticky doplní o aktivní přístupné podkategorie aktuálního katalogu v nativním pořadí; například Pánské, Dámské, Dětské a Nášivky mají stejný rozbalovací design jako Merch. Společný strom používá homepage, mobilní menu na všech stránkách i sekční vstupy. Opakované názvy se při hledání nativního odkazu rozlišují podle rodiče; odkazy používají nativní URL. Nasazeno do izolované kopie 8083 se soukromou zálohou původního modulu `local/private/category-navigation-20261020-100322/`.

Kliknutí na název rodičovské kategorie otevře její nativní stránku s přehledem podkategorií jako karet (Merch 2, Kapely 7, Vlastní potisk 10). Přehled vychází z aktivních nativních podkategorií, jejich názvů a URL; funguje i u ostatních větví. Rodič bez přímo přiřazených produktů zobrazuje přehled místo hlášky o prázdném katalogu. Pokud vlastní produkty má, zůstávají pod přehledem nativní seznam, filtry a stránkování. Úpravy jsou v `prestashop/themes/kafka/templates/catalog/`; styly mají verzované načítání kvůli prohlížečové cache. Průchody do kapely a do potisku triček byly ověřeny na desktopu i mobilu.

Pro izolovaný shop 8083 slouží `scripts/apply-client-categories.ps1`: bez přepínače pouze uloží návrh, s `-Apply` nejprve zálohuje databázi a frontend do soukromého `local/private/migration-lab-20261018/import-runs/`, potom nasadí modul/šablonu, upraví kategorie, zavolá `scripts/repair-category-memberships.ps1 -Apply` pro doplnění produktových vazeb a automaticky skončí úplnou SEO přejímkou. `scripts/check-client-categories.php` ověřuje názvy, rodiče, pořadí, veřejný přístup a šest hlavních kategorií. Běžící kopie 8083 má soubory ve vlastním volume, proto se na ni změny z projektu kopírují tímto postupem.

Existující odpovídající kategorie zachovávají ID, slugy, metadata, ostatní překlady a produktové vazby. Smíšené historické kategorie zůstávají aktivní pod technickým kořenem mimo nový strom Home; doplněné kompatibilní vazby zachovávají jejich úplný původní sortiment. Nové oddělené kategorie mají přiřazení podle původních podkategorií, jednoznačných názvů a prověřených popisů, včetně rozlišení obojků/kravat, knih/fanzinů a volných kovových hrotů/pyramid/cvoků. Výchozí kategorie produktů se nemění, a tím zůstávají původní produktové URL. Známá historická ID včetně kategorie 127 mimo SQL snapshot jsou rezervovaná a nesmějí být použitá pro nový obsah. Původní prototyp ani produkční obchod se tímto postupem nemění.

Přejímka změny: [SEO report](migration/evidence/seo-20261019-161159-480/REPORT.md) — PASS, 1 755 čerstvých HTTP odpovědí, 0 chyb směrování, 0 chyb přejímky; 44 historicky chybějících/neaktivních URL je evidováno odděleně. Přímé otevření všech 55 kategorií prošlo s HTTP 200. Desktop 1536 × 1024 a mobil 390 × 844: 39 kontrol navigace a šipek, dalších 51 kontrol nativních variant/košíku, bez chyb JavaScriptu. U všech 13 184 jazykových produktových záznamů zůstaly porovnávané URL a katalogové údaje beze změny. Výchozí soukromá záloha před změnou je v `local/private/migration-lab-20261018/import-runs/import-categories-20261019-160611-474/`.

Tato první přejímka byla nedostatečná pro obsah nového stromu: ověřila existenci odkazů, ne úplné zařazení katalogu. Nahrazuje ji [analýza a oprava produktových vazeb](migration/CATEGORIE-ANALYZA-A-OPRAVA-2026-10-19.md) a [nová SEO přejímka](migration/evidence/seo-20261019-172854-555/REPORT.md). Všech 3 194 aktivních katalogových produktů je dosažitelných přes nový strom, všech 55 kategorií má obsah a původní URL zůstaly stejné. Oprava přidala 613 vazeb, žádnou původní nesmazala. Samostatné opakování zařazení: `scripts/repair-category-memberships.ps1` pouze analyzuje; `-Apply` přidá soukromou zálohu, kontrolu neměnnosti katalogových dat, aplikaci jasných vazeb a úplnou SEO přejímku. Nejednoznačné aktivní produkty opravu zastaví; nic se nepřiřazuje naslepo. Návrh se vždy generuje z čerstvého lokálního katalogu, nepoužívat starý plán po nové migraci.

`kafkahome` 1.0.1 také normalizuje duplicitní produktová ID pouze pro čtecí endpoint `productcomments/CommentGrade`. Opravuje tím SQL chybu dodavatelského modulu Product Comments 8.0.1 při opakování stejného produktu mezi souvisejícími kartami; kód dodavatelského modulu a recenze se nemění. Registraci hooku zajistí lokální konfigurace nebo `scripts/ensure-category-compatibility.php`. Na desktopu a mobilu prošlo 87 kontrol kategorií a původních produktových cest a 51 kontrol variant/košíku, bez chyb konzole.

### Stav URL a SEO — audit 19. 10. 2026

Původní [SEO audit](migration/SEO-AUDIT-2026-10-19.md) a jeho HTTP evidence zůstávají zachované jako stav před opravou. Opravy směrování, českého prefixu, titulků, stránkování a sitemap na izolovaném `localhost:8083` řeší [aktuální realizační report](migration/SEO-OPRAVY-2026-10-19.md). Samostatný shop na 8080 není tímto cílem. Živý web má podle historické evidence nejméně 17 produktů a kategorii 127 mimo použitou zálohu; tato data se při SEO opravě nevytvářejí. Ostré přepnutí není schválené.

Původní audit byl čtecí. Podrobné původní URL a HTTP odpovědi jsou v `migration/evidence/seo-20261019/`; nepřepisovat je novým stavem. Nový lokální audit už nepřistupuje na produkci a všechny cílové HTTP odpovědi načítá znovu.

### Povinný krok po každém dalším SQL importu nebo migraci

Codex musí postupovat podle [SEO-POSTUP-PO-IMPORTU.md](migration/SEO-POSTUP-PO-IMPORTU.md). Čerstvé SQL se zpracuje až po tvém výslovném pokynu; původní soubor, jeho SHA-256 a obnovitelný checkpoint zůstanou zachované. PS 1.6 SQL se obnovuje do izolovaného zdroje a přenáší migrátorem, ne přímo do PS 9.

Existující soukromý dávkovací skript nyní volá verzovaný `scripts/run-isolated-batches.ps1`; po každém dokončení migrace automaticky následuje `scripts/complete-isolated-import.ps1`. Stejný krok je připojený k obnově přijatého SQL checkpointu. Pro jiný schválený importní adaptér použít `scripts/invoke-isolated-import.ps1`, který přidá soukromou zálohu cílové DB a automatickou SEO přejímku. Není dovoleno skončit po importu bez tohoto kroku nebo jej nahradit starým reportem. Samotná obnova zdrojového SQL ještě není převzatá cílová migrace.

Dokončovací postup bezpečně nasadí projektovou SEO implementaci do běžící kopie 8083, uloží dotčené soubory a nastavení, aplikuje pouze seznam v `scripts/migration-seo-policy.json`, ověří idempotenci, vytvoří sitemapu a čerstvě zkontroluje URL, přesměrování, titulky, canonical, hreflang a stránkování. Vlastní katalogová metadata zůstávají zachovaná. Prázdná registrační část potvrzení objednávky a zákaz indexace localhost zůstávají.

Samostatné opakování bez importu:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/complete-isolated-import.ps1
```

Výsledek každého běhu je v nové složce `migration/evidence/seo-YYYYMMDD-HHMMSS-mmm/REPORT.md`. `failures.csv` obsahuje chyby k opravě, `missing-content.csv` samostatně chybějící nebo neaktivní obsah. Chybný audit znamená nepřevzatý import. Zálohy pro cílený návrat zůstávají soukromě v laboratoři pod `seo-runs/`; `scripts/rollback-migration-seo.ps1 -Run IDENTIFIKATOR` nejprve jen ukáže plán. Celá databáze ani volumes se tímto rollbackem neobnovují nebo nemažou.

Místní sitemapový index: http://localhost:8083/1_index_sitemap.xml; `/sitemap.xml` má stejný obsah. Obsahuje pouze existující způsobilý obsah v aktivních jazycích CS/SK/DE. Místní XML s localhost se nikam neodesílá; ochranné `noindex` hlavičky zůstávají. Finální doména, veřejná indexace a Search Console patří do samostatně schváleného nasazení.

### České titulky systémových stránek — 19. 10. 2026

Potvrzení objednávky v šabloně Kafka už nenabízí dodatečnou registraci hosta ani volbu hesla. Úprava je v `prestashop/themes/kafka/templates/checkout/order-confirmation.tpl`, která přebírá nativní potvrzení a vyprazdňuje pouze blok `account_transformation_form`. Nasazeno také do samostatné testovací kopie na portu 8083; potvrzení, detaily objednávky a platební výstupy se nadále vykreslují nativně.

V migrační testovací kopii na `http://localhost:8083/` byly opravené anglické SEO titulky 26 systémových stránek české verze (např. potvrzení objednávky, košík a přihlášení). Jde o nativní nastavení v `meta_lang`, ne o text nahrazovaný JavaScriptem. Ostatní jazyky, URL a vlastní SEO názvy zůstávají zachované.

Opakovatelná oprava je v `scripts/localize-page-titles.php`. Spouští ji Codex přes PHP CLI uvnitř cílového shopu; skript povoluje jen domény `localhost:8080` a `localhost:8083`. Bez parametrů pouze vypíše návrh, `--apply --backup=/CESTA/MIMO/WEBROOT/nova-zaloha.json` uloží původní metadata a provede změny. Mění pouze přesně známé anglické výchozí titulky v češtině; ručně upravené názvy nepřepíše. Před dalším importem nebo při přípravě nasazení znovu ověřit titulky, protože migrace může metadata přepsat. Tento lokální pomocník se na produkci nespouští.

Záloha před touto opravou: `local/private/migration-lab-20261018/czech-page-titles-before-20261019.json`. Původní vývojový shop na portu 8080 se touto opravou nespouštěl ani neměnil.

### Testovací Český hosting — příprava 18. 10. 2026

Uživatel vyhradil `kafka.jirkanetrh.cz`, nastavil PHP 8.4, založil prázdnou DB a odstranil starý online prototyp. Kontrolní stránka podle výsledku dodaného uživatelem potvrdila PHP 8.4.26, potřebná rozšíření, zápis do složky a aktivní HTTPS. Po zvýšení paměti a času běhu uživatel potvrdil všechny kontroly zelené; přesné nové hodnoty nebyly nezávisle načtené. Toto je testovací cíl, nikoli změna produkčního WEDOSu. Starý migrační zdroj zůstává izolovaný na PC; propojení zakoupeného migrátoru dosud není ověřené.

První kontrola hostingu: nahrát pouze `local/packages/hosting-preflight-20261018/kafka-kontrola-b73d9e.php` do kořene této subdomény a otevřít přes HTTPS. Zdroj je v `scripts/hosting-preflight.php`; kontrola nevyžaduje hesla, nic nezapisuje do DB a nezobrazuje `phpinfo()`. Po převzetí výsledku soubor z FTP odstranit. Kontrola ověřuje jen PHP a limity; verze DB, připojení, ochrana přístupu, soubory shopu a fungování modulů se ověřují samostatně. SQL, archivy záloh a obnovovací klíče nepatří do veřejné FTP složky.

Po spuštění Dockeru byl vytvořen čerstvý export PS 9.1.5 s 19 demo produkty a šablonou Kafka. Testovací soubory jsou v `local/packages/hosting-20261018/upload/`, SQL a nové testovací přihlášení pouze v sousední `private/` (nikdy na veřejné FTP). Místní shop byl po krátkém pozastavení znovu spuštěn; jeho doména zůstala `localhost:8080`. Postup a hranice nasazení popisuje [migration/TESTOVACI-CESKY-HOSTING.md](migration/TESTOVACI-CESKY-HOSTING.md). Před zpřístupněním shopu nastavit ochranu přístupu a doplnit testovací DB; výchozí balíček má zákaz přístupu a údržbu. Lokální zálohy nepřepisovat. Spuštění na hostingu a propojení migrátoru dosud nejsou ověřené.

1. Spusť Docker Desktop a počkej na běžící engine.
2. Dvojklikem spusť `start.bat` v kořeni projektu.
3. První spuštění stáhne obrazy a nainstaluje nový obchod. Další spuštění použije uložený obchod a databázi; neprovádí nový import ani reset.
4. Po dokončení se otevře obchod, administrace a původní předloha. Zavření okna skriptu po dokončení obchod nevypíná.

Startovací skript před otevřením stránek vypne odesílání zákaznických e-mailů a nastaví průběžné překreslování šablon. Z bezpečnostních důvodů lze tuto konfiguraci použít jen na doméně `localhost:8080`.

Adresy:

- Nový obchod: http://localhost:8080/
- Administrace: http://localhost:8080/admin-dev/
- Původní homepage: http://localhost:4173/
- Původní detail: http://localhost:4173/product.html

Administrátorský e-mail a vygenerované heslo jsou v místním souboru `.env`: `ADMIN_EMAIL` a `ADMIN_PASSWORD`. Soubor můžeš otevřít v Codexu nebo Poznámkovém bloku. Heslo nepatří do chatu ani do Gitu. Změna hodnoty v `.env` po instalaci nemění heslo existujícího účtu; to se mění v administraci.

## Ovládací soubory

| Soubor | Účel |
| --- | --- |
| `start-migrace.bat` | Aktuální migrační shop 8083, zachová existující data |
| `start.bat` | Starší samostatné vývojové prostředí 8080 |
| `stop.bat` | Zastaví služby tohoto projektu, zachová data |
| `status.bat` | Ukáže stav služeb |
| `logs.bat` | Ukáže poslední provozní a instalační zprávy |
| `backup.bat` | Pozastaví obchod, uloží SQL, shopové soubory a konfiguraci do `local/backups/`, potom obnoví běh obchodu, pokud předtím běžel |

Tyto skripty ovládají pouze Compose projekt `kafka-local`, nikoli ostatní Docker projekty. Porty jsou dostupné jen z tohoto PC. Nejsou to veřejné adresy ani ostrý shop.

## Kde co najdeš

| Cesta v projektu | Co obsahuje |
| --- | --- |
| `graficky_navrh/index.html`, `graficky_navrh/product.html`, `graficky_navrh/product.css`, `graficky_navrh/app.js`, `graficky_navrh/product.js`, `graficky_navrh/theme-system.js` | Existující vizuální předlohu |
| `assets/` | Sdílené ilustrace, textury a produktové obrázky; zůstávají dostupné pro další vývoj |
| `graficky_navrh/scripts/` | Kontrolu původního detailu a generování katalogu předlohy |
| `design/VIZUALNI-ZADANI.md` | Pravidla vizuální věrnosti |
| `prestashop/themes/kafka/` | Naši novou nativní šablonu; soubory jsou přímo propojené s lokálním obchodem |
| `prestashop/themes/hummingbird/` | Oficiální základ Hummingbird 2.0; naše úpravy patří do vedlejší šablony Kafka |
| `prestashop/themes/kafka/config/theme.yml` | Nastavení šablony a vazbu na Hummingbird |
| `prestashop/themes/kafka/assets/css/kafka.css` | Vlastní styl nové šablony |
| `prestashop/themes/kafka/templates/` | Nativní Smarty šablony: hlavička, homepage, patička a načítání vlastních assetů |
| `prestashop/themes/kafka/modules/` | Úpravy vzhledu vyhledávání, účtu a košíku při zachování nativních funkcí |
| `prestashop/themes/kafka/assets/js/` | Pevná černá, ovládání vyhledávání a rozbalování kategoriálních karet |
| `prestashop/themes/kafka/assets/img/` | Grafika Kafka používaná skutečným obchodem |
| `assets/kafka-monster-client-logo.svg` | Oříznuté vektorové logo klienta s obloukovým nápisem, bez obdélníkového pozadí; kopie v šabloně se používá v hlavičce |
| `prestashop/modules/kafkahome/` | Vlastní modul šesti kategoriálních vstupů, navigace, servisních ikon, patičky a lokálních sekčních stránek |
| `prestashop/modules/kafkahome/data/sections.json` | Názvy, pořadí, podkategorie a aliasy pro napojení skutečného katalogu po migraci |
| `compose.yaml` | Verze služeb, propojení a porty |
| `scripts/local.ps1` | Logiku ovládacích BAT souborů |
| `scripts/configure-local.php` | Nastavení vývojové cache a vypnutí odesílání lokálních zákaznických e-mailů |
| `.env` | Místní přihlašovací údaje; není verzovaný |
| `migration/` | Audit, migrační plán a kontrolní podklady |

Jádro PrestaShopu, instalované moduly a nahrané shopové obrázky jsou v Docker volume `kafka-local_shopfiles`. Databáze je v `kafka-local_database`. Tyto volumes spravuje Docker Desktop ve svém Linuxovém prostředí, nejsou to běžné složky uvnitř projektu. Šablony Kafka a Hummingbird jsou oproti tomu skutečné složky v tomto projektu a Docker je připojuje přímo jako `/var/www/html/themes/kafka` a `/var/www/html/themes/hummingbird`.

Modul `kafkahome` je také přímo připojený z tohoto projektu do `/var/www/html/modules/kafkahome`. `start.bat` ověří jeho instalaci a zaregistrování hooků automaticky; ruční kopírování do Dockeru nepotřebuješ. Jádro neupravujeme kvůli designu. Naše šablona, databáze i zbytek shopových souborů jsou všechny potřeba pro kompletní obnovu; samotná kopie projektu nezálohuje Docker volumes.

## Jak zadávat změny Codexu

Například: „Uprav hlavičku nativního PrestaShopu podle původního index.html, zachovej mobilní chování a ověř výsledek.“ Uveď, jestli mluvíš o předloze nebo novém obchodu. Výchozí cíl implementace je nová šablona Kafka.

Soubory šablony se mění přímo na disku. Podle typu změny bude třeba obnovení stránky, vymazání cache PrestaShopu nebo sestavení CSS/JS; to udělá Codex. Restart celé instalace obvykle není potřeba.

Produkty, kategorie, ceny, sklad a objednávky jsou data v administraci, nikoli jednotlivé HTML soubory. Pro vývoj používáme dočasný katalog; první plný import jej může odstranit. Obsahová doplnění připravujeme odděleně až do ověření finální synchronizace.

## Zálohy a běžné potíže

- `stop.bat`, zavření prohlížeče a restart počítače data nemažou.
- Nepoužívej `docker compose down -v` ani mazání těchto volumes v Docker Desktopu: tím by se smazala lokální databáze a shopové soubory.
- Před migrací nebo výraznou změnou databáze spusť `backup.bat` a nech Codex ověřit obnovu na oddělené kopii. Zálohy jsou v `local/backups/`; tato složka je mimo Git. Obsahují i citlivou konfiguraci a nesmí se veřejně sdílet. Kontrolní součty ověřují integritu souborů, nikoli samy o sobě obnovitelnost.
- Pokud Docker neběží, zapni Docker Desktop a znovu spusť `start.bat`.
- Pokud je port 8080 nebo 4173 obsazený, nech Codex zjistit konflikt. Změna domény instalovaného obchodu musí být promítnuta i do databáze.
- Pokud instalace selže, otevři `logs.bat` a nech Codex chybu opravit; nemaž data jako první krok.
- Lokální e-maily, platby a crony nenastavujeme na produkční účty. Test reálných callbacků a hostingu přijde později.

## Další milníky

Pro lokální přenos přes MigrationPro začni přehledem [Lokální MigrationPro — 18. 10. 2026](migration/LOKALNI-MIGRATIONPRO-18-10-2026.md). Import dosud není provedený; nejprve vyřešit licenci, izolované propojení a čerstvý zdroj.

1. Ověřit nainstalovanou verzi, PHP, Hummingbird, administraci a opakované spuštění.
2. Aktivovat šablonu Kafka a převést společné prvky vzhledu.
3. Převést detail, kategorie a homepage podle předlohy; kontrolovat desktop i mobil.
4. Přenést data přes zakoupený migrátor a vyhodnotit kontrolní protokol.
5. Doplnit integrace a absolvovat neveřejnou zkoušku na WEDOS podle migračního plánu.

## Ověřený stav přípravy — 12. 10. 2026

- Nainstalovaný PS 9.1.5, skutečné PHP 8.3.28, Hummingbird 2.0 a aktivní odvozená šablona Kafka.
- Česká administrace: přihlášení s údaji z `.env` prošlo.
- Demo katalog má 19 produktů. Produkt se přidá do skutečného košíku a objeví se na stránce košíku bez chyb JavaScriptu.
- `start.bat` byl spuštěný i přímo přes Windows CMD. Po zastavení a opětovném spuštění zůstalo všech 19 produktů, aktivní šablona Kafka i vypnuté e-maily.
- Obchod i předloha byly otevřené v Chrome na desktopu 1536 × 1024 a mobilu 390 × 844. Předloha má také ověřené přepínání barev a variant.
- Zálohovací skript vytvořil SQL, shopový archiv a konfiguraci; kontrolní součty souhlasí. SQL bylo zkušebně obnovené do samostatné databáze, která měla 19 produktů a šablonu Kafka. Archiv prošel kontrolou gzip a obsahuje shopovou konfiguraci i projektovou šablonu. Kompletní spuštění obnovené kopie obchodu zatím není nacvičené.
- Odesílání zákaznických e-mailů je vypnuté (`PS_MAIL_METHOD=3`). Platby a skutečná objednávka zatím nejsou nakonfigurované ani testované.
- Na portu 8080 je už první vizuální vrstva Kafka: černé pozadí, původní fonty, textury, logo, hlavička, punkový hero, tmavé karty, detail a patička. Katalog zůstává demonstrační. Další vizuální rozdíly a připravenost na migraci popisuje [VIZUALNI-PREVOD.md](design/VIZUALNI-PREVOD.md).
- Homepage nyní používá šest vlastních kategoriálních karet a původní SVG ikony, bez generických produktových sekcí a newsletteru. Veřejné téma je pevně černé, bez přepínače. Část sekcí je do skutečné migrace prázdná; jejich odkazy zůstávají uvnitř nového obchodu.

Při lokálním spuštění je vypnuté slučování a minifikace CSS/JS, aby se úpravy přes Codex načítaly přehledně a bez starých sestavených souborů. Produkční nastavení výkonu se vyřeší před nasazením. Vlastní styl a skripty se načítají v `templates/_partials/head.tpl` za původními styly; originální assety Hummingbirdu zůstávají součástí obchodu.

Původní frontend je oddělený v `graficky_navrh/`. Reference dál běží na portu 4173; po změně cest stačí znovu spustit `start.bat`. Kontrola předlohy: `node graficky_navrh/scripts/qa-product.cjs`. Sdílenou grafiku v `assets/` předloha načítá relativně přes `../assets/`.

## Čitelné a otevřené filtry — 20. 10. 2026

Po posledním upřesnění uživatele jsou ve výchozím stavu sbalené všechny skupiny včetně Ceny; nativní otevření skupiny s aktivním filtrem zůstává zachované i po AJAX aktualizaci. Přepis `prestashop/themes/kafka/modules/ps_facetedsearch/views/templates/front/catalog/facets.tpl` zachovává původní data, odkazy a slider; sekce lze ručně sbalit. Cenový rozsah má vlastní kontrastní rámeček. Od 1200 px zabírá boční panel kategorie 29 % šířky. CSS verze `20261020-16`. Nasazeno pouze do izolované 8083 po záloze `local/private/facets-visible-*/theme-before.tar.gz`; při dalším nasazení zahrnout nový přepis modulu, CSS a head.tpl a obnovit Smarty cache.

Ověřeno existujícím Chrome/Playwright (Browser plugin není dostupný), desktop 1536 × 1024 a mobil 390 × 844: čitelná cena, otevřené volby, desktopové tažení posuvníku změnilo URL a aktivní filtr na 10–354,59 Kč a počet produktů na 1101; obnovení zachovalo rozsah. Mobilní panel se otevře a posuvník mění cenu. Bez vodorovného přetékání a chyb JavaScriptu. Ochrana X-Robots-Tag zůstala aktivní. Varianty/košík se touto úpravou nemění a nebyly znovu testovány. Původní prototyp zůstává zachovaný; širší panel a otevřená Cena jsou výslovně požadovaná odchylka. Následná kontrola na desktopu i mobilu potvrdila sbalení všech ostatních skupin a funkční ruční otevření Kategorie.

## Zavření ruší filtr a filtry Hudby — 20. 10. 2026

`kafka-ui.js` verze `20261020-1` při nativní události sbalení skupiny odebere její aktivní volby pomocí nativních URL a `updateFacets`. U více voleb počká na aktualizaci panelu a pokračuje další volbou; ostatní skupiny zachová. Po AJAX obnově udržuje ručně sbalené/otevřené skupiny. Sbalení Ceny ruší cenové omezení a ponechává ji zavřenou. Zavření celého mobilního panelu filtry neruší.

Hudba a CD neměly přiřazenou nativní šablonu filtrů. `scripts/configure-music-filters.php` doplní pouze chybějící přiřazení Hudby a jejího aktuálního podstromu ke stávající nejnovější šabloně hlavní kategorie; bez parametrů analyzuje, `--apply --backup=/soukroma/cesta.json` nejprve zálohuje tabulky konfigurace mimo webroot. Striktně jen izolovaný localhost:8083 / target-db. Nemění katalog ani nepřidává domyšlené atributy. Nasazeno do 8083 se zálohou souborů a nastavení v `local/private/facet-close-*/`. Opakované spuštění potvrdilo nulovou změnu. Aktuální Hudba i CD nabízejí dostupnost, výběr a cenu podle skutečných dat.

Chrome/Playwright, desktop 1536 × 1024 a mobil 390 × 844: zavření Kategorie odstranilo současně Fanziny i Hrnky, zachovalo cenu a sbalený stav. Zavření Ceny odstranilo cenový parametr. Mobilní zavření ceny u Hudby také odstranilo omezení a zachovalo sbalení. Ověřena nativní dostupnost Hudby a její zrušení zavřením. Bez chyb JavaScriptu a mobilního vodorovného přetékání. Košík a varianty nebyly znovu testovány.
## Odstranění pouze nadpisu „Vyber si“ — 20. 10. 2026

Odstraněný je pouze nadpis „Vyber si“. Všechny karty podkategorií, jejich odkazy a původní desktopové i mobilní styly zůstávají. Sekce nemá odkaz aria-labelledby na odstraněný nadpis. Nasazovat category-header.tpl, subcategories.tpl, kafka.css a head.tpl (CSS 20261020-19) a vyčistit Smarty cache.



Výchozí Cena — poslední upřesnění 20. 10. 2026: odstraněno automatické otevření Ceny. Otevření skupiny samo nenastavuje cenové omezení; to vzniká až změnou rozsahu. Ověřeno na 8083 desktop 1536 × 1024 a mobil 390 × 844: výchozí aria-expanded=false, žádné aktivní filtry ani parametr q; ruční otevření funguje a stále nefiltruje. Zavření aktivního filtru nadále ruší omezení. Záloha před změnou v local/private/price-opt-in-*/facets-before.tpl.
