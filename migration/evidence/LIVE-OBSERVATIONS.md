# Veřejné pozorování, 5. 10. 2026

Přímé prohlížení proběhlo v nově otevřené relaci bez přihlášení. Byly čteny stránky a DOM; nevznikla testovací objednávka ani platba. Níže jsou stručné pozorované výsledky, nikoli záruka kompletního crawlingu.

| Stránka | Pozorování |
|---|---|
| https://kafka.monster/ | Přesměrování na `/cs/`. |
| https://kafka.monster/cs/ | Veřejný storefront, současné produkty včetně produktu 3501. 13 hlavních kategoriálních větví. Pobídka zdarma od 1 699 Kč. |
| https://kafka.monster/cs/content/1-dodani | Text počítá s odesláním do čtyř pracovních dnů; převod může dobu prodloužit. |
| https://kafka.monster/cs/content/9-platba | Comgate, převod, dobírka +30 Kč; možnosti platby na pobočce. |
| https://kafka.monster/cs/content/10-jak-objednat-potisk | Domluva motivu e-mailem/telefonem, vytvoření produktu zakázky a běžná objednávka. |
| https://kafka.monster/cs/detska-tricka/3454-tricko-punk-is-freedom-rock-shop.html | Cena defaultu 259 Kč; 2 barvy, Druh = Dětské, 7 dětských velikostí. Default má 12 ks. Dvě fotografie 6494/6495. |
| https://kafka.monster/cs/cd/3415-cd-spuntqane-posledni-singly.html | Cena 99 Kč, 1 ks, fyzické CD, bez variant. |
| https://kafka.monster/cs/25-zakazky | Jeden zveřejněný produkt v kategorii. |
| https://kafka.monster/robots.txt | Prohlížení nebylo dostupné: browser uvedl blocked-by-client; webový reader také nedodal obsah. Stav souboru na serveru neznámý. |

Na homepage byly pozorovány skripty `default-bootstrap`, `shaim_cookies_eu`, `blockcart`, `mergado`, `packetery` a Packeta widget v6. Nejde o kompletní inventuru všech serverových integrací. Na homepage nebyl nalezen `link[hreflang]`; na vzorkovaném PDP nebyl nalezen canonical link ani JSON-LD script. Výsledek nepopírá případná microdata ani jiné formáty.

Výsledky webového vyhledávače homepage obsahovaly starší produkty, než přímo otevřená stránka. Pro aktuální nákupní pravidla a ukázky byly použity přímé stránky a SQL, nikoli starší cache vyhledávání.
