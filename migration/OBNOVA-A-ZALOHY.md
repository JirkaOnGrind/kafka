# Jak si osobně zkontrolovat zálohu Kafka Monster

## Otevření obnoveného obchodu

1. Nech spuštěný Docker Desktop.
2. V projektu dvojklikem spusť **`zkontrolovat-zalohy.bat`**. Skript pouze spouští hotové kopie; nepřepisuje databáze ani neprovádí nový import.
3. **Nový shop ze zálohy:** http://localhost:8081/ — administrace http://localhost:8081/admin-dev/. Přihlášení je stejné jako v záloze z 14. 10., uložené v `local/backups/20261014-135120/.env` (`ADMIN_EMAIL`, `ADMIN_PASSWORD`). Nejde o ostrý web; tato kopie zatím obsahuje 19 demo produktů.
4. **Starý obchod z dodaného FTP a SQL:** http://localhost:8082/ — administrace http://localhost:8082/admin-source/. E-mail je `admin@kafka-source.local`; soukromé heslo otevři lokálně v `local/source/20261014-135846/.env`, položka `SOURCE_ADMIN_PASSWORD`. Původní produkční účet se neměnil. Dostupnost a výsledek této obnovy jsou zaznamenané v `PROVEDENO-14-10.md`.

Na novém shopu si otevři kategorii, produkt a jeho varianty, přidej ho do košíku a potom se přihlas do administrace. Na starém shopu ověř své známé produkty, jejich obrázky a varianty; v administraci katalog, zákazníky a historické objednávky/faktury. Pro obnovený SQL z 5. 10. očekáváme 3 296 produktů a 574 objednávek. Novější objednávky v něm být nemohou; před skutečnou migrací získáme čerstvou zálohu.

Zdrojová kopie slouží k prohlížení a migraci, ne k dokončení nákupu. Prázdný i naplněný košík lze otevřít na `http://localhost:8082/index.php?controller=order` a přidávat do něj produkty. Pouze souhrn košíku je povolený; další kroky, platby, zákaznické formuláře a integrační vstupy jsou vypnuté. Kopie používá pro tuto kontrolu standardní souhrn namísto starého jednostránkového checkoutu. Vybrané prezentační moduly zůstávají aktivní, vzhled se proto může lišit od ostrého shopu. Neznamená to smazaná data ze zálohy. Interní síť brání odchozímu spojení aplikace; vlastní ochrana náhledu blokuje externí formuláře a přepisuje ostré odkazy na místní adresu. Před licencovaným importem konektor zpřístupníme pouze na této kopii podle ověřeného návodu výrobce. Před skutečným importem použít čerstvý zdrojový snapshot, nikoli pracovní kopii obsahující místní testovací košíky.

`zastavit-zkousky.bat` zastaví obě zkoušky a zachová data. `stop.bat` ovládá pouze vývojový shop na 8080. Nepoužívat `down -v` ani mazání volumes.

## Co je zálohované

- `local/backups/20261014-135120/`: databáze nového shopu, všechny jeho shopové soubory včetně připojených šablon/modulu, konfigurace, původní celý projekt s Git historií a kontrolní součty.
- `local/backups/source-original-20261014-135846/`: kompletní původní FTP archiv, původní SQL a kontrolní součty. Obsahuje i citlivá data a původní konfiguraci; je neveřejný a mimo Git.
- Následující zálohy přes `backup.bat` zahrnují i aktuální celý projekt. Pracovní zdrojová kopie a obnovovací klíče se do projektu nepřibalují.
- Šifrovaný balíček pro cloud obsahuje původní zdroj, nový shop i aktuální implementační soubory. Přesná cesta a stav ověření jsou v `PROVEDENO-14-10.md`.

## Ruční uložení na soukromý Google Drive

1. Nahraj celou připravenou složku `cloud-ready-20261014-final`: **`kafka-backup.kafkagcm`, sousední `kafka-backup.json`, malý `source-preview-fix-20261014.zip` a `AI-OBNOVA-KAFKA.md`** do soukromé složky na Disku. Doplněk obsahuje obnovovací nástroje, opravy lokálního náhledu a návody, žádná hesla ani zákaznická data. Nenahrávej nešifrované SQL, `.env` ani celé původní zálohové složky.
2. Soubor obnovovacího klíče z `local/private/` ulož zvlášť, ideálně do správce hesel s možností bezpečného exportu. Je nezbytný i při ztrátě tohoto PC. Nedávej jej vedle archivu na Disk a neposílej jeho obsah do chatu.
3. Po uploadu stáhni archiv i JSON z Disku do nové složky. Tím testujeme cloudovou kopii, ne původní soubor na PC.
4. Spusť **`overit-cloud-zalohu.bat`**, vlož cestu ke staženému archivu a cestu k oddělenému klíči. Skript ověří autentizaci a kontrolní součty průběžným dešifrováním, bez vytváření další velké nešifrované kopie.
5. Potom nech Codex provést úplnou obnovu ze staženého souboru. Použije `scripts/verify-cloud-backup.ps1 -Extract`, který ověří dostatek místa a rozbalí data do nové složky `local/backups/cloud-download-restore-*`. Pro rozbalení je potřeba přibližně dvojnásobek velikosti balíčku plus rezerva; samotný běžící shop vyžaduje další místo. Port 8081 musí být uvolněný zastavením předchozího náhledu; jeho volumes se ponechají. V obnoveném shopu zopakuj kontrolu katalogu, administrace a košíku. Teprve potom je cloudová záloha plně ověřená.

K obnově na jiném PC potřebuješ archiv, JSON, samostatný klíč a tento návod/skripty; v balíčku jsou také projektové soubory. Formát je AES-256-GCM, nikoli ZIP. Codex zajistí dešifrování a spuštění na jiném PC; klíč nelze obnovit bez jeho samostatné kopie.

## Ještě jedna záloha před ostrým nasazením

Ano. Před přepnutím na WEDOS se pořídí **finální záloha skutečného ostrého shopu: DB + soubory + údaje o PHP a aktivní cestě**. Po dohodnutém uzavření zápisů a vypořádání rozpracovaných plateb bude sloužit k poslednímu dorovnání i návratu. Soukromá kopie zůstane také mimo hosting. Aktuální místní archiv nenahrazuje budoucí finální zálohu živého webu.

## Jak by probíhala obnova na WEDOS

Toto je popis budoucí obnovy, nikoli pokyn nyní přepsat běžící shop. Šifrovaný balíček se nejdřív stáhne, ověří a rozbalí na PC s dostatkem místa. Na FTP se nenahrává `.kafkagcm`, SQL archiv, `.env` ani Docker konfigurace. Malý doplněk ZIP obsahuje také dešifrovací skripty a tento návod pro případ ztráty původního PC; jsou bez hesel a zákaznických údajů. Codex na novém PC zajistí potřebný Python s knihovnou cryptography a provede obnovu.

Pro návrat **starého PS 1.6** se po rozbalení použije složka `source-original-20261014-135846`: původní `source-files.tar.gz` a její `database.sql`. Nejde o upravenou pracovní kopii z Dockeru. Archiv souborů obsahuje původní konfiguraci i klíče pro přihlášení; zachovat je soukromě.

1. V dohodnuté odstávce uzavřít nákup a zapisující procesy, vypořádat rozpracované platby/callbacky a uložit současný stav jako další bod návratu.
2. Zálohované soubory připravit do oddělené neveřejné cesty na FTP. Aktivní cesta je v dodaném FTP `www/domains/kafka.monster`; její přepnutí se ověří na konkrétním hostingu. Nepřepisovat průběžně staré soubory a nemazat je jako první krok.
3. SQL importovat přes phpMyAdmin do samostatné obnovovací DB. Při změně názvu DB upravit import na tuto DB; u velkého exportu předem ověřit limit a vhodný způsob importu. Při překročení limitu Codex připraví vhodný balíček a domluví se podporovaný import s poskytovatelem, bez veřejných importovacích skriptů.
4. V `config/settings.inc.php` nové kopie nastavit WEDOS DB server, název a přihlášení. Ponechat původní klíče pro zákaznická hesla. Ověřit doménu a HTTPS, použít původní PHP 7.1 místo lokálního Docker připojení `db`.
5. Neveřejně ověřit administraci, katalog, varianty, nejnovější objednávky a zásoby. Aktivní soubory/DB přepnout až po kontrole. Maily, platby a crony zapnout řízeně pouze v aktivním shopu, poté zkontrolovat skutečný nákup a callbacky.

SQL této konkrétní staré zálohy je z **5. 10. 2026**. Jeho slepý import místo dnešní produkce by ztratil novější změny. Pro ostrý návrat se vytvoří čerstvý konzistentní snapshot těsně před nasazením. Po otevření nového shopu se navíc musí zachovat a vypořádat i nové objednávky a platby.

Pro **nasazení PS 9** se použije až dokončený a skutečně migrovaný nový shop. Aktuální `recovery-set-20261014-final` obsahuje rozpracovaný PS 9 s demo katalogem. Při jeho budoucím nasazení se obnoví `shopfiles.tar.gz` a SQL do nové DB, doplní vlastní implementace z projektu, připojení se nastaví v `app/config/parameters.php`, změní se doména z localhostu a podle schváleného okna PHP na 8.3. Lokální ochrana zdrojové kopie ani její testovací konfigurace se do produkce nepřenášejí.

Oficiální návody: [obnova souborů a DB](https://kb.vedos.cz/webhosting-zalohovani/), [import SQL přes phpMyAdmin](https://kb.vedos.cz/webhosting-import-export-pma/). WEDOS Extra podle dokumentace nabízí denní zálohy až pět dní zpětně; doplňují vlastní ověřenou finální zálohu, nenahrazují ji.
