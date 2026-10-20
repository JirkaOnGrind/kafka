# Packeta, Mergado a návazné úlohy — průběžné ruční posouzení FTP

Posouzeno 9. 10. 2026 kolem 01:14 Europe/Prague. Zdroj pouze čten, PHP a SQL nebyly vykonány. Soubory přenosu přibývají; tento dokument neuzavírá celý audit ani úplnost zálohy. Bezpečné otisky posouzených souborů, vybrané názvy sloupců a konkrétní řádky nálezu jsou v `evidence/integration-code-audit.json`.

## Packeta / Zásilkovna

Hlavní `modules/packetery/packetery.php` deklaruje verzi **2.0.9**, odpovídající SQL. Soubor config.xml má starší údaj 2.0.7; pro inventuru nepoužívat XML jako jediný důkaz. Veřejná metadata uvádějí Packetery, Ltd. Starý balíček nelze zaměnit za současný modul pro PS 9.

`hookNewOrder()` ukládá vazbu košíku a objednávky na dopravce, výdejní místo/adresní službu a dobírku. Dobírka se odvozuje také z mapování platebních modulů. `hookUpdateCarrier()` řeší změnu ID dopravce při jeho editaci v PrestaShopu. Nový shop proto potřebuje mapu starý dopravce → nový dopravce/služba; stejné číslo v nové DB samo o sobě kompatibilitu nedokazuje.

`AdminOrderPacketery::exportCsv()` je konkrétně dostupná cesta pro export zásilek. Čte vybrané objednávky, dovoluje změnit dobírku a váhu a vytváří CSV s identifikátorem objednávky, hodnotou, dobírkou, měnou, váhou, místem a doručovacími údaji; osobní hodnoty nebyly čteny ani exportovány. Rozlišuje preferenci ID objednávky nebo reference. Výchozí váhu bere z objednávky, pokud nebyla zadaná ručně. Příznak `exported` se používá v přehledu objednávek. CSV export byl ověřen staticky; není tím potvrzen celý skutečný postup klienta ani to, že tento balíček jinou cestu vůbec nemá. V rámci analýzy se žádná zásilka nevytvářela.

Tabulka packetery_order má podle předchozí SQL inventury **557 řádků**. Instalační schéma a čtený kód obsahují mimo jiné id_cart, id_order, id_branch, name_branch, currency_branch, carrier_pickup_point, is_cod, weight a exported. Obecný migrátor objednávek přenos těchto vlastních polí nezaručuje. Při neslučitelném novém schématu zachovat čitelný chráněný historický archiv a samostatně přenést data nevyřízených objednávek podle mapy IDs. Starou cache poboček nepoužívat jako aktuální nabídku.

Výdejní místo se zobrazuje v administraci i detailu/potvrzení objednávky. Mailový hook přidává jeho údaje, ale zároveň přiřazuje celé pole `extra_template_vars` znovu. To vytváří kandidáta na konflikt s proměnnými platebních modulů podle pořadí hooků; nejde o pozorovanou chybu produkce. V pilotu ověřit současně místo, dobírku a platební odkaz ve všech používaných jazycích.

Cílový kandidát je současný oficiální modul Packety. Oficiální repozitář uvádí řadu PS 1.7 až 9; kompatibilita přesného vydání s PS 9.2 a schválenou vlastní šablonou stále potřebuje ověření. [Oficiální repozitář Packety](https://github.com/Zasilkovna/prestashop).

## Mergado Pack

Dostupný zdroj je **4.1.0**, veřejný autor www.mergado.cz. Modul obsluhuje nejen feedy: v objednávkovém hooku volá integrace hodnocení/srovnávačů a v hooku změny stavu má cesty pro analytické refundace. Existence kódu neznamená, že má klient všechny tyto služby aktivní. Aktivované služby a jejich obchodní účel potvrdit v administraci bez předávání klíčů.

`cron.php` přijímá parametr feed a spouští FeedGeneratorManager. Ten vybírá produktový, kategoriální, dostupnostní nebo statický feed. Skript podporuje HTTP i CLI a kontroluje token odvozený ze starého shopu a instalaci modulu. Skutečný rozvrh úloh WEDOSu z FTP nelze zjistit. Nová instalace potřebuje vlastní podporované adresy/tokeny a plán; konkrétní tokeny se do reportů nekopírují.

`importPrices.php` je **zapisující cenový import**, nikoli pouze exportní feed. ProductPriceImportService stahuje nakonfigurované XML, kontroluje LAST_BUILD_DATE a pokračuje v dávkách. ITEM_ID rozkládá na produkt a případnou kombinaci. U samotného produktu nastavuje základní cenu; u kombinace počítá dopad jako dodaná PRICE minus základní cena produktu. Zachování nebo explicitní přemapování ID je proto důležité také pro externí cenový soubor. Při změně ID nebo jiném významu cen nelze import bez dalšího zapnout.

**Cenový import vypíná ověření TLS certifikátu i hostitele** (`ProductPriceImportService.php:136–137`). To oslabuje ověření serveru dodávajícího ceny. Aktivace tohoto importu v provozu není potvrzená a není zjištěn incident. Starou implementaci nekopírovat; u nového balíčku ověřit TLS a povolený zdroj XML před zapnutím. Ceny a příplatky variant porovnat před i po pilotním importu, v CZK/EUR a s konkrétním nastavením daní.

### Tři přibalené overrides

| Modulová cesta | Dostupné chování | Dopad na migraci |
| --- | --- | --- |
| override/classes/Product.php | Pouze nastavuje pack_stock_type na 3. Stejnou výchozí hodnotu má ProductCore v oficiální 1.6.1.13. | V tomto přibaleném těle není nová skladová funkce; nepřenášet jej do PS 9. V katalogu navíc nebyly zjištěny balíčky. |
| override/controllers/front/OrderController.php | Pro PS 1.6 a nižší ukládá souhlasy pro Heureka, Zboží, Arukereso/Compari/Pazaruvaj a Biano před parent::init(). | Nahradit aktuální integrací souhlasů v nativním checkoutu. Samotná přítomnost neprokazuje aktivní použití všech služeb. |
| override/controllers/front/OrderOpcController.php | Obdobné souhlasy při OPC; v příslušné větvi odstraňuje ajax z POST/GET. | Nekopírovat starý controller; otestovat souhlasy a aktualizaci dopravy/platby v cílovém checkoutu. |

Tyto tři soubory jsou kandidáti na původ stejnojmenných cest zaznamenaných v cache/class_index.php. **Kořenová těla overrides dosud nejsou stažená a shoda není potvrzená.** Platí totéž pro Order override z dobírkového modulu. Hook/Auth/Contact a případné další úpravy nelze těmto modulům zatím přiřadit.

Oficiální stránka nyní uvádí Mergado Pack **4.2.1**, podporu rodiny PS 9 a PHP 8+; samotný modul je bezplatný. FAQ téže stránky obsahuje i starší údaj PHP 7.1, proto pro vybraný balíček rozhodují aktuální požadavky a pilot. Podpora rodiny PS 9 není provedený test PS 9.2. [Mergado Pack pro PrestaShop](https://pack.mergado.cz/prestashop).

## Mapa funkcí do čistého PS 9.2

| Obchodní funkce | Cílová náhrada / kontrola |
| --- | --- |
| Výběr pobočky, adresa a Packeta dopravci | Současný podporovaný modul, čerstvý seznam míst, mapa dopravců a test mobilního checkoutu. |
| Export zásilek, ruční váha a dobírka | Potvrdit klientův CSV/API postup; reprodukovat jej v novém modulu a zachovat otevřené zásilky bez duplikací. |
| Historie poboček a exportu | Samostatná migrace podporovaných polí nebo chráněný archiv; obecný import orders nestačí. |
| Produktový/kategoriální/dostupnostní feed | Nový Mergado Pack, validace ID a URL i cen; přepnout odběratele až po akceptaci. |
| Import cen produktů a variant | Nejprve potvrdit používání; samostatný test XML, vazeb IDs, ceny produktu a dopadu kombinace, TLS a dávkového dokončení. |
| Recenze a analytické konverze/refundace | Zapnout pouze skutečně používané služby, bez opakovaného odeslání historických objednávek a bez duplikace frontendových značek. |
| Souhlasy v checkoutu a cookie lišta | Jedna ověřená sestava souhlasů a aktuálních modulů; otestovat udělení, odmítnutí i odvolání souhlasu. |
| Cron na WEDOSu | Zjistit názvy/frekvence/účel, nové adresy bezpečně zapsat až při implementaci, určit jediný aktivní zdroj. |

Nejlehčí postup zůstává čistá izolovaná instalace, současné moduly a standardní checkout před napojením schváleného vzhledu. Nové nálezy nezakládají důvod kupovat Warehouse. Odhad 8–18 pracovních dnů zůstává podmíněný; cenový import a vlastní schémata dopravy přidávají konkrétní kontrolní kroky. Dokud nejsou potvrzené používané integrace a zbývající overrides, nejde o pevný rozpočet.

## Konkrétně zbývá

- Kořenová těla sedmi overrides; doložit shodu nebo rozdíly proti modulovým kandidátům.
- DPD, antispam a cookie moduly, theme, fakturační PDF šablony a vlastní kořenové skripty/feedy dosud nedotažené v této momentce.
- Provozní nastavení Mergada, cron fronta WEDOSu, způsob expedice Packety a otevřené zásilky.
- Po dokončení FTP nová inventura a potvrzení vzdáleného manifestu/fronty; čerstvá SQL záloha před pilotem.

### Uzavření ruční analýzy dostupného kódu — 9. 10. 2026

Všechna tři kořenová Mergado těla i dobírkový Order už jsou dostupná a obsahově přiřazená modulovým kandidátům. Sedm skutečných kořenových overrides vysvětluje OVERRIDES-DPD-ANTISPAM.md a evidence/override-origins.json. Dřívější nejistota chybějících kořenových těl je vyřešená.

DPD 2.3.8 od Shaim má dokončený rozbor: místo k id_cart, reference dopravců, admin, mail/PDF hooky, cron aktualizace cache. Samotná tato funkce neprokazuje automatické podání zásilek/štítků. Cíl vyžaduje novou podporovanou sestavu a skutečný klientův expediční postup. Cookie seznamy jsou v dumpu serializované false; měření a Mergado souhlasy vyžadují runtime ověření. Root/tools reference byly dokončeně srovnány bez obsahových změn, neznámé externí cron rozvrhy stále potvrdit. Souhrnná mapa a rozhodnutí jsou v ZAVER-A-POSTUP.md.
