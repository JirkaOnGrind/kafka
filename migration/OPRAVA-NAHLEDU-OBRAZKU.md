# Oprava obrázků lokální staré kopie — 14. 10. 2026

Po upozornění uživatele byly prověřeny všechny obrázky homepage. Bannery a slider existovaly v původním FTP, v záloze i v obnoveném Docker svazku; jejich HTTP požadavky blokovalo příliš široké místní pravidlo pro `/modules/`.

`local/source-runtime/apache-source.conf` nyní dovoluje pouze statické assety modulů (obrázky, CSS, JS a fonty). Modulové PHP vstupy a konfigurace zůstávají blokované. Ověřeno všech 39 obrázků homepage: 39 načtených, žádný rozbitý. Modulový PHP soubor a `config/settings.inc.php` vracejí 403. Odchozí TCP spojení aplikace zůstává zablokované. Doklad je v `evidence/source-image-check.json`.

Povolení statických JS opravilo také dřívější chybu grafu administrace. Opakované přihlášení a otevření historických objednávek proběhlo bez chyb JavaScriptu.

Datové zálohy nejsou kvůli této opravě změněné; obrázky už obsahují. Původní šifrovaný balíček zůstává zachovaný s platnými kontrolními součty. Vedle něj je malý doplněk **`source-preview-fix-20261014.zip`**, který obsahuje opravené pravidlo a tento návod, bez hesel nebo zákaznických dat. Na soukromý Google Drive nahraj celou složku `cloud-ready-20261014-final` včetně doplňku.

## Následné zpřístupnění košíku

Pravidlo původně blokovalo také `controller=order`, který v PS 1.6 obsluhuje nejen dokončení objednávky, ale i souhrn košíku. Nyní dovoluje GET/HEAD souhrnu s krokem 0. Vlastní PHP ochrana `kafka-source-guard.php`, načtená přes `preview-guard.ini`, odmítá pokračování objednávky i POST požadavky na její vytvoření. Košík zobrazuje upozornění o místní kopii. Nastavení zdroje volí standardní souhrn (`PS_ORDER_PROCESS_TYPE=0`), nikoli jednostránkový checkout. Ověřeno otevření prázdného košíku, přidání produktu, zobrazení položky a blokace dalších kroků/platby přes GET i POST. Viz `evidence/source-cart-check.json`. Původní datové archivy se nezměnily.

Při obnově na jiném PC Codex po obnovení projektu použije všechny soubory z malého doplňku v jejich relativních cestách. Projekt a privátní cesty načte z `local/source-state.json`, nikoli z pevně převzatého jména jiné instalace. Pro novou kopii sestaví obraz přes `compose.source.yaml build shop`; pomocný skript nastaví standardní souhrn automaticky.

Pokud už stará kopie běží, do jejího kontejneru zkopíruje Apache pravidlo do `/etc/apache2/conf-enabled/kafka-source.conf`, PHP ochranu do `/usr/local/etc/php/kafka-source-guard.php` a INI do `/usr/local/etc/php/conf.d/kafka-preview-guard.ini`. Ověří Apache/PHP syntaxi, přenese a spustí `scripts/configure-source.php` ze souboru v `/tmp` a restartuje pouze tuto zdrojovou aplikaci. Znovu ověří košík, obrázky, HTTP 403 pro platby a zákaz odchozího TCP spojení. Databázi není nutné znovu importovat. Před migračním pilotem vytvoří nový čistý zdroj ze zálohy, aby se nepřenášely místní testovací košíky.
