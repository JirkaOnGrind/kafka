# Kafka Monster — homepage architektura

> **Rozsah:** pouze homepage, její navigace a kategorie vykreslené přímo na homepage.

## Trvalé pravidlo projektu

Homepage používá šest hlavních vstupů uvedených níže. Pět z nich vede do produktového katalogu; `Custom` je samostatná informační a cenová stránka bez produktového feedu.

## Redukovaný strom navigace

Primární navigace má přesně šest vstupů a maximálně tři úrovně.

- **Oblečení**
  - **Pánské**
    - Trička
    - Tílka
    - Mikiny
  - **Dámské**
    - Trička
    - Tílka
    - Mikiny
    - Sukně
  - **Dětské**
    - Body
    - Trička
    - Mikiny
- **Doplňky**
  - **Čepice a šátky**
    - Zimní čepice
    - Kšiltovky
    - Šátky
  - **Šperky**
    - Prsteny
    - Náušnice
    - Náhrdelníky
    - Náramky
  - **Tašky a peněženky**
    - Plátěné tašky
    - Vaky a batohy
    - Kapsy na pásek
    - Peněženky
  - **Ostatní doplňky**
    - Pásky a řetězy
    - Brýle
    - Obojky a kravaty
    - Hroty, pyramidy a cvoky
    - Hrnky
    - Vlajky
    - Otvíráky
    - Samolepky
    - Knihy a fanziny
  - **Nášivky**
    - Klasické
    - Nažehlovací
    - Zádové
  - **Placky & piny**
    - Placky
    - Piny
    - Špendlíky
- **Merche**
  - Kapely
  - Trička
  - Mikiny
  - Tašky
  - Samolepky
- **Hudba**
  - CD
  - Žánr jako filtr: Punk, Hardcore, Oi!, Ska
- **Výprodej**
  - Oblečení
  - Doplňky
  - Nášivky
  - Placky a piny
  - Hudba
- **Custom** *(informační stránka, ne produktová kategorie)*
  - Ceník potisku
  - Jak objednat
  - Příprava dat
  - Termíny
  - Kontakt

## Datový model a routing

- `catalog-data.js` je jediný zdroj pravdy pro navigaci, karty a typ cílové stránky.
- `kind: "catalog"` používá trasu `#/category/{slug}` a vykreslí rozcestník produktového feedu.
- `kind: "information"` používá trasu `#/info/custom-print` a vykreslí informační stránku s orientačním ceníkem bez feedu.
- Barevné téma zůstává v URL query parametru: `?theme=gray` a `?theme=red`; hash route a theme parametr lze kombinovat.

### Fasety místo dalších větví

Použít: typ produktu, pro koho, velikost, barva, motiv/žánr, způsob aplikace nášivky, zapínání mikiny, cena, dostupnost a sleva. Tím se zabrání návratu původního duplicitního stromu.

## Homepage wireframe

Rozložení zachovává logiku IQIT Demo 15, ale obsah je zredukovaný na navigaci a směrování do šesti kategorií.

| Pořadí | Blok | Typ | Obraz / formát | Akce |
|---:|---|---|---|---|
| 1 | Brand header | Transparentní obrazová značka, vyhledávání, utility ikony | Bílý kruh s červeným ptákem, SVG | Hledat, přihlášení, košík |
| 2 | Hlavní navigace | Kompaktní centrované uppercase menu | Bez obrazu | 6 hlavních kategorií; okamžitá 5px červená underline |
| 3 | USP banner | 3sloupcový informační box | Vlastní linocut/stencil SVG ikony | Bez primární akce |
| 4 | Hero | Dokumentární DIY banner | Desktop 16:7, mobil 4:5 | Podívat se do obchodu |
| 5 | Kategorie | 6 boxů v mřížce 3 × 2 | Stencil/linocut SVG ikona 16:9 | Kategorie a podkategorie |
| 6 | Footer | Kompaktní 4sloupcový box | Textová značka | Servisní a kontaktní odkazy |

## Art direction

- Hero: fiktivní černobílý punkový dav, tvrdý blesk, silné zrno, xerox a halftone textura. Každé jasně viditelné oči zakrývá neprůhledný černý pruh. Radiální CSS maska organicky rozpouští všechny okraje fotografie do canvasu.
- Hero zůstává bez klišé ilustrací a izolovaných produktových fotografií.
- Pozadí mezer: šest samostatných ilustrací kombinuje čtyři unikátní skull varianty a dvě unikátní havraní siluety. Mají různé velikosti, rotace a horizontální posuny, jsou ukotvené v dokumentu a scrollují spolu se stránkou při opacity 5,2 %.
- Kategorie: šest samostatných jednoobjektových SVG symbolů s deformací `feTurbulence`. Oblečení, Hudba a Výprodej zachovávají beze změny původní geometrii trička, vinylu a cenovky; Doplňky, Merche a Custom používají novou geometrii pásku, lebky s čírem a spreje. Všechny objekty jsou rovně a opticky centrované. Barvy používají pouze `--svg-base`, `--svg-accent` a `--svg-ink`, napojené na aktivní theme tokeny.
- Sekce Kategorie obsahuje pouze hlavní nadpis a šest karet; nemá eyebrow ani vysvětlující podnadpis.
- USP: tři čistě bílé linocut ikony pro dopravu, rychlé odeslání a vrácení zboží, bez barevných či černých detailů.
- Boxy: `#101113`, zvýšená plocha `#17181B`, canvas `#070809`, akcent `#D3212C`.
- Kontejner: 1240 px; mezery mezi bloky a kartami 32 px (`gap-8`).
- Písmo: Oswald pro display text, Inter pro rozhraní.

## Responzivní chování

- Desktop: kategorie 3 × 2, hero 16:7, horizontální navigace.
- Tablet: kategorie 2 × 3, navigace se sbalí pod hamburger.
- Mobil: kategorie 1 × 6, hero 4:5, dav se ořízne doprava a text zůstane v tmavší levé ploše.
- Jednotlivé background ilustrace se na mobilu zmenší, přesunou do rozdílných míst dokumentu a zeslabí z 5,2 % na 4 %.
- Mobilní navigace používá plnou červenou active/focus plochu místo hover underline.

## Implementace v IQIT

Homepage používá pouze moduly potřebné pro tuto skladbu: `iqitmegamenu`, Elementor hero/banner, icon boxes, homepage category image boxes a footer link manager. Kategorie používají místo fotografií vložené SVG ikony.
