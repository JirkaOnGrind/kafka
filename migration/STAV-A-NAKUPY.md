# Stav a nákupní rozhodnutí — 7. 10. 2026

> **Platné rozhodnutí po posledním zadání 10. 10.: nákup PrestaHero MigrationPro Ultimate doporučen; cíl PS 9.1.5, Hummingbird 2.0, oddělené prostředí WEDOS.** Přesnou 9.2 již nevyžadujeme. Řídíme se [REALIZACNI-PLAN-PS9.md](REALIZACNI-PLAN-PS9.md); níže uvedené starší podmínky nákupu a volba Addons 8934 jsou překonané. Licence zatím není koupená a pilot není provedený.

> Aktualizace 10. 10.: uživatel vybral **MigrationPro na Addons, ID 8934**, nikoli dříve srovnávaný PrestaHero Ultimate. Jeho cenu ani licenci nelze převzít z PrestaHero. Aktuální nákupní doporučení, dotaz správnému dodavateli a porovnání šablon jsou v [DALSI-KROKY-10-10.md](DALSI-KROKY-10-10.md). Následující text zachovává starší rozhodování.

## Potvrzeno uživatelem

- Migrátor není koupený. Uživatel chce technické doporučení před fakturací klientovi a nákupem.
- Informace o runtime nebyly nalezeny v administraci PrestaShopu; PHP webu zůstává neověřené.
- Obchod zůstává u WEDOS/ VEDOS. Uživatel má přístup do správy; odtud pocházejí dodané SQL exporty. Tarif není známý.
- Warehouse ani jiná placená šablona nejsou koupené.

## Doporučení

**Warehouse pro nynější rozsah nekupovat.** Schválený návrh je vlastní HTML/CSS/JS frontend a vyžaduje převod do nativní šablony. Doporučený základ je Hummingbird 2.1.x pro PS 9.2. Licence šablony neznamená nulové náklady na vývoj: napojení návrhu, katalogu a nákupu stále vyžaduje práci. Warehouse lze znovu posoudit, pokud se objeví konkrétní potřeba jeho editoru nebo modulů; nyní není prokázané, že by celkovou práci zkrátil.

[Oficiální Hummingbird a licence](https://github.com/PrestaShop/hummingbird/), [vydání a kompatibilita 2.1.x s PS 9.2](https://github.com/PrestaShop/hummingbird/releases).

**Doporučený kandidát na migrátor: PrestaHero MigrationPro Ultimate.** Není tím určeno, že jde o stejný produkt, který uživatel původně našel pod názvem Migration Pro. Stránka dne 7. 10. 2026 uvádí verzi 5.0.1, podporu rodiny PS 1.5–9.x a cenu 139,99 EUR. Jde o zobrazenou cenu; konečnou částku, daňový režim a kurz je nutné ověřit v objednávce. Rozpočet na tuto položku lze připravit, ale nákup doporučuji až po odpovědi dodavatele k přesné verzi 9.2.0, zdrojovému PHP, synchronizaci a stagingové licenci. Pilot zatím nebyl proveden.

[Produkt, cena, changelog a licence na jednu doménu](https://prestahero.com/149-prestashop-better-upgrade-and-migrate-tool.html).

## Nejbližší podklady od uživatele

1. Screenshot detailu konkrétního WEDOS webhostingu: tarif a parametry. Druhý screenshot obrazovky Konfigurace PHP: aktuální verze, memory_limit, max_execution_time. Pouze čtení, bez ukládání změn. Kontakty a citlivé údaje zakrýt.
2. Úplná souborová záloha webu z hostingu nebo FTP. Uložit do nové lokální složky a sdělit cestu. Zachovat strukturu, včetně img, modules, themes, override, download, upload a skrytých souborů .htaccess. Obsah může zahrnovat citlivé konfigurační soubory; neposílat jejich obsah do chatu. SQL už máme; před pilotem podle stáří doplnit čerstvý export hlavní databáze, nikoli information_schema.
3. Písemná odpověď dodavatele migrátoru na dotaz níže. Tento dotaz nebyl odeslán; nákup nebyl proveden.

PHP lze zkontrolovat ve WEDOS zákaznické administraci přes Hostingové služby → Webhosting (případně WMS podle produktu) → příslušná služba → Konfigurace PHP. [Aktuální návod VEDOS](https://kb.vedos.cz/en/webhosting-php/).

U standardního sdíleného webhostingu podpora uvádí společné nastavení PHP pro celý hosting. Nepředpokládat, že nova.kafka.monster na témže hostingu umožní jiné PHP. Pro dvě odlišná prostředí preferovat samostatnou hostingovou službu u stejného poskytovatele, nebo izolaci potvrzenou pro konkrétní tarif. Dočasná vývojová subdoména ještě neurčuje místo finálního provozu. [Vyjádření podpory k nastavení PHP](https://help.wedos.cz/otazka/muze-byt-na-subdomene-provozovana-jina-verze-php/94969/answer/94971/).

## Dotaz dodavateli před nákupem

> Dobrý den, zvažuji MigrationPro Ultimate pro přenos obchodu z PrestaShopu 1.6.1.13 do čistého PrestaShopu 9.2.0 na WEDOS/ VEDOS. Prosím potvrďte podporu přesné verze 9.2.0 v aktuálním vydání, minimální PHP zdrojového konektoru a podporované PHP cíle. Potřebujeme zachovat ID a URL, hesla zákazníků, kombinace, cenové příplatky, sklad, slevy a historii objednávek včetně faktur a plateb. Umožní konečná synchronizace přenos nových i změněných objednávek a skladů bez přepsání nové šablony a nově doplněných kategorií či vlastností? Lze jednu licenci použít při vývoji na nova.kafka.monster a následně na kafka.monster, a jak se tento přechod provádí? Prosím také o konečnou cenu a podmínky řešení případné nekompatibility.

Pro detailnější rozsah je připravený delší dotaz v OTAZKY-NA-RANO.md. Po obdržení skutečného PHP lze doplnit přesné runtime do dotazu.

## Co následuje

- Zkontrolovat souborovou zálohu, runtime, kapacitu a dostupné PHP rozšíření.
- Vybrat oddělené prostředí pro cílový PS 9.2; žádná změna PHP starého shopu během přípravy.
- Po potvrzení migrátoru koupit vhodnou licenci a provést pilot; moduly plateb/dopravy nacenit podle zvolené kompatibilní sestavy.
- Převést schválený frontend do nativní šablony nad ověřenými migrovanými daty.

Produkční zásahy ani instalace zatím neproběhly. Původní report ze dne 5. 10. je historický audit; tento soubor doplňuje nová potvrzení a aktuální nákupní doporučení.

## Doplnění platebních nákupů — 9. 10. 2026, 01:08 Europe/Prague

FTP potvrdilo autora dm_comgate a dm_cashondelivery: David Mrózek / prestashop.valasinec.cz. První kandidát pro nejlehčí přechod je kompatibilní aktuální balíček stejného autora. Nejprve zjistit klientův účet/licenci a možnost aktualizace; novou licenci automaticky nenaceňovat. Výrobce uvádí řadu PS 9, přesná 9.2.0 a vlastní checkout ale vyžadují potvrzení a pilot. Zkopírování starého Comgate 3.3.2 není schváleno jako cílové řešení: zdroj deklaruje PS 1.6 a má vypnuté TLS ověření. Nové sestavě ověřit TLS i idempotenci callbacků.

Dobírka v SQL má režim carrier, zapnuté zaokrouhlování a aktivní CZ sazby 49 Kč, hranici zdarma 3 000 Kč a horní limit 3 500 Kč. Veřejný text 30 Kč je s tím v rozporu; ceny vyžadují obchodní rozhodnutí klienta. Nejde o konečnou cenu při všech kombinacích měny/dopravce. Podrobné doklady, výjimky a cílové testy jsou v PLATBY-A-MAILY-FTP.md. Warehouse a migrátor mají stejné předchozí nákupní podmínky. Nic nebylo zakoupeno či odesláno dodavateli.

## Doplnění dopravy a feedů — 9. 10. 2026, 01:14 Europe/Prague

Packeta používá dostupný CSV export a vlastní záznamy výdejních míst/expedice. Cílem je současný oficiální modul po ověření přesné sestavy, včetně klientova skutečného způsobu expedice. Mergado Pack má dostupný bezplatný aktuální balíček pro rodinu PS 9; nemusíme automaticky kupovat nový placený exportní modul. Propojená placená služba a její klientův účet jsou samostatná otázka. Starý Mergado cenový import nekopírovat: kromě vazeb produktových/variantních IDs vypíná TLS ověřování. Podrobný rozbor a podmínky jsou v DOPRAVA-FEEDY-FTP.md. Kořenové overrides, DPD/antispam, theme a fakturační šablony stále čekají; finální soupis nákupů zatím není uzavřený.

### Uzavření ruční analýzy dostupného kódu — 9. 10. 2026

Kořenové overrides, DPD, antispam, theme a PDF jsou nyní posouzené; jejich dřívější čekání na FTP v historických odstavcích je vyřešené. Závěrečná nákupní tabulka je v ZAVER-A-POSTUP.md. Warehouse pro nynější rozsah **nekupovat**. MigrationPro je vhodný datový nástroj, ale nákup stále podmínit přesnou identitou produktu a odpovědí na 1.6.1.13 → 9.2.0, PHP, staging licenci, ID/hesla/historii a finální synchronizaci nových i změněných dat. Pilot nebyl proveden.

Kromě Valašince nejprve ověřit klientův nárok na aktualizace Shaim pro DPD, reCAPTCHA a cookies. Vybrat jednu potřebnou CMP; neobjednávat automaticky dva překrývající se moduly. Packeta a Mergado použít v současných podporovaných vydáních; Mergado Pack je bezplatný modul, účet/služba jsou samostatný náklad. Stará code metadata podpory řady PS 9 nenahrazují test přesné sestavy 9.2 s vlastním frontendem.

Aktuální potřebné odpovědi jsou na začátku OTAZKY-NA-RANO.md. Hosting zůstává WEDOS podle uživatele, migrátor ani Warehouse nejsou zakoupené; toto už není třeba znovu zjišťovat. Zdrojová verze je potvrzená 1.6.1.13, PHP webu zůstává provozní otázka. Nejprve získat podklady a konkrétní ceny, potom uživatel schválí fakturaci a koupí licence. Žádné nákupy či externí zprávy v analýze neproběhly.
