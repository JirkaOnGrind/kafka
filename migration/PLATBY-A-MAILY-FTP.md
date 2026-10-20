# Platby, dobírka a maily: nové důkazy z FTP

Posouzeno 9. 10. 2026 od 00:55 Europe/Prague. Zdrojová konfigurace potvrzuje PS 1.6.1.13. Kopie se dál stahuje; tento dokument uzavírá první rozbor dostupných platebních modulů, nikoli celý FTP audit. PHP ani SQL nebyly vykonány. Výstupy neobsahují přihlašovací údaje, klíče, kontakty zákazníků ani provozní logy.

## Co se tím mění pro migraci

Původní dm_comgate 3.3.2 a dm_cashondelivery 3.3.0 jsou moduly Davida Mrózka, odkazují na prestashop.valasinec.cz. Tuto identitu potvrzují veřejné produktové stránky výrobce. Nejde o moduly Shaim se stejnou obchodní funkcí. Nejjednodušší první kandidát je aktuální sestava stejného autora, pokud potvrdí přesný PS 9.2.0, použití na stagingu a zachování potřebných historických dat. Nejdříve zjistit, zda klient již vlastní licenci a má nárok na aktualizaci; nekupovat automaticky druhou licenci.

Výrobce uvádí podporu řady PS 9 u Comgate 3.3.7 a dobírky 3.3.0; to není ověřený pilot na 9.2.0 a vlastním checkoutu. Zdrojový dm_comgate 3.3.2 deklaruje rozsah 1.6.0.9–1.6.99.99, takže jeho soubory nelze považovat za instalační balíček pro nový shop. [Comgate výrobce](https://prestashop.valasinec.cz/platebni-moduly-a-brany/108-comgate-platebni-brana-modul-prestashop-17.html), [dobírka výrobce](https://prestashop.valasinec.cz/platebni-moduly-a-brany/83-dobirka-s-priplatkem-a-vazbou-na-dopravce-prestashop-17.html).

## Comgate: skutečný průběh a dvě důležité slabiny

`controllers/front/validation.php` kontroluje zákazníka, adresy a povolenou platební metodu; vytvoří objednávku se stavem DM_COMGATE_STATUS_CREATED a přesměruje na cgpayment. Rozpracovaná objednávka tedy může existovat ještě před zaplacením. ID nebo reference objednávky se volí konfigurací. Dodané SQL má identifikaci pomocí ID, vytvořeno = 33, zaplaceno = 2, zaplaceno bez skladu = 9, chyba = 8, zrušeno = 34 a předautorizace = 35. Testovací režim ani předautorizace podle porovnání s hodnotou 1 nejsou v tomto dumpu zapnuté; opakování platby a mail s odkazem zapnuté jsou.

`cgpayment.php` hledá existující záznam platby, dotazuje její stav a u čekající platby se shodnou částkou ji znovu použije. Při jiném stavu nebo změně částky může vytvořit novou platbu. `DmComgateApi::getDataPayment()` předává částku v setinách a ISO měny, identifikaci objednávky, jazyk a fakturační údaje. Nejde o důkaz skutečně používané předautorizace či všech platebních metod; to určují konkrétní nastavení.

`dm_comgate_notify.php` přijímá transId a refId, ověřuje odvozený token a zavolá `notifyPayment()`. Ta znovu čte stav přes API brány, porovnává částku a řeší PAID, CANCELLED nebo AUTHORIZED. U platby zapisuje stav do dm_comgate_payment pomocí upsertu, při PAID hledá již existující transakci před vytvořením OrderPayment. Mění historii objednávky a může posílat další maily. Chybějící pozitivní stav vrací neúspěšné potvrzení; callbacky je nutné při přepnutí vypořádat a otestovat, ne pouze změnit DNS.

**V dostupném kódu je vypnuté ověřování TLS certifikátu a hostitele** v cURL voláních create/status/refund a dalších API operacích (`CURLOPT_SSL_VERIFYPEER = false`, `CURLOPT_SSL_VERIFYHOST = false`). To oslabuje ověření protistrany. Není to důkaz incidentu; na produkci nebylo nic testováno ani měněno. Do nové sestavy toto chování nepřebírat a požadovat ověřování certifikátu v podporovaném modulu.

**Ochrana před duplicitním platebním záznamem není úplná ochrana stavů objednávky.** V PAID větvi není vidět omezení na nezaplacené stavy: pokud se aktuální stav liší od nastaveného zaplaceno, zavolá se změna historie. Opožděný opakovaný callback tak podle statického průchodu může vrátit expedovanou/doručenou objednávku na zaplaceno. Nejde o pozorovanou událost z logu. Pilot nového modulu musí ověřit opakování, pořadí událostí, zachování pokročilého stavu a vazbu transakce na správnou objednávku a měnu.

Historické tabulky dm_comgate_payment a dm_comgate_log mají význam mimo běžné orders/order_payment. Logy se do nové produkce automaticky nekopírují; archiv chránit. Migrační modul nemusí přenést vlastní platební reference, show_confirmation ani možnost pokračovat přes dříve odeslaný odkaz. Mail může vést na modulovou cestu s tokenem odvozeným ze starého shopu; staré odkazy proto nejsou zaručeně funkční v čisté instalaci. Pro otevřené platby připravit samostatné vypořádání a případně novou podporovanou výzvu až při skutečném přechodu.

## Dobírka: skutečná cena je složitější než jeden příplatek

Modul vybírá pravidlo z cashondelivery_fee podle dopravce, zóny, shopu a hodnoty košíku. Umí pevnou částku, procenta, kombinaci, horní/dolní limit, hranici zdarma, měnová omezení a vyloučení produktů. Dodaný dump má 14 pravidel včetně smazaných; smazaná či starému dopravci patřící pravidla nejsou cílová nabídka dopravy.

| Aktivní pravidlo v dodaném SQL | Zóna | Pevná částka v základní měně | Horní limit | Zdarma od |
| --- | --- | --- | --- | --- |
| Packeta výdejní místo 131 | CZ 9 | 49 Kč | 3 500 Kč | 3 000 Kč |
| Packeta výdejní místo 131 | SK 10 | 59 Kč | 3 500 Kč | 3 000 Kč |
| Packeta adresa 132 | CZ 9 | 49 Kč | 3 500 Kč | 3 000 Kč |
| Packeta adresa 132 | Europe 1 | 59 Kč | 3 500 Kč | 3 000 Kč |
| DPD Classic 133 | CZ 9 | 49 Kč | 3 500 Kč | 3 000 Kč |
| DPD Pickup CZ 137 | CZ 9 | 49 Kč | 3 500 Kč | 3 000 Kč |
| DPD Pickup SK 135 | Europe 1 | 69 Kč | 3 500 Kč | 3 000 Kč |
| Packeta výdejní místo 131 | Europe 1 | 0 Kč | 3 500 Kč | bez hranice |

Tabulka popisuje uložená pravidla, nikoli prokázanou nabídku při každé kombinaci checkoutu. První CZ pravidla pro Packetu mají omezení měny na ID 2 (CZK); jiná mají prázdné omezení. Modul využívá přepočet podle měnového kurzu uloženého v shopu; částku v EUR nelze prostě převzít jako 49 EUR. Dopravce 114 má ještě aktivní nulové pravidlo, ale není mezi šesti aktuálními dopravci. Povolené dopravce a skutečnou nabídku potvrdit v adminu/pilotu.

**Rozpor k vyřešení:** dříve zjištěná veřejná informace o dobírce uváděla 30 Kč. V dumpu je řádek 30 Kč smazaný a neaktivní; aktivní CZ pravidla mají 49 Kč. Před konfigurací nové pokladny klient musí potvrdit zamýšlené ceny, limity a hranici zdarma. Veřejný text není důkaz částky skutečně účtované checkoutem.

Dodané SQL má režim poplatku `carrier`, ID poplatkového produktu 0, zaokrouhlování zapnuté a PS_TAX = 0. V tomto režimu `hookActionValidateOrder()` přičítá poplatek k dopravě a aktualizuje celkovou cenu, OrderCarrier a případně existující OrderPayment. Alternativní větev umí produktový řádek a fakturu, ale není zvoleným režimem tohoto dumpu. CZK zaokrouhlování může vytvořit slevový CartRule; při migraci historie zachovat výsledné součty a vazby, ne je novým modulem přepočítat. Cílový test musí zabránit dvojímu příplatku a ověřit dopravu zdarma, slevy, EUR i editaci objednávky.

Instalační SQL obou dm modulů doplňuje vlastní příznaky do product a product_shop. V dumpu mají všech 3 296 produktových záznamů tyto příznaky; žádný produkt nemá dm_cod_active ani dm_comgate_active = 0. Nelze tedy doložit produktové výjimky, které by musely být přeneseny. Tato vlastní pole nejsou důvod kopírovat celé staré schéma do PS 9.

## Kandidát na původ jednoho override

Balíček dobírky obsahuje `override/classes/order/Order.php`, metodu `refreshShippingCost()`. Přepočítává dopravu pomocí dočasného košíku a pro objednávky dm_cashondelivery znovu přidává dobírkové. To vysvětluje, proč samostatný přenos běžné dopravy nestačí pro editaci dobírkových objednávek.

Je to **kandidát** na původ cache cesty override/classes/order/Order.php. Kořenový override dosud není dostupný, takže totožnost nelze potvrdit porovnáním těla. Ostatních šest cest v cache zůstává nepřiřazených. Modul obsahuje také úpravy checkoutových TPL pro vyšší verze PS; proto ani při stejné obchodní funkci nepředpokládat bezpracnou kompatibilitu s vlastní šablonou. Nový balíček testovat na cílovém theme a původní override nekopírovat.

## Maily: rozsah a co zachovat

Dostupné kořenové maily: CS, SK, DE, EN, každý 75 HTML/TXT/TPL souborů. Pro EN lze proti tagu 1.6.1.13 porovnat 67: 5 byte-identických a 62 odlišných; dalších 8 nemá stejnou referenční cestu. Pro CS/SK/DE oficiální core ZIP nemá odpovídající jazykovou referenci, takže překlady nelze tímto porovnáním označit za vlastní zásah. Přehled je v evidence/mail-template-audit.json. Rozdíl souboru sám o sobě není obchodní úprava.

U reprezentativních EN account/order_conf/bankwire/payment/password/password_query/shipped TXT se sada placeholderů oproti referenci nezměnila. Obsah, vzhled a překlady ale nejsou zaměnitelné automaticky. Modul Comgate má vlastní zákaznické i zaměstnanecké maily ve všech čtyřech jazycích a generuje odkaz k opakované platbě. Dobírka umí přidat řádek do potvrzení při režimu produktového poplatku; tento dump používá dopravu.

Staré account/password šablony obsahují placeholder passwd. Do nové instalace převzít zamýšlené texty a značku, ale použít podporované nové registrační/resetovací šablony a tok PS 9; staré heslové maily nekopírovat doslova. Nikde nebyla vyčtena ani exportována skutečná hesla.

Fakturační TPL v kořenovém pdf ještě nebyly dostupné. Osm PDF příloh v download je odlišná sada souborů a neposkytuje důkaz o úpravách faktur. Návrh maily a faktury v novém shopu samostatně otestovat podle výsledných objednávek a příplatků.

## Mapování do cílového shopu a další otázky

### Bankovní převod — doplněné statické čtení 9. 10. 2026, od 01:17

Bankwire 1.1.2 v posouzeném front controlleru kontroluje zákazníka, adresy a povolenou platební metodu, spočítá celkovou částku a vytvoří objednávku ve stavu PS_OS_BANKWIRE. Do mailu předá bankovní pokyny; v potvrzení zobrazí ID objednávky a případnou referenci. Skutečné bankovní údaje nebyly vypsány ani exportovány. V této cestě není automatické ověření úhrady či párování s bankou; případná externí integrace není tímto čtením vyloučena.

Pro nový podporovaný bankovní převod nastavit aktuální pokyny a potvrdit, zda klient používá číslo objednávky, referenci nebo vlastní variabilní symbol. Nezaplacené staré objednávky zachovat s původní částkou a identifikací, aby po přechodu šlo přiřadit pozdější příchozí úhradu. Obecný import historie není automatická rekonfigurace platebního modulu. Ranní otázka: jak klient dnes páruje převody a zda má externí bankovní/účetní propojení.

| Obchodní funkce | Nejlehčí cílový postup | Nutná kontrola |
| --- | --- | --- |
| Comgate checkout | Ověřit aktualizaci licence stejného autora a sestavu pro 9.2.0 | TLS, měny, callbacky, opakovaná platba, platební historie |
| Dobírkové | Podporovaný modul s potvrzenými pravidly | 49 vs 30 Kč, limity, dopravci, příplatek v dopravě, zaokrouhlení |
| Opakovaný odkaz na platbu | Nový podporovaný mechanismus; otevřené staré platby vypořádat zvlášť | Staré tokeny, modulová cesta, transId/refId a neuhrazené objednávky |
| Historické objednávky/faktury | Migrovat uložené částky a vazby | Nepřepočítávat staré příplatky; po migraci neztratit slevové řádky |
| Transakční maily | Nové podporované šablony se správnou jazykovou a značkovou úpravou | Platba, dobírka, host vs účet, reset hesla |
| Order override | Nejprve porovnat kořenové tělo; nahradit podporovanou funkcí nového modulu | Přepočet dopravy po editaci a zachování dobírkového |

Pro ráno: klientova licence a přístup k aktualizacím Valašince; potvrzení dobírkového a limitů; přehled povolených dopravců; skutečně otevřené Comgate platby; zda se běžně upravují již vytvořené objednávky. Žádný dotaz nebyl odeslán dodavateli a žádný nákup není proveden. Odhad 8–18 pracovních dnů zůstává předběžný do rozboru dalších integrací a pilotu.

### Uzavření ruční analýzy dostupného kódu — 9. 10. 2026

Kořenový override/classes/order/Order.php už je čitelný a obsahově odpovídá dm_cashondelivery po odstranění komentářů a whitespace při zachování řetězců. Účel zachování dobírkovného je potvrzen skutečným kořenovým tělem; dřívější označení za pouhého kandidáta už neplatí. PDF má 38 shodných referencí bez obsahového rozdílu v porovnaném rozsahu. Výslednou fakturu přesto ovlivní DPD hook a uložené částky.

Theme maily mají přednost před root maily podle classes/Mail.php:256–260, případně ještě přednější modulovou cestu v theme. CS/SK theme a root jsou shodné po 75 párech, EN má 38 různých z 72 párů. EN theme reference se shodují s oficiálním tagem; rozdílný root EN sám neprokazuje používanou úpravu. Podrobnosti a rozsah FTP-KOD-A-INTEGRACE.md, důkaz evidence/template-code-audit.json. Pilot mailů musí zachytit společně platební proměnné a údaje pobočky a nové resetování hesla. Závěr a nákupy: ZAVER-A-POSTUP.md.
