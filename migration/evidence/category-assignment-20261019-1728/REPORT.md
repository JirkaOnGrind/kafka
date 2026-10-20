# Přejímka produktových kategorií — 19. 10. 2026

**PASS.** 3 296 zachovaných produktových záznamů, 3 194 aktivních katalogových produktů, 0 nedosažitelných přes nový strom. Všech 55 klientských kategorií má obsah. Přidáno 613 vazeb; všech 4 331 původních vazeb a jejich pozic zůstalo zachovaných.

Čerstvě ověřeno 58 nativních kategoriálních výpisů včetně úplných historických společných kategorií a všech 438 aktivních produktů dotčených novým zařazením: celkem 496 HTTP odpovědí, žádné selhání. Počty skutečně vykreslených produktů odpovídají katalogu, výsledky neobsahují cizí produkty, canonical drží původní cesty a místní noindex zůstává aktivní.

Vůči zdrojovému katalogu porovnáno všech 9 888 produktových a 231 kategoriálních URL v aktivních jazycích: žádná změna cesty nebo porovnávaných SEO metadat. Sedm chráněných katalogových tabulek má stejné SHA-256 před a po úpravě.

- [Úplná analýza, příčina a vysvětlení rozdělení](../../CATEGORIE-ANALYZA-A-OPRAVA-2026-10-19.md)
- [Souhrn přejímky](summary.json)
- [Skutečné kategoriální počty](category-listings.csv)
- [Počty všech 55 kategorií před a po opravě](category-counts-before-after.csv)
- [Všech 613 přidaných produktových vazeb](added-product-links.csv)
- [Zachování původních URL a metadat](source-url-preservation.json)
- [87 kontrol kategorií v prohlížeči, desktop/mobil](browser-qa.json)
- [51 kontrol variant, původních URL a košíku](native-browser-qa.json)
- [Navazující úplná SEO přejímka — PASS, 1 755 nových HTTP odpovědí](../seo-20261019-172854-555/REPORT.md)

Samostatně zůstává 17 produktů a kategorie 127 z historické veřejné evidence mimo použitý SQL snapshot. Jejich 44 jazykových URL je zaznamenáno v [missing-content.csv](../seo-20261019-172854-555/missing-content.csv); nejde o nedokončené zařazení existujících produktů. Nevyrábějí se náhradní záznamy ani přesměrování na nesouvisející obsah. Produkce se neměnila. Docker volumes zůstaly zachované bez resetu či mazání.
