# Kafka Monster: závěr analýzy a nejlehčí postup migrace

> **Aktuální realizační rozhodnutí 10. 10.: PrestaHero MigrationPro Ultimate + čistý PS 9.1.5 + Hummingbird 2.0.** Poslední zadání uživatele ruší požadavek přesné 9.2. Nákup migrátoru doporučen. Konkrétní pořadí importu, vývoje, synchronizace a přepnutí je v [REALIZACNI-PLAN-PS9.md](REALIZACNI-PLAN-PS9.md). Zjištění auditu níže platí; starší nákupní rozhodování je zachováno jako historie.

> Doplnění 10. 10.: přesný uživatelův migrátor je Addons **8934**, není to PrestaHero Ultimate. Návrh je vzhled celého obchodu; migrovaný katalog vykreslí nativní šablony, produkty se nemají ručně přepisovat do HTML. Aktuální porovnání Hummingbird/Warehouse a další kroky: [DALSI-KROKY-10-10.md](DALSI-KROKY-10-10.md).

Stav 9. 10. 2026, Europe/Prague. Poslední FTP inventura: 01:31. Podklady: schválený lokální frontend, dodané SQL, dříve prohlédnutý veřejný obchod, nyní dostupný kód FTP a primární dokumentace. Jde o dokončenou přípravnou analýzu dostupných podkladů, nikoli provedenou migraci nebo certifikaci celé zálohy. Původní projekt a FTP zůstaly beze změny; zdrojové PHP/SQL nebyly spuštěny. Nic nebylo koupeno ani odesláno dodavatelům.

## Rozhodnutí

**Migrace je dobře proveditelná. Doporučuji čistý PrestaShop 9.2.0 v odděleném prostředí u WEDOS, přenos standardních dat migrátorem a vlastní nativní šablonu podle schváleného návrhu.** Největší práce bude napojení skutečného nákupu a ověření plateb, dopravy a obchodních pravidel. Sledované jádro neukázalo obsahové úpravy a všech sedm kořenových overrides má vysvětlenou modulovou funkci. Výsledky podporují čistou migraci, bez přenášení starého kódu 1.6.

Schválený frontend je HTML/CSS/JS prototyp. Potřebuje skutečné ceny a příplatky variant, košík/session, zákaznický účet, checkout, výdejní místa a serverové zpracování objednávky. Migrační modul tuto práci neudělá. Nejlehčí cestou je nativní PS šablona a standardní checkout, s Hummingbird jako ověřovaným základem; samostatný headless obchod by přidal práci s integracemi.

## Co už máme prokázané

| Oblast | Zjištění | Praktický dopad |
| --- | --- | --- |
| Zdrojová verze | settings.inc.php potvrzuje **1.6.1.13**, SQL odpovídá | Porovnáváme správnou oficiální verzi |
| Standardní data v dodaném SQL | **3 296 produktů**, 11 786 kombinací, 574 objednávek, 582 zákaznických řádků | Běžný migrovatelný katalog a historie; zákaznické řádky zahrnují 467 hostů a 115 registrovaných |
| Datové výjimky | Dvě kombinace s parent product ID 0; 25 historických položek objednávek po smazaných produktech | Osiřelé kombinace řešit na cíli, účetní historii zachovat |
| Jádro | **414/414** sledovaných PHP souborů přesně shodných s oficiálním tagem | V tomto rozsahu žádné obsahové core rozdíly ani dodatky |
| Rozšířený rozsah root/tools | **603** referenčních PHP souborů přesně shodných | Bez rozdílů, nedotažených referencí a dodatečných tools PHP; nesčítat bez deduplikace s předchozím rozsahem |
| Overrides | **7/7** dostupných a vysvětlených | Dobírka, Mergado souhlasy, antispam, cookie filtr; stará těla do PS 9 nekopírovat |
| Stará šablona a PDF | **543** referenčních theme souborů a **38** PDF souborů přesně shodných | V porovnaném rozsahu nebyl zjištěn vlastní checkout či vlastní fakturační layout |
| Média | **4 962/4 962** očekávaných originálů a **9/9** příloh dostupných a nenulových | Dodaný SQL má potřebná základní média v kopii; provedené dekódování originálů nezjistilo chyby |
| Moduly | Všech **78** SQL přiřazených modulů má dostupný vstupní PHP | Lze určit zdroj hlavních integrací; nemusí být nutné obnovit každou starou prezentační funkci |

Čísla platí pro dodaný dump a uvedené lokální momentky. Inventura shopu má 109 944 souborů, přibližně 1,61 GB v desetinných jednotkách; velkou část tvoří cache a odvozené obrázky. **Tento počet neprokazuje dokončení FTP.** Zbývá potvrzení dokončené fronty bez nevyřešených přenosů, případně vzdálený manifest a zkouška obnovy souborů spolu s čerstvou DB. Audit nezaručuje shodu s aktuálními zápisy na produkci.

## Nákupy před fakturací klientovi

| Položka | Doporučení nyní | Co musí předcházet nákupu / aktivaci |
| --- | --- | --- |
| **Warehouse** | **Nekupovat pro nynější rozsah** | Schválený vlastní návrh nevyžaduje tento builder; žádná doložená úspora práce |
| **MigrationPro** | Technicky vhodný kandidát, podmíněný souhlas s nákupem | Přesný odkaz/výrobce, potvrzení 1.6.1.13 → 9.2.0, PHP, staging licence, ID/hesla/historie a finální nové i změněné záznamy |
| PrestaHero MigrationPro Ultimate | Relevantní konkrétní kandidát; dříve zobrazená cena 139,99 EUR | Cena je údaj z 7. 10., ne nabídka včetně daně/kurzu; přesnou sestavu a podmínky potvrdit |
| Comgate + dobírka | Nejprve nárok na aktualizace **David Mrózek / Valašinec** | Staré dm moduly jsou od tohoto autora; dostupný podporovaný balíček a pilot 9.2 |
| DPD, reCAPTCHA, cookie modul | Nejprve existující licence **Shaim / psmoduly.cz** | Vybrat aktuální potřebné moduly; nekupovat automaticky druhou CMP |
| Packeta | Ověřit aktuální oficiální modul | Checkout i skutečný postup expedice, ne pouze zobrazení mapy |
| Mergado Pack | Dostupný současný modul je bezplatný | Externí služba/účet samostatně; potvrdit feedy, případný cenový import a souhlasy |
| Hosting | Oddělené cílové prostředí u WEDOS | Tarif, PHP, paměť, rozšíření, cron, kapacita; subdoména sama nezaručuje jiné PHP |

„Migration Pro“ používá více produktů; nelze potvrdit nejlepší recenze ani podporu přesné verze jen ze SQL. Připravený dotaz je v [STAV-A-NAKUPY.md](STAV-A-NAKUPY.md). [PrestaHero produkt](https://prestahero.com/149-prestashop-better-upgrade-and-migrate-tool.html), [Hummingbird vydání](https://github.com/PrestaShop/hummingbird/releases), [DPD výrobce](https://psmoduly.cz/dpd-parcel-shop-pick-up-vyber-odberneho-mista-primo-v-objednavce-alzaboxy-242/vydejni-mista.htm), [Packeta](https://github.com/Zasilkovna/prestashop), [Mergado](https://pack.mergado.cz/prestashop).

## Obchodní funkce → nejlehčí náhrada

| Funkce starého shopu | Cílové řešení a rozhodující kontrola |
| --- | --- |
| Katalog, varianty, slevy, sklady | Migrátor se zachováním ID, porovnání základních cen a dopadů variant; dvě osiřelé kombinace izolovat |
| Historie a faktury | Zachovat uložené součty, měny, platby, čísla a vazby; nerepočítat historii novými pravidly |
| Zákaznické účty | Podporovaný přenos hesel a testovací účet; moderní reset hesla, staré session nepřenášet |
| Comgate a opakované platební odkazy | Aktuální modul; otevřené staré transakce a tokenové odkazy vypořádat samostatně |
| Dobírkové | Současný modul, potvrzená sazba/limity; zachování příplatku po editaci objednávky |
| Bankovní převod | Podporovaný současný modul, potvrzený postup párování pozdějších úhrad starých objednávek |
| Packeta / DPD Pickup | Současné moduly, mapa dopravců a jejich referencí, zachování místa u rozpracovaných objednávek |
| Expedice / štítky | Obnovit klientův skutečný CSV/API/ruční postup; samotný výběr pobočky nestačí |
| Mergado feedy a případný import cen | Nové crony a podporované URL, ověřené produktové/variantní ID; export a zapisující import rozlišit |
| reCAPTCHA a souhlasy | Podporovaná ochrana formulářů a jedna funkční CMP; test skutečné validace i blokování měření |
| Individuální potisk | Zachovat domluvu → konkrétní produkt zakázky → běžnou platbu, dokud klient neschválí změnu |
| Nová navigace / filtry | Šest rozcestníků nad původním katalogem; nové vlastnosti a vazby fotek variant doplnit cíleně |
| Maily a PDF | Nové podporované šablony; místo, příplatky a platební odkaz ověřit současně ve všech používaných jazycích |
| SEO | Zachovat ID, slugs, jazyky a výchozí kategorie; nezbytné změny pokrýt 301 mapou a novou sitemap |

Vlastní tabulky Comgate/Packety/DPD nejsou automaticky zahrnuté v běžném přenosu objednávek. Pro uzavřenou historii lze použít chráněný starý archiv; rozpracované objednávky vyžadují mapování do skutečně podporovaného cílového schématu. Importující modul nesmí pouze slepě kopírovat staré tabulky.

## Nejlehčí pořadí práce

1. **Uzavřít zálohu a provozní podklady.** Potvrdit FTP frontu, obnovitelnost, aktuální PHP/tarif a klientova pravidla. Čerstvý SQL export hlavní DB bude potřeba před pilotem i přepnutím.
2. **Vybrat kompatibilní sestavu a oddělený staging.** PS 9.2.0, odpovídající PHP, migrátor, šablona a podporované moduly. Nepřepínat PHP služby se starým shopem. Staging chránit, vypnout produkční e-maily, zápisy do externích služeb a ostré platby.
3. **Provést malý funkční pilot, potom plný datový pilot.** Produkt s variantami, CZK/EUR, účet i host, Comgate, dobírka, Packeta/DPD. Následně porovnat celý katalog, sklady, slevy, historii, doklady, přihlášení a média.
4. **Napojit schválený vzhled na ověřený nativní nákup.** Skutečné entity místo demonstračních cen a košíku v paměti. Současně doplnit CS/SK/DE, měny a servisní stránky. Prioritní filtry/fotky doplnit bez hromadné změny původních URL.
5. **Nacvičit finální synchronizaci.** Nové i změněné objednávky, platby, stavy a zásoby, bez duplikací a přepsání nových obsahových úprav. Změřit čas a vyřešit pozdní platby a rozpracované zásilky.
6. **Po akceptaci připravit přepnutí a rollback.** Krátce zastavit zápisy, udělat snapshoty, finálně synchronizovat, přepnout doménu/endpointy a ověřit reálný průchod. Starý shop ponechat neveřejný. Po první nové platbě rollback vyžaduje sladění událostí, ne jen změnu DNS.

Orientační odhad zkušeného implementátora zůstává **8–18 pracovních dnů** při uvedeném rozsahu a dostupných podporovaných modulech. Není to pevná nabídka ani slíbená odstávka. Doplnění kompletních filtrů, upload/kalkulátor potisku nebo nové ERP nejsou v tomto odhadu automaticky zahrnuté. Čas finální odstávky určí nácvik.

## Rozhodnutí a rizika, která zbývají ověřit

- Doprava zdarma se liší mezi návrhem, veřejným obchodem a konfigurací: 1 500 / 1 699 / 3 000 Kč. Dobírka má v aktivních CZ pravidlech 49 Kč, veřejný text 30 Kč. Odeslání 48 hodin vs. čtyři pracovní dny. Potvrdit závaznou politiku včetně CZ/SK/EUR.
- Starý Comgate a cenový import Mergada vypínají TLS ověřování. Comgate má také kandidátní riziko opožděného PAID callbacku po doručení. Jsou to statické nálezy, nikoli prokázaný incident; nové sestavě ověřit nápravu a stavový postup.
- Starý reCAPTCHA při síťovém selhání ověřování propouští. Cookie filtr má v dumpu oba seznamy modulů serializované false. Obě funkce je potřeba ověřit na novém checkoutu a normálním prohlížeči.
- Záloha neprokazuje skutečné PHP hostingu, existující licence, WEDOS cron rozvrh, externí účetní/skladové integrace ani aktuální počet otevřených plateb. Tyto odpovědi jsou připravené v prioritách pro ráno.
- **Při analýze můj filtr jednou propustil citlivý údaj z nestandardní konfigurace do výstupu nástroje.** Není v reportech a nebyl použit. Filtr byl opraven a synteticky ověřen; doporučuji příslušný údaj změnit. Jeho platnost nebyla testována, produkce nebyla změněna.

## Kde najít podrobnosti

- [OTAZKY-NA-RANO.md](OTAZKY-NA-RANO.md): na začátku pouze dosud potřebné odpovědi; starší seznam je zachován jako historie.
- [MIGRACNI-ANALYZA.md](MIGRACNI-ANALYZA.md): původní podrobný SQL, webový a frontendový rozbor, doplněný o nové závěry.
- [PLATBY-A-MAILY-FTP.md](PLATBY-A-MAILY-FTP.md): původ a chování Comgate/dobírky/převodu, maily a cílové testy.
- [DOPRAVA-FEEDY-FTP.md](DOPRAVA-FEEDY-FTP.md): Packeta, Mergado, exporty/importy a crony.
- [OVERRIDES-DPD-ANTISPAM.md](OVERRIDES-DPD-ANTISPAM.md): sedm overrides, DPD, reCAPTCHA, cookie filtr a Usercentrics.
- [FTP-KOD-A-INTEGRACE.md](FTP-KOD-A-INTEGRACE.md): datovaná historie a poslední porovnání theme/PDF/tools/mailů.
- [FTP-ANALYZA.md](FTP-ANALYZA.md): poslední automatická inventura; [SPUSTENI-A-KONTROLY.md](SPUSTENI-A-KONTROLY.md): pilot, akceptace, přepnutí a návrat.

Ruční analýza důležitých nyní dostupných součástí je hotová. Dočasná automatizace „Dokončit FTP analýzu Kafka Monster“ byla pozastavena; stav PAUSED je potvrzený nástrojem i uloženou konfigurací. Pozastavení nemění ani nevypíná FTP přenos. Další etapa je potvrzení podkladů od klienta, dodavatelů a hostingu a teprve potom implementace/pilot. Celá záloha ani společná funkčnost přesné cílové sestavy zatím nejsou ověřené.
