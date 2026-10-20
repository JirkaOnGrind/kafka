# Opakovatelná SEO kontrola izolované migrace

Platí pro PS 9.1.5 na `http://localhost:8083/`, službu `target` v izolované migrační laboratoři. Porty 8080, 8081 a 8082 nejsou cílem tohoto postupu. Produkce, její konfigurace ani síťová izolace se nemění.

## Jediná spravovaná SEO nastavení

Zdroj pravdy je verzovaný `scripts/migration-seo-policy.json`:

- nativní produktové pravidlo `{category:/}{id}-{rewrite}{-:ean13}.html`;
- český prefix i prefixy ostatních aktivních jazyků;
- canonical přesměrování 301;
- původní formát dodavatele `{id}__{rewrite}` a značky `{id}_{rewrite}`;
- název obchodu `Kafka Monster`, výchozí jazyk podle existujícího ISO `cs`;
- aktivní projektový modul `kafkaseo` a jeho tři konkrétní hooky.

`scripts/apply-migration-seo.php` se odmítne spustit mimo `localhost:8083`, databázový server `target-db` a jediný shop. Uloží původní řádky pouze uvedených konfiguračních klíčů. Ostatní nastavení, aktivitu jazyků a katalog nezapisuje. Před instalací případně kolidujícího rozšíření `Link` skončí s chybou; cizí override nepřepisuje.

`kafkaseo` formátuje pouze vykreslený titulek (`title`, OG i související nativní výstupy); vlastní uložený meta title zůstává zachovaný. Pokud obsahuje značku, další se nepřidává. `localize-page-titles.php` dále mění pouze přesně známé anglické instalační titulky českých systémových stránek. Vlastní systémové titulky nepočešťuje násilím.

PS 9 do produktového odkazu přidává prázdný otazník, když pravidlo neobsahuje `id_product_attribute`. Úzký modulový override `Link::getProductLink` odstraňuje jen tento prázdný znak, včetně jeho výskytu před kotvou. Nativní neprázdný parametr kombinace a kotva zůstávají. Jádro se neupravuje.

Staré `p` na podporovaných produktových výpisech přejde jedním 301 na nativní `page`, spolu s případnou opravou cesty. Ostatní parametry zůstávají; při současném `p` i `page` má přednost explicitní nativní `page`. `p=1` vede na základní výpis. POST, AJAX a košík se nezachytávají. Kořen `/` a původní explicitní `/?id_lang=…` používají 302 na aktivní jazyk, ne 301.

## Dokončení bez nového importu

Z kořene projektu:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/complete-isolated-import.ps1
```

Tento příkaz **neimportuje SQL**. Ověří interní sítě aplikace a správný localhost, uloží veřejná metadata před opravou a archiv dotčených souborů, nasadí projektový modul do skutečně běžícího volume 8083, aplikuje výslovnou politiku, opraví známé české systémové titulky, ověří idempotenci, vyčistí pouze kompilované Smarty šablony, sestaví sitemapy, provede nativní test URL/variant a spustí nový HTTP audit.

Výstupy: nová složka `migration/evidence/seo-YYYYMMDD-HHMMSS-mmm/`, zejména `REPORT.md`, `summary.json`, `failures.csv`, `missing-content.csv`, `legacy-url-results.csv`, `fresh-http.jsonl`, `native-runtime-checks.json`, `idempotence.json`. Soukromé zálohy jsou v `local/private/migration-lab-20261018/seo-runs/STEJNY-IDENTIFIKATOR/`. Každý běh má novou složku; existující HTTP výstup se odmítne znovu použít. Při chybě je výsledek neúspěšný a import nelze převzít. Automatika nic nevrací do staré DB a nemaže data.

## Nově dodané SQL nebo další migrace

SQL se získává a zpracovává jen po novém výslovném pověření uživatele. Tento postup takové pověření sám nevytváří. **PS 1.6 SQL se nikdy přímo nenahrává do PS 9**; obnovuje se do izolovaného zdroje a přenáší ověřeným nativním migračním adaptérem. Zachovat původní soubor a SHA-256, obnovitelný checkpoint, vypnuté integrace a interní aplikační sítě.

Existující `local/private/migration-lab-20261018/run-batches.ps1` nyní deleguje na verzovaný `scripts/run-isolated-batches.ps1`. Každé dokončení migrace, také Mode 4, automaticky volá dokončovací SEO postup. Obnova přes existující `recover-accepted.ps1` jej volá také po návratu cílového SQL checkpointu. Původní soukromé skripty mají datované kopie před úpravou. Při přesunu laboratoře používat verzované skripty a parametr `-LabPath`; soukromé adaptéry, licence a přihlašovací údaje se do Gitu nevkládají.

Jiný uživatelem schválený lokální SQL/importní adaptér spouštět přes `scripts/invoke-isolated-import.ps1`:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/invoke-isolated-import.ps1 -SnapshotPath "ABSOLUTNI_SOUKROMA_CESTA_K_DODANEMU.sql" -ImportScript "ABSOLUTNI_CESTA_K_PROJEKTOVEMU_IMPORTNIMU_ADAPTERU.ps1"
```

Adaptér musí mít pojmenované parametry `SnapshotPath` a `LabPath`; wrapper mu předá přesně hashovaný vstup a správnou laboratoř. Další argumenty předat jako pole `-ImportArguments` při volání z PowerShellu. Wrapper před adaptérem ověří izolaci a uloží aktuální cílovou DB soukromě do `import-runs/import-…/database-before.sql`; po jeho úspěchu ověří nezměněný původní SQL a vždy spustí SEO dokončení a čerstvý audit. Adaptér musí při selhání skončit nenulovým kódem nebo výjimkou a před návratem obnovit lokální bezpečnostní konfiguraci. Neúspěšný adaptér nebo audit znamená nepřevzatý import. Pro import musí zbýt alespoň 4 GiB rezervy na C: před zálohou i po ní; potřebný prostor pro nový SQL, média a Docker se posuzuje navíc. Zálohy se kvůli tomu automaticky nemažou.

Nový SQL může oprávněně změnit metadata nebo aktivitu obsahu. Audit porovnává zachování metadat **před a po samotné SEO opravě**, nikoli jejich neměnnost oproti historickému SQL. Historické změny cest se evidují a staré funkční URL musí stále dojít ke stejné existující entitě. Chybějící nebo neaktivní obsah dostává samostatný přehled; neexistující produkty se nikdy nevyrábějí ani nenahrazují cizími cíli.

Po samotné obnově zdrojového PS 1.6 SQL není migrace do 8083 dokončena. Zaznamenat ji jako čekající na přenos; za převzatý výsledek ji označit až po dokončeném cílovém importu a uvedeném auditu. Nepoužívat historické čtecí skripty pro 8081/8082 jako obcházení tohoto přejímacího kroku.

## Sitemap a rozsah důkazů

`generate-local-sitemaps.php` vybírá existující aktivní produkty s katalogovou viditelností a veřejnou výchozí kategorií, veřejné aktivní kategorie kromě kořenů, indexovatelné aktivní CMS, dostupné značky/dodavatele a homepage. Vyžaduje skutečné jazykové řádky a neprázdné názvy/slugs. CMS kategorie, účet, košík, hledání, filtry, stránkování, kombinace, neaktivní jazyky a záznamy se do XML nedostávají. Nic nedoplňuje. XML obsahuje vzájemné alternativy jen existujících způsobilých verzí.

Index: `http://localhost:8083/1_index_sitemap.xml`; stejný obsah má `/sitemap.xml`. Části po nejvýše 2 000 URL se publikují před indexem. Před přegenerováním jsou staré soubory v soukromé záloze; zastaralé části odstraňuje pouze náš předchozí manifest. XML používá localhost výhradně pro tuto chráněnou místní zkoušku. `X-Robots-Tag: noindex, nofollow, noarchive` na všech odpovědích zůstává. Veřejná sitemap pro finální doménu a odstranění noindex vyžadují samostatně schválené nasazení; místní XML se do Search Console neposílá.

Audit používá zachovanou baseline `migration/evidence/seo-20261019/` jen pro historické cesty a klasifikaci tehdejších odpovědí produkce. Produkci nenavštěvuje. Všechny cílové HTTP výsledky sbírá znovu, včetně přesměrování, titulků, canonical, hreflang cílů, stránkování a sitemap. XML se ověřuje celé proti způsobilému místnímu katalogu; HTTP test není kompletní procházení každého produktu.

Bez dalšího SQL je integrační zapojení ověřitelné:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/test-isolated-seo-workflow.ps1 -OutputPath "CESTA_K_NOVEMU_REPORTU.json"
```

Tento test nahrazuje Docker, import i audit testovacími hranicemi; nic neimportuje a na obchod nesahá. Ověřuje pořadí záloha → adaptér → SEO/audit, odmítnutí při chybě, izolaci, rezervu disku a automatické dokončení nativních dávek.

Skutečné varianty a košík na desktopu/mobilu ověřuje `node scripts/qa-seo-storefront.cjs ABSOLUTNI_CESTA_K_EVIDENCNI_SLOZCE`. Používá existující produkt 3454 a jeho skutečné kombinace, změní cenu, obnoví variantovou URL, přidá do skutečného košíku a vlastní řádek zase odebere. Nevytváří objednávku. Pokud tento produkt v dalším snapshotu chybí, upravit výběr testu podle existujících dat a evidovat důvod, nikoli produkt znovu založit. Playwright a Chrome jsou ze stávajícího místního runtime; jiné cesty lze zadat přes `KAFKA_PLAYWRIGHT_PATH` a `KAFKA_CHROME_PATH`.

## Návrat pouze SEO změn

Základní plán se bez přepínače `-Apply` jen načte a ověří:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/rollback-migration-seo.ps1 -Run seo-YYYYMMDD-HHMMSS-mmm
```

Při zamýšleném návratu přidat `-Apply`. Obnoví pouze spravované konfigurační klíče, původní stav modulu, zálohované soubory a případně naše počeštěné systémové titulky, pokud je mezitím nikdo nezměnil. Nekopíruje DB katalogu ani nemaže volume. Pro návrat celé migrace použít její samostatný soukromý checkpoint. Při návratu původního stavu před touto opravou vybrat nejstarší úplnou zálohu `seo-20261019-114710-534`; tím se úmyslně vrátí i původní SEO chyby. Modulové soubory mohou po odinstalaci zůstat neaktivně na disku.

SEO záloha musí odpovídat stejnému importovanému snapshotu. Starší SEO zálohu nepoužívat po novém importu s jiným mapováním jazykových nebo systémových ID; v takovém případě vybrat zálohu příslušného nového dokončovacího běhu.
