# Kafka Monster: analýza a plán migrace na PrestaShop 9.2

> Aktuální závěr po FTP analýze z 9. 10.: [ZAVER-A-POSTUP.md](ZAVER-A-POSTUP.md). Níže je zachovaný původní rozbor s pozdějším datovaným doplněním na konci.

Zpracováno 5. října 2026. Rozsah: současný lokální návrh, oba dodané SQL exporty, veřejný obchod a primární dokumentace dodavatelů. Jde o přípravu migrace; produkční web ani databáze nebyly změněny. Stávající rozpracované soubory návrhu zůstaly zachované.

## Doporučené řešení

**Čistá instalace PrestaShopu 9.2 na odděleném prostředí → zkušební migrace se zachováním ID → nativní šablona podle schváleného návrhu → nové podporované platební a dopravní moduly → kontrolovaná finální synchronizace a přepnutí stejné domény.**

Pro nejmenší množství práce doporučuji první spuštění se šesti schválenými vstupy v navigaci, ale se zachovanými původními ID, URL, výchozími kategoriemi produktů a nákupním procesem pro individuální zakázky. Velké přestavění celého katalogu a doplnění všech filtrů lze dělat postupně. Design lze dodržet i bez změny tisíců adres.

MigrationPro je rozumný kandidát na přenos standardních dat. **Z dodaného SQL ale nelze potvrdit funkčnost konkrétního vydání modulu na PS 9.2.** Potřebujeme přesný odkaz na produkt, potvrzení dodavatele a pilot na kopii. Přejmenování „MigrationPro“ používá několik odlišných řešení.

PrestaShop **9.2.0 je stabilní vydání z 30. 9. 2026**, takže zvolený cíl existuje. Je ale čerstvý: hlavním rozhodovacím bodem bude kompatibilita přesné sestavy šablona / platby / výdejní místa / checkout. [Oficiální vydání 9.2.0](https://github.com/PrestaShop/PrestaShop/releases/tag/9.2.0)

## 1. Co je prokázané a co ještě chybí

| Úroveň důkazu | Co známe |
|---|---|
| Přesně z exportu | Počty vložených záznamů, verze DB, moduly a jejich přiřazení, ID, jazyky, ceny, vazby, konfigurace, dopravní pásma |
| Ověřeno na živém webu | Navigace, dva produkty z návrhu, aktuální platební informace, způsob objednání zakázky, text o odesílání, načtené veřejné skripty |
| Tvrzení dodavatelů | Kompatibilita některých aktuálních modulů s rodinou PS 9, funkce migrátorů; samostatně uvedeno v textu a CSV |
| Zatím neověřeno | Skutečná verze aplikace a PHP webu, FTP soubory, overrides, licence, 9.2 checkout integrace, platby, zákaznické přihlášení, reálná doba migrace |

SQL soubory byly čteny jako data, ne vykonávány. Vygenerované přílohy neobsahují zákaznická jména, e-maily, adresy, hesla ani API klíče. Webové texty a dokumentace byly použity jako podklady, nikoli jako oprávnění něco měnit.

Přesné počty níže platí pro export vytvořený **5. 10. 2026 v 01:53 podle hlavičky phpMyAdminu**. Při finálním přenosu budou potřeba nové kontrolní počty. Čas exportu nemusí být ve stejném pásmu jako počítač.

## 2. Skutečný stav lokálního frontendu

V kořeni projektu jsou HTML, CSS a vanilla JavaScript. Nenašel jsem instalaci PrestaShopu, PHP aplikaci, konfiguraci šablony `theme.yml`, `package.json` ani napojení na obchodní backend.

| Soubor / část | Stav | Důsledek pro implementaci |
|---|---|---|
| `index.html`, `app.js` | Schválený vizuál homepage, responzivní menu, karty a hash routing | Převést do šablony a malého prezentačního modulu |
| `catalog-data.js` | Šest vstupů; hudba a výprodej mají po jednom ukázkovém produktu | Skutečný katalog má tisíce položek; feed nahradí PrestaShop |
| `catalog-live.generated.js` | Scrape navigace z 24. 9. 2026 | Není to synchronizace produktů, cen ani skladu |
| `product.html`, `product.js` | Dvě ukázky PDP: tričko a CD | Napojit všechny skutečné atributy, cenu, množství, obrázky a dostupnost |
| Košík | Pouze zvýšení `state.cartCount` v paměti stránky | Objednávková logika zatím chybí |
| Přihlášení, servisní odkazy | Často `href="#"` | Doplnit reálné odkazy a stránky |
| CSS homepage | Tailwind přes veřejné CDN | Pro produkci sestavit a hostovat výsledné CSS v šabloně |
| Filtry, účet, checkout | Nejsou hotové produkční obrazovky | Nutné dokončit, i když homepage/PDP už klient schválil |

Zachovat vizuální tokeny, SVG, fotografie, písma, rozvržení a chování karet. **Nepřevádět demo logiku dostupnosti ani ukázkové ceny do produkce.**

V dokumentu `HOMEPAGE-ARCHITECTURE.md` je zmíněno IQIT/Warehouse. To je podklad pro architekturu, nikoli důkaz zakoupené licence nebo hotové implementace. Také se rozchází rozsah dokumentovaného stromu s aktuální zjednodušenou implementací: dokument popisuje detailní fasety, kód často odkazuje do starého webu. Za schválený vizuál považuji aktuální projekt; datovou strukturu musíme doplnit samostatně.

### Volba šablony

**Pokud už máš Warehouse licenci a zkušenost s IQIT:** nejprve ověřit přesnou aktuální verzi na PS 9.2, poté použít child theme a malý vlastní modul pro šest kategoriálních karet a hero. IQIT deklaruje Warehouse 4.x pro PS 1.7/8/9; konkrétní podporu 9.2 z toho nelze odvodit. [IQIT](https://iqit-commerce.com/), [dokumentace child theme Warehouse](https://iqit-commerce.com/xdocs/warehouse-theme-documentation/)

**Pokud licenci nemáš nebo jde hlavně o přesné převedení současného HTML:** doporučuji nativní úpravu výchozí šablony Hummingbird a malý vlastní modul. Vyhneme se instalaci rozsáhlého builderu jen kvůli homepage. V distribuci 9.2 je Hummingbird vedený mezi závislostmi. [Zdroj distribuce 9.2](https://github.com/PrestaShop/PrestaShop/blob/9.2.0/composer.json)

Konečnou volbu rozhodnout krátkým prototypem: skutečný produkt s variantami, košík, Packeta a Comgate v cílové sestavě. Nepřepisovat core. Headless frontend je možný, ale pro tento požadavek by přidal samostatnou práci se session, košíkem, platbami a výdejními widgety; není mou výchozí volbou pro nejlehčí migraci.

Child theme je vhodný pro omezené zásahy; pokud bude potřeba výrazně přestavět markup většiny obrazovek, oficiální dokumentace doporučuje vycházet přímo z Hummingbird a vytvořit vlastní udržovanou šablonu. To rozhodne pilot převodu návrhu. [Tvorba child theme](https://devdocs.prestashop-project.org/9/themes/reference/template-inheritance/parent-child-feature/)

## 3. SQL audit: rozsah obchodu

| Oblast | Přesný stav v exportu |
|---|---:|
| `PS_VERSION_DB` | **1.6.1.13** |
| Prefix tabulek | `ejf6_` |
| Tabulky | 306, z toho jedna bez standardního prefixu (`skrill_order_ref`) |
| Vložené řádky celkem | 3 186 757 |
| SQL soubor | 211 325 190 B, přibližně 201,5 MiB |
| Produkty | **3 296** |
| Aktivní / neaktivní produkty | **3 194 / 102** |
| Existující produkty s variantami | **1 599**; v kombinacích je navíc neplatný parent ID 0 |
| Kombinace produktů | **11 786**, z toho 2 bez existujícího produktu |
| Skupiny atributů / hodnoty | 16 / 183 |
| Vazby kombinace–atribut | 32 449 |
| Kategorie | 77, z toho 74 aktivních včetně dvou technických rootů |
| Aktuální hlavní katalogové větve | 13 |
| Obrázky | **4 962 záznamů** |
| Zákaznické řádky | **582** |
| Registrované účty / hosté | **115 / 467** |
| Adresy | 609 |
| Objednávky / položky objednávek | **574 / 1 873** |
| Faktury / platební záznamy | 538 / 540 |
| Vrácení zboží / dobropisy | 1 / 0 |
| CMS stránky | 12 |
| Specifické ceny / slevové kupóny | 1 564 / 8 |
| Přílohy / vazby na produkty | 9 / 89 |
| Produktové vlastnosti `feature` | **0** |
| Obrázky přiřazené ke kombinacím | **0 vazeb** |
| Moduly v `module` / přiřazené do shopu | **85 / 78** |
| Shopy / multistore | 1 / vypnutý |

**Verze 1.6.1.13 je verze databáze.** Skutečný `_PS_VERSION_` v souborech nebo administraci je ještě potřeba porovnat; ruční úpravy či nedokončený upgrade mohou způsobit rozdíl.

Hlavička exportu uvádí MariaDB **10.11.18** a PHP **5.4.23**. PHP může být runtime phpMyAdminu, nikoli e-shopu; nepovažuji to za potvrzenou verzi PHP produkční aplikace. Údaj o serveru odpovídá databázovému hostu WEDOS, ale tarif a webový hosting zatím nejsou ověřené.

### `information_schema.sql`

Je to export metadat databázového serveru, ne druhá databáze e-shopu. Obsahuje 79 tabulek metadat a 11 367 řádků. Pro obchod popisuje stejných 306 názvů tabulek jako hlavní export. Odhad fyzického objemu dat a indexů je 392 544 256 B, přibližně 374,4 MiB.

`TABLE_ROWS` u InnoDB je odhad: součet 3 143 124 se liší od skutečného počtu vložených tuple v dumpu. Proto se migrační kontrola má opírat o `COUNT(*)` nebo skutečné záznamy. **`information_schema.sql` se neimportuje do nové databáze PrestaShopu.**

### Co se dá vynechat z aktivní migrace

Vybrané návštěvnické logy, cache a vyhledávací/fasetové indexy tvoří **2 991 825 řádků, přibližně 93,9 % všech řádků exportu**. Například `guest` má 1 184 227 řádků a `connections` 1 167 404. To nejsou zákaznické účty.

Tyto tabulky uchovat ve staré záloze, ale do nového obchodu je běžně nepřenášet. Indexy vyhledávání a filtrů vytvořit znovu. Vynechání logů se netýká objednávek, jejich historie, adres ani zákazníků.

## 4. Data, která mění náročnost migrace

### Varianty nejsou jen barva a velikost

Skupiny obsahují mimo jiné Velikost, Barvu, Velikost placek, Počet, Druh, Druh 2, Věk, dětské velikosti, druhy hrnků, BODY, druh piercingu a velikosti cvoků/nášivek. **9 765 kombinací má tři atributy**, 1 134 dva a 885 jeden. Zbývající 2 jsou osiřelé.

Generický PDP musí umět všechny tyto skupiny. U skutečného dětského trička jsou Barva + Druh + Velikost dětských triček. Skupinu s jedinou hodnotou lze vizuálně skrýt, ale správnou kombinaci musí stále určovat backend.

Pro existující produkty s kombinacemi audit nenašel více než jednu výchozí variantu ani chybějící výchozí variantu. Všech 3 296 produktů má prázdný `reference`; jako migrační klíč proto **nepoužívat SKU**. Zachovat zdrojová ID a jejich vazby.

### Konkrétní rozdíl: tričko z návrhu

[Živý produkt 3454](https://kafka.monster/cs/detska-tricka/3454-tricko-punk-is-freedom-rock-shop.html) má základní cenu **259 Kč**, dvě barvy a sedm dětských velikostí: 90, 104/110, 116/122, 128/134, 140/146, 152/158 a 164. V SQL je 14 kombinací s příplatky 0, 10, 30 nebo 40 Kč; vzorkované varianty mají zásobu 12 kusů každá.

Návrh naproti tomu používá velikosti 92–152, ukázkové nedostupné kombinace a statické ceny. **Schválení vizuálu neznamená schválení změny velikostí, cen nebo skladového stavu.** Po napojení musí cenu i dostupnost určovat skutečná zvolená kombinace.

[CD 3415](https://kafka.monster/cs/cd/3415-cd-spuntqane-posledni-singly.html) má skutečně cenu 99 Kč a jeden kus skladem. Je to fyzický produkt, nikoli download.

### Filtry a fotografie

Databáze nemá produktové `features`. Filtry typu motiv, hudební žánr, materiál, zapínání a způsob aplikace nášivky tedy nejsou připravené pro automatický přenos. Pro první spuštění použít existující kategorie, atributy, cenu a dostupnost. Chybějící vlastnosti doplnit mapováním kategorií nebo řízeným dávkovým importem; motiv/žánr neodhadovat nekontrolovaně z názvu.

Vazeb kombinace–obrázek je nula. Obrázky se přenesou jako produktová galerie, ale přepnutí fotografie podle barvy nebude automaticky spolehlivé. U trička známe dvě fotografie, pro celý katalog zatím nemáme ověřené pravidlo jejich pořadí. Začít korektní galerií, poté přiřadit obrázky variantám u prioritních produktů. Bílé produktové pozadí může zůstat součástí schváleného tmavého designu; není potřeba před migrací zpracovat všechny fotografie.

### Slevy a „Výprodej“

1 564 záznamů `specific_price` používá procentní slevu; žádný není přiřazen konkrétnímu zákazníkovi a žádný nemá množstevní práh nad jeden kus. Kategorie Výprodej má pouze **56 produktových vazeb**. Nezaměňovat katalogovou kategorii s automatickou množinou aktuálně zlevněných produktů; platnost slev a jejich překryvy musí prověřit pilot.

### Jazyky a měny

Aktivní jsou **CS, SK a DE**, výchozí CS. EN je neaktivní. V každém jazyce je 3 296 produktových záznamů, ale to neprokazuje překlad: **2 916 německých názvů a 1 572 slovenských názvů se přesně shoduje s českými**. Jazykové verze při migraci zachovat; kvalitu překladů řešit zvlášť.

Aktuální měny: CZK ID 2 a EUR ID 4. V databázi zůstává i smazaná EUR ID 3 a GBP ID 1. Párování měn musí respektovat stav i historii, nikoli jen ISO kód. V exportu je 560 objednávek CZK a 14 EUR; jazyky objednávek jsou 546 CS, 27 SK a 1 DE.

### Daně a doklady

`PS_TAX=0`, všechny produkty mají tax rules group 0. Přítomnost starých sazeb v tabulce daní nedokazuje jejich používání. Před spuštěním ověřit s klientem skutečný daňový režim; automatický import lokalizačního balíčku nesmí změnit výsledné ceny.

Zachovat původní uložené částky, platební záznamy, historii stavů a čísla faktur. Staré objednávky nepřepočítávat podle nových cen nebo dopravních pravidel. Ověřit číselnou řadu nové první faktury a dostupnost historických PDF/příloh ze souborové zálohy. Úpravu daňového režimu a účetních pravidel řeší klient se svým účetním.

## 5. Kvalita a konzistence dat

| Kontrola | Nález | Postup |
|---|---:|---|
| Objednávka bez existujícího zákazníka | 0 | Dobré východisko |
| Položka bez objednávky | 0 | Dobré východisko |
| Položky odkazující na smazaný produkt | 25 | Zachovat historický snapshot řádku objednávky; nevytvářet z něj automaticky zboží |
| Kombinace bez produktu | 2 | ID 9621 a 23881, parent 0; vyřešit v kopii/přenosové vrstvě |
| Skladové řádky bez produktu | 152 | Pozůstatky včetně ID 0 a smazaných produktů; nepřenášet do prodejního katalogu |
| Skladové řádky bez kombinace | 2 | Kontrolovat při importu kombinací |
| Výchozí kategorie produktu chybí | 0 | Vazby zachovat |
| Kategorie–produkt odkazují na chybějící entitu | 0 | Vazby zachovat |
| Obrázek odkazuje na chybějící produkt | 0 | Souborovou existenci zatím neověřeno |
| Aktivní produkt bez obrázkového záznamu | 0 | Jeden neaktivní produkt obrázek nemá |
| Záporné skladové množství | 0 | Neznamená ověřený fyzický sklad |

Tyto nálezy nepovažuji za důvod psát vlastní migrátor celého e-shopu. Jsou důvodem pro pilot, explicitní výjimky v kontrolním protokolu a případný malý doplňkový import. **Zdrojové záznamy nemažeme před zálohou ani bez zdokumentování.**

Není zapnutý pokročilý sklad, tabulky fyzického skladu/pohybů jsou prázdné. `stock_available` používá převážně zděděnou politiku `out_of_stock=2`; globálně je objednávání bez skladu zakázané. Přenést výslednou politiku, ne pouze číslo na produktu.

## 6. Platby, doprava a moduly

Všechny řádky `module` mají `active=1`, ale jen **78 z 85 modulů má vazbu do shopu**. Proto pouhý seznam „aktivní“ není spolehlivá informace o skutečném používání.

| Priorita | Současný modul | Evidence použití | Cílový postup |
|---|---|---|---|
| P0 | `dm_comgate` 3.3.2 | 312 objednávek, 120 v roce 2026 | Podporovaný Comgate modul, callbacky a testy CZK/EUR |
| P0 | `dm_cashondelivery` 3.3.0 | 190 objednávek, 61 v roce 2026 | Dobírka s příplatkem a dopravními omezeními |
| P0 | `bankwire` 1.1.2 | 47 objednávek, 13 v roce 2026 | Nový `ps_wirepayment`, nastavit účet/pokyny |
| P0 | `packetery` 2.0.9 | 557 záznamů v module order tabulce | Nový oficiální modul, výběr pobočky a export zásilky |
| P0 | `shaim_dpdparcelshop` 2.3.8 | Dva aktuální dopravci CZ/SK, 8 module data řádků | Aktuální verze, samostatný test checkoutu |
| P1 | `mergado` 4.1.0 | Data modulu a veřejné měřicí skripty | Aktuální Pack, zachovat feed ID a nastavit cron |
| P1 | `shaim_cookies_eu` + `usercentrics` | Oba přiřazené, veřejně viditelný shaim banner | Vybrat jednu hlavní CMP a ověřit blokování měření |
| P1 | `shaim_recaptcha_v3` | Přiřazený modul | Aktuální ochrana kontaktního a dalších formulářů |
| P1 | `mailalerts` | Přiřazený modul | Aktuální mail alerts + test e-mailů |
| P1 | `gsitemap` | Přiřazený modul | Nová sitemap až po finální konfiguraci |
| Ověřit potřebu | `ps_checkout` 6.3.5.1 | Přiřazený, žádná objednávka s tímto modulem | Přidat pouze pokud je skutečně požadován |
| Archiv | `paypal` 3.17.0, `cashondelivery` 1.0.0 | Nejsou přiřazené do shopu, historické objednávky existují | Uchovat historii; nejsou automaticky požadované nové brány |

Oficiální **Packeta** dokumentuje podporu PS 9; **Shaim DPD a Comgate** uvádějí PS 9.x; **Mergado Pack** uvádí PS 9. To je potvrzení rodiny platformy, nikoli společného fungování konkrétního nativního one-page checkoutu 9.2. [Packeta](https://github.com/Zasilkovna/prestashop), [DPD](https://psmoduly.cz/dpd-parcel-shop-pick-up-vyber-odberneho-mista-primo-v-objednavce-alzaboxy-242/vydejni-mista.htm), [Comgate](https://psmoduly.cz/platebni-modul-platebni-brana-comgate-293/platebni-moduly.htm), [Mergado](https://pack.mergado.cz/prestashop)

**Nativní one-page checkout je volba, ne podmínka migrace.** První pilot provést se standardním podporovaným checkoutem. Nový OPC zapnout až po úspěšném testu výdejních míst, cen dopravy, dobírky, změny adresy a platebních callbacků. Pokud integrace nefunguje, lze spustit PS 9.2 s ověřeným standardním checkoutem a zachovat schválený storefront.

### Zvláštní modulová data

Standardní migrace objednávek nezaručuje přenos těchto tabulek:

- `ejf6_dm_comgate_payment`: 304 řádků; transakční reference a vazby na starou bránu.
- `ejf6_packetery_order`: 557 řádků; zvolené pobočky, zásilky a další provozní údaje.
- `ejf6_shaim_dpdparcelshop_data`: 8 řádků; historické výdejní volby.
- `ejf6_mergado*`: nastavení, logika exportu a záznamy měření.

U uzavřených objednávek může stačit zachovat starý obchod jako chráněný archiv. U **otevřených a neodeslaných objednávek** musí být výdejní místo, stav platby a způsob expedice dostupný i během přepnutí. S klientem rozhodnout: doexpedovat na starém shopu, nebo vytvořit řízený převod těchto údajů pro aktuální schéma nového modulu. Nekopírovat module SQL tabulky mezi verzemi naslepo.

V dumpu jsou 3 objednávky ve zpracování a 4 odeslané; to není definitivní seznam rozpracovaných plateb. Ten se musí sestavit z aktuální administrace a brány při cutoveru.

### Doprava: konkrétní pravidla

Aktuálních nesmazaných aktivních dopravců je šest: osobní odběr Hájek, Packeta výdejní místo/Z-Box, Packeta na adresu, DPD Classic, DPD Pickup SK a DPD Pickup CZ.

| Dopravce | Zóna CZ: základní cena v DB | Další zóny v DB |
|---|---:|---|
| Osobní odběr | 0 Kč | — |
| Packeta pobočka/Z-Box | 79 Kč | SK 145 Kč, zóna Europe 175 Kč |
| Packeta na adresu | 124 Kč | SK 169 Kč, Europe 190 Kč |
| DPD Classic | 154 Kč | SK 197 Kč, Europe 220 Kč |
| DPD Pickup CZ | 109 Kč | — |
| DPD Pickup SK | — | SK 186 Kč |

Cenová pásma těchto placených dopravců přecházejí na nulovou cenu od **1 699 Kč do horní meze 10 000 Kč**. Přístupnost konkrétních zemí a chování nad horní mezí vyžadují checkout test. Globální `PS_SHIPPING_FREE_PRICE` je přitom 3 000 a `PS_SHIPPING_HANDLING` 109; výsledná částka závisí i na nastavení dopravce. CSV obsahuje základní sazby, ne ověřenou celkovou cenu každého košíku.

Na živém prázdném košíku je pobídka dopravy zdarma od 1 699 Kč. Návrh uvádí 1 500 Kč. Je nutné vybrat jedno obchodní pravidlo a sjednotit výpočet i text.

Živá [platební stránka](https://kafka.monster/cs/content/9-platba) uvádí bankovní převod a kartu bez příplatku, dobírku s příplatkem **30 Kč** a možnost hotovosti/karty na pobočce. Návrhová loga Apple Pay a Google Pay zatím neprokazují aktivaci těchto metod u klientova účtu Comgate.

[Dodání](https://kafka.monster/cs/content/1-dodani) mluví o odeslání do **čtyř pracovních dnů**, návrh o 48 hodinách. Potvrdit skutečnou kapacitu klienta; termín nesmí být jen převzatý z demo textu.

## 7. Vlastní potisk a zakázky: funkce, kterou musíme zachovat

V databázi jsou `customizable`, `uploadable_files` a `text_fields` u všech produktů nulové. Vlastní potisk tedy není hotový PrestaShop konfigurátor s uploadem motivu.

Živá [stránka Jak objednat potisk](https://kafka.monster/cs/content/10-jak-objednat-potisk) popisuje domluvu e-mailem/telefonem, následné vytvoření zákaznické zakázky v kategorii Zakázky a její standardní objednání. Kategorie **25** má 98 vazeb na produkty, z toho jen **jeden aktivní produkt**, což odpovídá současnému veřejnému výpisu.

Proto může nový hlavní vstup **Custom zůstat informační CMS stránkou bez produktového feedu**, jak klient schválil. Musí však nadále existovat možnost poslat zákazníkovi konkrétní produkt jeho zakázky a zaplatit jej běžným checkoutem. Staré zakázkové produkty a jejich objednávky je potřeba přenést; neaktivní zůstanou neaktivní.

Nový upload formulář, konfigurátor nebo automatická kalkulace nejsou nutnou součástí nejlehčí migrace. Pokud je klient chce, jde o samostatnou funkci. Ukázkové ceny 290/190 Kč z `app.js` nejsou ověřený ceník klienta.

## 8. MigrationPro: rozhodnutí pro tento obchod

| Řešení | Co jsem ověřil | Co zůstává otevřené |
|---|---|---|
| Migration PRO z PrestaShop Addons | Existuje profil dodavatele; přesný současný listing nebyl dostupný k úplnému ověření | Přesný produkt, verze, cena, licence, 1.6.1.13 → 9.2, hesla, aktualizace existujících záznamů |
| PrestaHero MigrationPro Ultimate | Produkt uvádí 1.6 → 9, ID režimy a zachování hesel; changelog 5.0.0 uvádí zdroj 1.6 přes connector | Výslovné ověření 9.2 a PHP starého serveru; vlastní tabulky a konektory |
| ZM40 Migration Pro | Jiný výrobek; dodavatel deklaruje 1.6/1.7/8/9 | Konkrétní 9.2, rozsah edge cases, nikoli stejné recenze jako Addons produkt |
| Migrationpro.io / Shopify aplikace | Aktuální web se zaměřuje na migraci do Shopify | Není podkladem pro potvrzení nativního PrestaShop modulu |

[Addons profil Migration PRO](https://addons.prestashop.com/en/774-Migration-pro), [PrestaHero produkt](https://prestahero.com/149-prestashop-better-upgrade-and-migrate-tool.html), [ZM40 produkt](https://zm40.com/en/migration-pro), [Migrationpro.io](https://migrationpro.io/)

PrestaHero na vlastní stránce uvádí 5,0/5 ze 175 hodnocení. To není nezávislé srovnání všech migrátorů a nepotvrzuje tvrzení „má nejlepší recenze“. Recenze se navíc mohou týkat jiné kombinace verzí. Pro Kafku je rozhodující pilot na skutečných datech.

**Moje doporučení:** první kandidát je přesně ten nativní MigrationPro, který sis vybral, pokud dodavatel doloží podporu konkrétní migrace a umožní pilot. Z veřejně ověřitelných materiálů je PrestaHero Ultimate relevantní alternativa. Nevolil bych automaticky jiný produkt jen kvůli shodnému názvu a nepovažuji zakoupení modulu za vyřešení integrací a šablony.

### Podmínky před koupí / použitím

Dodavateli je připravený dotaz v `OTAZKY-NA-RANO.md`. Potřebujeme vědět:

1. Konkrétní kompatibilní vydání pro zdroj PS 1.6.1.13 a cíl PS 9.2.0 v našem PHP prostředí.
2. Zachování product, combination, category, customer a order ID a správné mapování jazyků/měn/shopu.
3. Přenos cenových příplatků, specific prices, skladu, adres, dokladů, příloh, vrácení zboží a historie stavů.
4. Zachování přihlášení starým heslem a potřebný mechanismus; SQL samo nestačí k ověření klíče uloženého v souborech.
5. Rozdíl mezi „recent data“ a aktualizací již existujících záznamů, zejména skladu, ceny a stavů objednávek.
6. Chování na nalezených osiřelých řádcích a bezpečný scope finální synchronizace.
7. Pravidla licence pro staging a finální stejnou doménu.

Dokumentace PrestaHero rozlišuje režimy čistého importu, importu jen nových ID a importu s aktualizací existujících ID. **„Keep IDs“ může při prvním importu smazat vybraná cílová data**, proto první import patří do prázdného stagingu. Přesný rozsah mazání/aktualizací u finálního běhu je nutné otestovat. [Režimy modulu](https://prestahero.com/149-prestashop-better-upgrade-and-migrate-tool.html), [Keep IDs](https://prestahero.com/help-center/migrationpro-ultimate/25-keep-data-id-during-migration)

**Nedělat finální kompletní přemigraci katalogu po úpravách kategorií a nových vlastností**, pokud by je migrátor přepsal. Po pilotu zdokumentovat scope: zákazníci/adresy/nové a změněné objednávky + řízené aktualizace skladu; změny katalogu na starém shopu buď zmrazit, nebo přenést předem ověřeným aktualizačním režimem. Samotné přeskočení existujících ID nesynchronizuje změněné stavy ani sklad.

## 9. Navigace a SEO bez zbytečné práce

| Nový vstup | Zdrojové hlavní kategorie | Nejjednodušší provedení při spuštění |
|---|---|---|
| Oblečení | 107 Pánské, 105 Dámské, 82 Dětské | Nový nativní hub/menu; původní kategorie pod ním zachovat |
| Doplňky | 32, 88, 90, 3, 19, 58 | Jednotný hub/menu nad stávajícími kategoriemi |
| Merche | 111 a kapely | Zachovat ID a produktové vazby |
| Hudba | 49 CD | Celý skutečný výpis; žánry doplnit později |
| Výprodej | 81 | Zpočátku zachovat kurátorovaný výpis; změnu na automatické slevy schválit |
| Custom | 10 a CMS 10 | Informační vstup; zachovat individuální zakázkové nákupy |

CSV `category-mapping.csv` popisuje všech 77 kategorií včetně technických a neaktivních. Zachování kategorií v DB nevyžaduje jejich všechny zobrazit v menu. Nové huby mohou mít vlastní skutečné URL; jejich serverové odpovědi mají být indexovatelné. Hash adresy `#/category/...` z prototypu nejsou produkční URL plán.

### Co chránit

- Produkt: `/cs/detska-tricka/3454-tricko-punk-is-freedom-rock-shop.html`.
- Kategorie: `/cs/84-detska-tricka`.
- CMS: `/cs/content/10-jak-objednat-potisk`.
- Jazykové prefixy `/cs/`, `/sk/`, `/de/`, přepínání měny a root domény.
- ID položek v reklamních feedech, nejen viditelný název produktu.

Přesun výchozí kategorie může změnit produktovou cestu, i když ID a slug zůstanou. Proto nejprve změnit menu a vzhled; `id_category_default` ponechat. Pokud se adresa přesto změní, potřebuje přesnou 301 na odpovídající obsah, ne hromadné přesměrování na homepage.

Připravený `source-url-inventory.csv` obsahuje **13 540 rekonstruovaných URL** produktů, kategorií a CMS pro čtyři databázové jazyky. Není to crawl ani garantovaný seznam živých/indexovaných stránek. Obsahuje také neaktivní EN, neaktivní entity a technické kategorie. Vzorkované produktové adresy souhlasí s živým webem; úplný seznam před cutoverem ověřit proti sitemapě, Search Console a skutečným routám.

Na vzorkovaných stránkách jsem nenašel canonical link; homepage neměla nalezené `link[hreflang]` a produktová ukázka neměla JSON-LD skript. To není úplný audit všech SEO značek: může existovat jiné formátování strukturovaných dat. Nový web má mít korektní canonical/hreflang a produktová data podle skutečné ceny a skladu. `robots.txt` se přes dostupné prohlížení nepodařilo přečíst; existenci a obsah sitemap nebylo možné potvrdit. Chyba prohlížení není důkaz, že soubor na serveru chybí.

Všech 12 CMS záznamů má `indexation=0`. Je potřeba ověřit efekt na výstupní HTML a zachovat záměr klienta; neoznačit automaticky všechny nové stránky index/follow.

## 10. Konkrétní migrační postup

### Fáze A: inventura přístupů a úplná záloha

Z administrace ověřit skutečnou verzi, PHP, důležité moduly, případný export do účetnictví, cron a otevřené objednávky. Z FTP získat kompletní snapshot a zkontrolovat `override/`, vlastní změny `classes/` a `controllers/`, `modules/`, `themes/`, `.htaccess`, případné vlastní endpointy a cron skripty.

Zvlášť potřebujeme `img/p`, `img/c`, obrázky CMS, `download/`, `upload/`, případné module složky a uložená PDF. SQL obsahuje záznamy o obrázcích a přílohách, nikoli jejich binární soubory. Klíče z `config/settings.inc.php` potřebné pro kompatibilitu hesel uchovat bezpečně mimo veřejné soubory projektu.

Přenos starých hesel musí používat ověřený mechanismus konkrétního migrátoru. Nekopírovat bez rozmyslu starý cookie/encryption klíč přes bezpečnostní konfiguraci nové instalace; správné řešení a legacy ověřování se liší podle nástroje a musí projít login testem.

Zálohu musí být možné obnovit. Ověřit obnovu kopie, ne jen přítomnost ZIP souboru. Primární dump nemá z pohledu kontroly vazeb zásadně rozpadlé objednávky, ale nebyl obnovován do SQL serveru a nejsou ověřené soubory.

### Fáze B: nové prostředí a průchod základním nákupem

Nový document root, nová DB a samostatný PHP runtime. **Neměnit PHP společně pro starý 1.6 a nový 9.2**, pokud oba běží na jednom účtu a změna by zasáhla produkci.

Pro návrh infrastruktury počítám s Linuxem, HTTPS, podporovaným SQL serverem, alespoň 512M PHP memory limit a možností cronu. MariaDB 10.11.18 uvedená v dumpu překračuje dokumentované minimum 10.2, ale to samo nepotvrzuje celý hosting. Zdroj verze 9.2 vyžaduje PHP >=8.1; navrhuji při pilotu PHP 8.4, pokud jej konkrétní moduly a installer podporují. Dokumentace PHP matice zatím výslovně rozepisuje 9.0/9.1, proto poslední kontrola musí být pro přesnou 9.2 sestavu. [Požadavky](https://devdocs.prestashop-project.org/9/basics/installation/system-requirements/), [composer 9.2](https://github.com/PrestaShop/PrestaShop/blob/9.2.0/composer.json)

Staging chránit přístupem, noindex a nepoužívat produkční automatické e-maily/marketingové exporty. Izolaci zákaznických dat zachovat při zpřístupnění konektoru; povolit jen potřebná spojení. V prázdném obchodě ověřit standardní checkout, následně připojit platební a dopravní moduly v testovacím režimu.

### Fáze C: pilot dat

První úplný import do prázdného cíle se zachováním ID a zdokumentovanou mapou. Staré neaktivní produkty, guest zákazníky a historii zachovat. Zbytečné logy/cache vynechat.

Konektor může číst produkční zdroj, pokud je to ověřený a přístupově omezený postup. Pro zásahy do integrity a první experimenty použít zdrojovou kopii. Po dokončení dočasný přístup odstranit. Administrátory a stará bezpečnostní nastavení není potřeba automaticky kopírovat.

Přenést média podporovaným způsobem modulu nebo podle jeho dokumentace; přímé kopírování image adresářů pouze s ověřenou mapou ID. Regenerovat miniatury pro cílovou šablonu, index vyhledávání a fasety. Přílohy a odkazy uvnitř HTML popisů/CMS zahrnout do kontroly.

### Fáze D: převod schváleného návrhu

Vytvořit šablonu / child theme a prezentační modul. Skutečná Presta data budou zdrojem cen, variant, skladů, zákaznické session a košíku. Obnovit CS/SK/DE a CZK/EUR ovládání, které v návrhu není kompletní.

Nastavit šest navigačních vstupů, skutečné PLP, vyhledávání, PDP všech atributových typů, zákaznický účet, servisní CMS, formulář kontaktu a Custom postup. Úpravy katalogu držet oddělené od finálního importu. Chybějící filtry a variantové obrázky doplňovat prioritně, aby neblokovaly celý redesign.

### Fáze E: druhý průchod a nácvik finální synchronizace

Na stagingu ověřit, že migrační režim doplní nové entity, ale umí také aktualizovat sklad a existující objednávky bez duplikací nebo smazání nových kategorií/úprav. Změřit dobu přenosu, regenerace, kontrol a přepnutí. Udělat aktuální inventuru otevřených plateb a expedice.

Pokud migrátor nepodporuje bezpečnou aktualizaci požadovaných entit, použít před cutoverem zmrazení editací katalogu a malou cílenou synchronizaci změněných dat. Přesný postup rozhodne pilot; není bezpečné slíbit „stačí kliknout recent migration“.

### Fáze F: přepnutí

Úplný postup je v `SPUSTENI-A-KONTROLY.md`. V nízké návštěvnosti dočasně zastavit zápisy do starého obchodu, dořešit platby a crony, udělat finální zálohu, delta přenos, kontrolu skladu/dokladů a přepnout směrování `kafka.monster` na nový document root nebo server.

Pokud zůstává server, změna document root/proxy může být jednodušší než DNS. Při změně serveru řešit TTL, SSL a zabránit tomu, aby starý obchod během propagace přijímal jiné objednávky. MX/e-mailové DNS záznamy se nemají měnit jen kvůli přesunu storefrontu.

Starý shop zachovat jako neveřejný archiv pro historii a rollback, s vypnutými veřejnými zápisy a automatickými úlohami. Po první nové objednávce už není návrat prosté přepnutí DNS: je potřeba před návratem převést nové objednávky/platby a sladit sklad.

## 11. Odhad práce a pořadí, které šetří čas

Tohle je odborný odhad podle dostupných podkladů, ne cenová nabídka. Zahrnuje zachování schváleného designu, nikoli nový kreativní návrh.

| Balík | Pracovní odhad |
|---|---:|
| Inventura přístupů, záloha a staging | 0,5–1,5 dne |
| Pilot migrace a řešení datových výjimek | 1–2 dny |
| Šablona a dokončení skutečných nákupních stránek | 4–8 dnů |
| Platby, doprava, e-maily, feedy | 1–3 dny |
| QA, nácvik synchronizace a spuštění | 1,5–3 dny |
| Dohromady při běžném průběhu | **8–18 pracovních dnů** |

Čekání na dodavatele, nedostupné licence, zásadní overrides, kompletní překlady a ruční doplnění tisíců vlastností/obrázkových vazeb jsou navíc. Samotný import může trvat výrazně méně, ale jeho dobu nelze určit z velikosti SQL; chybí objem médií a výkon cíle. Délku odstávky nastavit až z nácviku.

Nejvíc času ušetří: zachovat URL a výchozí kategorie, zachovat jednoduchou domluvu zakázek, použít nativní session/košík/checkout, migrovat jen obchodní data a nejprve doplnit jen skutečně využitelné filtry. Nedělat mezikroky 1.6 → 1.7 → 8 → 9 jen kvůli přenosu dat, pokud migrátor ověřeně umí přímý přenos do čistého cíle.

## 12. Podklady připravené pro další práci

- `OTAZKY-NA-RANO.md`: prioritní otázky s místem ověření a připraveným dotazem dodavateli.
- `SPUSTENI-A-KONTROLY.md`: přijímací scénáře, cutover a rollback.
- `module-matrix.csv`: všech 85 modulů, přiřazení do shopu, navržené řešení a zdroje.
- `category-mapping.csv`: všech 77 kategorií a mapování na šest nových vstupů.
- `shipping-rules.csv`: všech 23 sazeb aktuálních dopravců a cenová pásma.
- `evidence/table-inventory.csv`: 306 tabulek, skutečné počty řádků a místo DDL v exportu.
- `evidence/source-url-inventory.csv`: 13 540 zdrojových URL k následné validaci.
- `evidence/audit-summary.json`: strojově čitelný agregovaný audit a provozní konfigurace bez zákaznických údajů/klíčů.
- `PRE-FLIGHT-READONLY.sql`: read-only dotazy pro ověření v aktuální zdrojové DB.
- `audit_dump.py`, `build_inventories.py`: opakovatelný audit a tvorba inventur. SQL se nevykonává.

Ráno stačí začít prvními deseti otázkami. Nejdůležitější rozhodnutí jsou přesný MigrationPro produkt, volba šablony, cílový hosting a obchodní podmínky dopravy/zakázek. Poté lze připravit skutečný staging a ověřit první migrační běh.

### Uzavření ruční analýzy dostupného kódu — 9. 10. 2026

Původní text z 5. 10. je zachovaný historický rozbor. Aktuální rozhodnutí a stav podkladů přináší ZAVER-A-POSTUP.md. Verze zdroje 1.6.1.13 je potvrzená nastavením FTP. Všech 414 sledovaných core PHP a samostatně 603 root/tools referencí je shodných s oficiálním tagem v jejich částečně se překrývajícím rozsahu. Dostupných sedm kořenových overrides má vysvětlený účel. Theme 543 a PDF 38 referencí jsou shodné; všechny SQL očekávané produktové originály a přílohy jsou dostupné.

Staré dm platební moduly jsou od Davida Mrózka / Valašince, nikoli Shaim. Nejprve ověřit aktualizace jeho licencí. DPD/reCAPTCHA/cookies jsou Shaim; jejich nárok na aktualizace řešit samostatně. Warehouse není koupený a pro schválený vlastní návrh jej nyní nedoporučuji kupovat. MigrationPro Ultimate je konkrétní kandidát, stále podmíněný potvrzením přesné sestavy a synchronizace. Staré informace o dosud nedotažených důležitých složkách už neplatí pro dostupnou momentku, **celá záloha ale není ověřená**. PHP, licence, externí integrace a skutečné chování nových modulů zůstávají provozní kontrola a pilot.

Podrobnosti: FTP-KOD-A-INTEGRACE.md, PLATBY-A-MAILY-FTP.md, DOPRAVA-FEEDY-FTP.md a OVERRIDES-DPD-ANTISPAM.md. Nákupy STAV-A-NAKUPY.md, aktuální otázky OTAZKY-NA-RANO.md a nové konkrétní testy SPUSTENI-A-KONTROLY.md. Analýza nevykonala zdrojový PHP/SQL a nezasáhla původní frontend ani produkci.
