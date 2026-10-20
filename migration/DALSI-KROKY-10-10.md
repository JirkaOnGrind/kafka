# Kafka Monster: konkrétní migrátor, šablona a další kroky

> **Překonáno posledním rozhodnutím 10. 10.: vybíráme PrestaHero MigrationPro Ultimate, PS 9.1.5 a Hummingbird 2.0.** Nákup doporučen; na potvrzení přesné 9.2 nečekáme. Platný konkrétní postup a tři bezprostřední úkoly jsou v [REALIZACNI-PLAN-PS9.md](REALIZACNI-PLAN-PS9.md). Níže je předchozí porovnání a původní volba Addons 8934 pro historii.

Aktualizace 10. 10. 2026, Europe/Prague. Uživatel nyní určil přesný MigrationPro na Addons (produkt 8934) a upřesnil, že návrh je vizuální nástřel celého obchodu; úspora několika hodin práce může odůvodnit placenou šablonu. Původní frontend ani FTP nebyly změněny, žádné licence nebyly koupené, žádný dotaz dodavateli nebyl odeslaný.

## Rozhodnutí o konkrétním MigrationPro

[MigrationPro na Addons, produkt 8934](https://addons.prestashop.com/en/data-transfer-backup/8934-migrationpro-prestashop-migration-migrate-upgrade.html) je nyní uživatelem vybraný kandidát. **Není to PrestaHero MigrationPro Ultimate z dřívějšího srovnání; jeho cena 139,99 EUR se na tento produkt nevztahuje.**

Typ nástroje odpovídá našemu plánu: přenos starého katalogu a historie do nové instalace. Konkrétní nákup ale ještě nelze označit za ověřeně bezproblémovou migraci 1.6.1.13 → 9.2.0. Aktuální Addons stránka má název s podporou PS 9/8, dostupný textový výstup však nenačetl produktové údaje o přesné verzi, ceně, synchronizaci ani licenci. Tyto informace proto nejsou potvrzené a cenu nelze převzít z jiného modulu stejného názvu.

Veřejný starší [manuál MigrationPro na edelcomponents.com](https://edelcomponents.com/modules/migrationpro/documentation/documentation_en.pdf) popisuje instalaci modulu na cílový shop a bridge konektoru na zdroj, mapování jazyků/měn/stavů a možnost zachování hesel. Dokument je starší a mluví také o migraci na 1.7, **nepotvrzuje dnešní balíček na 9.2**. Dále uvádí výchozí mazání dat cíle v automatickém režimu; toto je důvod ověřit nastavení aktuálního balíčku a použít kontrolovaný režim na čistém stagingu, ne naslepo opakovat import do již upraveného shopu.

**Moje doporučení:** tuto položku můžeš předložit klientovi jako plánovaný migrační nástroj. Před koupí si nech potvrdit následující tři body pro přesný Addons produkt; pilot potom ověří skutečná data. Produkt má vyřešit standardní data, nové platby/dopravu a vzhled řešíme samostatně.

## Text k odeslání dodavateli MigrationPro

Text je připravený, nebyl odeslaný:

> Dobrý den, chceme koupit váš MigrationPro, Addons ID 8934, pro migraci jednoho shopu z PrestaShopu 1.6.1.13 do čistého PrestaShopu 9.2.0. Máme 3 296 produktů, 11 786 kombinací a 574 objednávek, CS/SK/DE a CZK/EUR. Prosím potvrďte: (1) přesné podporované vydání vašeho modulu a bridge konektoru pro 9.2.0, PHP zdroje/cíle, zachování ID/URL, hesel, kombinací, slev, zásob, historie plateb/faktur a jazyků; (2) zda finální synchronizace přenese i změny již existujících objednávek, jejich stavů, plateb a skladů, jak zabrání duplikacím a co přepíše nebo smaže v novém shopu; (3) licenci pro staging → produkci na kafka.monster a konečnou cenu včetně případné obnovy podpory. Zdroj má dvě osiřelé kombinace s parent product ID 0; potřebujeme je bezpečně vynechat bez ztráty historie. Vlastní Comgate/Packeta/DPD tabulky budeme řešit zvlášť, pokud je standardní přenos nepodporuje.

Skutečné PHP doplníme po podkladech WEDOS. Nejde o nutnost žádat klienta o další obecné schválení analýzy; zbývá konkrétní kompatibilita kupovaného balíčku a jeho licenční podmínky.

## Frontend znamená celý fungující obchod

Schválený nástřel určuje vzhled. Výsledný PrestaShop bude mít všechny migrované produkty, kategorie, detail produktu, varianty, galerii, vyhledávání/filtry, košík, checkout, účet, jazyky, měny a servisní stránky. Neplánujeme pouhou homepage.

**Produkty se nemají ručně přepisovat do HTML ani vytvářet po jedné kartě.** Migrátor uloží katalog a vazby do DB. Šablona vykreslí seznam produktů a detail podle dat aktuálního produktu/kategorie. Jedna dobře napojená produktová karta tak obslouží celý katalog. Chybějící data jsou jiný úkol: ve zdroji nejsou vazby obrázek–kombinace ani nové vlastnosti požadovaných filtrů, takže jejich doplnění nepokryje žádná šablona automaticky.

## Hummingbird versus Warehouse

| Hledisko | Hummingbird 2.1.x | Warehouse |
| --- | --- | --- |
| Celý e-shop | Nativní šablona pro kompletní PS shop, nikoli jen homepage | Kompletní šablona s dalšími IQIT moduly |
| Všechny produkty | Zobrazuje migrovaný katalog přes PS entity | Stejný princip, licence nemigruje produktová data |
| Úprava vzhledu podle nástřelu | Přímá práce s nativní šablonou a cílenými moduly | Theme editor, builder a přizpůsobení existujícího základu |
| Kdy šetří práci | Když chceme vlastní vzhled a využijeme nativní PS funkce | Když přímo využijeme editor, mega menu, velikostní tabulky a další hotové funkce |
| Kdy práce přibývá | Když je nutné vlastními moduly doplnit mnoho nadstandardních funkcí | Když přepisujeme většinu markup/layoutů a řešíme překryvy přibalených modulů |
| Doložená verze | Oficiální tabulka uvádí 2.1.x pro PS 9.2 | Výrobce uvádí 4.8.x pro PS 9.x; changelog 4.8.2 výslovně zmiňuje ověření 9.1, přesnou 9.2 ještě potvrdit |
| Licence/cena | Veřejná bezplatná šablona | Produktová stránka 10. 10. zobrazuje 119 USD; konečnou částku ověřit v checkoutu |

[Oficiální Hummingbird](https://github.com/PrestaShop/hummingbird), [Warehouse – výrobce na ThemeForest](https://themeforest.net/item/warehouse-responsive-prestashop-16-theme-blog/3178575), [IQIT dokumentace editoru a modulů](https://iqit-commerce.com/xdocs/warehouse-theme-documentation/).

Pro práci s AI se nerozhoduje podle toho, zda je šablona placená. Pracujeme se skutečnými soubory, dokumentací, nativními vazbami a testovacím shopem. Hummingbird má otevřený zdroj a dokumentaci architektury; aktuální repozitář obsahuje i podklady pro AI vývoj. Jejich stav na develop větvi ale není automaticky totožný s instalovaným 2.1.x balíčkem; při implementaci rozhodují jeho konkrétní soubory. Není nutné kupovat Warehouse jen proto, aby AI znala strukturu PrestaShopu.

**Pro Kafka nyní preferuji Hummingbird. Warehouse je rozumný placený kandidát, pokud chceme používat jeho hotové editory a doplňky, nikoli je obcházet vlastním kódem.** Úsporu hodin před porovnáním konkrétních požadovaných obrazovek a funkcí neumím slíbit. Rozhodnutí o přenosu všech produktů tuto volbu nemění. Před případnou koupí Warehouse potvrdit 9.2 a vybrat konkrétní využívané funkce; základní funkce už obsažené v PrestaShopu nepovažovat za jeho placenou úsporu.

## Co udělat teď

1. **WEDOS podklady:** pošli zakrytý screenshot tarifu a konfigurace PHP. Ověř samostatné cílové prostředí/PHP; během vývoje neměnit službu starého shopu. Potvrď dokončenou FTP frontu a nevyřešené přenosy, nikoli jen počet souborů.
2. **Konkrétní nabídka migrátoru:** použij dotaz výše a pošli odpověď dodavatele + aktuální zobrazenou cenu/verzi z Addons. Přesný odkaz už máme, není potřeba jej hledat znovu. Do rozpočtu nevkládat cenu PrestaHero.
3. **Šablona:** výchozí volba Hummingbird. Pokud chceš Warehouse kvůli klientově pozdější editaci nebo hotovým nadstandardním funkcím, napiš které z uvedených funkcí využijeme. Nepotřebujeme znovu kreslit schválený vzhled; potřebujeme jej aplikovat na všechny hlavní stránky.
4. **Zkušební shop:** následuje čistý chráněný PS 9.2, jazyky/měny, instalace vybraného migrátoru a kontrolovaný pilot. Konektor a jakýkoli první zápis na produkci připravit jako samostatný implementační krok po obnovitelné záloze.
5. **Funkční převod:** pilotně zkontrolovat katalog/historii, nastavit podporované platby a dopravu, převést všechny potřebné šablony. Potom ověřit ceny, varianty, účet, objednávku, pobočku, maily a fakturu. Spuštění na ostré doméně až po nacvičené finální synchronizaci.

Původní detailní analýza a výjimky dat zůstávají v ZAVER-A-POSTUP.md a MIGRACNI-ANALYZA.md. Tento dokument upřesňuje konkrétní produkt a šablonu podle nové zprávy uživatele; není povolením provést ostrý import.
