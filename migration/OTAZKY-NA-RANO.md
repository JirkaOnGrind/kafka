# Kafka Monster: otázky na ráno

> **Aktualizace po posledním zadání 10. 10.: migrátor je vybraný — PrestaHero MigrationPro Ultimate, nákup doporučen; cílem je PS 9.1.5.** Staré dotazy k Addons 8934 a přesné 9.2 již nejsou podmínkou pokračování. Teď stačí zakoupený balíček, WEDOS parametry a potvrzení zálohy podle [REALIZACNI-PLAN-PS9.md](REALIZACNI-PLAN-PS9.md). Obchodní pravidla vyřešíme před nastavením checkoutu; nemusí blokovat první import.

## Aktuální priority po dokončení FTP analýzy — 9. 10. 2026

Už víme: migrátor a Warehouse nejsou koupené, web zůstává u WEDOS a máš přístup; zdrojové PHP je v kopii a verze **1.6.1.13** je potvrzená. Autoři hlavních modulů i účel sedmi overrides jsou zjištěné. Tyto odpovědi nemusíš opakovat. Historický seznam níže zůstává pro podrobnosti, není seznam všech znovu potřebných odpovědí.

Začni těmito podklady (bez hesel, tokenů a zákaznických údajů):

1. **FTP:** je fronta dokončená a nejsou nevyřešené neúspěšné přenosy? Počet souborů sám nestačí. Je dostupný vzdálený soupis nebo hostingová záloha a možnost obnovy?
2. **WEDOS:** přesný tarif, současné PHP webu, memory_limit a možnost samostatného cílového PHP/hostingu. Stačí zakrytý screenshot; nic nepřepínej. Dále názvy a frekvence cronů bez URL s klíči.
3. **Migrátor — upřesnění 10. 10.:** přesný odkaz už máme, jde o Addons produkt **8934**. Dotaz jeho dodavateli je nyní v DALSI-KROKY-10-10.md: zbývá potvrzení 9.2, zdrojového/cílového PHP, staging licence, ID/hesel/dokladů a přenosu nových i změněných dat. Cenu PrestaHero nepřebírat. Nic jsem dodavateli neposlal.
4. **Licence:** klientův nárok na aktualizace Valašinec (Comgate/dobírka) a Shaim (DPD/reCAPTCHA/cookies). Stačí dostupné verze a potvrzení nároku, neposílat přihlášení.
5. **Obchodní pravidla:** závazná doprava zdarma 1 500 / 1 699 / 3 000 Kč, dobírka 30 / 49 Kč a limity, odeslání 48 hodin / čtyři pracovní dny, CZ/SK/EUR, DPH a vlastní potisk. Zdrojový SQL a texty si odporují.
6. **Provoz:** způsob expedice Packety/DPD, párování převodů, počet rozpracovaných plateb/zakázek/zásilek a případné účetnictví/sklad mimo shop. Stačí postupy a počty bez konkrétních zákazníků.
7. **Mergado a souhlasy:** používá se cenový import, které feedy/měření běží, která lišta je skutečně zapojená a kdo to spravuje? V dodaném SQL mají oba Shaim seznamy blokovaných modulů hodnotu serializované false; Usercentrics setting záznam se nenašel. Nejde o runtime důkaz chování celého webu.
8. **Pilot:** testovací zákaznický účet, jehož heslo znáš (heslo neposílat), člověk pro ověření CZK/EUR platby a expedice, termín a závazné funkce prvního spuštění. Je nutná fotografie podle barvy a nové filtry hned, nebo se mohou doplnit postupně?

**Nákupní závěr:** Warehouse nekupovat pro nynější rozsah. MigrationPro je vhodný kandidát až po uvedeném potvrzení. U provozních modulů nejprve aktualizace stávajících licencí. Nejlehčí postup a odhad jsou v [ZAVER-A-POSTUP.md](ZAVER-A-POSTUP.md).

Při mém čtení nestandardní konfigurace se jeden citlivý řádek chybně zobrazil ve výstupu nástroje. Filtr je opravený a údaj není v reportech. Doporučuji příslušný údaj změnit, bez zasílání jeho hodnoty. Nebyla testovaná platnost ani provedena produkční změna.

## Historický podrobný seznam otázek

Připraveno 5. 10. 2026. Odpovědi mohou být krátce pod čísly. Není potřeba posílat hesla, API klíče, zákaznické údaje ani celé citlivé konfigurační soubory. U obrázků administrace případné klíče a kontakty zakrýt.

## Nejdřív těchto 10 otázek

| # | Otázka | Kde / proč |
|---:|---|---|
| 1 | Jaký **přesný odkaz na MigrationPro** jsi našel a koupil jsi jej už? Pokud ano, jakou verzi a licenci? | Rozlišit Addons Migration PRO, PrestaHero Ultimate, ZM40 a jiná řešení. Podpora 9.x sama nepotvrzuje 9.2. |
| 2 | Kde má běžet nový shop: stávající WEDOS hosting, nový hosting, nebo VPS? Máš přístup k hostingu a DNS a lze pro nový web nastavit PHP samostatně? | Dvě aplikace vyžadují izolované prostředí; změna PHP nesmí zasáhnout starý shop. |
| 3 | Co ukazuje **Pokročilé parametry → Informace**: verze PrestaShopu, PHP, memory limit a webserver? Je k dispozici úplná souborová záloha a FTP/SFTP přístup? | DB hlásí 1.6.1.13; údaj PHP 5.4.23 je z phpMyAdminu. Potřebujeme runtime webu a obrázky/přílohy. |
| 4 | Je **Warehouse/IQIT** skutečně vybraný a licencovaný, nebo jde jen o inspirační základ návrhu? Pokud jej máš: přesná verze. Která barevná varianta je finálně schválená? | Volba mezi child theme Warehouse a úpravou Hummingbird. |
| 5 | Které platební metody se mají používat po spuštění: Comgate, převod, dobírka, hotovost na pobočce? Kdo dodal `dm_comgate` a `dm_cashondelivery`? Máš jejich aktuální podporu/licenci? | Nové balíčky musí podporovat 9.2; historie ukazuje hlavně tyto tři metody. |
| 6 | Jaké jsou **závazné dopravní podmínky**: zdarma od 1 500, 1 699, nebo 3 000 Kč; dobírka 30 Kč; odeslání 48 hodin, nebo čtyři pracovní dny? Platí stejné prahy pro CZ/SK/EUR? | Návrh, globální konfigurace a dopravní pásma se liší. Potvrdit i osobní odběr, DPD a Packetu. |
| 7 | Je klient neplátce DPH a mají zůstat daně vypnuté? Kdo řeší faktury/účetnictví a kam se objednávky exportují? | SQL má `PS_TAX=0`, produkty tax group 0; nesmíme změnit ceny a doklady automatickou lokalizací. |
| 8 | Má **vlastní potisk** zůstat jako nyní: domluva e-mailem/telefonem → vytvoření konkrétního produktu zakázky → zaslaný odkaz → běžná objednávka? Nebo je schválený i nový upload/kalkulátor? | Nový Custom nemá produktový feed, ale platba individuální zakázky musí fungovat. |
| 9 | Existuje jiné napojení než zjištěné moduly: účetní software, sklad, POS/prodejna, ruční CSV/XML, jiné marketplace, automatické úlohy nebo vlastní PHP endpointy? | SQL nedokáže odhalit skripty na FTP ani externí procesy. |
| 10 | Mají při spuštění zůstat **CS + SK + DE, CZK + EUR a doména kafka.monster**? Jaký je termín a kolik času může trvat odstávka nákupu? | Doporučuji zachování jazyků/URL; odstávku stanovíme po nácviku. |

## Další informace před pilotem

11. **Úpravy core:** Vzpomíná si klient/dodavatel na ruční zásahy do `classes/`, `controllers/` nebo `override/`? Na FTP stačí inventura názvů souborů, zatím nikoli jejich spuštění.

12. **Hesla účtů:** Chce klient zachovat přihlašování stávajícím heslem? Doporučuji ano. Je možné použít testovací účet, jehož heslo znáš, pro porovnání na starém i novém shopu? Hostů je 467 a registrovaných účtů 115; milion návštěvnických řádků nejsou účty.

13. **Historie:** Je správně, že export obsahuje objednávky až od 4. 6. 2023? Existuje ještě jiná starší databáze nebo archiv? Mám v novém shopu přenést všech 574 objednávek a 102 neaktivních produktů? Výchozí plán je přenést a zachovat stav.

14. **Otevřené objednávky:** Které objednávky ještě čekají na platbu, výrobu, expedici, vrácení nebo reklamaci? Může je klient dořešit na starém chráněném shopu, nebo musí být plně obsloužitelné z nové administrace včetně pobočky a transakční reference?

15. **Zásilkovna:** Používá klient oficiální `packetery` také k vytváření zásilek/štítků a automatické změně stavů, nebo pouze mapu? Je potřeba pokračovat v existujících rozpracovaných zásilkách? Má přístup do klientské sekce a zná označení odesílatele?

16. **DPD:** Je smlouva CZ, SK nebo obojí? Používá se jen výběr Pickup místa, nebo také API/CSV podání a tisk štítků? Aktuální zdroj má DPD Classic a dvě Pickup varianty.

17. **Comgate:** Které měny, země a metody má klient schválené v merchant účtu? Je dostupný testovací režim a někdo, kdo ověří callback URL/IP omezení? Mají se skutečně nabízet Apple Pay/Google Pay uvedené v návrhu? Klíče zatím neposílat.

18. **E-maily a faktury:** Používá web SMTP nebo PHP mail? Kdo dostává upozornění na objednávky, jaké PDF/šablony jsou upravené a jaké je pravidlo číslování nové první faktury? Zachovat branding a přílohy.

19. **Marketing:** Které služby se opravdu používají v Mergadu: Heureka, Zboží.cz, Google Merchant Center, Google Ads, GA4, Meta, jiné? Existuje Search Console přístup a aktuální sitemap/feedy? Jsou identifikátory produktů navázané na Presta ID nebo kombinace?

20. **Souhlasy:** Používá klient skutečně oba `usercentrics` a `shaim_cookies_eu`, nebo je jeden pozůstatek? Co je záměrně aktivní pro newsletter a marketing? Je k souhlasům dostupná historie/zdroj? Není potřeba ji posílat; nejprve ověřit existenci.

21. **Sklad:** Znamenají hodnoty „12 kusů“ u každé varianty trička skutečný fyzický sklad, nebo jen prodejní dostupnost výroby? Snižuje zásoby jiný systém/prodejna? Kdo je zdroj pravdy pro finální stock sync?

22. **Obrázky variant:** Má být změna fotografie podle barvy u všech produktů už při spuštění? Je nějaké pravidlo pořadí obrázků a někdo, kdo ověří přiřazení? V celé DB není jediná vazba obrázek–kombinace; výchozí plán je korektní galerie a doplňování prioritních produktů.

23. **Filtry:** Které filtry jsou nutné pro první spuštění? Existující data dovolují atributy, kategorii, cenu a dostupnost. Motiv/žánr/materiál/zapínání nejsou v `features`. Je klient ochoten je doplnit a kdo rozhodne hodnoty?

24. **Výprodej:** Má nový výpis zahrnovat současnou kurátorovanou kategorii 81 (56 vazeb), nebo všechny platné slevy? Jsou aktuální kupóny a jejich podmínky správné?

25. **Servisní obsah:** Jsou finálně schválené texty dopravy, reklamací, ochrany údajů, vrácení, velikostní tabulky a skutečný ceník vlastního potisku? Mají odkazy „Prodejna“, „FAQ“, YouTube a TikTok z návrhu reálný obsah?

26. **FTP a hostingové limity:** Kolik GB zabírají `img`, `download`, `upload` a celý web? Je k dispozici SSH/cron, čerstvé zálohy, vlastní SSL a samostatný staging? Nejsou v cestě WAF/Cloudflare omezení pro konektor a platební callbacky?

27. **Změny při vývoji:** Lze po počáteční migraci na krátkou dobu zmrazit změny kategorií a produktových textů na starém shopu? Objednávky mohou běžet dále; potřebujeme ale zdokumentovat změny skladu a stavů.

28. **Akceptace:** Kdo u klienta projde nákup CZK/EUR, výdejní místo, zakázku, fakturu a expedici a potvrdí, že může přijímat reálné objednávky? Je k dispozici člověk i při přepnutí a případném rollbacku?

## Připravený dotaz dodavateli migrátoru

Tento text není nikam odeslaný. Po výběru přesného produktu jej můžeš použít:

> Dobrý den, připravujeme migraci jednoho obchodu z PrestaShopu 1.6.1.13 do čisté instalace PrestaShopu 9.2.0. Máme 3 296 produktů, 11 786 kombinací, 4 962 obrázkových záznamů, 582 zákaznických řádků včetně hostů a 574 objednávek. Používáme CS/SK/DE a CZK/EUR, bez multistore a pokročilého skladu. Prosím potvrďte konkrétní vydání vašeho modulu a konektoru podporující tento přenos, včetně minimálního PHP zdroje a cíle. Potřebujeme zachovat ID produktů, kombinací, kategorií, zákazníků a objednávek, slugs/URL, hesla účtů, cenové příplatky, sklad, specific prices, kupóny, přílohy, faktury, platby, historii stavů a vrácení zboží. Jak řešíte konečnou synchronizaci nových i změněných záznamů, zejména skladu a stavů existujících objednávek, bez přepsání nově přidaných kategorií/vlastností a bez duplikací? Které entity tento režim smaže nebo přepíše? Umíte bezpečně přeskočit dvě osiřelé kombinace s parent product ID 0 a 152 skladových řádků po smazaných produktech, aniž by se poškodila historie objednávek? Je součástí přenosu tabulka `packetery_order`, `dm_comgate_payment` nebo `shaim_dpdparcelshop_data`, nebo je máme řešit samostatně? Jak funguje licence staging → produkce na stejné finální doméně, pilotní přenos, technická podpora a řešení nekompatibility s 9.2?

Po ověření runtime doplnit přesné PHP. Výše uvedené tabulky se ptáme především kvůli rozsahu podpory; automatický přenos mezi různými schématy nových modulů neočekáváme.

## Odpovědní formulář

```text
1. MigrationPro odkaz / koupeno / verze:
2. Cílový hosting / DNS / samostatné PHP:
3. Skutečná PS + PHP verze / záloha / FTP:
4. Warehouse licence a verze / barevná varianta:
5. Platby / dodavatel dm modulů:
6. Doprava zdarma / dobírka / odeslání / CZ-SK-EUR:
7. DPH / účetnictví:
8. Vlastní potisk:
9. Další integrace:
10. Jazyky / měny / doména / termín / odstávka:
```

## Nové prioritní otázky z FTP — 9. 10. 2026

- Má klient účet/licenci na prestashop.valasinec.cz k dm_comgate a dm_cashondelivery a nárok na aktuální balíčky? Přístupové údaje ani licenční klíče neposílat do odpovědi; stačí potvrzení a dostupné verze.
- Má být dobírkové opravdu 49 Kč podle aktivních CZ pravidel v SQL, nebo 30 Kč podle veřejného textu? Potvrdit i hranici zdarma 3 000 Kč, horní limit 3 500 Kč, další zóny a povolené dopravce. Podrobnosti PLATBY-A-MAILY-FTP.md.
- Jsou nyní otevřené Comgate platby a používá klient opakované odkazy z mailů? Stačí počet a popis postupu bez zákaznických kontaktů či tokenů. Upravuje po objednání dopravu/položky u dobírkových objednávek?

Podklady upozorňují na vypnuté TLS ověřování starého Comgate a riziko opožděného PAID callbacku pro již doručený stav. Nové řešení bude vyžadovat nápravu a ověření. Žádná produkční změna ani požadavek na bránu v rámci analýzy neproběhly.

### Packeta a Mergado — doplnění z 01:14

- Jak klient expeduje Packetu: CSV ze shopu nahrává ručně, nebo používá API/jinou službu? Potřebuje ručně zadávat váhu či měnit dobírku? Stačí popis a počet dosud neodeslaných objednávek, bez zákaznických údajů.
- Používá Mergado jen pro export feedů, nebo také import cen? Pokud importuje ceny, kdo spravuje externí XML a má zachovat produktová/variantní ID? Neposílat konkrétní tokenizované URL.
- Které služby jsou v Mergadu zapnuté: Heureka, Zboží, GA4, GTM, Google Ads, Facebook či další? Kdo má přehled o cookie liště a měření?
- V administraci WEDOSu zjistit seznam cron úloh: názvy, frekvence a účel. Adresy s klíči/tokeny neposílat do chatu; pro analýzu stačí názvy skriptů a popis. Je mezi nimi importPrices.php?

Podrobný průběžný rozbor je v DOPRAVA-FEEDY-FTP.md. Cenový import dostupného Mergada má také vypnuté TLS ověřování; jeho skutečné používání zatím není potvrzené.

### DPD, antispam a souhlasy — doplnění z 01:23

- Má klient účet a nárok na aktuální balíčky psmoduly.cz pro DPD, reCAPTCHA a cookies? Stačí potvrzení, dostupné verze a stáří nákupu; klíče a přihlášení neposílat.
- Kde vytváří DPD zásilky a štítky? Má nevyřízené Pickup objednávky? Stačí postup a počty bez osobních údajů.
- Která cookie lišta je opravdu zobrazovaná: Shaim, Usercentrics, jiná? V Shaim administraci zjistit názvy zařazených analytických/reklamních modulů. Je zvlášť měření vložené přes GTM nebo přímo do šablony?
- Jak se má ochrana formulářů chovat při nedostupnosti ověřovací služby? Zdrojový reCAPTCHA kód v této situaci propouští; cílové řešení ověřit podle zvoleného provozního postupu.
- Jak párujete bankovní převody: ID objednávky, reference, vlastní variabilní symbol, účetní/bankovní integrace? Jak vyřídíte platby došlé po přepnutí za staré objednávky?

Kvůli jednorázovému chybnému zobrazení citlivého řádku při analýze doporučuji změnit příslušný údaj z nestandardní kořenové konfigurace. Hodnotu neposílat do chatu; platnost údaje nebyla ověřena a produkce nebyla změněna. Podrobnosti bez hodnoty jsou v OVERRIDES-DPD-ANTISPAM.md.
