# Lokální migrace Kafka Monster přes MigrationPro — průzkum 18. 10. 2026

Tento přehled doplňuje `ODDELENA-MIGRACE.md`, nemění jeho bezpečnostní hranice. Zadání nyní znamená migraci a test na PC. Připravený balíček pro `kafka.jirkanetrh.cz` je jiný testovací cíl; pro první lokální pilot ho nepotřebujeme. Produkční přepnutí není součást tohoto kroku.

## 1. Výsledek průzkumu

Pro projekt je vybraný **PrestaHero MigrationPro Ultimate**. Přenos PS 1.6 → 9 výrobce deklaruje, ale skutečný import naší kopie dosud neproběhl. Před nákupem/importem je nutné uzavřít podporu izolovaného Docker propojení a aktivaci licence. Podobně pojmenovaný MigrationPro z Marketplace je jiná produktová řada; nepoužívat pro něj zde uvedené režimy, cenu a konektor bez ověření konkrétního balíčku.

Aktuální produktová stránka při přímém načtení ukazuje verzi **5.0.2**, cenu **139,99 EUR**, podporu PS 1.5.x–9.x a rok technické podpory. Konečná cena podle objednávky. Vyhledávací index současně vracel starší 4.6.7 a slevovou cenu; při nákupu ověřit skutečné vydání ke stažení. Přímý ZIP z účtu je podporovaná možnost instalace, není nutné kvůli stažení připojovat starý zdroj k PrestaHero Connect. [Produkt a changelog](https://prestahero.com/149-prestashop-better-upgrade-and-migrate-tool.html).

**Dva neuzavřené rozpory v podkladech výrobce:**

- FAQ uvádí nutnost veřejně dostupného zdroje i cíle; manuál naopak používá ve spojovacím příkladu localhost. To není důkaz, že funguje naše konkrétní izolovaná síť, stahování médií a případná licenční kontrola. Potřebujeme odpověď výrobce a poté skutečný connection test.
- FAQ uvádí jednu produkční a jednu vývojovou/testovací instalaci na licenci, ale produktová stránka stručně uvádí jednu doménu. Potvrdit registraci pro `kafka.monster`, localhost a případný pozdější test na `kafka.jirkanetrh.cz`; nepředpokládat automaticky několik souběžných testovacích instalací.

[FAQ MigrationPro](https://prestahero.com/es/help-center/migrationpro-ultimate/361-faq-migration?category_rewrite=migrationpro-ultimate&id_hc_post=361&module=helpcenter), [manuál výrobce](https://prestahero.com/download-document?idLang=1&idProduct=149). Dotaz pro podporu už existuje v `DOTAZ-PRESTAHERO.md`; nic nebylo odesláno.

## 2. Co máme skutečně připravené

| Součást | Stav a význam |
| --- | --- |
| Nový cíl | Docker projekt `kafka-local`, konfigurace PS 9.1.5 / PHP 8.3 / MySQL 8.0, vlastní Kafka nad Hummingbirdem 2.0, adresa `http://localhost:8080/` |
| Aktuální běh | Dne 18. 10. read-only kontrola Dockeru: nový shop a DB běží, DB zdravá; předloha a zdrojová/obnovovací kopie jsou zastavené |
| Starý zdroj | Obnovený PS 1.6.1.13 / PHP 7.1.33, vlastní DB, interní síť; po spuštění náhled na `http://localhost:8082/` |
| Obnovovací kopie cíle | Oddělená kopie na `http://localhost:8081/`; není to pracovní cíl importu |
| Obnova | Protokol 14. 10. doložil přihlášení, katalog, košík nového shopu a historickou fakturu starého zdroje; dnes se tyto testy neopakovaly |
| Data | Zdrojová DB ze dne **5. 10. 2026**, nový cíl podle posledního protokolu/demo exportu 19 produktů; dnešní počty DB nebyly znovu změřeny |
| Zakoupený balíček | V určené cestě `migration/licensed-packages/` nebyl nalezen dodaný balíček; to neprokazuje, že uživatel nic nekoupil jinde |
| Místo | Při kontrole přibližně **11,6 GiB volných na C:**; kapacitu pro další archiv, Docker data, import a náhledy obrázků ověřit předem |
| Zálohy | Lokální archiv i šifrovaný cloudový balíček existují podle předchozí dokumentace; stažení/dešifrování cloudové kopie není doložené jako dokončené |

Původní FTP zůstává archivem. Úplnou obnovu a původní datové počty dokládá `PROVEDENO-14-10.md`. Pro aktuální pilot je podle závazného plánu potřeba **čerstvý SQL export a doplnění změněných médií**. Starý SQL stačí pro historický nácvik obnovy, není obraz dnešního prodeje.

## 3. Propojení: co nepůjde pouhým vložením adresy

`localhost` uvnitř nového Docker kontejneru označuje tento kontejner, nikoli Windows nebo starý obchod. Adresa `http://localhost:8082/` v prohlížeči proto sama nepotvrzuje dosažitelnost konektoru z migrátoru. Oba obchody nyní patří do různých Compose sítí.

Navíc starý zdroj záměrně blokuje modulové PHP vstupy, `fc=module`, checkout a zákaznické formuláře. Konektor bez úzce omezeného povolení pravděpodobně dostane 403. Tyto ochrany se nesmí celé vypnout.

Po potvrzení výrobce a dodání balíčku připravit konkrétní interní propojení: izolované aplikační kontejnery, jednoznačné interní jméno zdroje, pevný localhost náhled pro uživatele, tokenový přístup jen ke skutečně potřebným cestám konektoru a médiím. Zachovat zákaz odchozího spojení zdroje i obnovovací aplikace. Ověřit spojení z migrátoru, případné zpětné volání, přesměrování a všechny URL obrázků. Potřebné licenční přístupy musí výrobce vysvětlit; izolaci kvůli nim neobcházet.

Veřejný tunel, veřejný proxy, kopírování staré PS 1.6 na hosting ani konektor v produkci nejsou řešením povoleným současným plánem. Online cíl na Českém hostingu se k PC na localhost běžně nedostane; první import proto připravujeme celý lokálně.

## 4. Konkrétní pořadí prvního importu

1. Potvrdit přesný produkt a písemnou podporu místního propojení, přesných verzí, licence a opakovaných importů. Uživatel zajistí nákup/ZIP a případnou odpověď podpory. Hesla ani tokeny neposílat do chatu.
2. Ověřit volné místo. Vytvořit novou zálohu cíle, souborů a projektu včetně neverzovaných změn; nezaměňovat starou zálohu za aktuální. Zachovat existující volumes.
3. Získat čerstvou produkční zálohu pouze čtením. Obnovit ji do izolovaného zdroje a před spuštěním neutralizovat maily, platby, expedici, importy a crony. Nepoužívat jako čistý snapshot kopii s místními testovacími košíky.
4. Změřit zdrojové počty, částky po měnách a vazby pomocí `scripts/migration-check.php`; uložit identitu snapshotu a známé výjimky. Případné opravy provést jen na pracovní kopii a evidovat rozdíl oproti originálu.
5. Na cíli instalovat MigrationPro Ultimate a Password Keeper, na izolovaném zdroji jeho odpovídající PrestaShop Connector. Připravit interní propojení podle předchozí části a ověřit skutečný zákaz spojení ven.
6. Zaznamenat rozsah, jazyky CS/SK/DE, CZK/EUR, stavy objednávek, dopravce, daně a mapování shopů. Neukládat do reportů tajné tokeny. Staré zaměstnance neimportovat automaticky; ověřit zachování cílového správce.
7. První plný přenos: **Mode 1 — vyčistit cílová data a zachovat zdrojová ID**. Tento režim maže cílová data; spouštět pouze nad zálohovaným lokálním cílem. Nepředpokládat ochranu demo objednávek ani ručně doplněných produktových textů.
8. Přenášet související skupiny společně: produkty s kategoriemi; objednávky se zákazníky/adresami a dopravou. Zahrnout varianty, vlastnosti, sklad, ceny/slevy, obrázky, přílohy, historii, platby, faktury, CMS a potřebnou lokalizaci. Staré PHP moduly, overrides a šablonu nenahrazovat datovým importem.
9. Uchovat soukromý log a projít i varování a přeskočené řádky. Dokončené procento samo není převzetí. Po importu znovu ověřit doménu localhost, vypnuté maily, neaktivní ostré integrace, správce a aktivní Kafka/kafkahome.
10. Zálohovat přijatý výsledek. Další testy i opakování importu musí mít známý výchozí snapshot.

Režimy, související skupiny a dávkování popisuje [manuál](https://prestahero.com/download-document?idLang=1&idProduct=149). **Mode 3** přidává nové ID a neaktualizuje shodná; pro změněné staré objednávky tedy nestačí. **Mode 4** přidává a aktualizuje podle ID; vybraná cílová data může přepsat. Mode 2/5 pro tento plán s uchováním ID nepoužíváme.

## 5. Co musí projít převzetím

Čísla níže platí pouze pro SQL z 5. 10.; novému snapshotu se musí určit vlastní baseline.

| Data | Historická reference |
| --- | ---: |
| Produkty / aktivní | 3 296 / 3 194 |
| Kombinace / s platným produktem | 11 786 / 11 784 |
| Kategorie | 77 |
| Zákaznické řádky / hosté / adresy | 582 / 467 / 609 |
| Objednávky / položky | 574 / 1 873 |
| Faktury / platby | 538 / 540 |
| Produktové originály / přílohy | 4 962 / 9 |

- **Výjimky v datech:** dvě osiřelé kombinace s product ID 0 řešit jen na kopii. Zachovat 25 historických položek bez dnešního produktu; jejich smazání by poškodilo historii. Počty se nesmí slepě srovnat odstraněním historických záznamů.
- **Katalog:** reference, EAN, atributy, výchozí kombinace, cenové příplatky, měny, specifické ceny, slevy, dostupnost, sklad, zaokrouhlení a daňové nastavení. Změna varianty musí přepočítat cenu, obrázek a dostupnost; do košíku musí přijít správná kombinace.
- **Média:** soubory skutečně existují, jsou čitelné, mají správné vazby a náhledy. U popisů/CMS hledat staré absolutní odkazy a obrázky, aby test nesahal na produkci.
- **Zákazníci:** host není registrovaný účet. Ověřit autorizovaným testovacím účtem staré heslo přes Password Keeper a samostatně reset hesla. Správcovská hesla z 1.6 nebrat jako zaručeně přenositelná. [Kontroly po migraci](https://prestahero.com/help-center/migrationpro-ultimate/26-to-do-list-after-data-migration).
- **Historie:** shodné reference, data, měny, částky, doprava a dobírka, slevy, stavy, úhrady, čísla faktur a vazby dokladů. Porovnat PDF na vzorku včetně EUR a smazaného produktu; generovaný PDF vzhled nemusí být bitově totožný.
- **Nový nákup:** zákazník i host, adresy, doprava, testovací platba, dobírka a její limit, objednávka, sklad a doklad. Použít označená testovací data a odděleně zachytávané maily; nikdy ostré zákaznické adresy nebo platby.
- **Vzhled a navigace:** desktop i mobil proti vykreslené předloze; černá bez přepínače, šest ikonových karet, skutečné kategorie přes kafkahome bez natvrdo předpokládaných ID. Ověřit hledání, filtry, produkt, varianty a košík.
- **SEO:** porovnat staré a nové cesty napříč jazyky, metadata, canonical, odkazy CMS a chování chybějících produktů. Zachovaná ID nejsou záruka stejných URL. FAQ upozorňuje, že změněné adresy vyžadují samostatná 301 přesměrování; nespoléhat na marketingový slib zachování pozic.

## 6. Co datová migrace sama nevyřeší

Projektový audit už identifikoval samostatné práce:

- Comgate: nový podporovaný modul, původní modulové platební reference a otevřené platby řešit zvlášť. Tabulky modulu nejsou automaticky běžnou platební historií PrestaShopu.
- Packeta a DPD: výdejní místa a expediční data, reference dopravců, štítky a exporty. Pouhý import názvu dopravce neobnoví integraci ani uložené místo zákazníka.
- Dobírka: rozpor **30 vs 49 Kč**, limity a hranice zdarma; zároveň se v podkladech liší doprava zdarma **1 500 / 1 699 / 3 000 Kč**. Nejde o volbu, kterou smí migrátor nebo Codex odhadnout. Historické částky zachovat, novou nabídku musí potvrdit klient.
- Mergado, souhlasy, cookies, antispam a staré automatické importy: zprovoznit samostatně v podporovaných vydáních. Nekopírovat staré overrides a crony.
- Vlastní šablona, maily a faktury: dokončit a otestovat v PS 9. Nákup migrátoru nezahrnuje převod schváleného designu ani všechny externí integrace.

Podklady: `PLATBY-A-MAILY-FTP.md`, `DOPRAVA-FEEDY-FTP.md`, `OVERRIDES-DPD-ANTISPAM.md`, `OTAZKY-PRO-KLIENTA.md`. Starší odkazy na cíl PS 9.2 v auditních souborech nepřebírat jako změnu aktuálního cíle 9.1.5.

## 7. Nácvik posledního dorovnání

Před nasazením nacvičit **Mode 4 nad obnovitelnými kopiemi**. Vytvořit kontrolované změny: nový zákazník/objednávka, změna stavu i úhrady staré objednávky, cena, sklad, změna adresy a smazání položky ve zdroji. Ověřit správný výsledek a opakovat běh bez dalších změn: nesmějí přibýt duplicity ani se dvakrát odečíst sklad či přičíst poplatek. Zacházení se smazanými záznamy není potvrzené a musí se změřit.

Testovací objednávky v cíli mohou později kolidovat se zdrojovými ID. Finální základ proto obnovit z přijatého migračního snapshotu podle nacvičeného postupu; netestovat nekontrolovaně v jediné kopii určené pro finální synchronizaci. Obsahové úpravy importovaných produktů odložit, jinak je Mode 4 může přepsat. Změřit dobu importu, médií, kontrol i obnovy; nyní nelze slíbit konkrétní délku migrace.

Lokální test neověří veřejné HTTPS prostředí, SMTP, platební callbacky a limity skutečného hostingu. Ty přijdou v samostatné chráněné serverové zkoušce. Ostrý přechod vyžaduje zvlášť schválené okno, finální konzistentní zálohu a vypořádání rozpracovaných plateb podle `ODDELENA-MIGRACE.md`.

## 8. Nejbližší podklady od uživatele

1. Přesný produkt, případně zakoupený ZIP do `migration/licensed-packages/`.
2. Odpověď PrestaHero na už připravený dotaz: izolované Docker propojení, aktivace localhost → kafka.monster, případný další testovací hostname, aktualizace/historie/mazání při Mode 4 a známé osiřelé kombinace.
3. Čerstvý export hlavní shopové DB a změněná média jako soukromé soubory, nikoli jejich obsah do chatu.
4. Pro úplný checkout později potvrzená doprava/dobírka, požadované integrace a licencované balíčky jejich podporovaných vydání.

Průzkum neprovedl import, instalaci konektoru, reset DB, start zastavených kopií ani žádnou změnu produkce. Jedinou změnou projektu je tento přehled a odkazy na něj.
