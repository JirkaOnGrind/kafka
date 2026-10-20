# Stav přípravy a obnovy — 14. 10. 2026

Produkční web, jeho DB, soubory, PHP a konektory nebyly během této realizace změněny. Všechny operace proběhly na PC. Zakoupený migrační modul zatím není dodaný; skutečný přenos do PS 9 ani nasazení neproběhly.

## Co si můžeš otevřít sám

Dvojklik **`zkontrolovat-zalohy.bat`** v kořeni projektu spustí hotové obnovené kopie. Neprovádí nový import. Přihlašovací údaje otevři pouze lokálně podle [OBNOVA-A-ZALOHY.md](OBNOVA-A-ZALOHY.md).

| Kopie | Obchod | Administrace | Ověřené chování |
| --- | --- | --- | --- |
| Nový shop obnovený ze zálohy | http://localhost:8081/ | http://localhost:8081/admin-dev/ | 19 demo produktů, aktivní Kafka, skutečné přidání do košíku, přihlášení |
| Starý obchod obnovený z FTP a SQL | http://localhost:8082/ | http://localhost:8082/admin-source/ | Produkt s obrázkem, přihlášení, seznam objednávek, generování historické faktury PDF |

Předloha běží na 4173; rozpracovaný vývojový shop na 8080. Tyto adresy nejsou obnovené kopie. Staré i nové kopie mají vlastní DB a svazky. Neúspěšné pomocné svazky z prvních nácviků byly zachované, nikoli smazané. Ostré platby, zákaznické maily, expedice a produkční crony v kopii neběží. U obou aplikací byl skutečným pokusem ověřen zákaz odchozího TCP spojení.

## Převzetí dat staré zálohy

SQL z 5. 10. 2026 bylo skutečně importované do samostatného MySQL. PHP kopie je 7.1.33. Dvanáct hlavních tabulek má stejné počty jako původní audit SQL:

| Data | Počet |
| --- | ---: |
| Produkty / aktivní | 3 296 / 3 194 |
| Kombinace | 11 786 |
| Kategorie | 77 |
| Zákaznické řádky / adresy | 582 / 609 |
| Objednávky / položky | 574 / 1 873 |
| Faktury / platby | 538 / 540 |
| Obrázky / přílohy | 4 962 / 9 |

Dvě známé osiřelé kombinace a 25 historických položek po smazaných produktech zůstaly zachované. Jejich řešení patří do kontrolovaného migračního pilotu, nikoli do opravy produkce. U dodaného FTP byla znovu ověřena existence všech očekávaných produktových originálů a příloh. Dokončení FTP fronty potvrdil uživatel; nejde o nezávislý aktuální manifest ostrého serveru.

Souhrny a kontrolní otisky jsou v `evidence/source-restored-check.json`. Skript `scripts/migration-check.php` čte pouze lokální DB a vydává počty, součty a kontrolní otisky vybraných vazeb, nikoli kontaktní údaje nebo obsah objednávek. Slouží jako podklad pro pozdější porovnání skutečného importu; shoda těchto souhrnů sama nenahrazuje prohlídku reprezentativních dokladů a chování shopu.

## Šifrovaná záloha k ručnímu nahrání

Finální složka pro upload: **`local/backups/cloud-ready-20261014-final/`**. Nahraj oba soubory `kafka-backup.kafkagcm` a `kafka-backup.json` do soukromého Google Drive. Samostatný klíč je **`local/private/cloud-recovery-20261014-final.key`**; ulož jej odděleně do správce hesel, nikoli vedle archivu.

Balíček obsahuje kompletní původní FTP/SQL a obnovovací sadu nového shopu s aktuálním celým projektem. Stav úspěšné autentizace a dešifrování uvádí jeho JSON (`local_decryption_verified`). Pokud uvedená finální složka ještě nemá oba soubory a příznak true, balíček není dokončený. Předchozí pracovní balíček z 14:23 byl na výslovný pokyn uživatele odstraněn při [úklidu 14. 10.](UKLID-14-10.md); jeho samostatný klíč zůstal zachovaný. Pro upload použij finální variantu, která znovu prošla kontrolou autentizace, dešifrování a kontrolních součtů.

Po následné opravě náhledu je ve stejné složce malý `source-preview-fix-20261014.zip` s aktualizovaným pravidlem pro statické assety. Nahraj celou finální složku včetně doplňku. Obrázky v původní datové záloze nechyběly; lokálně je blokovalo pravidlo `/modules/`. Nová kontrola ověřila **39 z 39 obrázků homepage** a zachování blokace PHP i odchozích spojení. Postup je v [OPRAVA-NAHLEDU-OBRAZKU.md](OPRAVA-NAHLEDU-OBRAZKU.md).

Upload a zkouška stažené cloudové kopie zatím **neproběhly** — provede je uživatel podle [OBNOVA-A-ZALOHY.md](OBNOVA-A-ZALOHY.md). Místní dešifrování není potvrzení dokončeného cloudového uploadu. Na C: zbývá málo volného místa; další kompletní rozbalení vyžaduje dostatečnou rezervu a skript ji předem kontroluje. Průběžné ověření šifrovaného souboru další velkou kopii nevytváří.

## Kontrola v prohlížeči a omezení

Chrome přes bundled Playwright; Browser plugin není v relaci dostupný. Desktop 1536 × 1024 a mobil 390 × 844. Nová obnovená homepage odpovídá rozpracovanému lokálnímu shopu; předloha zůstala zachovaná. Obě obnovené kopie se vykreslily bez horizontálního přetečení. Nový shop prošel bez chyb JavaScriptu, s reálným košíkem a přihlášením.

Stará kopie umožňuje prohlížení katalogu, obrázků, objednávek a faktur. Následná oprava přístupu ke statickým assetům opravila také chybu grafu administrace: nová kontrola přihlášení a objednávek proběhla bez chyb JavaScriptu. Následně byl zpřístupněn prázdný i naplněný košík a ověřeno skutečné přidání produktu. Další kroky checkoutu a zákaznické formuláře zůstávají blokované; PHP ochrana kontroluje i POST parametry a zobrazuje vysvětlení pro místní kopii. Kontrola košíku je v `evidence/source-cart-check.json`. Při testu se projevila neblokující chyba původního produktového JavaScriptu (`price_with_reduction_without_tax`); produkt se přesto uložil do košíku a souhrn se načetl. Tento test není převzetím všech variant a cen původního frontendu. Náhled není veřejný obchod ani test ostrých plateb. Zdrojové PHP moduly a overrides se do nového PS 9 nepřenášejí.

Snímky nové i staré kopie jsou uložené mimo repozitář ve složce vizualizací tohoto chatu. Soukromá zkušební historická faktura je pouze v `local/private/restored-invoice-check.pdf`; do veřejných reportů ani projektového archivu se nepřibalila.

## Co zbývá

1. Uživatel si osobně projde obnovené kopie a uloží šifrovanou druhou zálohu mimo PC.
2. Získat potvrzení výrobce a koupit PrestaHero podle [DOTAZ-PRESTAHERO.md](DOTAZ-PRESTAHERO.md); ZIP dodat do `migration/licensed-packages/`.
3. Získat čerstvé SQL a změněná média pro aktuální pilot. Potvrdit ceny dopravy/dobírky a dodat případné aktualizace integračních modulů.
4. Codex provede Mode 1, kontroly importu, dokončení nativních stránek a následný nácvik Mode 4. Tyto milníky zatím nejsou označené za splněné.
5. Až potom připravit konkrétní odstávku, finální zálohu ostrého WEDOSu, serverový test a návrat. Nyní nic na WEDOS nepřepínat.
