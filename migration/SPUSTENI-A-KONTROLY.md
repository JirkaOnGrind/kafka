# Kafka Monster: kontrolní protokol, spuštění a rollback

Tento dokument je plán pro staging a následné spuštění. Nic z uvedených mutací zatím nebylo provedeno. Časy níže jsou pořadí kroků, ne slíbená délka odstávky.

## 1. Podmínky zahájení

- [ ] Přesná verze aplikace a PHP zdroje ověřena proti DB verzi 1.6.1.13.
- [ ] Vybraný migrátor má potvrzené konkrétní vydání pro zdroj a PS 9.2.
- [ ] Obnovitelná záloha SQL i souborů; staging je samostatný document root, DB a runtime.
- [ ] Inventura core změn, overrides, custom endpointů, cronu a externích integrací.
- [ ] Rozhodnuté platby, doprava, daně, jazyky/měny, šablona a vlastník expedice otevřených objednávek.
- [ ] Staging přístupově chráněný a noindex; produkční e-maily/feedy/marketingové úlohy tam neodesílají skutečná data.

## 2. Přijímací kontrola dat

Při první migraci porovnat níže uvedený baseline. Při finálním přenosu se baseline vytvoří znovu, protože původní shop může mezitím přijímat objednávky a měnit sklad. Cílové čisté počty mohou zahrnovat systémové záznamy nové verze; vedle počtů porovnat **množiny zdrojových ID a jejich mapu**, nikoli jen celkové číslo.

| Entita | Baseline z 5. 10. 2026 | Kritérium |
|---|---:|---|
| Produkty | 3 296, aktivních 3 194 | Všechna přenesená ID, stav active a výchozí kategorie |
| Kombinace | 11 786 | 11 784 platných kombinací; 2 osiřelé řešeny jako výjimka, pokud pilot potvrdí tento scope |
| Kategorie | 77 | Zdrojové ID, slug, parent, vazby; nové huby mají oddělená ID |
| Product–category vazby | Viz inventory | Porovnat skutečné dvojice, ne součet počtů za větve |
| Obrázky | 4 962 | Záznamy i soubory, cover/pořadí; všechny aktivní produkty mají obrázek |
| Přílohy | 9, 89 vazeb | Funkční soubory a odkazy v produktu |
| Zákaznické řádky | 582: 115 účtů, 467 hostů | Zachovat vazby a guest stav |
| Adresy | 609 | Zachovat vlastnictví a případné deleted flags |
| Objednávky | 574 | Zachovat ID/reference, částky/měnu/kurz, zákazníka, adresy, stavy, historii |
| Order detail | 1 873 | Zachovat názvy a ceny historických položek i u 25 vazeb na smazané produkty |
| Faktury / platby | 538 / 540 | Zachovat čísla, součty, reference; bez nového rozesílání |
| Vrácení / dobropisy | 1 / 0 | Přenést existující vrácení a ověřit jeho vazby |
| CMS | 12 | Obsah, media URL, jazykové vazby, indexation |
| Specific prices / cart rules | 1 564 / 8 | Výsledná cena, datum platnosti, podmínky |
| Stock available | 15 234 | Porovnat platné product/combination klíče a množství; 152 osiřelých řádků řešit jako zdokumentovanou výjimku |

Export z dodané DB ukazuje 3 296 produktů s prázdným reference. Kontrolní join se tedy opírá o ID nebo migrační mapu, nikoli SKU.

## 3. Funkční testy na stagingu

### Produkt a košík

- [ ] Produkt **3454**: 2 barvy × 7 velikostí, tři atributové skupiny; ceny 259/269/289/299 Kč podle kombinace před případnými aktuálními slevami. Referenční stav určí finální zdroj.
- [ ] Produkt **3415**: fyzické CD za 99 Kč, bez variant, jeden kus ve výchozím dumpu.
- [ ] Produkt **3501**: jednoduchý doplněk za 149 Kč, tři kusy ve výchozím dumpu.
- [ ] Jedna placka s velikostí; jeden produkt s atributem Počet; jeden hrnek nebo piercing s jiným atributem než barva/velikost.
- [ ] Skutečně vyprodaná kombinace; nedovolí objednání, pokud to zdroj zakazuje.
- [ ] Výchozí varianta, změna varianty a množství, reload košíku, odebrání, více druhů zboží.
- [ ] Ceny CZK/EUR, zaokrouhlení, sleva, kupón, nulová DPH podle potvrzeného režimu.
- [ ] Galerie správně funguje i bez vazeb obrázků na kombinace. Žádná neověřená automatická výměna barvy.

### Doprava a placení

- [ ] CZ a SK adresa, další prodávaná země, osobní odběr.
- [ ] Packeta pobočka, Z-Box a doručení na adresu; pobočka zůstává ve vytvořené objednávce a expedici.
- [ ] DPD Classic, Pickup CZ/SK; odpovídající mapa a validace pobočky.
- [ ] Objednávka na výdejní místo bez zvolené pobočky se zastaví s jasným upozorněním.
- [ ] Změna adresy/země/dopravce nezachová neplatnou pobočku ani starou cenu.
- [ ] Košíky pod/přesně na/nad schváleným limitem dopravy zdarma; otestovat CZK/EUR, slevový kupón a dobírku.
- [ ] Pásmo kolem 10 000 Kč a vyšší objednávka; žádné neočekávané zmizení dopravy.
- [ ] Dobírkový příplatek jednou a ve správné měně; omezení pro dopravce/boxy podle obchodních pravidel.
- [ ] Bankovní převod: správné pokyny a čekací stav.
- [ ] Comgate sandbox: úspěch, chyba, zrušení, timeout, opakovaná notifikace, pozdě doručený callback.
- [ ] Opakovaný callback nevytvoří druhou objednávku, druhou platbu ani opakovaný skladový pohyb.
- [ ] Platba funguje i když zákazník neotevře návratovou success stránku.
- [ ] Přechod CZK/EUR při checkoutu, jazyk CS/SK/DE, guest vs registrovaný zákazník.
- [ ] OPC9.2 testovat zvlášť; zapnout pouze po ověření přesné kombinace modulů.

### Zákazník, obsah a provoz

- [ ] Testovací registrovaný účet se přihlásí starým heslem; zobrazí se jeho objednávky/adresy.
- [ ] Reset hesla, nová registrace, objednávka hosta, kontakt a reCAPTCHA.
- [ ] Potvrzovací e-maily zákazníkovi a obchodníkovi; SMTP doručitelnost a správné jazykové šablony.
- [ ] Historické faktury a vrácení dostupné; první nová faktura nenaráží na staré číslování.
- [ ] Test individuální zakázky přes přímý produktový odkaz; Custom zůstává informační hub.
- [ ] Stavy Comgate, dopravci a výdejní místo dostupné u otevřených objednávek nebo jasný postup obsluhy archivu.
- [ ] Feedy mají zachované item ID, správné URL, ceny, dostupnost a měny.
- [ ] Měření nákupu jednou; cookie odmítnutí, volba souhlasů a změna souhlasu fungují.

## 4. Design a SEO

- [ ] Věrnost schválenému homepage/PDP na mobilu a desktopu.
- [ ] Huby, úplné PLP, filtry s dostupnými daty, řazení, stránkování, vyhledávání.
- [ ] Není ponechaný demo text, demo cena, statický badge košíku ani nefunkční `href="#"`.
- [ ] Zákaznický účet, checkout, chyby formulářů, prázdný košík, nenalezený produkt, vyprodáno.
- [ ] Žádné odchody přes menu na starý staging nebo produkční demo URL.
- [ ] Reálné serverové adresy; žádný produkční produktový katalog založený jen na hash routách.
- [ ] Vzorek starých product/category/CMS URL ze všech aktivních jazyků vrací odpovídající obsah nebo jednu správnou 301.
- [ ] Přesný seznam změněných URL ověřen proti Search Console/sitemapě; žádné redirect smyčky/hromadné homepage redirecty.
- [ ] Canonical, hreflang, meta robots, tituly, popisy a produktová structured data.
- [ ] Přeneseny HTML obrázky/odkazy v CMS a popisech, PDF přílohy a obrázkové URL používané ve feedech.
- [ ] Produkce bez staging noindex a bez přístupové ochrany; archiv zůstává chráněný/noindex.
- [ ] Sitemap a robots ověřené z finální domény.

## 5. Nácvik finální synchronizace

1. Zaznamenat verzi migrátoru, všechna nastavení, datum pilotu, použité mapy ID a čas běhu.
2. Na izolované kopii vytvořit nové testovací entity a změnit existující zásobu/stav; ověřit přesný podporovaný delta režim.
3. Ověřit, že nové cílové huby/vlastnosti/theme nastavení nepřepíše opakovaný běh.
4. Ověřit rozpracovanou platbu a její pozdní callback na správném endpointu. Při změně názvu module endpointu nespoléhat na homepage redirect.
5. Vyzkoušet finální změnu domény, cache, SSL, cron, e-maily a kontrolu zásob.
6. Změřit součet času freeze → export → delta → ověření → přepnutí → první test. Podle výsledku zvolit odstávku a komunikační okno.

## 6. Cutover v pořadí

| Krok | Provedení | Výstup před dalším krokem |
|---:|---|---|
| 1 | Klient potvrdí akceptaci a termín; katalogové úpravy se zastaví | Jasný vlastník spuštění a rollbacku |
| 2 | Omezit příjem nových zápisů na zdroji, pozastavit integrační crony | Žádné paralelní objednávky během synchronizace |
| 3 | Reconcile rozpracované platby a expedici; nepřijímat slepě maintenance jako stop webhooků | Seznam čekajících transakcí a odpovědnost za pozdní notifikace |
| 4 | Finální úplná DB+files záloha a baseline | Obnovitelné snapshoty obou stran |
| 5 | Ověřená delta migrace a skladová synchronizace | Nové i změněné entity sjednocené |
| 6 | Kontrola ID, objednávek, dokladů, otevřených zásilek, variant a skladu | Bez nevyřešené obchodní chyby |
| 7 | Přepnout document root/proxy nebo DNS; správná finální URL v PrestaShopu | HTTPS a jednotný write target |
| 8 | Aktivovat produkční platební endpointy, e-maily a pouze cílové crony; zrušit staging noindex | Integrace směřují na nový shop |
| 9 | Smoke test, kontrola callbacků a se souhlasem klienta malá reálná testovací objednávka | Objednávka, platba, stock a e-mail potvrzené |
| 10 | Otevřít prodej, regenerovat sitemap/feedy, spustit monitoring | Klient umí expedovat a má kontakt na řešitele |

Při změně serveru starý web nesmí během DNS propagace přijímat jiné objednávky. Je nutná údržba nebo ověřené směrování na nový server. MX záznamy a e-mailové schránky měnit pouze při skutečné migraci pošty.

## 7. Rollback

Spouštěcí důvody: nejde objednat/zaplatit, ztrácí se pobočky nebo objednávky, nesedí zásoby/ceny nebo nastává rozsáhlá nedostupnost. Jednotlivý kosmetický problém sám o sobě nemusí být důvodem.

**Před první novou objednávkou:** zastavit nový web, zachovat jeho snapshot/logy, přepnout na známý starý snapshot a znovu ověřit platby/crony/SSL. U brány zkontrolovat, zda už vznikla transakce bez objednávky.

**Po první nové objednávce nebo platbě:** zastavit zápisy na obou stranách, zazálohovat nový stav, vyexportovat nově vzniklé objednávky/platby/adresy a skladové změny. Teprve po jejich reconcile rozhodnout o převodu zpět, dočasné obsluze na novém systému nebo opravě vpřed. **Pouhé přepnutí DNS by ztratilo obchodní události.**

Pozdní webhooky, rozpracované karetní platby a vrácení plateb musí mít vlastníka. Jejich endpointy nelze automaticky řešit běžnou SEO 301. Klient musí vědět, v jaké administraci kterou objednávku expedovat.

## 8. Po spuštění

- První hodiny kontrolovat chyby checkoutu a modulů, callbacky, sklad a potvrzovací e-maily.
- První dny kontrolovat 404/301, Search Console, feed validace, měny a expedici.
- Prověřit zálohování nového shopu a obnovu; odstranit konektor nebo omezit jeho přístup po dokončení.
- Starý shop držet neveřejně pro historii a doexpedování potřebných objednávek; veřejné cron/odesílání vypnuté.
- Po stabilizaci doplnit vlastnosti, obrázkové vazby, překlady a další schválené funkce.

### Uzavření ruční analýzy dostupného kódu — 9. 10. 2026

Do pilotu zahrnout konkrétní nálezy z FTP, nikoli jen obecný průchod homepage:

- Comgate: TLS certifikát/hostitel, vazba objednávka–částka–měna–transakce, opakovaný a opožděný callback, CANCEL/AUTH/PAID, PAID po doručení; existující otevřené platby a staré opakované odkazy. Ověřit, že zaplacená objednávka nevytvoří duplicitní obchodní událost ani nevrátí pokročilý stav.
- Dobírka: schválená sazba 30/49 Kč a limity, CZ/SK/EUR, zaokrouhlení, editace dopravy a množství variant po vytvoření objednávky; příplatek nesmí zmizet ani se zdvojit. Historické částky nezměnit.
- Packeta/DPD: ID místa a reference dopravce v košíku i objednávce, chybějící/zrušené místo, přepnutí země/služby, boxy vs. pobočky, mobil, nový seznam míst, mail/PDF, rozpracované staré zásilky a skutečný CSV/API postup expedice.
- Mergado: feed ITEM_ID produktu/varianty, nové URL a rozvrhy, TLS cenového zdroje, cena kombinace minus základ, slevy a měny před/po případném importu. Staging nesmí měnit ostré externí feedy, ceny či odesílat konverze/refundace.
- Souhlasy: běžný prohlížeč (ne pouze Lighthouse), před souhlasem/odmítnutí/odvolání, GTM a přímé JS i modulové hooky, jazyky a změny stránky/checkoutu. Staré session/souhlasy nepovažovat za automaticky přenositelné.
- Antispam: platný/chybějící/chybný token/action, síťový výpadek, kontakt a účet/host/AJAX. Schválený postup při nedostupnosti služby musí být funkční; starý modul v této situaci propouští.
- Maily/faktury: skutečná precedence theme/modul/root, branding a používané jazyky, místo+dobírkové+platební odkaz současně, číslování a uložené historické součty, bezpečný reset hesla. Shoda PDF šablon není test výsledné faktury.

Čerstvou úplnost záloh, faktický runtime a synchronizaci nových **i změněných** záznamů ověřit samostatně. Statická analýza žádný z těchto provozních testů nevykonala. Podklady a nákupní postup: ZAVER-A-POSTUP.md.
