# Kafka Monster — závazný pracovní plán migrace do PrestaShopu 9

> **Rozhodnutí 14. 10. 2026 má přednost:** aktuální provedení je v [ODDELENA-MIGRACE.md](ODDELENA-MIGRACE.md). Během přípravy produkce pouze jako zdroj zálohy; konektor výhradně na izolované kopii na PC. Historické pokyny níže k produkčnímu konektoru, samostatné nové hostingové službě a opakovanému vyžádání již dodaných parametrů se nepoužívají. Modul dosud není koupený. Obnovu a zálohy popisuje [OBNOVA-A-ZALOHY.md](OBNOVA-A-ZALOHY.md).

> **Stav 12. 10. 2026:** lokální prostředí je nainstalované: PS 9.1.5, PHP 8.3.28, Hummingbird 2.0 a aktivní šablona Kafka. Pracovní soubory, ovládací BAT skripty a návod jsou v [JAK-PRACOVAT.md](../JAK-PRACOVAT.md). Používáme demonstrační data; první vizuální vrstva Kafka je připravená podle [VIZUALNI-PREVOD.md](../design/VIZUALNI-PREVOD.md). Produkce ani skutečná migrace nejsou provedené. Starší formulace o dosud nepřipraveném lokálním prostředí jsou tím překonané.

> **Aktuální změna po dohodě s uživatelem: lokální vývoj na PC, ostrý provoz na současném WEDOS Extra; druhý hosting nekupovat.** Dřívější části doporučující novou hostingovou službu jsou překonané. Platný postup prostředí, jednodenní neveřejné zkoušky a návratu je v [LOKALNI-VYVOJ-A-ROLLBACK.md](LOKALNI-VYVOJ-A-ROLLBACK.md). FTP i parametry současného hostingu jsou již dodané; neopakovat požadavky. Zbývá čerstvá DB, lokální prostředí a migrační balíček.

Rozhodnutí 10. 10. 2026 podle posledního zadání uživatele. Tento dokument nahrazuje předchozí nákupní doporučení a podmínku přesné verze 9.2. Starší reporty zachováváme jako historii a důkazy. Plán není záznamem provedené migrace: pilot, instalace ani nákup dosud neproběhly.

## Vybraný základ a nákupy

Upřesnění identity po dotazu uživatele: dostupné manuály ukazují dvě odlišné produktové řady. Manuál PrestaHero výslovně označuje MigrationPro Ultimate jako svůj produkt a používá PrestaShop Connector / Password Keeper. Starší manuál MigrationPro z Marketplace odkazuje na profil prodejce 202_migrationpro a používá migrationpro.zip / migrationpro_bridge_connector_module.zip. Aktuální stránka Addons 8934 v textovém načtení neposkytla údaje výrobce a balíčku; současnou totožnost distribucí tedy nelze prokázat pouze tímto načtením. Doporučení zůstává koupit přímo konkrétní PrestaHero produkt, pro který je plán napsaný; není založené na tvrzení, že druhý nástroj je horší.

Doklady: [manuál PrestaHero](https://prestahero.com/es/download-document?idLang=1&idProduct=149), [starší manuál Marketplace MigrationPro](https://edelcomponents.com/modules/migrationpro/documentation/documentation_en.pdf). Starší manuál slouží k rozlišení produktové řady, ne k potvrzení dnešní kompatibility Addons balíčku.

| Součást | Rozhodnutí |
| --- | --- |
| Cílový obchod | Čistá instalace PrestaShop 9.1.5, samostatná databáze |
| Hosting | Vývoj na PC; později stávající WEDOS Extra v samostatně dohodnuté odstávce |
| Přenos dat | **PrestaHero MigrationPro Ultimate — nákup doporučen** |
| Vzhled | **Hummingbird 2.0 pro PS 9.1**, přizpůsobený schválenému návrhu |
| Warehouse | Pro tento plán nekupovat; pracujeme s nativním základem |
| Integrace | Podporovaná vydání Comgate, dobírky, Packety, DPD, Mergada a ochrany formulářů; nejprve využít nárok klienta na aktualizace |

PrestaHero uvádí verzi 5.0.1, podporu zdroje 1.6 a cíle 9, cenu 139,99 EUR a licenci na jednu doménu. Konečná cena se řídí checkoutem. Není to Addons produkt 8934. Vybral jsem jej pro doložený postup následné synchronizace, nikoli na základě vlastního srovnávacího testu obou migrátorů. Aktivaci pro staging a následnou ostrou doménu vyřešit při pořízení licence; jedna licence automaticky neznamená dvě současně licencované domény.

Zdroje: [PrestaHero](https://prestahero.com/149-prestashop-better-upgrade-and-migrate-tool.html), [vydání PS 9.1.5](https://github.com/PrestaShop/PrestaShop/releases/tag/9.1.5), [PHP pro 9.1](https://build.prestashop-project.org/news/2026/prestashop-9-1-0-available/), [Hummingbird a tabulka kompatibility](https://github.com/PrestaShop/hummingbird). Instalovat vydání odpovídající 9.1, nikoli vývojovou větev šablony. PHP 8.3 je naše výchozí volba v podporovaném rozsahu; instalační kontrola ověří také DB a rozšíření konkrétního hostingu.

## 1. Připravit obnovitelný zdroj a nové prostředí

### Parametry dodané uživatelem po sestavení plánu

Potvrzený současný tarif: **Extra**, Linux; soubory webu 1 713 MB, databáze 367 MB. Současné **PHP 7.1**, `max_execution_time=300`, `max_input_vars=10000`, `max_file_uploads=20`, `allow_url_fopen=On`. Uživatel potvrdil dokončené FTP v původní cestě. Detail služby a konfigurace PHP jsou tím dodané, neopakovat požadavek na stejné screenshoty.

Aktuální [dokumentace WEDOS/ VEDOS](https://kb.vedos.cz/webhosting-php/) uvádí pro Extra tarifní `memory_limit=1024 MB`, `upload_max_filesize=512 MB`, `post_max_size=512 MB`. Jsou to dokumentované tarifní hodnoty, nikoli změřený runtime této konkrétní služby. Dokumentace zároveň potvrzuje společné PHP nastavení pro celý webhosting. **Pro souběh starého PHP 7.1 a nového PHP 8.3 proto volíme samostatný cílový webhosting Extra u stejného poskytovatele**, vlastní DB a chráněnou testovací adresu. Stávající službu nyní nepřepínat. Skutečné cílové parametry a funkčnost modulů ověří instalační kontrola a pilot; velikost starého shopu sama neprokazuje výkon cíle.

**Uživatel:** potvrdit dokončení FTP fronty a vyřešení neúspěšných přenosů, doplnit čerstvý export hlavní shopové DB. Information_schema není databáze objednávek. Zálohu držet neveřejně. Úplnost a obnovitelnost ověřit na izolované kopii; dostupnost médií v auditu sama nepotvrzuje celou zálohu.

**Implementace:** založit nové prostředí u WEDOS, staging adresu, HTTPS a novou DB. Neměnit PHP staré služby. Subdoména na stejné sdílené službě není důkaz odděleného PHP; pokud tarif izolaci neposkytuje, použít samostatnou hostingovou službu u WEDOS. Staging chránit přihlášením, vypnout zákaznické e-maily, ostré platby a produkční crony. Nastavit také noindex; ten nenahrazuje ochranu přístupu.

**Výstup:** funkční prázdný PS 9.1.5 s Hummingbirdem, instalační kontrola prošla, zálohu lze obnovit.

## 2. Propojit migrátor a provést první přenos

Na nový lokální shop instalovat zakoupený ZIP MigrationPro Ultimate až po potvrzení licence. Konektor pro 1.6 patří výhradně na izolovanou zdrojovou kopii na PC, nikdy do produkce. Zdroj pro finální dorovnání obnovit z finální produkční zálohy. Omezit přístup k přenosu; po dokončení konektor deaktivovat/odstranit podle návodu výrobce.

**První přenos: Mode 1 — vyčistit data prázdného cíle a zachovat zdrojová ID.** Před spuštěním uložit nastavení a rozsah importu. Tento destruktivní režim nikdy nepouštět proti starému shopu ani proti dokončenému novému obchodu.

Přenést katalog, kategorie, varianty, ceny a slevy, zásoby, obrázky, přílohy, zákazníky a adresy, objednávky s položkami a historií, platby, faktury, jazyky, měny, CMS a potřebné dopravní vazby. Nastavit mapování CS/SK/DE a CZK/EUR, stavů a daní. Nepřenášet starou šablonu, PHP moduly, overrides, cache ani návštěvnické statistiky. Nové administrátorské přihlášení a podporované nastavení PS 9 spravovat odděleně od starých konfiguračních hodnot.

[Závislosti importu podle výrobce](https://prestahero.com/help-center/migrationpro-ultimate/24-how-to-select-data-for-seamless-migration): objednávky potřebují zákazníky, adresy a dopravu; katalog potřebuje kategorie. Rozsah nelze ořezat bez kontroly těchto vazeb.

## 3. Převzít data podle měřitelných kontrol

Referenční čísla platí pro dodaný SQL, ne pro budoucí den přepnutí:

| Kontrola | Základ z auditu |
| --- | --- |
| Produkty | 3 296 celkem, 3 194 aktivních |
| Kombinace | 11 786 řádků, z toho 2 osiřelé; 11 784 s platným produktem |
| Kategorie | 77 |
| Zákaznické řádky | 582, včetně 467 hostů |
| Objednávky / položky | 574 / 1 873 |
| Faktury / platby | 538 / 540 |
| Obrázky / přílohy | 4 962 originálů / 9 příloh |

Dvě kombinace s product ID 0 vynechat nebo opravit na pracovní kopii podle dohledané vazby, nikdy automaticky přiřadit k náhodnému produktu. Pokud migrátor neumožní jejich vynechání, připravit opravenou izolovanou zdrojovou kopii pro import. Produkci kvůli migraci neopravovat naslepo.

Zachovat 25 historických položek objednávek po smazaných produktech: nesmí zmizet jen proto, že produkt dnes neexistuje. Kontrolovat nejen počty, ale ID, vztahy, celkové částky, měny, stavy, čísla dokladů a datum. Otevřít reprezentativní historické faktury a porovnat obsah; archiv původních dokladů držet zvlášť. Ověřit přihlášení známého testovacího účtu se starým heslem bez sdílení hesla v chatu. Vygenerovat nové náhledy a vyhledávací index.

**Výstup:** protokol rozdílů, každá odchylka vysvětlená. Chybný pilot se opravuje na stagingu před vývojem a ostrým přepnutím.

## 4. Převést návrh na celý obchod

Z Hummingbirdu vycházet pro homepage, všechny kategorie, produktový detail, varianty, galerii, hledání, košík, checkout, zákaznický účet a CMS. Schválený nástřel určuje vzhled; data vykresluje PrestaShop. Jedna napojená produktová karta obslouží celý katalog, produkty se ručně nevkládají do HTML.

Použít nativní checkout a mechanismy PS. Napojit skutečné příplatky variant, dostupnost, cenu, měnu, slevy a dopravu. Mobilní nákup je součást prvního spuštění. Vlastní potisk zachovat jako stávající domluvu a následný produkt/objednávku; nový konfigurátor není součást základní migrace.

**Do finální synchronizace neměnit na cíli importované názvy, popisy a strukturu katalogu.** Nové texty a datová obohacení připravit odděleně a aplikovat po posledním importu. Ve zdroji nejsou vazby fotografie–varianta ani vlastnosti nových filtrů; ty se doplňují jako samostatná práce. Vyhledávání a výběr variant musí fungovat už při spuštění.

## 5. Obnovit provozní funkce podporovanými moduly

| Funkce | Cílové řešení a kontrola |
| --- | --- |
| Comgate | Podporovaný modul původního dodavatele; test platby, návratu a callbacku, kontrola částky/měny a opakovaného oznámení |
| Dobírka | Podporovaný modul; správný příplatek podle dopravce, součet v košíku i na faktuře |
| Převod | Podporovaná bankovní platba, instrukce a ruční párování dle současného postupu |
| Packeta | Aktuální oficiální modul, výdejní místo, dobírka, expedice; ověřit používaný CSV/API postup |
| DPD | Modul pro PS 9 se skutečně používanou funkcí výdejních míst/expedice; neodvozovat tisk štítků pouze z přítomnosti starého modulu |
| Mergado | Aktuální modul; feed a případný cenový import spouštět z jednoho prostředí |
| Souhlasy a formuláře | Jedna funkční cookie lišta, ověřené blokování měření a ochrana kontaktu/registrace |
| Maily a faktury | Funkční nové šablony, jazyky, doručení a správné částky/číslování |

Staré vlastní tabulky nejsou automaticky pokryté standardním migrátorem. Uzavřenou historii Packety/DPD/Comgate archivovat; u rozpracovaných objednávek ověřit a převést potřebná ID transakcí, výdejní místa a zásilky do podporovaného cílového řešení. Nepřepisovat je prostou kopií tabulek do odlišných modulů.

**Před nastavením checkoutu klient potvrdí jednu tabulku platných cen dopravy, dobírky a dopravy zdarma.** Audit našel rozpory mezi SQL, veřejným webem a návrhem. Pilot dat na tuto odpověď čekat nemusí. Zaznamenat i používané CZ/SK/EUR, režim DPH a expedici. Nastavení nových modulů a dopravců uložit do samostatného kontrolního seznamu, aby se po posledním importu dalo přesně obnovit.

## 6. Nacvičit aktualizaci a úplný nákup

**Následný přenos: Mode 4 — přidat nové záznamy a aktualizovat existující ID.** Nejprve na zálohovaném stagingu. Mode 3 přidává pouze nové záznamy a pro naše finální dorovnání nestačí.

Ověřit změněný stav již existující objednávky, novou objednávku, změněný sklad a platbu, opakování bez duplikací a zachování cílového vzhledu. Importovaná pole mohou přepsat ruční úpravy cíle: proto katalogové změny odkládáme. Dodržet závislosti importu; po přenosu obnovit a zkontrolovat cílové dopravní a modulové nastavení, pokud je rozsah importu zasáhl. Testovací objednávky a účty nesmějí kolidovat se zdrojovými ID; pro opakované nácviky obnovovat čistý pracovní snapshot.

Vyzkoušet desktop a mobil, CZK i EUR, produkt s variantami a příplatky, slevu, dopravu pod/nad limitem, dobírku, převod, Comgate, výdejní místo, účet/hosta, potvrzovací mail a fakturu. Platební testy řídit testovacím režimem brány nebo předem domluvenou kontrolovanou platbou. Projít staré důležité URL; zachovat cesty tam, kde lze, jinak připravit konkrétní 301 přesměrování. Zachování ID samo nezaručí stejné URL.

**Výstup:** úspěšný protokol funkčních testů a synchronizace, změřená délka posledního importu. Teprve ta určí délku odstávky.

## 7. Přepnout obchod jednou, po finálním dorovnání

1. Předem připravit doménové přiřazení nové služby, HTTPS a callback adresy. Pokud se mění DNS, připravit TTL; neměnit poštu/MX.
2. V dohodnutém okně zastavit nové objednávky a administrativní změny zdroje, importy cen a crony zapisující do DB. Údržba obchodu sama nezastaví platební callbacky: rozpracované platby zvlášť dohledat a určit, kdo je zpracuje při přechodu.
3. Udělat finální zálohu obou shopů, spustit nacvičený Mode 4, porovnat s čerstvým zdrojovým stavem. Zkontrolovat zásoby a otevřené objednávky/platby/zásilky.
4. Obnovit cílové nastavení, aplikovat připravené nové texty, ověřit doklady, ceny a dopravu, nastavit ostrou doménu a přepnout provoz na novou službu.
5. Aktivovat maily, ostré platby, callbacky a crony pouze na novém shopu, odstranit noindex a otevřít nákup. Projít jednu kontrolovanou objednávku od nákupu po expedici.
6. Starý shop držet neveřejně pro dohledání a návrat. Při problému před otevřením obnovit původní provoz. Po přijetí nových objednávek/plateb už návrat vyžaduje jejich vypořádání a přenos; nesmí zahodit nové transakce.

Sledovat první objednávky, callbacky, maily, sklad, feedy a chyby po spuštění. Potom odstranit přenosový konektor a archivovat konfiguraci migrace. Původní audit a zálohy uchovat neveřejně.

## Co udělat teď — pouze tři úkoly

1. **Předložit klientovi a koupit PrestaHero MigrationPro Ultimate.** Uložit instalační ZIP a konektor lokálně do `migration/licensed-packages/`; účty/licenci vést ideálně na klienta. Neposílat hesla nebo klíče do chatu. Warehouse do tohoto nákupu nepřidávat.
2. **Poslat zakrytý screenshot WEDOS tarifu a konfigurace PHP** a potvrdit, zda lze založit samostatnou cílovou službu. Staré PHP neměnit. Podle těchto údajů zvolíme konkrétní cílový tarif; dosud neznámé parametry neumožňují poctivě určit jeho kapacitu.
3. **Potvrdit stav FTP fronty a zajistit čerstvou shopovou DB.** Cesty k lokálním souborům stačí; není potřeba vkládat zákaznické údaje do chatu.

Další pracovní krok po těchto podkladech je instalace čistého stagingu a první měřitelný import, nikoli další obecná analýza migrátorů. Celkové pracovní rozpětí z auditu zůstává orientačně 8–18 odborných pracovních dnů podle vývoje šablony a integrací; není to délka odstávky ani pevná cenová nabídka. Přesnější rozpočet vznikne po prvním importu a potvrzení obchodních pravidel.
