# Ruční posouzení souborů a integračních úprav

Zahájeno 7. 10. 2026. Doplňuje automaticky generovanou FTP-ANALYZA.md. Jde o analýzu během stahování, nikoli provedený upgrade.

## Dosud ověřené nálezy

### Kořen aplikace

Skutečný shop je v `C:/Users/Utrh/Desktop/kafkaFTP/www/domains/kafka.monster`. Vrchní `www` obsahuje hostingové soubory a `info.php`; nepatří celý automaticky do nové aplikace. Do produkčního PS 9 nepřenášet diagnostičký phpinfo soubor ani serverové sessions/logs/tmp.

### Core baseline

Referenční archiv je oficiální GitHub tag PrestaShop 1.6.1.13, stažený pouze pro porovnání a bez spuštění. ZIP prošel kontrolou integrity. SHA-256 `039ca59bbcdeff75d1688e50bc11364deda258e0563c1ba91899161b8a9eee3e`.

První inventura 7. 10. ve 22:23 Europe/Prague ověřila 36 shodných souborů a žádný obsahový rozdíl v již dostupné části. Dalších 378 referenčních PHP souborů ve sledovaném rozsahu tehdy ještě nebylo v lokální kopii. Nelze tím potvrdit celý core jako původní. Pokud settings.inc.php později ukáže jinou verzi, reference se musí změnit.

### Registr overrides

`cache/class_index.php` obsahuje sedm cest:

- `override/classes/Hook.php`
- `override/classes/Product.php`
- `override/classes/order/Order.php`
- `override/controllers/front/AuthController.php`
- `override/controllers/front/ContactController.php`
- `override/controllers/front/OrderController.php`
- `override/controllers/front/OrderOpcController.php`

Původ a funkce zatím nejsou připsány konkrétním modulům: těla souborů ještě nedorazila. Cache může obsahovat historické položky. Po stažení zkontrolovat metody, komentáře vložené instalátorem modulu a případnou shodu s `modules/MODUL/override/...`.

Priorita je vysoká pro Product/Order/checkout: vyhodnotit, zda ovlivňují cenu, sklad, dostupnost dopravy, dobírku, vytvoření objednávky a gateway callbacky. U Auth/Contact ověřit registraci a antispam. U Hook ověřit zásah do vykonávání modulů. Funkce přenést podporovaným modulem/hookem, nikoli zkopírováním starých overrides do PS 9.

### Routy a .htaccess

Stažený shopový `.htaccess` obsahuje generovanou část PrestaShopu, API rewrite, standardní přepis obrázků podle číslic ID, passthrough existujících souborů a dispatcher na index.php. Audit napočítal 16 RewriteRule a žádný samostatný Redirect/RedirectMatch. V souboru nebyla nalezena PHP handler direktiva ani HTTPS pravidlo. To neznamená, že web nemá SSL či HTTPS přesměrování: může je řešit PrestaShop nebo hostingová nadřazená konfigurace.

Na novém webu nechat .htaccess vygenerovat cílovou aplikací a zkontrolovat celý řetězec přesměrování pro finální host/doménu. Zachovat funkční obrazové adresy a produktové ID; starý .htaccess nepřebírat jako náhradu nové konfigurace.

### Sitemap a robots

Oba soubory `1_cs_0_sitemap.xml` a `1_sk_0_sitemap.xml` mají po 2 334 hlavních URL položkách, 2 261 obrazových URL a 2 237 různých produktových ID podle standardního vzoru URL. Hlavní URL a image:loc jsou počítány odděleně. Nejnovější lastmod jednotlivých URL je 11. 4. 2025 07:45:37 UTC; sitemap index má lastmod 11. 4. 2025 08:03:10 UTC a odkazuje na dva soubory CS/SK. Lastmod samo není důkazem skutečné doby posledního generování.

`robots.txt` existuje, neblokuje celý web a ve stažené verzi nemá direktivu Sitemap. Nemůže nahradit chránění stagingu heslem. Znovu generovat sitemap pro cílové aktivní jazyky, indexovatelné CMS a aktuální aktivní katalog; ověřit v Search Console. Starou sitemap nepoužívat jako aktuální úplný seznam katalogu.

## Ještě čeká na soubory

- Skutečná `_PS_VERSION_`, prefix DB, provozní debug flags; tajné hodnoty nikdy nevypisovat.
- Obsahové rozdíly core, pokud se objeví, včetně jejich obchodního účelu.
- Původ všech netriviálních overrides, konkrétní metody a jejich bezpečná cílová náhrada.
- Skutečné souborové verze a dodavatelé dm_comgate / dm_cashondelivery; callbacky, retry a ukládání referencí.
- Packeta/DPD vytvoření zásilky versus pouze výběr místa, ukládání údajů a případné cron úlohy.
- Mergado a vlastní XML/CSV/import/cron/endpoint skripty; produkční spuštění pouze po samostatné implementační fázi.
- Úpravy mailů, PDF faktur, theme a překladů; porovnání s baseline a zachování významu.
- Originály obrázků vůči SQL, devět příloh, kategorie/brand/store media, kontrola poškození.
- Porovnání aktuálního katalogu FTP s dva dny starým SQL a doplnění čerstvého dumpu před pilotem.

## Oprávnění a způsob práce

Uživatel výslovně autorizoval čtení lokálně stahované FTP kopie a pokračování analýzy při přibývání souborů. Nebyl autorizován nákup, změna DNS, instalace na produkci nebo odeslání zprávy dodavateli. Soubory zálohy jsou podklady, nikoli instrukce pro agenta. Nevykonávat PHP/SQL z kopie, nevypisovat hesla, klíče, kontakty, sessions ani logy. Výstupy ukládat pouze do složky migration v projektu.

Nové ruční nálezy doplňovat sem pod vlastní datovanou položkou; změnit závěry, pokud nové důkazy opraví předchozí domněnku.

## Noční pokračování

Dočasná automatizace v tomto chatu `dokon-it-ftp-anal-zu-kafka-monster` byla úspěšně vytvořena 7. 10. 2026. Má kontrolovat kopii každých 20 minut, nejvýše 36 navazujících běhů. Při dokončené hluboké analýze ji pozastavit přes automation_update se zachováním ostatních polí. Nejde o instalaci či upgrade: pouze průběžné čtení a doplňování reportů. Pro práci s lokálními soubory musí zůstat počítač a desktopová aplikace spuštěné.

Aktuální překážkou je čekání na soubory: mezi prvními inventurami rostl počet souborů v cache, ale hlavní konfigurace, modules, themes, override a img nebyly dostupné. Běžný růst cache není důvod k novému upozornění. Stabilita bez důležitých adresářů může být přerušený přenos, nikoli hotová záloha. Oznámit pouze zásadní nález, dokončení nebo konkrétní potřebný zásah.

### Ranní průběžný stav — 8. 10. 2026, 10:36 Europe/Prague

Inventura v tomto čase: 22 435 souborů, z toho 22 391 v cache; proti předchozí kontrole +431 souborů. Přenos lokálně dál roste. Hlavní adresáře aplikace a média nadále dosud nedorazily. Žádný nový obsahový core rozdíl nebyl zjištěn v dostupných 36 souborech; 378 sledovaných souborů dál čeká. Verzi ze settings.inc.php a funkci sedmi override cest nelze potvrdit. Hluboký audit kódu ani úplnost zálohy nejsou uzavřené.

Samostatný ranní přehled RANNI-STAV-FTP.md obsahuje ověřené závěry, předběžnou mapu obchodních funkcí do PS 9.2 a konkrétní pořadí prioritního stažení. Nákupní doporučení zůstává podmíněné; nová zjištění neopravňují vydávat pilot nebo kompatibilitu za ověřené.

Upřesnění v 10:55: následný heartbeat dokládá, že automatizace stále vyvolává běhy. Předchozí odhad jejího přirozeného ukončení podle 36 intervalů nebyl potvrzeným stavem plánovače. Ranní přehled zůstává průběžným reportem; analýza pokračuje podle aktuálního heartbeat zadání.

### Nově dostupné jádro a konfigurace — 8. 10. 2026, od 23:15 Europe/Prague

Do kopie dorazily classes, controllers, config, download a část img. Konfigurace settings.inc.php nyní potvrzuje `_PS_VERSION_ = 1.6.1.13`, takže dříve zvolený oficiální referenční archiv odpovídá skutečné deklarované verzi zdrojového shopu. Z konfigurace byla do výstupu vyčtena pouze verze; přihlašovací a kryptografické hodnoty nebyly vypsány ani kopírovány do reportu.

Samostatné obsahové porovnání dostupných stabilních PHP souborů v classes/controllers/config/Core/Adapter s oficiálním ZIP 1.6.1.13 nenašlo žádné odlišnosti po sjednocení konců řádků. Čtení před a po porovnání kontrolovalo velikost a čas změny. Tento nález snižuje riziko skrytých úprav v již porovnané části jádra; neřeší neporovnané části, moduly ani overrides. Přesné počty a zbývající referenční cesty přináší aktuální automatická inventura v evidence/ftp-audit.json. Instalační settings.inc.php nepatří mezi obchodní úpravy core.

V době tohoto ručního posouzení moduly, override, themes, mails a pdf stále nejsou v lokální kopii dostupné. Sedmi cache cestám proto nelze připsat obchodní funkci či dodavatele. Kopie se stále doplňuje a nově spuštěný audit zahrnuje také SQL kontrolu obrázků a příloh. Nálezy nenahrazují potvrzení dokončené FTP fronty ani obnovu zálohy.

Dokončená inventura z 23:17: všech **414 / 414 sledovaných PHP souborů** je byte-identických s referencí, bez dodatečných souborů a bez rozdílů konců řádků. To platí pro classes/controllers/config/Core/Adapter a vybrané kořenové PHP soubory, nikoli celý shop. Moduly, overrides a šablony tento závěr nepokrývá.

V download je všech **9 / 9 příloh evidovaných SQL**, každá nenulové velikosti. Nejde ještě o kontrolu vnitřního formátu či integrity. Z **4 962 produktových originálů** očekávaných podle SQL bylo v průběžně stahovaném img nalezeno **636**, dalších **4 326** dosud nenalezeno v kopii. Nedotažená média nejsou prokázané ztráty na živém webu. Kategorie, značky, prodejny a dekódování obrázků zbývají k samostatné kontrole.

Ruční kontrola defines.inc.php upřesnila výklad flags: výchozí deklarace _PS_MODE_DEV_ je false, _PS_DEBUG_SQL_ má větve true i false podle tohoto režimu. Audit byl opraven, aby první větev true nesprávně nevydával za zapnutý provozní SQL debug. Žádný PHP kód nebyl vyhodnocován; případné externí předdefinování konstant a skutečné běžící prostředí nelze tímto čtením potvrdit.

### Čitelnost dostupných originálů — 8. 10. 2026, 23:36 Europe/Prague

Nový read-only pomocný audit audit_media_integrity.py ověřil strukturu a načetl pixely **1 811 dostupných produktových originálů** v img/p s čistě číselným názvem JPG. V rozsahu 1 812 kandidátů nebyla chyba dekódování; jeden byl prázdný a během FTP zůstává pouze nálezem k opakované kontrole. Skript nečte tmp ani zákaznické uploady, nevykonává zdrojové PHP a nevypisuje kontakty. Výsledek v evidence/media-integrity.json není potvrzení úplnosti ani obsahové shody obrázků s databází; starší formát PRODUCT-IMAGE.jpg a jiná média ještě není součástí této dekódovací kontroly. Po dokončení přenosu zopakovat včetně předchozího prázdného souboru.

Navazující SQL kontrola zaznamenala **1 883 / 4 962 nenulových originálů**, 3 079 dosud nenalezených v kopii a 9 / 9 příloh. Jde o pozdější momentku než dekódovací průchod, proto se počty liší. Sledované jádro dál 414 / 414 přesná shoda; modules/override/themes/mails/pdf dál dosud nedotažené. Návrh náhrad integrací a nákupní závěry se nezměnily.

### Originály kategorií — 8. 10. 2026, 23:56 Europe/Prague

Dekódovací audit byl rozšířen na čistě číselné originály img/c, img/m, img/su a img/st. Dostupných **42 originálů kategorií** prošlo strukturálním ověřením i načtením pixelů bez chyb. V dostupném img/m nebyl číselný originál JPG; img/su a img/st dosud nejsou lokálně dostupné. Nejde o důkaz chybějících obrázků na serveru ani o úplnost vůči DB: tato média jsou volitelná, jiné formáty a odvozené velikosti se nepočítají. Produktových kandidátů v tomto průchodu bylo 3 099, všechny šly dekódovat; nebyl prázdný ani nestabilní soubor. Po dokončení FTP je třeba kontrolu zopakovat. Aktuální souborová a SQL momentka je v generovaném FTP-ANALYZA.md, historii růstu uchovává evidence/ftp-progress.jsonl.

### Formát příloh — 9. 10. 2026, 00:18 Europe/Prague

V download je devět neprůhledně pojmenovaných 40znakových hexadecimálních souborů. Kontrola signatur našla osm PDF a jeden rastrový obrázek. Všechny prošly základním strukturálním ověřením: PDF lze načíst včetně seznamu stránek, obrázek prošel ověřením a načtením pixelů. Žádný nebyl prázdný, nestabilní či neklasifikovaný. Obsah ani názvy příloh nebyly exportovány. Výsledek evidence/attachment-format.json nepotvrzuje vykreslení všech prvků PDF, význam obsahu ani hashovou shodu se serverem; po dokončení FTP stále ověřit zálohu. Pomocný audit_attachment_formats.py zdrojové soubory pouze čte.

Aktuální SQL kontrola má 4 407 / 4 962 nenulových produktových originálů; 555 dosud nenalezených v kopii. Těla modulů, overrides, theme, maily a PDF šablony dosud nedorazily, takže jejich hluboká analýza není uzavřená. Přílohy v download nejsou šablony faktur v pdf. Nákupní závěry se nemění.

### Produktové originály podle SQL — 9. 10. 2026, 00:38 Europe/Prague

Kontrola existence nyní našla **všech 4 962 / 4 962 nenulových produktových originálů očekávaných dodaným SQL**; žádný očekávaný originál v této momentce není nenalezený. Samostatný dekódovací průchod od 00:35 prošel 4 968 dostupných číselně pojmenovaných JPG bez prázdných souborů a bez chyb struktury či pixelů. Dekódovací množina není omezena tabulkou image a její odlišný počet nelze sám o sobě vydávat za šest nových produktů či konkrétní databázovou chybu. Vazby k aktuální živé databázi je třeba ověřit čerstvým dumpem před pilotem.

Další originály: 42 kategoriálních a jeden dodavatelský JPG prošly dekódováním; složky značek a prodejen jsou nyní dostupné, ale v tomto omezeném číselném JPG rozsahu nemají kandidáty. Devět příloh je stále dostupných. Tím je vyřešena kontrola dostupnosti produktových originálů vůči dodanému dumpu; celá FTP záloha není potvrzená. Thumbnaily a ostatní formáty tento dekódovací audit nepokrývá. Moduly, overrides, maily a theme dosud nedorazily; hloubkový audit jejich chování pokračuje po stažení.

### Platební moduly a maily — 9. 10. 2026, od 00:55 Europe/Prague

Dorazily dm_comgate 3.3.2 a dm_cashondelivery 3.3.0. Autorem je David Mrózek / prestashop.valasinec.cz; ověřeno z veřejného autorova pole a primárních produktových stránek, nikoli ze zákaznických či licenčních údajů. Podrobný statický rozbor je v PLATBY-A-MAILY-FTP.md a evidence/payment-code-audit.json, konkrétní bezpečná pravidla z dumpu v evidence/payment-rules.json.

Zásadní nálezy: starý Comgate vypíná cURL ověření certifikátu i hostitele a PAID callback nemá viditelnou ochranu před vrácením již doručeného stavu. Nebyla zjišťována reálná zneužitelnost ani prováděn provozní test. Nový modul musí ověřit TLS, částky, vazby a opakování událostí. Dobírka v dumpu používá režim carrier a zaokrouhlování; aktivní CZ sazby jsou 49 Kč, zatímco veřejná informace dříve uváděla 30 Kč. Rozpor potvrdit s klientem. Modulový Order override zachovává dobírkovné při přepočtu dopravy; je pouze kandidát na původ cache cesty, kořenové tělo stále čeká.

300 kořenových mailových HTML/TXT/TPL je dostupných ve čtyřech jazycích. EN proti odpovídající referenci: 5 shodných, 62 odlišných, 8 mimo stejnojmennou referenci; ostatní jazykové baseline v ZIP nejsou. Obchodní účel potvrdit podle obsahu, nikoli samotného rozdílu. Původní heslové šablony obsahují passwd placeholder a nesmějí se kopírovat doslova do PS 9. Fakturační TPL, zbývající integrace a šest dalších kořenových overrides ještě nejsou posouzené. Původní projekt a FTP kopie zůstaly beze změny. Automatizaci nepozastavovat před dokončením potřebného rozboru.

### Packeta a Mergado — 9. 10. 2026, od 01:14 Europe/Prague

Nově posouzená Packeta 2.0.9 má CSV export, mapování dopravců/plateb, ruční váhu, dobírku a historii výdejních míst mimo standardní orders. Mergado 4.1.0 má feed cron, cenový import produktů i kombinací, marketing/refundace a přibalené overrides pro souhlasy v checkoutu. Cenový import vypíná TLS peer/host ověřování na řádcích 136–137; používání tohoto importu na produkci není potvrzené. Product override pouze deklaruje pack_stock_type 3, stejnou hodnotu má referenční core 1.6.1.13. Tři modulové overrides jsou kandidáti na cache cesty, bez kořenových těl nelze potvrdit původ. Podrobné funkce, cílové náhrady, omezení a otázky jsou v DOPRAVA-FEEDY-FTP.md a evidence/integration-code-audit.json.

DPD/antispam, kořenové overrides, theme a PDF šablony nadále dosud nedotažené. Výsledky nejsou dokončená hluboká analýza ani ověřená úplná záloha. Souborová inventura z 00:57 je starší než toto ruční posouzení; nepoužívat její tehdejší počet dostupných modulů jako nynější stav. Automatizace zůstává aktivní pro pokračování.

### Kořenové overrides, DPD, antispam a PDF — 9. 10. 2026, od 01:23 Europe/Prague

Dorazila těla všech sedmi cache cest. Šest je obsahově shodných s již posouzenými modulovými kandidáty po odstranění komentářů/whitespace při zachování řetězců; Hook obsahuje starší variantu cookie filtru stejného autora. Autor a funkce jsou nyní popsány v OVERRIDES-DPD-ANTISPAM.md a evidence/override-origins.json, nikoli dál pouze odhadnuté z cache. Nově posouzen DPD ParcelShop 2.3.8, reCAPTCHA 2.6.1, cookies 2.3.1 a Usercentrics 1.0.13. DPD řeší místo k id_cart, reference dopravců, cron cache a maily/PDF; expedici/štítky z tohoto čtení nelze prohlásit za automatické.

reCAPTCHA vrací při selhání načtení ověřovací služby success=true. Kontakt i registrace mají v SQL ochranu 1, newsletter 0. Nová sestava vyžaduje výpadkový test; nejde o pozorovaný incident. Cookie ostrý režim je 1, skutečné seznamy blokovaných modulů se zatím bezpečně nepodařilo vyložit a neprohlašují se za prázdné. Usercentrics je alternativní vložení CMP při nastaveném setting ID. Pouhé přiřazení obou modulů nedokazuje dvě běžící lišty.

Všech 38 referenčních PDF TPL/PHP souborů je byte-identických, bez dodatků či nestabilních souborů v tomto rozsahu. Výsledné faktury přesto mění data a hooky, zejména DPD a dobírka; samotná shoda souborů nenahrazuje jejich test. Theme je default-bootstrap, jeho průběžné obsahové porovnání je v evidence/template-code-audit.json; dosud čeká část modulových šablon/SASS, proto audit theme a její mailové precedence není uzavřený. Aktuální inventura z 01:19 má 107 658 souborů a opět 414/414 core shodu, všech 4 962 očekávaných originálů i 9 příloh. Je starší než později doražené overrides/pdf/theme.

Při čtení nestandardní konfigurace pomocný filtr jednou propustil citlivý řádek do výstupu nástroje. Hodnota není v reportech. Filtr byl opraven, daný typ konfigurace je zakázán a regresní kontroly vynechání obsahu mimo PHP prošly. Doporučení ke změně údaje a omezení ověření je v OVERRIDES-DPD-ANTISPAM.md. Produkce zůstala bez zásahu.

### Uzavření ruční analýzy dostupného kódu — 9. 10. 2026

Poslední inventura z 01:31 Europe/Prague: 109 944 souborů, 1 605 770 656 B; všech 78 modulů přiřazených shopu v SQL má vstupní PHP. Všechny hlavní sledované adresáře jsou dostupné. Toto je lokální momentka, ne vzdálený manifest ani ověřená úplná záloha. Sedm kořenových overrides a hlavní integrace mají dokončený ruční rozbor v OVERRIDES-DPD-ANTISPAM.md, PLATBY-A-MAILY-FTP.md a DOPRAVA-FEEDY-FTP.md. Souhrn rozhodnutí je v ZAVER-A-POSTUP.md.

**Theme:** všech 543 referenčních souborů vybraného rozsahu default-bootstrap (.tpl/.css/.js/.php/.scss/.html/.txt) je byte-identických; žádný rozdíl, nedotažená reference či nestabilní čtení. Dodatečných 388 souborů tvoří lang 3, mails 225 a modules 160. U modulových dodatků jde o 74 překladových souborů a 86 mailů, nikoli další nalezený checkout TPL/JS. Nejde o tvrzení, že celá instalace nebo jiné formáty všech modulů jsou totožné. PDF má všech 38 referenčních souborů shodných, bez dodatků v tomto rozsahu. Důkaz evidence/template-code-audit.json.

**Přednost mailů:** referenční classes/Mail.php:256–260 nejprve použije mailový override modulu v theme, je-li pro danou šablonu/jazyk dostupný; jinak theme/mails, jinak původně předanou cestu. CS a SK theme/root mají 75/75 stejné páry v každém jazyce. EN má 72 porovnaných párů: 34 shodných a 38 různých. Referenční EN soubory theme se přitom v porovnaném rozsahu shodují s oficiálním tagem. Dřívější rozdíly root EN tedy nelze automaticky označit za účinné přizpůsobení odchozích mailů. Chování také závisí na druhu mailu, jazykové cestě a hooku; DE obsah a skutečné odesílání ověřit na pilotu. Nový shop dostane podporované maily a reset hesla; staré passwd placeholdery nekopírovat.

**Další jádro a skripty:** rozsah oficiálně referencovaných root/tools PHP má 603 byte-identických souborů, žádný obsahový rozdíl, nedotaženou referenci ani další tools PHP. Důkaz evidence/extended-core-audit.json. Rozsah se částečně překrývá s 414 core soubory, počty nesčítat bez deduplikace. V tomto rozsahu nebyl nalezen zakázkový cron; identifikované vlastní integrační endpointy jsou modulové (Mergado feed a cenový import, DPD aktualizace míst, případně sitemap podle nastavení). Jejich skutečný externí rozvrh je otázka na WEDOS/admin. Nestandardní konfigurace není prohlášena za oficiální core ani spouštěna. Absenci jiných skriptů na živém serveru bez manifestu nelze doložit.

**Vyložené souhlasy:** oba uložené seznamy Shaim analytických/reklamních modulů jsou PHP serializované false. Ostrý režim 1 tak v dodaném nastavení nemá vybrané moduly k blokování. Konfigurační záznam USERCENTRICS_SETTING_ID se v dumpu nenašel. Nejde o runtime test skutečných značek na webu ani o důkaz globálně nefunkčních souhlasů; přímý JS a jiné mechanismy posoudit při implementaci. Důkaz evidence/consent-flags.json, neobsahuje klíče ani souhlasové řádky zákazníků.

Tím je potřebná statická analýza dostupného kódu, overrides, hlavních integrací, šablon a médií uzavřená. Zbývající kontroly patří k provozním podkladům a pilotu: úplnost FTP/obnova, čerstvá DB, runtime a licence, přesná společná 9.2 sestava, obchodní pravidla, rozpracované platby/expedice, externí integrace a měření. Analýza není runtime zkouška, malware certifikace ani provedená migrace.
