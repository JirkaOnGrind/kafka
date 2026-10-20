# Migrační příprava Kafka Monster

**Aktuální ověření URL/SEO — 19. 10. 2026:** [SEO-AUDIT-2026-10-19.md](SEO-AUDIT-2026-10-19.md). Veřejný scrape a místní porovnání prokázaly nefunkční původní produktové URL, dočasná přesměrování, chybějící novější katalog a další body před nasazením. Audit obsahuje konkrétní výsledky 888 produkčních a 1 257 lokálních adres, katalogovou inventuru a navržené pořadí nápravy. Nejde o souhlas s produkčním přepnutím; pravidla izolace a samostatně schváleného okna zůstávají platná.

Průzkum pro nynější lokální pilot (18. 10. 2026): [LOKALNI-MIGRATIONPRO-18-10-2026.md](LOKALNI-MIGRATIONPRO-18-10-2026.md). Obsahuje skutečný stav kontejnerů, neuzavřené podmínky lokálního propojení/licence a přijímací testy. Nenahrazuje migrační autoritu níže.

**Začni aktuálním [ODDELENA-MIGRACE.md](ODDELENA-MIGRACE.md).** Návod k vlastní kontrole obnovených obchodů je [OBNOVA-A-ZALOHY.md](OBNOVA-A-ZALOHY.md); dotaz před nákupem je [DOTAZ-PRESTAHERO.md](DOTAZ-PRESTAHERO.md). Starší auditní reporty níže představují historii, ne aktuální pořadí práce. Produkční konektor není povolený.

Začni `MIGRACNI-ANALYZA.md`, ráno odpověz na prvních deset otázek v `OTAZKY-NA-RANO.md`. Pro přepnutí je připravený `SPUSTENI-A-KONTROLY.md`.

Aktuální souborovou inventuru během stahování FTP najdeš v `FTP-ANALYZA.md`, ruční posouzení kódu v `FTP-KOD-A-INTEGRACE.md` a potvrzené odpovědi / nákupy v `STAV-A-NAKUPY.md`.

## Průběžný audit FTP kopie

```powershell
python migration/audit_ftp.py --root 'C:/Users/Utrh/Desktop/kafkaFTP/www/domains/kafka.monster' --media
python migration/render_ftp_report.py
```

Oba skripty pouze čtou zdrojovou FTP kopii a píší sanitizované výsledky do `migration`. První porovnává dostupný core s pouze čteným oficiálním ZIP tagu 1.6.1.13 v `migration/evidence`, inventarizuje moduly a overrides a s `--media` kontroluje nenulové originály/přílohy vůči SQL. Druhý přegeneruje `FTP-ANALYZA.md`; ruční poznámky držet v samostatném `FTP-KOD-A-INTEGRACE.md`. Nevykonávají PHP ani SQL. Pro generátor reportu na Windows musí být dostupná data časových zón (použitý bundled Python je má).

Nedotažené soubory se nesmějí označovat jako ztracené ze serveru. Stabilní počet souborů také nedokazuje dokončenou FTP frontu. Stav úplnosti potvrdit pomocí výsledku klienta nebo vzdáleného manifestu a kontrolních počtů; citlivé logy/session obsah se neexportuje. Během čekání je vhodné opakovat inventuru po 20 minutách a provést ruční posouzení nově dostupných důležitých souborů.

CSV soubory jsou UTF-8 s BOM, oddělovač čárka. V českém Excelu je případně načti přes Data → Z textu/CSV s výběrem čárky; nejde o XLSX soubory.

## Opakování auditu

Z kořene projektu, s dostupným Pythonem 3.11 nebo novějším:

```powershell
python migration/audit_dump.py 'C:/Users/Utrh/Downloads/d278806_rvujjz.sql' --schema 'C:/Users/Utrh/Downloads/information_schema.sql'
python migration/build_inventories.py
```

Na tomto počítači byl použit bundled Python vrácený nástrojem workspace dependencies. Skript používá pouze standardní knihovnu, nic neinstaluje a nikdy se nepřipojuje k databázi ani nevykonává SQL.

Parser je určený pro dodaný phpMyAdmin dump s explicitními názvy sloupců v `INSERT ... VALUES`. Pro každou tabulku počítá skutečné vložené záznamy. Z většiny tabulek si ponechá jen počet; z provozních tabulek pouze whitelisted pole. Zákaznická/order ID používá uvnitř procesu pro kontrolu vazeb; nevytváří seznam zákaznických účtů ani kontaktních údajů. Konfigurační hodnoty jsou omezené whitelistem bez klíčů/hesel.

Pro netříděné velké logové tabulky využívá ověřený jednořádkový tuple formát tohoto dumpu. Při jiném formátu se vrací k parseru tuple. Pro budoucí jiné formáty exportu nebo cílový dump je nutné parser adaptovat; prefix `ejf6_` je zde specifický pro zdrojový obchod.

`information_schema` je pouze doplňková kontrola metadat. Jeho odhadované počty InnoDB nejsou přijímací baseline.

## Omezení a ověření

- Analýza není provedená migrace. Nebyl instalován migrátor ani cílový PrestaShop.
- Nebyla obnovena DB na skutečném SQL serveru, kontrolována existence všech mediálních souborů ani ověřené heslo zákaznického účtu.
- URL inventory je rekonstrukce ze standardní struktury zdrojových ID/slugs, nikoli crawl. Obsahuje i neaktivní entity a EN. Všech 13 540 adres je v inventuře unikátních; jejich HTTP a redirect chování musí být ověřeno.
- Kontrola potvrdila 306 tabulek v obou výstupech, shodu součtu inventury s audit-summary, 85 modulů, 77 kategoriálních mapování a 23 cenových sazeb aktuálních dopravců.
- Dodané SQL je UTF-8; nebyl nalezen replacement znak U+FFFD. Některé terminálové výpisy konzole zobrazovaly diakritiku nesprávně, uložené JSON/CSV jsou korektní.
- Přílohy neobsahují zákaznické kontakty, hesla nebo API secrets. Kompletní dumpy nebyly zkopírovány do projektu.

`source-manifest.json` obsahuje velikosti a SHA-256 vstupů pro identifikaci přesného podkladu. `LIVE-OBSERVATIONS.md` popisuje omezený přímý průzkum živého webu.

`audit_media_integrity.py` pouze čte originály img/p, img/c, img/m, img/su a img/st s čistě číselným názvem JPG, ověřuje strukturu a dekóduje pixely přes bundled Pillow. Ukládá souhrn do evidence/media-integrity.json; nepouští zdrojové PHP a nečte zákaznické uploady. Nezahrnuje starší PRODUCT-IMAGE.jpg ani jiné složky či formáty médií. Průběžné nálezy při stahování musí být znovu ověřeny po dokončení FTP. Renderer je uvádí s vlastním časem kontroly; počty kategoriálních a ostatních volitelných obrázků nepředstavují úplnost vůči DB.
