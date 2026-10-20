# Testovací Kafka na Českém hostingu — 18. 10. 2026

Uživatel nově vyhradil existující hosting `kafka.jirkanetrh.cz` pro nový testovací shop. Nejde o ostrý `kafka.monster` na WEDOSu. Změna se týká testovacího cíle; starý zdroj zůstává v izolovaném Dockeru na PC. Na sdílený hosting se stará PS 1.6 kopie nenahrává. Propojení MigrationPro ani jeho licence nejsou tímto krokem ověřené.

## Stav hostingu

Uživatel založil prázdnou DB `jirkanetrhcz02`, odstranil starý online frontend a spustil kontrolní PHP soubor. Výsledek potvrdil PHP 8.4.26, HTTPS, rozšíření a zapisovatelnou složku. Po zvýšení paměti a času běhu uživatel potvrdil zelené kontroly. Verze databáze, její připojení a skutečná funkčnost shopu čekají na ověření.

## Oddělené výstupy

`local/packages/hosting-20261018/` je neveřejná pracovní složka vyloučená z Gitu:

- `upload/`: pouze shopové soubory určené pro FTP. Kořenový `.htaccess` začíná zákazem přístupu. Konfigurace DB obsahuje zástupné hodnoty, nikoli místní hesla. Dockerové odkazy na admin assety jsou nahrazené skutečnými soubory. Alias `admin-kafka` se nepřenáší, administrace je `admin-dev`.
- `private/database-hosting.sql`: import pouze přes phpMyAdmin do vyhrazené prázdné DB. Nikdy na veřejné FTP. Obsahuje čerstvý demo snapshot a na konci nastavení testovací domény, údržby, vypnutí mailů a známých platebních modulů, nové testovací přihlášení. Nahrazuje nepodporovanou MySQL kolaci `utf8mb4_0900_ai_ci` za `utf8mb4_unicode_ci` pro kompatibilitu s MariaDB.
- `private/admin-login.txt`: nové přihlášení pouze pro testovací kopii. Uložit soukromě, neposílat do chatu a nenahrávat na FTP.
- Ostatní `private/` soubory jsou pracovní zdrojové exporty. Celou tuto složku nikdy nenahrávat na FTP.

Příprava: `scripts/prepare-hosting-export.php` je CLI pomocník spuštěný uvnitř místního shopu; nemění zdrojovou DB. `scripts/package-hosting.py` sestavuje samostatnou kopii ze souborového exportu a aktuálních vlastních šablon/modulu. Odmítne přepsat existující `upload/`.

## Pořadí nasazení testovací kopie

Ověření přípravy: SQL byl úspěšně importován do nové oddělené kontrolní DB `kafka_hosting_check_20261018` na místním MySQL 8.0. Výsledek: 19 produktů, šablona Kafka, správná testovací doména, aktivní HTTPS nastavení, vypnuté maily, údržba, právě jeden aktivní testovací administrátor a žádný aktivní platební modul z kontrolovaného seznamu. Kontrolní DB zůstala zachovaná. Nejde o test na cílové MariaDB. Exportní PHP i vygenerovaná konfigurace prošly `php -l`; FTP strom neobsahuje `.env`, SQL ani logy. Skutečné spuštění na hostingu ještě neproběhlo.

1. Na vyhrazenou subdoménu nahrát jako první kořenový `upload/.htaccess`. Ověřit blokaci přístupu (403). Potom nahrát obsah `upload/`, včetně skrytých souborů. Pokud zákaz nefunguje, nepokračovat v uploadu shopu.
2. Ověřit verzi MariaDB a přesný DB hostname/uživatele v administraci hostingu; neodvozovat je z názvu phpMyAdmin serveru. Heslo doplnit lokálně do kopie `app/config/parameters.php`, mimo chat. Následně nahrát pouze tuto konfiguraci přes šifrované připojení.
3. Přes phpMyAdmin importovat `private/database-hosting.sql` do potvrzené vyhrazené prázdné DB. Neimportovat původní PS 1.6 SQL ani data do jiné existující DB.
4. Před odebráním zákazu přístupu nastavit a ověřit ochranu testovací subdomény v hostingu. `noindex` nenahrazuje autentizaci. Kořenový zákaz zatím ponechat; údržba v DB je další ochrana.
5. Po zabezpečení přístupu odstranit pouze úvodní `Require all denied` v kořenovém `.htaccess`, ponechat zákaz indexace a ostatní ochrany. Zkontrolovat logy soukromě, přihlášení novým testovacím účtem, HTTPS, doménu, přegenerování `.htaccess` a cache, vypnuté maily/platby. Přegenerování nesmí odstranit ochranu přístupu.
6. Katalog zpřístupnit jen chráněnému testovacímu přístupu a ověřit desktop/mobil, šablonu, obrázky, varianty a košík. Jde stále o 19 demo produktů, nikoli dokončenou migraci.

Nezřizovat produkční crony ani ostré platební/expediční integrace. Ochranu starého zdroje neobcházet tunelem nebo veřejným proxy. Přenos skutečných dat, aktuální zdroj, licencovaný migrátor, první import i následné dorovnání jsou samostatné dosud nedokončené milníky podle `ODDELENA-MIGRACE.md`.
