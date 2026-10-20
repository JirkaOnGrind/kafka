# Kafka Monster: souborová analýza FTP a doporučení pro PrestaShop 9

Poslední inventura: **09. 10. 2026 01:31, Europe/Prague**. Zdroj: `C:\Users\Utrh\Desktop\kafkaFTP\www\domains\kafka.monster`.

**Stav úplnosti stažení není potvrzený.** Chybějící položky v kopii neznamenají, že chybí na živém webu. FTP kopie je pouze čtena. Nebyl spuštěn její PHP kód, změněna databáze ani živý web.

## Závěr pro rozhodnutí

**Doporučuji migraci do čistého PrestaShopu 9.2.0, nikoli přepsání staré instalace novými soubory.** Katalog a historie jsou podle předchozího SQL auditu rozumně přenositelné. Největší nejistota je v integracích nových platebních/dopravních sestav, nikoli v počtu produktů. Účel všech sedmi starých overrides už vysvětluje samostatný ruční report. Schválený vlastní frontend převést do nativní šablony na základě Hummingbird 2.1.x. Warehouse pro tento rozsah nekupovat bez konkrétního důvodu, který by prokazatelně šetřil vývoj.

Migrační modul je nástroj na data, nikoli převodník zdrojového kódu modulů a checkoutu. PrestaHero MigrationPro Ultimate zůstává doporučeným kandidátem, s nákupem až po potvrzení přesné 9.2.0, zdrojového PHP a licence pro staging → produkci. Pilot je nutný před závazným termínem přepnutí.

[Stabilní PS 9.2.0](https://github.com/PrestaShop/PrestaShop/releases/tag/9.2.0), [Hummingbird a kompatibilita](https://github.com/PrestaShop/hummingbird/releases), [aktuální migrátor](https://prestahero.com/149-prestashop-better-upgrade-and-migrate-tool.html).

## 1. Rozsah dostupné kopie

- Dostupné soubory: **109,944**, celkem **1531.38 MiB** (jen shop, nikoli celé FTP).
- Přítomné hlavní složky: classes, controllers, config, modules, themes, img, download, upload, override, mails, pdf, tools.
- Dosud nedostupné hlavní složky: žádné z hlavních adresářů.
- Kořen aplikace je `www/domains/kafka.monster`; nejde o samotný vrchní adresář `www`.

| Složka | Soubory | MiB | Nulová velikost |
| --- | --- | --- | --- |
| Adapter | 12 | 0.02 | 0 |
| Core | 19 | 0.05 | 0 |
| [root] | 13 | 2.34 | 0 |
| cache | 39264 | 206.72 | 4 |
| classes | 228 | 3.50 | 0 |
| config | 26 | 1.19 | 0 |
| controllers | 138 | 2.89 | 0 |
| css | 3 | 0.00 | 0 |
| download | 11 | 4.58 | 0 |
| guzg3arkfjazszdr | 977 | 10.18 | 0 |
| img | 46801 | 1119.82 | 0 |
| js | 739 | 3.75 | 1 |
| localization | 84 | 0.39 | 0 |
| log | 3 | 0.00 | 0 |
| mails | 306 | 0.89 | 0 |
| modules | 19441 | 151.90 | 41 |
| override | 31 | 0.04 | 0 |
| pdf | 39 | 0.08 | 0 |
| themes | 1009 | 4.67 | 14 |
| tools | 767 | 8.91 | 0 |
| translations | 27 | 4.26 | 0 |
| upload | 4 | 5.20 | 0 |
| webservice | 2 | 0.00 | 0 |

Údaje jsou momentka živě se měnící lokální kopie, nikoli přesná velikost serveru. Při stahování vznikají krátkodobě prázdné či rozepsané soubory. Audit před a po čtení kontroluje velikost a timestamp a takto zachycené změny souboru vynechává. Potvrzení dokončené fronty a nulových nevyřešených přenosů ve FTP klientovi je stále potřeba; samotná stabilita počtu souborů není důkaz úplnosti.

## 2. Konfigurace a verze

| Kontrola | Výsledek |
| --- | --- |
| config/settings.inc.php | dostupný |
| Verze aplikace _PS_VERSION_ | 1.6.1.13 |
| Prefix databáze | ejf6_ |
| SQL verze databáze | 1.6.1.13 |
| Výchozí deklarace _PS_MODE_DEV_ | false |
| Skutečné PHP webu | z FTP nelze spolehlivě potvrdit; ověřit ve správě WEDOS |

Flags jsou statické deklarace, nikoli ověření běžícího prostředí. V referenční konfiguraci se SQL debug přepíná podle _PS_MODE_DEV_; výchozí false odpovídá vypnutému debug režimu, pokud konstantu předem nezmění jiný kód. Hesla DB, cookie klíče a šifrovací klíče nejsou přenášeny do výstupů. Archiv se starou konfigurací uchovat neveřejně. Přihlášení starým zákaznickým heslem ověřit pomocí podporovaného mechanismu migrátoru a testovacího účtu; nepřepisovat naslepo klíče nové aplikace.

## 3. Porovnání s oficiálním core 1.6.1.13

Pro porovnání je uložen pouze čtený archiv oficiálního tagu GitHub. SHA-256: `039ca59bbcdeff75d1688e50bc11364deda258e0563c1ba91899161b8a9eee3e`. [Zdroj oficiálního tagu](https://github.com/PrestaShop/PrestaShop/tree/1.6.1.13).

| Metrika | Počet |
| --- | --- |
| Referenční PHP soubory v classes/controllers/config/Core/Adapter + vybrané root soubory | 414 |
| Přesná shoda | 414 |
| Shoda kromě konců řádků | 0 |
| Obsahové rozdíly ve stažené části | 0 |
| Referenční soubory zatím nenalezené v lokální kopii | 0 |
| Dodatečné PHP soubory ve sledovaných core adresářích | 0 |

**Celý sledovaný rozsah jádra se shoduje s referencí.** Nejde o porovnání všech souborů instalace; moduly, overrides, šablony, maily a PDF se posuzují samostatně. Při odlišné skutečné verzi aplikace musí být baseline změněna. Každý obsahový rozdíl vyžaduje posouzení funkce a způsobu náhrady v nové verzi.

## 4. Overrides — kritická část upgrade

Cache registr tříd odkazuje na **7 override souborů**. Je to důkaz o obsahu registru cache, nikoli jistota současné existence či aktivace každého souboru. Registr může být zastaralý. Po stažení je potřeba zkontrolovat samotný kód, původ modulu a metody.

| Soubor odkazovaný cache | Proč ho musíme posoudit |
| --- | --- |
| override/classes/Hook.php | Vykonávání hooků a kompatibilita modulů |
| override/classes/Product.php | Produkt: ceny, dostupnost a varianty |
| override/classes/order/Order.php | Objednávky / checkout / platby |
| override/controllers/front/AuthController.php | Registrace, přihlášení nebo antispam |
| override/controllers/front/ContactController.php | Kontaktní formulář nebo antispam |
| override/controllers/front/OrderController.php | Objednávky / checkout / platby |
| override/controllers/front/OrderOpcController.php | Objednávky / checkout / platby |

Dosud načtené soubory overrides: **7**. Rozpis tříd a metod je v `evidence/ftp-overrides.csv`.

**Postup:** každému netriviálnímu override přiřadit majitele a obchodní funkci. Pokud jej vytváří starý modul, nahrazovat novým modulem podporujícím 9.2. Vlastní nezbytnou funkci implementovat v samostatném modulu přes podporované hooky. Do nové instalace nepřenášet starou složku override a starý cache registr.

## 5. Moduly, platby a doprava

SQL obsahuje 85 modulů, 78 přiřazených shopu. V kopii je zatím 118 adresářů modulů; hlavní PHP soubor se zatím našel u 78 přiřazených modulů. Zbytek se během přenosu neoznačuje jako ztracený.

| Modul | SQL verze | Verze podle PHP | Hlavní soubor v kopii |
| --- | --- | --- | --- |
| bankwire | 1.1.2 | 1.1.2 | ano |
| dm_cashondelivery | 3.3.0 | 3.3.0 | ano |
| dm_comgate | 3.3.2 | 3.3.2 | ano |
| gsitemap | 3.2.0 | 3.2.0 | ano |
| mailalerts | 3.7.0 | 3.7.0 | ano |
| mergado | 4.1.0 | 4.1.0 | ano |
| packetery | 2.0.9 | 2.0.9 | ano |
| ps_checkout | 6.3.5.1 | 6.3.5.1 | ano |
| shaim_cookies_eu | 2.3.1 | 2.3.1 | ano |
| shaim_dpdparcelshop | 2.3.8 | 2.3.8 | ano |
| shaim_recaptcha_v3 | 2.6.1 | 2.6.1 | ano |
| usercentrics | 1.0.13 | 1.0.13 | ano |

Úplná inventura: `evidence/ftp-modules.csv`. Strategie nových modulů a zdroje kompatibility: `module-matrix.csv`.

Nově dostupné dm platební moduly a kořenové maily mají samostatný statický rozbor v [PLATBY-A-MAILY-FTP.md](PLATBY-A-MAILY-FTP.md). Ten dokumentuje autora, TLS, callbacky, skutečné dobírkové sazby a Order override; jeho datovaná zjištění upřesňují předběžné body níže.

Nově dostupná Packeta a Mergado jsou ručně rozebrané v [DOPRAVA-FEEDY-FTP.md](DOPRAVA-FEEDY-FTP.md): CSV expedice, vlastní historie poboček, feed cron, zapisující cenový import, TLS a tři modulové kandidáty na overrides. Datum ručního rozboru může být pozdější než tato souborová inventura.

Všech sedm kořenových overrides, DPD, reCAPTCHA a cookie filtr jsou vysvětlené v [OVERRIDES-DPD-ANTISPAM.md](OVERRIDES-DPD-ANTISPAM.md). Závěrečné rozhodnutí a nejlehčí postup: [ZAVER-A-POSTUP.md](ZAVER-A-POSTUP.md). Srovnání theme/PDF/tools a přednost mailových šablon doplňuje [FTP-KOD-A-INTEGRACE.md](FTP-KOD-A-INTEGRACE.md).

- **Comgate a dobírka:** původní dm moduly dodal David Mrózek / Valašinec; vybrat podporovaný cílový modul, ověřit příplatek, měny, callbacky a jejich opakování. Historické platební reference a otevřené platby zachovat; nepočítat s tím, že nový modul automaticky přečte vlastní tabulky starého.
- **Packeta a DPD:** oddělit výběr pobočky od tvorby zásilek a štítků. Testovat skutečný checkout a uložené ID místa v objednávce; zachovat informace rozpracovaných zásilek.
- **Mergado / marketing:** určit skutečně používané feedy, měření a cron. Důležitá jsou stabilní ID produktů i kombinací a zachování měn; staging nesmí posílat živé feedy nebo konverze.
- **Antispam / cookies:** nahradit staré závislosti na Auth/Contact overrides, vybrat jeden zamýšlený souhlasový mechanismus a zkontrolovat pořadí načtení měření.
- **ps_checkout:** přítomnost modulu není rozhodnutí jej používat. V dodaném SQL nemá historické objednávky; nezavádět jej jako novou platební cestu bez požadavku klienta.

## 6. Obrázky, přílohy a další média

| Kontrola originálů vůči SQL exportu | Počet |
| --- | --- |
| Očekávané originály produktových obrázků | 4962 |
| Nenulové originály nalezené v kopii | 4962 |
| Zatím nedotažené nebo chybějící originály | 0 |
| Z toho obrazové záznamy aktivních produktů | 0 |
| Přílohy v SQL | 9 |
| Nalezené nenulové přílohy | 9 |

Kontrola respektuje standardní adresáře obrázků podle číslic ID i starší formát `img/p/PRODUCT-IMAGE.jpg`. Nenulová velikost je základní kontrola, nikoli dekódování kvality každého obrázku. Po dokončení přenosu zopakovat kontrolu poškození originálů. Thumbnaily regenerovat pro nový theme; nepřebírat staré cache a hromady odvozených velikostí jako důkaz originálů.

Předchozí SQL zjistil nulové vazby obrázek–kombinace. To je samostatný datový úkol: změna fotografie podle barvy u všech variant se nevyřeší pouhým překopírováním img.

Kontrola dekódování z **09. 10. 2026 00:35, Europe/Prague**: **4968 / 4968** dostupných originálů s čistě číselným názvem JPG prošlo ověřením struktury a načtením pixelů. Prázdné: **0**, nestabilní/nečitelné: **0**, chyby dekódování: **0**. Jde o samostatnou momentku dostupných img/p originálů, nikoli potvrzení shody s celým SQL katalogem. Během přenosu mohou být prázdné či nedotažené soubory; kontrolu zopakovat po dokončení. Starší formát PRODUCT-IMAGE.jpg tento dílčí dekódovací audit nezahrnuje. Podrobnosti: evidence/media-integrity.json.

| Další číselné originály JPG | Dostupná složka | Nalezené | Dekódované | Prázdné | Chyby dekódování |
| --- | --- | --- | --- | --- | --- |
| categories | ano | 42 | 42 | 0 | 0 |
| manufacturers | ano | 0 | 0 | 0 | 0 |
| suppliers | ano | 1 | 1 | 0 | 0 |
| stores | ano | 0 | 0 | 0 | 0 |

Počty dalších originálů nejsou kontrola úplnosti vůči databázi: obrázek kategorie či značky může být volitelný. Odvozené velikosti a jiné formáty se tímto průchodem neověřují.

Kontrola formátu příloh z **09. 10. 2026 00:18, Europe/Prague**: z 9 souborů s neprůhledným 40znakovým hexadecimálním názvem prošlo základním strukturálním ověřením **9**; PDF: 8, rastrové obrázky: 1, chyby ověření: 0. PDF kontrola ověřuje načtení dokumentu a stránek, nikoli úplné vykreslení či význam obsahu. Obrázky prošly dekódováním. Nejde o potvrzení úplnosti FTP ani hashové shody se serverem. Názvy souborů ani obsah příloh nejsou exportovány. Podrobnosti: evidence/attachment-format.json.

## 7. SEO, robots a sitemap

| Soubor | URL / odkazů na sitemap | Obrazových URL | ID produktů | Nejnovější lastmod |
| --- | --- | --- | --- | --- |
| 1_cs_0_sitemap.xml | 2334 | 2261 | 2237 | 2025-04-11T07:45:37+00:00 |
| 1_index_sitemap.xml | 2 | 0 | 0 | 2025-04-11T08:03:10+00:00 |
| 1_sk_0_sitemap.xml | 2334 | 2261 | 2237 | 2025-04-11T07:45:37+00:00 |

robots.txt dostupný: **ano**. Blokace celého webu: **nenalezena**. Direktiva Sitemap pro kafka.monster: **nenalezena**.

Stažené sitemap mají lastmod z dubna 2025 a index nyní odkazuje na CS a SK; jejich katalog nepokrývá dnešní stav SQL automaticky. Datum lastmod není totéž co čas generování nebo skutečná poslední změna. Po migraci sitemap znovu generovat pro všechny cílové aktivní jazyky včetně DE, ověřit indexovatelnost CMS a odeslat správnou sitemap do Search Console. Nevycházet ze starého XML jako z úplného seznamu současných produktů.

Nové routy mají zachovat ID a slugs, včetně výchozí kategorie produktu. Případné nezbytné změny musí mít konkrétní 301 mapu. První verze šestice navigačních vstupů může fungovat jako rozcestníky nad zachovaným katalogem. Samotné hash routy prototypu nejsou produkční SEO adresy katalogu.

## 8. Hosting a staging u WEDOS

Z FTP nelze potvrdit aktuální PHP, memory_limit, tarif, CLI, výkon, cron ani DNS oprávnění. Uživatel potvrdil přístup do WEDOS a pokračování u tohoto poskytovatele. U běžného sdíleného hostingu platí PHP pro celý hosting; samotná subdoména nedokazuje izolaci. Preferovat samostatnou hostingovou službu u WEDOS pro novou aplikaci, pokud tarif nemá potvrzenou jinou izolaci. [Vyjádření podpory k PHP](https://help.wedos.cz/otazka/muze-byt-na-subdomene-provozovana-jina-verze-php/94969/answer/94971/), [nastavení PHP](https://kb.vedos.cz/en/webhosting-php/).

Cílové PHP vybrat podle průniku podpory konkrétního PS 9.2, migrátoru, plateb, výdejních míst a šablony. Dřívější návrh PHP 8.4 je pracovní kandidát, nikoli ověřené nastavení tohoto serveru. Nové PHP nikdy nepřepínat na službě se starým obchodem během vývoje. [Požadavky PS 9](https://devdocs.prestashop-project.org/9/basics/installation/system-requirements/), [composer PS 9.2.0](https://github.com/PrestaShop/PrestaShop/blob/9.2.0/composer.json).

## 9. Moje hodnocení a pořadí dalších prací

| Oblast | Hodnocení nyní | Podmínka pokračování |
| --- | --- | --- |
| Standardní data | Dobrá migrovatelnost; střední náročnost ověření | Pilot s ID, účetními součty, variantami a hesly |
| Přímý upgrade staré instalace | Nedoporučuji | Čistý cílový shop místo přepsání starého |
| Core a overrides | Sledovaný core shodný; sedm kořenových overrides vysvětleno | Nahradit staré zásahy aktuálními moduly a ověřit checkout |
| Platby a výdejní místa | Největší integrační riziko | Přesné moduly pro 9.2 + reálný test objednávky |
| Nový frontend | Proveditelný; potřebuje vývoj | Nativní theme, skutečné ceny/varianty/košík |
| Úplnost FTP | Nepotvrzená | Dokončená fronta + ověření souborů a médií |
| Termín / odstávka | Nelze závazně slíbit | Benchmark pilotu a delta synchronizace |

1. Dokončit a ověřit lokální zálohu. Získat screenshot tarifu a PHP z WEDOS; nemusíme čekat na nalezení obrazovky Informace ve starém PrestaShop adminu.
2. Použít dokončenou mapu sedmi overrides a integrací v ručních reportech. Potvrdit s klientem skutečné používané funkce, licence, expedici a obchodní pravidla.
3. Ověřit migrátor s dodavatelem a připravit chráněný staging bez skutečných e-mailů, plateb a feedů. Koupit pouze licence potřebné pro zvolenou sestavu.
4. Pilotně přenést katalog, jazyky, měny, zákazníky, historii, ceny, slevy a média. Nepřenášet starou návštěvnickou statistiku a cache; ověřit dvě osiřelé kombinace a odstraněné produkty v historických objednávkách bez ztráty účetních údajů.
5. Napojit frontend na skutečné PrestaShop entity. Nativní košík a standardní checkout použít jako výchozí funkční cestu; OPC až po ověření celé sestavy.
6. Potvrdit obchodní pravidla: doprava zdarma 1 500 / 1 699 / 3 000 Kč, dobírka, termín odeslání, daně, zakázky a způsob skladové dostupnosti.
7. Nacvičit finální synchronizaci nových i změněných záznamů. Zvlášť sklad a stavy objednávek; nepřepsat nově doplněná data nebo zakázky nevhodným režimem plného importu.
8. Po akceptaci připravit konkrétní přepnutí a rollback včetně nových objednávek a plateb přijatých po spuštění. Starý obchod nechat jako neveřejný archiv.

Původní odhad 8–18 pracovních dnů je orientační odborný odhad, nikoli nabídka ani garance. Může se změnit podle funkce overrides, integrací a kompletnosti zálohy. Délku odstávky určí nácvik, nikoli celková velikost FTP cache.

## 10. Co automatická inventura nenahrazuje

- Neověřuje běh skutečného shopu, podporu PS 9.2 konkrétním zakoupeným modulem nebo úspěšné callbacky.
- Nezná stav FTP fronty ani seznam všech vzdálených souborů. Stabilní kopie může být i zastavený přenos.
- Neprovádí malware certifikaci ani obnovu zálohy. Nevykonává nedůvěryhodný PHP.
- Pro zásahy do živého webu, DNS nebo databází je potřeba samostatná fáze implementace; současné oprávnění je analýza a příprava.

Technické důkazy: `evidence/ftp-audit.json`, `evidence/ftp-progress.jsonl`, `evidence/ftp-modules.csv`, `evidence/ftp-overrides.csv` a případně `evidence/ftp-pending-images.csv`. Samostatné lidské posouzení kódu průběžně doplňuje `FTP-KOD-A-INTEGRACE.md`.

Navazující dokumenty: `MIGRACNI-ANALYZA.md`, `STAV-A-NAKUPY.md`, `OTAZKY-NA-RANO.md` a `SPUSTENI-A-KONTROLY.md`. Tento soubor se při další inventuře generuje znovu.
