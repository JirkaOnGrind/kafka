# Analýza a oprava zařazení produktů — 19. 10. 2026

**Výsledek: opraveno a ověřeno na izolovaném localhost:8083.** Všech 3 194 aktivních katalogových produktů z použitého SQL je dostupných přes nový klientský strom. Všech 55 kategorií a podkategorií má obsah. Původní URL produktů a kategorií, slugy, výchozí produktové kategorie a SEO metadata zůstaly zachované. Produkce se neměnila; nový SQL se neimportoval.

## Proč zboží zdánlivě chybělo

Předchozí úprava vytvořila nový strom, ale nedokončila produktová přiřazení při rozdělení původních společných kategorií. Devět nových koncových kategorií zůstalo prázdných. Přesun společných historických kategorií mimo Home navíc odstranil 409 aktivních produktů z nové navigace, přestože v databázi stále existovaly. Původní kontrola HTTP 200, názvů a šipek byla pro ověření obsahu nedostatečná.

Lokální databáze obsahuje všech **3 296 produktů** z porovnávaného zdrojového SQL; žádný z jeho produktových záznamů se při této úpravě neztratil. Z nich je 3 194 aktivních a viditelných v katalogu, dalších 102 zůstává ve svém původním neaktivním nebo jinak neveřejném stavu.

## Co bylo změněno

Bylo přidáno **613 řádků do `category_product`**, z toho 450 pro dokončení nového zařazení a 163 pro úplný původní sortiment historických společných kategorií. Všech **4 331 původních vazeb včetně jejich pozic** zůstalo zachovaných; výsledkem je 4 944 vazeb. Žádný produkt nebyl aktivován, přejmenován ani přepsán na jinou výchozí kategorii.

| Nová kategorie | Před opravou | Po opravě: aktivní katalogové produkty |
| --- | ---: | ---: |
| Čepice | 0 | 187 |
| Kravaty | 0 | 8 |
| Obojky | 0 | 14 |
| Hroty | 0 | 5 |
| Pyramidy | 0 | 2 |
| Cvoky | 0 | 1 |
| Knihy | 0 | 22 |
| Fanziny | 0 | 14 |
| Tašky, včetně vaků a kapes na opasek | 0 | 159 |
| Módní doplňky / Placky | 186 | 202 |
| Merch / Kapely | 143 | 157 |
| Merch / Vlastní potisk / Placky | 16 | 20 |

Počty jsou počty produktů, nikoli kombinací. Kategorie se mohou překrývat: například čepice s motivem klientovy kapely patří do Čepic i do jejího merche. Součty hlavních kategorií proto nejsou počtem unikátních produktů celého obchodu.

Úplné rozdělení všech 55 uzlů je v [tabulce před/po](evidence/category-assignment-20261019-1728/category-counts-before-after.csv), jednotlivé přidané vazby v [podrobném seznamu](evidence/category-assignment-20261019-1728/added-product-links.csv).

## Jak se rozhodovalo o zařazení

- Čepice: původní Zimní a Kšiltovky, doplněné explicitním názvem typu. Zakázkový potisk čepic zůstává samostatnou službou ve Vlastním potisku.
- Tašky: původní Plátěné tašky, Vaky/batohy/tašky a Kapsy na pásek. Produkt s obecným názvem „PND 4“ byl ověřen z popisu jako plátěná taška. Peněženky zůstávají zvlášť.
- Obojky a kravaty: rozdělení všech 22 produktů původní společné kategorie podle jednoznačného názvu typu. Obojek s pyramidami patří do Obojků; ne mezi samostatné kovové Pyramidy.
- Hroty, pyramidy a cvoky: rozdělení osmi kusových doplňků původní společné kategorie. Náušnice ve tvaru hrotu není volný hrot na připevnění k oblečení.
- Knihy a fanziny: ověřené názvy i popisy. Sedm titulů Petra Neklana Benkeho má v popisu „Knížka“; Punkavec a jeho komplety mají „Punkový Fanzin“. Fanziny se nezaměňují za stejně pojmenovaný kapelní merch.
- Placky: šestnáct aktivních produktů chybějících v samostatných Plackách má jednoznačný popis „Placka se spínacím špendlíkem“. Jde o placky, nikoli samostatné špendlíky.
- Vlastní potisk: čtyři prověřené 58mm varianty butonu, magnetu, zrcátka a otvíráku s množstevním ceníkem byly doplněny do zakázkových Placek; nepřidávaly se mezi běžný katalog.
- Kapely: čtrnáct chybějících aktivních přiřazení se shodným celým jménem klientovy kapely v názvu produktu. Neaktivní zakázky se takto automaticky nepřidávají.

Nejednoznačný aktivní produkt nebo neodpovídající identita původní zdrojové kategorie celý plán zastaví. Plán nesmí vytvářet zboží ani přiřazovat podle osamoceného slova vytrženého z typu produktu. Dvanáct regresních kontrol ověřilo tato rozlišení a odmítnutí nejednoznačného vstupu.

## Zachování SEO a původních URL kafka.monster

Přidávaly se další katalogové vazby, **nikoli změny `id_category_default`**. Produkt obojku se například zobrazuje v nové kategorii `/cs/133-obojky`, ale jeho původní produktová cesta nadále obsahuje `/cs/obojky-kravaty/…html`. Canonical produktové stránky zůstává na původní produktové cestě. Není potřeba vyrábět řetězce přesměrování.

Úplné porovnání vůči zachovanému zdrojovému katalogu:

- **9 888 produktových URL** v aktivních jazycích CS/SK/DE: 0 změněných cest, 0 změněných porovnávaných metadat;
- **231 původních kategoriálních URL** v těchto jazycích: 0 změněných cest, slugů nebo SEO metadat;
- celé tabulky `product`, `product_shop`, `product_lang`, `category`, `category_lang`, `category_shop` a `category_group` mají při porovnání před a po opravě stejné SHA-256; kontrola zahrnuje i ostatní překlady a produktové popisy;
- žádná původní produktová vazba ani její pozice nebyla odstraněna nebo přepsána.

Původní společné URL zůstávají aktivní a zachovávají úplný původní sortiment, i když jejich podkategorie nyní patří do jiného stromu:

| Původní URL na kafka.monster | Ověřený původní i výsledný sortiment |
| --- | ---: |
| `/cs/3-plackyspendlikypiny` | 267 produktů |
| `/cs/32-cepice-satky` | 190 produktů |
| `/cs/58-taskypenezenky` | 180 produktů |

Tato kompatibilita je mimo šest hlavních vstupů klientského menu; starý smíšený sortiment se nevnucuje do nových samostatných kategorií. Kategorie 93 zůstává funkční pro všechny původní obojky i kravaty, 98 pro knihy/fanziny a 102 pro původní společné kovové doplňky. Chybějící původní ID 127 není použito pro žádný nový obsah.

Místní odpovědi a sitemapy používají `localhost:8083`, protože jde o chráněnou zkoušku. Cesty se porovnávají s původními `kafka.monster`; veřejná doména, DNS, indexace a produkční soubory se neměnily. Hlavička `X-Robots-Tag` na localhost zůstává zapnutá.

## Skutečně chybějící data ve vstupní záloze

To je jiná věc než chybné zařazení. Zachovaná veřejná evidence z 19. 10. obsahuje **17 produktových ID a kategorii 127**, které nejsou v použitém SQL. Jde o produkty 3502–3508, 3510–3516 a 3518–3520; kategorie 127 je „Tašky přes rameno“. Poslední SEO audit eviduje jejich jazykové URL jako 44 chybějících adres.

Tyto záznamy se nevyráběly, nepřesměrovávaly na nesouvisející produkty a nepřidávala se k nim vymyšlená média. Jejich doplnění vyžaduje čerstvý zdrojový SQL a samostatně autorizovaný migrační přenos, nikoli opravu produktových přiřazení. [Přesný seznam chybějících URL](evidence/seo-20261019-172854-555/missing-content.csv).

Srovnání vychází z původního SQL a zachované zdrojové HTTP evidence. Z této opravy nelze vyvozovat, že se mezitím na produkci nezměnil další sortiment; žádný nový SQL import součástí práce nebyl.

## Zjištěná a opravená chyba hodnocení

Browser test odhalil HTTP 500 z `productcomments/CommentGrade`, když se tentýž produkt objevil v několika kartách na stránce. Product Comments 8.0.1 po `array_unique()` zachovává mezery v indexech pole a podle těchto indexů sestavuje čárky SQL dotazu pro průměrné hodnocení. Duplicitní ID tak mohou vyrobit neplatné SQL.

`kafkahome` 1.0.1 normalizuje pole do souvislých indexů pouze na tomto konkrétním čtecím endpointu, před vstupem do původního modulu. Dodavatelský kód, uložené recenze a jejich obsah se nemění. Čtyři přímé reprodukční požadavky, včetně duplicitních ID, nyní vracejí HTTP 200; následný browser test nemá žádné chybné síťové odpovědi ani chyby konzole.

## Výsledky přejímky

| Kontrola | Výsledek |
| --- | --- |
| Úplné zařazení všech 3 194 aktivních katalogových produktů | PASS, 0 nedosažitelných |
| Opakovaný plán po aplikaci | PASS, 0 dalších změn |
| 55 klientských + 3 historické kategoriální výpisy | PASS, skutečné počty a zobrazené produkty odpovídají katalogu |
| Všech 438 aktivních produktů dotčených novým zařazením | PASS, čerstvé HTTP 200 na původních cestách a správné canonical |
| Souhrnný obsahový/URL audit | PASS, 496 čerstvých HTTP odpovědí |
| Úplná SEO přejímka | PASS, 1 755 čerstvých HTTP odpovědí, 0 chyb směrování, 0 chyb přejímky |
| Sitemap | PASS, přesně 9 828 způsobilých lokálních URL, CS/SK/DE |
| Kategorie a stránkování obojků, desktop 1536×1024 / mobil 390×844 | PASS, 87 browser kontrol, čistá konzole a síť |
| Varianty, ceny, původní variantové URL a košík | PASS, dalších 51 browser kontrol, testovací řádek košíku odebrán |

Obsahový audit: [souhrn](evidence/category-assignment-20261019-1728/summary.json), [skutečné kategoriální výpisy](evidence/category-assignment-20261019-1728/category-listings.csv), [porovnání původních URL a otisků](evidence/category-assignment-20261019-1728/source-url-preservation.json), [browser kontroly kategorií](evidence/category-assignment-20261019-1728/browser-qa.json), [browser kontroly variant a košíku](evidence/category-assignment-20261019-1728/native-browser-qa.json). Kompletní SEO audit: [REPORT.md](evidence/seo-20261019-172854-555/REPORT.md), [failures.csv](evidence/seo-20261019-172854-555/failures.csv), [missing-content.csv](evidence/seo-20261019-172854-555/missing-content.csv).

Předběžné neúspěšné či přerušené běhy zůstávají zachované. Úspěch se opírá o výše uvedené nové složky a nové HTTP odpovědi. Audit navíc po úplném parsování a výpočtu otisku HTML neuchovává nepotřebné HTML v paměti; rozsah kontrol se nesnížil. Případné stderr a návratový kód se nově ukládají do soukromé diagnostiky.

## Opakování a soukromé zálohy

- `scripts/repair-category-memberships.ps1` pouze vyexportuje čerstvý lokální katalog a připraví plán bez změny databáze.
- `scripts/repair-category-memberships.ps1 -Apply` uloží soukromou SQL zálohu s SHA-256, ověří identitu a otisky vstupu, přidá jasné vazby v transakci, ověří neměnnost chráněných tabulek a všech původních vazeb, vyčistí odvozenou cache výpisů, ověří opakovatelnost a automaticky spustí úplnou SEO přejímku.
- `scripts/apply-client-categories.ps1 -Apply` nyní po sestavení stromu automaticky volá i tento postup; samotné založení kategorií není dokončení zařazení.
- `scripts/plan-category-assignments.py`, `scripts/audit-category-assignments.py` a `scripts/test-category-planner.py` obsahují opakovatelný plán, obsahovou/URL přejímku a regresní kontroly skutečných smíšených typů.

Výchozí záloha před produktovým zařazením: `local/private/migration-lab-20261018/import-runs/import-category-products-20261019-172008-227/`. Záloha před doplněním historických seznamů: `…/import-category-products-20261019-172238-266/`. Opakovací běh a záloha projektového modulu: `…/import-category-products-20261019-172837-445/`. SQL, popisy úplného katalogu a vstupní plány zůstávají soukromé. Staré zálohy, Docker volumes a produkce nebyly mazány ani resetovány.
