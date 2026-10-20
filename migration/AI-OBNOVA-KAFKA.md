# Kafka Monster — předání obnovy dalšímu AI / správci

Tento návod popisuje zálohu vytvořenou 14. 10. 2026. Je technickým podkladem, nikoli souhlasem s přepsáním produkce. Nejprve zjisti, zda uživatel požaduje lokální kontrolu, obnovu rozpracovaného nového shopu, nebo návrat starého shopu na hosting. Během přípravy se běžícího kafka.monster nedotýkej.

## Co musí uživatel dodat jako místní soubory

- `kafka-backup.kafkagcm` — šifrovaný archiv, 1 770 629 166 bytů.
- `kafka-backup.json` — kontrolní součty a seznam obsažených záloh, zachovat vedle archivu.
- `source-preview-fix-20261014.zip` — veřejné obnovovací nástroje a novější opravy lokálního náhledu.
- Tento soubor `AI-OBNOVA-KAFKA.md`.
- Samostatně uložený `cloud-recovery-20261014-final.key`. Získat pouze cestu k místnímu souboru; obsah klíče nevyžadovat v chatu ani nevypisovat.

Na původním PC je cloudová složka `C:/Users/Utrh/Documents/ChatGPT/KAFKA/local/backups/cloud-ready-20261014-final/`. Klíč leží odděleně v `C:/Users/Utrh/Documents/ChatGPT/KAFKA/local/private/cloud-recovery-20261014-final.key`. Tyto cesty na jiném PC samozřejmě nemusejí existovat. Klíč nesmí být součástí uploadované složky; musí mít vlastní bezpečně uloženou kopii mimo původní PC.

## Otevření archivu na novém PC

Nejde o běžný ZIP. Použij přiložené nástroje pro existující AES-256-GCM formát; nic znovu nešifruj. Nejprve prohlédni obsah doplňkového ZIPu, potom jeho nástroje rozbal do nového soukromého pracovního adresáře se zachováním cest `scripts/`, `migration/`, `local/source-runtime/`. Nekopíruj přes již existující rozpracovaný projekt bez porovnání změn.

Potřebuješ Python alespoň 3.12 s knihovnou `cryptography`. Pro samotné ověření není potřeba Docker. Skripty odvozují kořen pracovního adresáře od umístění `scripts/`; výstup obnovy musí být pod jeho `local/backups/`. Na původním PC má Codex vlastní Python runtime, na jiném PC jej vyhledej nebo připrav odpovídající prostředí.

1. Ověř archiv bez vytváření velké nešifrované kopie:

   ```text
   python scripts/cloud-backup.py --verify "CESTA/kafka-backup.kafkagcm" --key-file "ODDELENA-CESTA/cloud-recovery-20261014-final.key"
   ```

2. Po úspěchu vytvoř nový, prázdný soukromý adresář `local/backups/recovery-UNIKATNI/`. Zkontroluj volné místo: dešifrovaný TAR a jeho rozbalený obsah potřebují přibližně dvojnásobek velikosti archivu plus rezervu; Docker a rozbalení shopových souborů potřebují další místo. Původní soubory ani Docker volumes nemaž kvůli uvolnění prostoru.

3. Dešifruj a teprve po úspěšné autentizaci a kontrole součtů rozbal:

   ```text
   python scripts/cloud-backup.py --verify "CESTA/kafka-backup.kafkagcm" --key-file "ODDELENA-CESTA/cloud-recovery-20261014-final.key" --decrypt-to "KOREN/local/backups/recovery-UNIKATNI/bundle.tar"
   python scripts/extract-cloud-backup.py --bundle "KOREN/local/backups/recovery-UNIKATNI/bundle.tar"
   ```

   `KOREN` nahraď skutečnou absolutní cestou pracovního adresáře. Při chybě klíče, autentizace či součtu zastav obnovu; částečný výstup nepoužívej. Nástroj odmítá přepsání existujícího výstupu. Na Windows lze použít také `scripts/verify-cloud-backup.ps1 -ArchivePath ... -KeyFile ... -Extract`; nejprve přečti jeho předpoklady pro Python.

## Co je uvnitř a k čemu to slouží

- `source-original-20261014-135846/source-files.tar.gz`: původní FTP soubory starého PS 1.6.1.13 včetně původní konfigurace. Původní podklady zůstaly nedotčené.
- `source-original-20261014-135846/database.sql`: původní SQL **z 5. 10. 2026**, nikoli aktuální produkční DB. Sousední `manifest.json` obsahuje kontrolní součty.
- `recovery-set-20261014-final/`: DB nového PS 9, `shopfiles.tar.gz`, soukromá `.env`, konfigurace Compose, `project.tar.gz` s implementací a Git historií a `checksums.csv`.

Nový PS 9.1.5 je rozpracovaný obchod s 19 demo produkty. Licencovaná migrace ještě neproběhla. Starý SQL obsahuje 3 296 produktů, 11 786 kombinací, 582 zákazníků a 574 objednávek. Zachovat dvě známé osiřelé kombinace a historické řádky objednávek s již smazanými produkty; řešit pouze na pracovní kopii.

Projekt z `project.tar.gz` nejprve bezpečně prohlédni a rozbal do samostatného neveřejného projektu. Ověř jeho kontrolní součty a přečti `AGENTS.md`, `JAK-PRACOVAT.md` a `migration/ODDELENA-MIGRACE.md`. Doplněk ZIP obsahuje pozdější opravy než projekt uvnitř šifrovaného archivu: porovnej a použij jeho aktuální pomocné soubory pro náhled. Původní `.env`, konfigurace a SQL obsahují tajné údaje / osobní data; nevypisovat, necommitovat, nedávat na veřejné FTP.

## Nejprve skutečná lokální obnova

Nový shop obnov přes `scripts/restore-rehearsal.ps1 -BackupPath ...` do nového Compose projektu a nových volumes. Skript očekává obnovovací složku pod `local/backups/`. Zachovej stávající volumes a ověř dostupnost portu 8081. Přečti `compose.restore.yaml`; aplikace a DB musí být na interní síti, pouze místní náhled zpřístupňuje shop.

Pro starý shop vytvoř z původního archivu novou pracovní kopii a novou místní DB; nepouštěj původní produkční konfiguraci. Přečti a použij `scripts/prepare-source.py`, `scripts/source-rehearsal.ps1` a `compose.source.yaml`. Při změně PC uprav místní cesty/stavové soubory podle skutečnosti, nepoužívej slepě staré absolutní cesty. Zdroj potřebuje kompatibilní PHP 7.1 s rozšířeními dle `local/source-runtime/Dockerfile`. Před prvním spuštěním odděl DB, odpoj produkční služby, zakaž maily, platby, feedy a crony a ověř blokované odchozí spojení. Použij opravy náhledu z doplňkového ZIPu a `migration/OPRAVA-NAHLEDU-OBRAZKU.md`.

Nestačí ověřit archiv: spusť obchod, administraci, katalog, obrázky, varianty, přidání do košíku a historickou fakturu. Starý náhled záměrně dovoluje jen souhrn košíku, nikoli dokončení objednávky. Porovnej počty a vazby s `migration/evidence/`; nezobrazuj zákaznická data ve výstupech. Hlášení drobné chyby starého produktového JavaScriptu je známé a není důkazem úplného otestování všech cen variant. Cloudový upload a následné stažení jsou samostatná kontrola: původní JSON je zatím označuje jako neprovedené.

## Případný návrat na WEDOS — až podle výslovného zadání

Podrobný postup je v přiloženém `migration/OBNOVA-A-ZALOHY.md`. Neuploaduj šifrovaný archiv přímo jako web. Pro starý shop použij původní FTP archiv a jeho původní SQL, nikoli upravený Docker náhled. Připrav oddělené neveřejné soubory a samostatnou DB, v `config/settings.inc.php` nastav skutečné hostingové DB připojení a zachovej původní klíče zákaznických hesel. PHP musí odpovídat starému shopu; na stávajícím WEDOS Extra změna PHP zasahuje celý hosting, proto ji během místní přípravy neprováděj. Aktuální dostupnost staré verze PHP a parametry hostingu ověř před obnovou.

Před schváleným přepnutím: dohodnutá odstávka, zastavení zapisujících procesů, vypořádání plateb a callbacků, čerstvá konzistentní záloha současných souborů a DB mimo hosting, ověření připravené obnovy. SQL z 5. 10. nelze slepě použít místo novější produkce: ztratil by pozdější objednávky a změny. Po přijetí nových objednávek musí i návrat zachovat tyto transakce. Aktivní soubory/DB přepni až po ověření a podle souhlasu uživatele s konkrétním zásahem. Původní stav zachovej pro návrat.

PS 9 nasazuj až po dokončené a přijaté migraci, nikoli jako nynější demo zálohu. Používá jiné připojení (`app/config/parameters.php`) a PHP 8.3. Lokální ochrany zdrojové kopie a Docker konfiguraci nepřenášej na ostrý hosting.
