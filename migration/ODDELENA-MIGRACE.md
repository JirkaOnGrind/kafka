# Platný postup Kafka Monster — 14. 10. 2026

Tento dokument má přednost před historickými doporučeními v ostatních migračních reportech. Produkce zůstává na stávajícím WEDOS Extra; vývoj a migrace se připravují na PC. Druhý hosting se nekupuje.

## Pevné hranice

- Během přípravy pouze získat zálohu produkce. Žádný konektor, zápis do produkční DB, změna PHP ani změna souborů produkce.
- Konektor patří výhradně do izolované staré kopie. Ta má vlastní DB a interní Docker síť. Místní náhled zajišťuje pevný reverse proxy, nikoli síťový přístup aplikace ven.
- E-maily, platby, zapisující crony a napojení na expedici/feed služby ve staré kopii vypnout před prvním spuštěním. Zákaz odchozího přenosu ověřit skutečným pokusem o spojení.
- Zachovat původní FTP, SQL, všechny zálohy a Docker volumes. Žádné rutinní mazání svazků nebo reset DB.
- Migrované texty a katalogové změny odložit do posledního dorovnání. Šablona a vlastní moduly se vyvíjejí odděleně od importovaných dat.

## Pořadí práce

1. Záloha současného lokálního shopu a celého projektu včetně dosud neverzovaných souborů. Úplný nácvik obnovy do čerstvých svazků.
2. Archiv původního FTP a SQL; pracovní zdrojová kopie PS 1.6.1.13/PHP 7.1. SQL z 5. 10. slouží k nácviku, nikoli jako finální aktuální stav.
3. Vyjasnit licenci a místní migraci s PrestaHero podle `DOTAZ-PRESTAHERO.md`. Uživatel zajistí odpověď, nákup a ZIP v `migration/licensed-packages/`. Neodesílat zprávy dodavateli bez samostatného pokynu.
4. Po nové záloze cíle importovat Mode 1 se zachováním ID. Import nesmí začít bez ověřeného zdroje, zakoupeného modulu a potvrzené licence. Před aktuálním pilotem získat čerstvý SQL a doplnit změněná média.
5. Zkontrolovat katalog, historické objednávky, faktury a vazby. Očekávané počty odvozovat od konkrétního snapshotu, ne automaticky od starého auditu. Dvě osiřelé kombinace řešit pouze na kopii; historické položky smazaných produktů zachovat.
6. Dokončit nativní stránky a napojení kategorií. Veřejný shop je černý, bez přepínače, homepage má šest původních ikonových karet. Testovat desktop i mobil proti vykreslené předloze.
7. Po potvrzení dopravy/dobírky nastavit podporované integrační moduly. Nacvičit Mode 4 nad obnoveným snapshotem; kontrolovat nové i změněné objednávky, platby, ceny a sklad, opakování bez duplikací i chování smazaných zdrojových dat. Změřit trvání.

## Zálohy a práce uživatele

Codex dělá místní technické kroky, skripty a kontroly. Uživatel řeší nákup, přihlášení a obchodní rozhodnutí. Připravený šifrovaný archiv nahraje uživatel ručně do soukromého Google Drive. Klíč uchová mimo archiv a mimo stejnou cloudovou složku, nejlépe v osobním správci hesel. Cloudová záloha je ověřená teprve po stažení a úspěšném dešifrování.

Uživatel si obnovu projde sám podle `OBNOVA-A-ZALOHY.md`. Samotná návštěva původního shopu na portu 8080 není test obnovy; obnovená kopie používá 8081, starý zdroj 8082.

## Finální nasazení — nyní neprovádět

Před nasazením na WEDOS se vytvoří další kompletní záloha produkční DB a souborů, zaznamená se stávající PHP a aktivní cesta shopu. Hostitelská kopie zálohy sama nestačí: také stáhnout soukromou kopii mimo hosting a ověřit její integritu a obnovitelnost. Souborové zálohy nesmí být veřejně dostupné ve webrootu.

V samostatně schváleném okně: statická údržba, zastavení administrativních změn/importů/zapisujících cronů, dohledání rozpracovaných plateb a řízené zpracování callbacků, finální konzistentní snapshot, aktualizace izolovaného zdroje a nacvičený Mode 4. Nový PS 9 používá samostatnou DB. Balíčky starých a nových souborů připravit odděleně; ověřit přesný způsob přepnutí podle hostingu. PHP přepnout až při odstávce. Zachovat poštu/MX.

Před veřejným otevřením ověřit HTTPS, účet, ceny/sklad, varianty, dopravu, platební návraty/callbacky, maily a doklady. Neveřejný serverový test musí mít vlastní ochranu a testovací platební režim. Návrat před otevřením používá původní soubory, DB a PHP; jeho čas se změří. Po veřejných objednávkách není návrat prostým obnovením starého SQL — nové transakce se musí zachovat a vypořádat.

Konkrétní nasazení a návrat se dopracují až po pilotu a integračních testech. Dokud chybí zakoupený modul, čerstvá DB nebo obchodní pravidla, daný milník zůstává nesplněný; nenahrazovat jej demonstračním testem.
