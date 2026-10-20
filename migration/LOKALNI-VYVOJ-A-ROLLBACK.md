# Lokální vývoj a jednodenní zkouška na současném WEDOS Extra

> **Upřesnění 14. 10. 2026:** platný postup je [ODDELENA-MIGRACE.md](ODDELENA-MIGRACE.md). Během přípravy se produkce nemění, slouží pouze k získání zálohy. Migrační konektor se instaluje pouze do izolované kopie na PC. FTP fronta je uživatelem potvrzená jako dokončená. Šifrovanou druhou zálohu nahraje uživatel do soukromého Google Drive ručně. Zkouška na WEDOS a finální přepnutí zůstávají budoucím samostatně schváleným zásahem.

Aktuální rozhodnutí uživatele: vývoj na PC, produkce na stávajícím Extra. Druhý hosting se nekupuje. Tento postup nahrazuje dřívější doporučení samostatné hostingové služby. Produkce nebyla změněna.

Aktualizace 12. 10. 2026: lokální PS 9.1.5 s PHP 8.3, Hummingbirdem 2.0 a aktivní odvozenou šablonou Kafka je nainstalovaný v Dockeru. Ovládání a cesty jsou v [JAK-PRACOVAT.md](../JAK-PRACOVAT.md). Obsahuje pouze demonstrační katalog. Migrátor ani skutečný import zatím nejsou provedené. První společná vizuální vrstva Kafka je připravená; další napojení a odchylky shrnuje [VIZUALNI-PREVOD.md](../design/VIZUALNI-PREVOD.md).

## Před zakoupením migrátoru

Připravit lokální čistý PS 9.1.5 s PHP 8.3 a vlastní DB, přednostně v Dockeru. Instalace PS 9 nevyžaduje migrační licenci; pro vývoj lze dočasně použít demonstrační produkty, které první plný import odstraní. Původní FTP zůstává archivem. Zdrojová PHP a SQL se během této analýzy nespouštějí.

Připravit čerstvý export hlavní databáze, neveřejný archiv zálohy a potvrdit obchodní pravidla dopravy/dobírky. Převod vzhledu lze začít na nativních šablonách bez ručního přepisování katalogu. Lokální test SMTP, callbacků a serverového prostředí nenahrazuje test na WEDOS.

## Bezpečná zkouška na hostingu

Jednodenní zkouška se provede v předem dohodnuté odstávce: veřejnost vidí statickou stránku údržby, nový shop je dostupný jen testovacímu týmu. Nepřijímají se veřejné objednávky ani veřejné ostré platby. Testovací objednávky zapisují pouze do nové DB. Použít testovací režim brány; případná domluvená ostrá testovací transakce musí být zvlášť evidovaná a vypořádaná při návratu.

Před odstávkou připravit oba balíčky souborů na serveru odděleně a ověřit jejich úplnost. Aktivní cesta nového shopu se řeší připraveným přepnutím adresářů/doménové cesty, nikoli přepisováním starých souborů jedním po druhém. Způsob přepnutí ověřit podle skutečné struktury FTP. Starou kopii neservírovat veřejně z alternativního adresáře. Na celém hostingu zkontrolovat také jiné PHP aplikace, které přepnutí verze zasáhne.

Po uzavření zdroje a zastavení administrativních změn, cenových importů a zapisujících cronů pořídit finální zálohu. Finální import přes migrátor může pracovat s izolovanou zdrojovou kopií na PC s původním PHP; konfigurace kopie musí odkazovat na vlastní DB a mít vypnuté e-maily, ostré brány a crony. Spojení migrátoru musí být ověřené ještě před přepnutím PHP. Nelze předpokládat, že starý konektor na WEDOS bude fungovat po změně na PHP 8.3.

Rozpracované staré Comgate platby zvlášť dohledat: údržba frontendu nezastaví všechny zápisy. Sepsat transakce a stav, domluvit zachycení/pozdější kontrolu u brány a před otevřením či návratem všechny změny vypořádat. Callbacky nesmí bez kontroly měnit starou DB, zatímco ji považujeme za zmrazenou. Žádné API údaje do veřejného reportu.

Použít dvě samostatné DB: starou pro návrat, novou pro PS 9. Nikdy neinicializovat PS 9 nad starou DB. Na WEDOS přepnout PHP 7.1 → 8.3 až po připravení statické údržby. Ověřit aplikovanou verzi, novou DB, HTTPS, cookies, přihlášení, ceny, varianty, dopravu, platební návraty, callbacky, SMTP, fakturu, uploady a crony. Maily směrovat pouze na testovací adresy. Vlastní údržba / ochrana stagingu musí podle potřeby umožnit ověřené callbacky testovací brány; není důvod otevřít shop veřejnosti.

## Návrat před otevřením

1. Zachovat veřejnou statickou údržbu, zastavit zápisy a testovací crony nového shopu.
2. Archivovat novou DB a testovací protokol; zkontrolovat případné platební transakce.
3. Přepnout aktivní soubory na připravenou původní verzi, ověřit připojení ke staré DB.
4. Vrátit původní PHP 7.1 a uložené parametry. Vyčkat a ověřit skutečně aplikovanou verzi.
5. Vypořádat odložené platby, ověřit nejnovější objednávky, sklad, přihlášení a checkout starého shopu. Obnovit pouze jeho crony a otevřít provoz.

**Návrat není okamžitý.** WEDOS uvádí zavedení konfigurace PHP až do 30 minut; navíc potřebujeme čas na soubory a kontrolu. Délku změří nácvik. Neplánovat návrat závislý na opětovném uploadu celého FTP nebo na dostupnosti podpory.

Po otevření nového shopu pro veřejné objednávky vzniká nový zdroj obchodních dat. Návrat pak není prostá obnova staré zálohy: nové objednávky, platby, sklad, zákazníky a doklady je nutné bezpečně přenést nebo vypořádat. Proto je nejbezpečnější jednodenní test se zavřeným veřejným nákupem a otevření až po přijetí výsledku klientem. Odstávka znamená ztrátu možnosti objednávat, její délku musí klient znát předem.

Zdroje: [PHP a společné nastavení WEDOS](https://kb.vedos.cz/webhosting-php/), [vlastní zálohy a obnova](https://kb.vedos.cz/webhosting-zalohovani/), [údržba PrestaShopu](https://docs.prestashop-project.org/v.8-documentation/user-guide/configuring-shop/shop-parameters/general-settings/maintenance). Oficiální dokumentace údržby upozorňuje, že webservice zůstává aktivní; samotné vypnutí frontendu není úplné zmrazení dat.
