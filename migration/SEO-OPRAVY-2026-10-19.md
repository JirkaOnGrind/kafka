# Kafka Monster — opravy SEO a opakování po importu, 19. 10. 2026

Cíl této práce je výhradně izolovaný PS 9.1.5 na `http://localhost:8083/`. Nový SQL se nestahoval ani neimportoval, produkce a samostatná instalace 8080 se neměnily. Katalog, překlady a média se nedoplňovaly.

## Provedené opravy

- Obnoveno původní nativní produktové pravidlo `{category:/}{id}-{rewrite}{-:ean13}.html`, české `/cs/` a odpovídající aktivní jazykové verze SK/DE. Původní formáty dodavatelů a značek zůstávají kompatibilní.
- Canonical změny mají trvalé 301. Kořen a explicitní původní volba jazyka zůstávají dočasné 302. Staré `index.php?controller=product&id_product=…&id_lang=…` zachovávají požadovaný aktivní jazyk.
- Dočasné ploché PS9 produktové adresy přejdou přímo na původní strukturu. U ploché adresy s ID kombinace se ověří skutečná vazba produktu a kombinace a zachová se varianta. Nativní kombinace dále používají parametr a kotvu, nikoli nově vymyšlená katalogová data.
- Modulový override `Link::getProductLink` odstraňuje pouze prázdný otazník generovaný PS9 při pravidle bez ID kombinace. Tím odstraňuje nativní canonical smyčku. Neprázdné variantové parametry a kotvy zůstávají. Jádro není upravené.
- Vykreslený titulek dostává ` - Kafka Monster` pouze tehdy, pokud značku již neobsahuje; OG používá stejnou hodnotu. Vlastní uložené titulky a další katalogová metadata se nepřepisují. Známé anglické instalační titulky v češtině opravuje existující podmíněný pomocník; vlastní systémová metadata zachovává.
- `?p=2` přejde jedním 301 na skutečné `?page=2`; zachovají se jiné parametry i jazyk. Přitom se opraví i případně zastaralá cesta, aby nevznikal zbytečný řetězec.
- Košík zůstává bez veřejného canonical a v HTML má explicitní `noindex, nofollow`. Celková ochrana localhost v HTTP hlavičkách zůstává zapnutá.
- Sitemapy jsou vytvořené z existujícího způsobilého místního obsahu a obsahují vzájemné jazykové alternativy. Indexy `/1_index_sitemap.xml` a `/sitemap.xml` mají shodný obsah; šest dílů obsahuje 9 792 URL, po 3 264 v CS/SK/DE. Neaktivní angličtina, neaktivní nebo neveřejný obsah, varianty a soukromé stránky jsou vyloučené.

## Automatický postup po dalším SQL

Implementaci a přesné příkazy popisuje [SEO-POSTUP-PO-IMPORTU.md](SEO-POSTUP-PO-IMPORTU.md). Hlavní procedura je `scripts/complete-isolated-import.ps1`; její seznam spravovaných hodnot je `scripts/migration-seo-policy.json`. Procedura skutečně nasadí modul z projektu do běžícího volume 8083, uloží zálohu, aplikuje nastavení, zkontroluje idempotenci, vytvoří sitemapu a spustí nový audit. Není to pouze dokumentovaný seznam ručních úkonů.

Soukromý existující `run-batches.ps1` deleguje na verzovaný `scripts/run-isolated-batches.ps1`, který po každé dokončené migraci automaticky volá SEO přejímku. `recover-accepted.ps1` ji volá po obnově cílového checkpointu. Oba původní soukromé skripty mají datované záložní kopie. Jiný schválený projektový adaptér se spouští přes `scripts/invoke-isolated-import.ps1`: uloží původní cílovou DB, předá konkrétní hashovaný vstup a po importu automaticky spustí opravu a audit. Kontroluje izolaci, původní SHA-256 a alespoň 4 GiB rezervy před zálohou i po ní. Samotný wrapper nové oprávnění k importu nedává.

Povinnost tohoto postupu je přidaná do `AGENTS.md` i `JAK-PRACOVAT.md`. Neúspěšný audit znamená nepřevzatý import. Existující HTTP výsledky se znovu nepoužívají; každý běh má nový export, novou evidenční složku a čerstvé odpovědi. Před paralelní částí se aktivní jazyky načtou postupně, aby vytvoření nativních překladových katalogů nesoutěžilo mezi prvními požadavky.

## Výsledky ověření

**Finální automatický běh prošel 19. 10. 2026 ve 14:41:51 místního času.** Evidence: [REPORT.md](evidence/seo-20261019-143403-984/REPORT.md), [summary.json](evidence/seo-20261019-143403-984/summary.json). Předchozí neúspěšné a přerušené běhy zůstávají zachované a nejsou vydávané za důkaz úspěchu.

| Kontrola | Finální výsledek |
|---|---|
| Čerstvé HTTP výsledky na cíli | 1 755; 1 388 konečných 200 a 367 očekávaných 404; bez 500, transportních chyb, smyček a nadbytečných řetězců |
| Původní vstupní URL baseline | 888 porovnaných; 533 funkčních existujících entit, 7 systémových vstupů, 44 chybějících jazykových URL a 304 adres nedostupných už na zdroji; nula routingových regresí |
| Chybějící/neaktivní obsah proti historicky funkčnímu zdroji | 44 jazykových URL, samostatně v `missing-content.csv`; žádná náhrada cizím obsahem |
| Vlastní metadata | 13 656 katalogových a systémových jazykových záznamů před/po opravě; žádná neautorizovaná změna |
| Titulky a OG | Značka právě jednou; český košík a přihlášení ověřené ve čerstvém HTML; vlastní metadata zachovaná |
| Canonical a hreflang | Veřejné úspěšné stránky: správná lokální cesta, aktivní jazyky, přímé cíle, stejná entita a vzájemnost alternativ; soukromý košík má nativně prázdný canonical a explicitní noindex |
| Přesměrování | Opravy cesty 301 jedním krokem; původní explicitní jazyk a kořen 302; query jazyk i ID kombinace zachované |
| Staré stránkování | Všech šest CS/SK párů prošlo: `p=2` vede na obsah skutečné druhé stránky, správný canonical a zachované další parametry |
| XML | 9 792 způsobilých URL v šesti souborech; CS/SK/DE po 3 264; všechny položky i alternativy porovnané s místními daty |
| Idempotence | Opakované nastavení: nula změn, požadované hooky aktivní a kompatibilní Link override načtený |
| Automatické kontrolní body | 583/583 prošlo; raw HTTP navíc bez transportních nebo serverových chyb |
| Desktop a mobil | 51/51 prohlížečových kontrol; bez JS a HTTP chyb; správná varianta v košíku a vlastní testovací položky odebrané |
| Budoucí importní workflow | 7/7 integračních kontrol; testované hranice jsou nahrazené a skutečných SQL importů je nula |
| Nasazená kopie a baseline | SHA-256 modulu i potvrzení objednávky odpovídá workspace; Git zachovává původní bajty historické baseline |

Veškeré cílové HTTP výsledky tohoto finálního běhu jsou nové. Staré cílové cache se jako důkaz nepoužily. Na produkci nebyl odeslaný žádný požadavek a žádný SQL nebyl importovaný.

Ověření nativního generování URL, volitelného EAN bez zápisu do DB, variant a prázdného registračního bloku je v `native-runtime-checks.json`. Opakované nastavení je v `idempotence.json`; vrací nula změn, aktivní požadované hooky a skutečně načtený override.

Samostatný integrační test sedmi situací ověřuje automatické pořadí záloha → importní adaptér → SEO/audit, odmítnutí neúspěšné zálohy nebo auditu, interní sítě, diskovou rezervu a dokončení nativních dávek. Docker, import i audit jsou při tomto integračním testu nahrazené testovacími hranicemi; žádné SQL se neimportuje. Výstup: `workflow-integration.json`.

Skutečné prohlížečové testy používají Chrome přes existující Playwright, protože Browser plugin není dostupný. Rozměry: desktop 1536 × 1024, mobil 390 × 844. Produkt 3454: změna černé na červenou 259 → 269 Kč, velikost 164 → 299 Kč, obnovení variantové URL a převod staré ploché variantové cesty se zachováním výběru, vložení správné kombinace do nativního košíku a odebrání vlastního testovacího řádku. Objednávka se nevytváří. Důkazy jsou v `browser-qa.json` a příslušných PNG.

Vizuální kontrola používá i aktuálně vykreslenou černou předlohu na portu 8085. SEO změny neupravují šablonu, CSS, šest kategoriálních karet ani layout. Nativní produkt má importovaný obsah odlišný od demonstračního detailu předlohy. Existující snapshot nemá vazby kombinace–fotografie; změna barvy proto nepřiřazuje nově vymyšlený obrázek. Fotografie se načítají a cena i košík odpovídají skutečné kombinaci.

## Chybějící data a hranice

- Historická baseline prokázala nejméně 17 novějších produktů a kategorii 127 mimo použitý snapshot, dohromady 44 jazykových URL. Nejsou ručně zakládané ani přesměrované na cizí obsah. Finální `missing-content.csv` je vykazuje odděleně od routingových regresí.
- Angličtina zůstává neaktivní. Její metadata se zachovávají, ale není vydávaná za fungující storefront ani zahrnutá do sitemap.
- V použitém místním katalogu není vyplněný produktový EAN. Nativní tvorba a rozpoznání EAN segmentu jsou ověřené pouze parametrem v paměti a čtecím požadavkem; nevznikl žádný nový EAN v DB. Úplný test produktu se skutečným importovaným EAN se zopakuje, až takový záznam uživatel dodá.
- Původní audit eviduje také chybějící média již nefunkční na zdroji. Tato práce žádná média nezískává nebo nedoplňuje a netvrdí, že zopakovala celý mediální audit.
- Finální XML obsahuje localhost pouze jako součást chráněné zkoušky. Veřejná doména, HTTPS, odstranění testovací indexační blokace a předání sitemap do Search Console vyžadují samostatně schválené nasazení.
- SQL importy jsou v této práci záměrně nulové. Skutečný budoucí import zůstává novým uživatelsky schváleným krokem. Před ním znovu zkontrolovat volné místo; těsná rezerva se neřeší automatickým mazáním záloh nebo volumes.

## Záloha a návrat

Původní stav před první opravou: `local/private/migration-lab-20261018/seo-runs/seo-20261019-114710-534/`, zejména `settings-before.json`, `files-before.tar.gz` a veřejný katalog před změnou. Další běhy mají vlastní zálohy a kontrolní součty. Archivy i původní volumes zůstávají zachované.

Čtecí plán návratu původních SEO hodnot byl ověřen přes `scripts/rollback-migration-seo.ps1 -Run seo-20261019-114710-534`. Přepínač `-Apply` je určený pro zamýšlený návrat; tato práce jej nespouštěla. Návrat obnovuje jen dotčenou konfiguraci, stav SEO modulu a soubory, nikoli celou katalogovou DB. Původní rollback záměrně vrátí i tehdejší SEO chyby. Potvrzení objednávky zůstalo shodné s projektovým souborem; SHA-256 je v nativním runtime reportu.
