# První vizuální převod Kafka — 12. 10. 2026

Skutečný obchod na portu 8080 má vlastní vizuální vrstvu podle původních HTML/CSS stránek. Předloha na portu 4173 zůstala zachovaná. Úpravy jsou v `prestashop/themes/kafka/` a vlastním modulu `prestashop/modules/kafkahome/`; rodičovský Hummingbird se neměnil.

Poslední rozhodnutí klienta: **veřejný obchod je pouze černý**. Barevný přepínač byl pouze pro klientskou předlohu. Na homepage nejsou generické produktové sekce Doporučené / Nejnovější / Speciální.

## Připraveno

- Pevné černé pozadí `#070809`, růžové akcenty, Inter, Oswald a Black Ops One. Staré uložené barevné volby ani parametr `theme` barvu nezmění.
- Původní logo a textury, středový wordmark, mobilní hlavička a hledání s ovládáním klávesnicí.
- Šest vlastních kategoriálních karet se stejnými SVG symboly jako v předloze: tričko, vinyl, cenovka, pásek, lebka a sprej. Stejné pořadí, podkategorie a rozbalování; desktop více otevřených karet, mobil jedna otevřená karta.
- Řádek se třemi původními ikonami dopravy, odeslání a vrácení. Částky a lhůty čekají na potvrzená obchodní pravidla.
- Vlastní servisní patička podle předlohy, bez výchozího newsletteru a generického produktového sloupce.
- Původní hero obrázek a slogan, tmavé panely, hranatá tlačítka, přerušované rámečky produktových karet.
- Vizuální základ kategorií, filtrů, detailu, košíku, formulářů a patičky.
- Navigace nad skutečnými kategoriemi, nativní vyhledávání a mobilní menu. Účet a košík zachovávají původní nativní ovládání; prázdný košík je také kliknutelný.
- Opravené chybné cesty `//modules/...` u výchozích ikon blockreassurance pouze v odvozených šablonách; modul ani jeho data se neměnily.

## Co zůstává datové

Produkty, ceny, sklad, varianty, obrázky, kategorie, jazyky, měny i jejich odkazy poskytuje PrestaShop. V šabloně nejsou pevná produktová ani kategoriální ID. Bylo ověřeno 19 demonstračních produktů a 39 kombinací; skutečná migrace zatím neproběhla.

Homepage vykresluje vlastní hero a šest kategoriálních karet místo obecného `displayHome`. Data starter modulů ani produktů se nemažou. Vlastní modul `kafkahome` dodává také navigaci, servisní ikony a patičku.

Názvy, původní SVG a pořadí karet vycházejí přímo z předlohy. Definice a aliasy jsou v `prestashop/modules/kafkahome/data/sections.json`. Modul hledá existující přístupné aktivní kategorie podle názvů v aktuální a české lokalizaci, takže se neopírá o neověřená číselná ID starého shopu. Po importu se odkazy přepočítají z nových dat. Pokud odpovídající kategorie dosud neexistuje, odkaz vede do vlastní lokální sekce s podkategoriemi nebo prázdným stavem. Servisní odkazy obdobně hledají skutečné CMS stránky; chybějící obsah má lokální kontaktní stránku. Finální mapování i obsah zkontrolovat po migraci.

## Rozdíly oproti předloze a další práce

| Předloha | Současný nativní obchod | Následující krok |
| --- | --- | --- |
| Šest hlavních vstupů a ilustrované kategoriální karty | Stejné karty, ikony, podkategorie a šest odkazů v menu; aktuálně se napojují na demo kategorie nebo lokální prázdné sekce | Ověřit aliasy a všechny cílové odkazy nad importovaným katalogem |
| Fotky Kafka a tričko Punk is freedom | Demo fotografie PrestaShopu, často s bílým pozadím | Po importu porovnat skutečné fotografie, poměry stran a variantní vazby |
| Detail s konkrétními rozměry a variantními tlačítky | Tmavé rozložení nad nativním detailem, výběr velikostí zůstává nativní | Doladit přesné řazení a ovladače na reprezentativních produktech, včetně vyprodaných variant |
| Doprava zdarma, doba odeslání a další obchodní sliby | Nejsou pevně vepsané nové cenové ani dopravní podmínky | Doplnit potvrzené podmínky klienta a jejich skutečná pravidla |
| Statické servisní a sociální odkazy | Vlastní patička podle předlohy s nativními CMS, kontaktními a prodejními odkazy, jazykovými a měnovými volbami | Prověřit importované CMS a doplnit skutečné sociální profily |

## Ověření

Pozadí používá šest samostatných obrázků v absolutně umístěné vrstvě přes délku dokumentu, se stejnými pozicemi, velikostmi a natočením jako předloha. Vrstva je oříznutá na stránku, nevytváří horizontální scroll ani prázdnou délku stránky. Motivy nejsou připnuté k oknu. Při ověření na šířkách 1536 a 390 px se obrázek při scrollu o 600 px posunul přesně o 600 px; všech šest obrázků se načetlo bez chyby.

Chrome přes bundled Playwright; Browser plugin v relaci není dostupný. Homepage a detail byly vykreslené při 1536 × 1024 a 390 × 844 bez prázdné stránky, chybového překryvu, chyb JavaScriptu, chyb v konzoli a chyb načítání lokálních assetů. Homepage nepřetéká při šířkách 320, 390, 768, 1024 a 1536 px.

Prošly interakce: pevná černá i při uložené červené volbě a `?theme=red`; absence přepínačů, generických sekcí a newsletteru; všech šest hlavních odkazů; CMS/informační odkaz; nezávislé rozbalování více karet na desktopu a zavření klávesou Escape; jeden otevřený accordion na mobilu; mobilní menu se šesti odkazy; nativní vyhledávání a skutečný košík s AJAX počítadlem. Předchozí kontrola ověřila i změnu nativní varianty.

Toto není převzetí skutečného migrovaného katalogu ani ověření finálních plateb a dopravy. Kompletní vizuální shoda všech obrazovek zatím není dokončená.
