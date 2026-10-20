# Fresh local SEO acceptance — 2026-10-19T10:09:52.360934+00:00

**FAIL**

Target: http://localhost:8083. Production requests: 0. Fresh HTTP records: 1748. Baseline legacy URLs: 888.

Routing regressions: 0. Missing/inactive content URLs: 44 (separate from routing). Actionable failures: 97.

Catalog metadata preserved: True. Sitemap: 9792 eligible URLs, 6 files, languages cs, sk, de.

## Actionable failures

- single_permanent_normalization: {"url": "http://localhost:8083/index.php?controller=product&id_product=3&id_lang=3", "expected": "http://localhost:8083/sk/damska-tricka/3-jing-jang.html", "chain": [{"url": "http://localhost:8083/index.php?controller=product&id_product=3&id_lang=3", "status": 301, "location": "http://localhost:8083/cs/damska-tricka/3-jing-jang-punk-rock-shop.html"}, {"url": "http://localhost:8083/cs/damska-tricka/3-jing-jang-punk-rock-shop.html", "status": 200, "location": ""}]}
- single_permanent_normalization: {"url": "http://localhost:8083/index.php?controller=product&id_product=3&id_lang=4", "expected": "http://localhost:8083/de/damska-tricka/3-jing-jang-punk-rock-shop.html", "chain": [{"url": "http://localhost:8083/index.php?controller=product&id_product=3&id_lang=4", "status": 301, "location": "http://localhost:8083/cs/damska-tricka/3-jing-jang-punk-rock-shop.html"}, {"url": "http://localhost:8083/cs/damska-tricka/3-jing-jang-punk-rock-shop.html", "status": 200, "location": ""}]}
- brand_title_once_and_og: {"url": "http://localhost:8083/1099-pnd-4.html", "title": "Nášivka PND 4 - Kafka Monster", "og": "Nášivka  PND 4 - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/1197-black-metal.html", "title": "Nášivka Black Metal - Kafka Monster", "og": "Nášivka  Black Metal - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/1358-hvezda-pulena.html", "title": "Nášivka Hvězda 2 - Kafka Monster", "og": "Nášivka  Hvězda 2 - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/1429-metallica-kompletni-pribeh.html", "title": "Kniha Metallica – kompletní příběh - Kafka Monster", "og": "Kniha  Metallica – kompletní příběh - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/1432-visaci-zamek.html", "title": "Kniha Visací zámek - Kafka Monster", "og": "Kniha  Visací zámek - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/15-placka-37-mm.html", "title": "Výroba Placka 37 mm se špendlíkem - Kafka Monster", "og": "Výroba  Placka 37 mm se špendlíkem - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/1509-beerman.html", "title": "Plátěná taška Beerman - Kafka Monster", "og": "Plátěná taška  Beerman - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/171-smajl-kurak.html", "title": "Tričko Smajl kuřák - Kafka Monster", "og": "Tričko  Smajl kuřák - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/1783-i-love-ska.html", "title": "Mikina zip I Love SKA - Kafka Monster", "og": "Mikina zip  I Love SKA - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/1837-pruser.html", "title": "Tričko Průser - Kafka Monster", "og": "Tričko  Průser - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/1846-ryba.html", "title": "Tričko Ryba - Kafka Monster", "og": "Tričko  Ryba - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/1851-toxic.html", "title": "Tričko Toxic - Kafka Monster", "og": "Tričko  Toxic - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/1859-svatba-game-over.html", "title": "Tričko Svatba Game Over - Kafka Monster", "og": "Tričko  Svatba Game Over - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/196-pavouk.html", "title": "Tričko Pavouk - Kafka Monster", "og": "Tričko  Pavouk - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/2786-zadova-nasivka-death-metal.html", "title": "Zádová nášivka Death Metal - Kafka Monster", "og": "Zádová nášivka  Death Metal - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/2841-platena-taska-old-punks-never-die-2.html", "title": "Plátěná taška Old Punks Never Die 2 - Kafka Monster", "og": "Plátěná taška  Old Punks Never Die 2 - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/3159-zadova-nasivka-old-school-hardcore.html", "title": "Zádová nášivka Old School Hardcore - Kafka Monster", "og": "Zádová nášivka  Old School Hardcore - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/3429-zadova-nasivka-gegen-nazis-punk-rock-shop.html", "title": "Zádová nášivka Gegen Nazis - Kafka Monster", "og": "Zádová nášivka  Gegen Nazis - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/3472-nasivka-stop-the-violence.html", "title": "Nášivka Stop The Violence - Kafka Monster", "og": "Nášivka  Stop The Violence - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/3497-nahrdelnik-3-hvezdy.html", "title": "Náhrdelník Hvězdy - Kafka Monster", "og": "Náhrdelník  Hvězdy - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/405-fanynka.html", "title": "Kšiltovka Fanynka - Kafka Monster", "og": "Kšiltovka  Fanynka - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/414-plesnivy-fazole.html", "title": "Kšiltovka Plesnivý Fazole - Kafka Monster", "og": "Kšiltovka  Plesnivý Fazole - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/63-vyroba-naramku-s-vlastnim-motivem", "title": "Výroba náramků s vlastním motivem - Kafka Monster", "og": "Výroba  náramků s vlastním motivem - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/644-canabis.html", "title": "Otvírák Canabis - Kafka Monster", "og": "Otvírák  Canabis - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/695-skinhead.html", "title": "Otvírák Skinhead - Kafka Monster", "og": "Otvírák  Skinhead - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/735-placka-58-mm-s-magnetem.html", "title": "Placka 58 mm s magnetem - Kafka Monster", "og": "Placka 58  mm s magnetem - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/63-cenik-vyroby-naramku-s-vlastnim-motivem", "title": "Výroba náramků s vlastním motivem - Kafka Monster", "og": "Výroba  náramků s vlastním motivem - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/63-vyroba-naramku-s-vlastnim-motivem", "title": "Výroba náramků s vlastním motivem - Kafka Monster", "og": "Výroba  náramků s vlastním motivem - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/black-friday/171-smajl-kurak.html", "title": "Tričko Smajl kuřák - Kafka Monster", "og": "Tričko  Smajl kuřák - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/black-friday/1846-ryba.html", "title": "Tričko Ryba - Kafka Monster", "og": "Tričko  Ryba - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/black-friday/1851-toxic.html", "title": "Tričko Toxic - Kafka Monster", "og": "Tričko  Toxic - Kafka Monster"}
- one_local_canonical: "http://localhost:8083/cs/cart"
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/cenik-vlastni-potisk/735-placka-58-mm-s-magnetem.html", "title": "Placka 58 mm s magnetem - Kafka Monster", "og": "Placka 58  mm s magnetem - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/cenik-vyroby-placek/15-placka-37-mm.html", "title": "Výroba Placka 37 mm se špendlíkem - Kafka Monster", "og": "Výroba  Placka 37 mm se špendlíkem - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/damska-tricka/1837-pruser.html", "title": "Tričko Průser - Kafka Monster", "og": "Tričko  Průser - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/damska-tricka/1846-ryba.html", "title": "Tričko Ryba - Kafka Monster", "og": "Tričko  Ryba - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/damska-tricka/1851-toxic.html", "title": "Tričko Toxic - Kafka Monster", "og": "Tričko  Toxic - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/damska-tricka/1859-svatba-game-over.html", "title": "Tričko Svatba Game Over - Kafka Monster", "og": "Tričko  Svatba Game Over - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/klasicke/1099-pnd-4.html", "title": "Nášivka PND 4 - Kafka Monster", "og": "Nášivka  PND 4 - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/klasicke/1197-black-metal.html", "title": "Nášivka Black Metal - Kafka Monster", "og": "Nášivka  Black Metal - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/klasicke/1358-hvezda-pulena.html", "title": "Nášivka Hvězda 2 - Kafka Monster", "og": "Nášivka  Hvězda 2 - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/klasicke/3472-nasivka-stop-the-violence.html", "title": "Nášivka Stop The Violence - Kafka Monster", "og": "Nášivka  Stop The Violence - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/knihyfanziny/1429-metallica-kompletni-pribeh.html", "title": "Kniha Metallica – kompletní příběh - Kafka Monster", "og": "Kniha  Metallica – kompletní příběh - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/knihyfanziny/1432-visaci-zamek.html", "title": "Kniha Visací zámek - Kafka Monster", "og": "Kniha  Visací zámek - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/ksiltovky/405-fanynka.html", "title": "Kšiltovka Fanynka - Kafka Monster", "og": "Kšiltovka  Fanynka - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/ksiltovky/414-plesnivy-fazole.html", "title": "Kšiltovka Plesnivý Fazole - Kafka Monster", "og": "Kšiltovka  Plesnivý Fazole - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/mikiny-na-zip-panske/1783-i-love-ska.html", "title": "Mikina zip I Love SKA - Kafka Monster", "og": "Mikina zip  I Love SKA - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/nahrdelniky/3497-nahrdelnik-3-hvezdy.html", "title": "Náhrdelník Hvězdy - Kafka Monster", "og": "Náhrdelník  Hvězdy - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/otviraky/644-canabis.html", "title": "Otvírák Canabis - Kafka Monster", "og": "Otvírák  Canabis - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/otviraky/695-skinhead.html", "title": "Otvírák Skinhead - Kafka Monster", "og": "Otvírák  Skinhead - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/panska-tricka/171-smajl-kurak.html", "title": "Tričko Smajl kuřák - Kafka Monster", "og": "Tričko  Smajl kuřák - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/panska-tricka/196-pavouk.html", "title": "Tričko Pavouk - Kafka Monster", "og": "Tričko  Pavouk - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/platene-tasky/2841-platena-taska-old-punks-never-die-2.html", "title": "Plátěná taška Old Punks Never Die 2 - Kafka Monster", "og": "Plátěná taška  Old Punks Never Die 2 - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/taskypenezenky/1509-beerman.html", "title": "Plátěná taška Beerman - Kafka Monster", "og": "Plátěná taška  Beerman - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/vlastni-potisk-cenik/735-placka-58-mm-s-magnetem.html", "title": "Placka 58 mm s magnetem - Kafka Monster", "og": "Placka 58  mm s magnetem - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/vyroba-placek-vlastni-motiv/15-placka-37-mm.html", "title": "Výroba Placka 37 mm se špendlíkem - Kafka Monster", "og": "Výroba  Placka 37 mm se špendlíkem - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/zadova-nasivka/2786-zadova-nasivka-death-metal.html", "title": "Zádová nášivka Death Metal - Kafka Monster", "og": "Zádová nášivka  Death Metal - Kafka Monster"}
- brand_title_once_and_og: {"url": "http://localhost:8083/cs/zadova-nasivka/3159-zadova-nasivka-old-school-hardcore.html", "title": "Zádová nášivka Old School Hardcore - Kafka Monster", "og": "Zádová nášivka  Old School Hardcore - Kafka Monster"}

Full failures: `failures.csv`. Missing data: `missing-content.csv`. Redirects: `legacy-url-results.csv`. Fresh HTTP and headers: `fresh-http.jsonl`.

Historical source HTTP was used only to classify the preserved baseline. All target evidence in this folder was fetched during this run; no earlier target HTTP cache was read.

Sitemap membership and alternate links are checked for every XML entry against existing eligible local records. HTTP coverage is the baseline plus normalization, language and pagination probes; it is not every catalog URL.

No new SQL, content, translations, media, order or production change is part of this audit. Local noindex protection must remain enabled. Production cutover and Search Console require separate authorization.
