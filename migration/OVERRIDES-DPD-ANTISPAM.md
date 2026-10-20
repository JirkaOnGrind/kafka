# Kořenové overrides, DPD a ochrana formulářů

Ruční posouzení 9. 10. 2026 od 01:23 Europe/Prague. Zdrojová kopie pouze čtena, nic z ní nebylo spuštěno. Původní frontend ani FTP nebyly změněny. Důkazy: `evidence/override-origins.json`, `evidence/integration-code-audit.json`, `evidence/consent-flags.json`. Přenos ještě pokračuje; tento dokument samostatně neprokazuje úplnou zálohu.

## Sedm kořenových cest už má vysvětlenou funkci

Všech sedm cest z cache/class_index.php je nyní čitelných. Šest má po odstranění komentářů a whitespace shodný obsah s příslušným přibaleným modulem, při zachování obsahu řetězců. Jde o statické srovnání, nikoli vykonání kódu či důkaz stejného runtime. Zbývající Hook byl ručně přečten a obsahuje starší variantu stejného mechanismu cookie modulu. Otisky původních souborů jsou v důkazním JSON.

| Kořenové tělo | Vysvětlený původ / chování | Náhrada v PS 9.2 |
| --- | --- | --- |
| classes/Hook.php | Shaim cookies; filtruje seznam spouštěných modulů podle souhlasů hosta v DB a při ostrém režimu. Obsah není totožný s přibalenou současnou variantou. | Aktuální zvolená CMP a správné blokování modulů/skriptů; starý core override nepřenášet. |
| classes/Product.php | Mergado, pouze pack_stock_type = 3. Stejný default je v referenčním ProductCore 1.6.1.13. | Nepřenášet redundantní override; žádný nový obchodní požadavek z tohoto těla nevyplynul. |
| classes/order/Order.php | dm_cashondelivery; zachovává příplatek při refreshShippingCost. | Podporovaný dobírkový modul a test editace dopravy/položek. |
| controllers/front/AuthController.php | Shaim reCAPTCHA, kontrola registrace účtu za podmínek register, nový zákazník, bez guest_email a bez AJAX. | Aktuální ochrana registrace a zákaznického checkoutu; samostatný test hosta i AJAX. |
| controllers/front/ContactController.php | Shaim reCAPTCHA, při neúspěšné validaci zastaví odeslání kontaktního formuláře. | Podporovaný modul napojený na cílový formulář. |
| controllers/front/OrderController.php | Mergado, ukládá souhlasy pro srovnávače/recenze na starém checkoutu. | Nová integrace souhlasů v nativním PS 9 checkoutu. |
| controllers/front/OrderOpcController.php | Mergado, totéž v OPC; v souhlasové větvi odstraní AJAX parametry. | Aktuální checkout bez kopírování starého controlleru, test souhlasů při změnách košíku. |

Kořenový Hook oproti modulovému kandidátu načítá seznamy kategorií modulů až po zajištění hosta/cache a nemá časný návrat při prázdných seznamech. Používá také explicitní isset pro id_guest. V posouzeném těle není další platební, cenová či katalogová funkce. Blokuje jen moduly zařazené do konkrétních seznamů; nepředstavuje automatickou ochranu libovolného JS přidaného do nového frontendu.

## DPD ParcelShop / Pickup

Zdrojový shaim_dpdparcelshop **2.3.8** je od Dominika Shaima Ulricha / psmoduly.cz. Jde o výběr výdejního místa v checkoutu, jeho uložení k id_cart, následné dohledání přes objednávku, zobrazení/změnu v administraci, doplnění mailů a PDF. **DPD Classic doručení na adresu a Pickup místo jsou rozdílné obchodní cesty.** Tuto funkci nelze nahradit pouhým ručním přidáním dopravce v administraci.

AJAX používá aktuální košík z Context, kontrolu aktivního modulu a odvozeného secure_key; zapisuje ID a typ místa a umí načíst metadata widgetu do cache poboček. Hodnoty klíčů ani konkrétních zákazníků nebyly exportovány. `GetPobocka()` ověřuje, že dopravce objednávky/košíku patří do mapovaných služeb. Používá referenci dopravce; při cílové migraci zachovat nebo přemapovat reference, ne pouze název/ID dopravce.

Instalace má pro nové checkouty hook kontroly dokončeného dopravního kroku. Na PS 1.6 se zapojují staré carrier/header cesty; jejich chování závisí na JS a šabloně. Bez živé objednávky není ověřené blokování všech variant starého checkoutu. Pilot cíle musí pokrýt chybějící místo, změnu země a dopravce, změnu místa, adresní službu i návrat k dříve vybranému místu.

Cron obnovuje XML seznam míst, metadata, otevírací dobu a možnosti výdeje/plateb. Umí vyloučit boxy a aktualizuje jen nastavené země. Lokální cache má hodinový interval obnovy. Skutečný plán WEDOSu se musí zjistit samostatně. Staré URL/odvozené klíče se do cíle nekopírují.

Předchozí SQL inventura má **8 řádků historie výběru a 6 298 poboček**. Výběry pro historické/nevyřízené objednávky zachovat samostatně nebo archivovat; seznam aktuálních poboček obnovit z podporovaného zdroje. Tento balíček z posouzených cest nedokládá automatické vytvoření zásilek či tisk přepravních štítků; způsob klientovy expedice stále potvrdit.

Mailový a PDF hook přidává místo jen k platnému dopravci a pokud nedetekuje vlastní starší Order override. U právě dostupného kořenového Order jde o dobírku, takže doplnění místa řeší hook. Nativní PDF šablony mohou být beze změn a přesto výsledná faktura obsahovat modulová data; je nutný funkční test cílové faktury.

Pro cílovou sestavu preferovat aktualizaci stávající licence. Dodavatel má v changelogu podporu PS 9 a pozdější podporu Hummingbird/Bootstrap 5; současně dokumentuje opravy mapování zemí a bezpečnější obnovy seznamu míst. Proto samotná stará deklarace kompatibility nestačí a starý balíček nepřenášet jako finální řešení. [DPD modul a changelog výrobce](https://psmoduly.cz/dpd-parcel-shop-pick-up-vyber-odberneho-mista-primo-v-objednavce-alzaboxy-242/vydejni-mista.htm).

## reCAPTCHA v3 a její chování při výpadku

Zdrojový modul **2.6.1**, stejný autor. Balíček deklaruje rozsah PS 1.6.0.0 až 9.99.99; to je metadata rozsahu, nikoli test přesné 9.2. Kód má cesty pro kontakt, registraci, přihlášení, administrátorské přihlášení a newsletter; dostupnost konkrétní cesty závisí na verzi, nastavení a hooku.

Bezpečně vyčtené flags z dodaného SQL: **kontakt 1, registrace 1, newsletter 0**. Klíče nebyly čteny do výstupů. Hledané jednoduché flag názvy login/bo_login v dumpu nalezeny nebyly, což není důkaz neexistence jiné konfigurační cesty ani vypnutí v živém shopu.

`verifyResponse()` odmítá prázdný token, kontroluje odpověď success, shodu action a pro příslušné akce skóre. **Pokud HTTP načtení ověřovací služby selže a vrátí false, nastaví success = true** (`recaptchalib.php:145–148`). Tedy při síťovém výpadku propouští. Není to zjištěný útok ani pozorovaný spamový incident. Na cíli rozhodnout o chování při výpadku, otestovat jej a případně zajistit podporovaný záložní mechanismus.

Host/AJAX má ve starém Auth override výjimky. To není důkaz, že každá taková cesta je nechráněná: pro jiné verze existují hooky. Do schváleného frontendu musí ochrana patřit i funkčně, ne pouze vizuálně. Testovat platný, chybějící, zamítnutý, opakovaný token, nesprávnou action, výpadek ověřování a běžnou registraci hosta/účtu. Je také třeba ověřit povolené staging/produkční domény klíčů soukromě při implementaci.

Dodavatel nabízí antispamové moduly pro řadu PS 9. Nejprve ověřit klientův nárok na balíček a pilot; další licenci automaticky nekupovat. [Primární nabídka výrobce](https://psmoduly.cz/44-zabezpeceni).

## Cookie lišta a Usercentrics

shaim_cookies_eu **2.3.1** má serverový Hook override a AJAX uložení souhlasů podle id_guest. Ostrý režim je v dumpu **1**. Jazykové texty bere z modulových souborů; cookie název odvozuje také od domény a starého cookie klíče. Starou návštěvnickou cookie/session databázi tedy nelze vydávat za přenositelný souhlas pro novou čistou instalaci.

Následné vyložení uloženého formátu potvrzuje, že oba seznamy analytických/reklamních modulů v dodaném dumpu obsahují PHP serializované false. Tento serverový filtr tedy podle tohoto nastavení nemá vybrané moduly, které by blokoval. Není to důkaz chování celého živého webu: jiné JS/CMP mechanismy, změny po exportu a konkrétní spuštění měření nejsou tímto ověřené. Root Hook umí odstranit z hook seznamu odmítnuté moduly, přímé značky nového frontendu vyžadují vlastní napojení na zvolenou CMP.

Současně je v SQL aktivní/přiřazený Usercentrics **1.0.13**. Jeho displayHeader vloží šablonu jen při nastaveném setting ID a používá volbu SDP. Konfigurační záznamy USERCENTRICS_SETTING_ID a USERCENTRICS_ENABLE_SDP nebyly v dodaném dumpu nalezeny; žádná hodnota ID nebyla exportována. Přítomnost modulu sama o sobě neprokazuje dvě zobrazené lišty ani funkční Usercentrics. Zvolit jednu hlavní sestavu CMP a zkontrolovat překryv s Mergadem, GTM a vlastními skripty.

Shaim lišta se podle dostupného kódu může skrýt pro Lighthouse a nastavené robotické user-agenty. Samotný automatický Lighthouse průchod proto nenahradí test běžného prohlížeče a reálného chování souhlasů.

## Cílová mapa a nákupní dopad

| Funkce | Nejlehčí náhrada | Ověření |
| --- | --- | --- |
| DPD Pickup, místa v mailu/PDF | Aktuální modul stejného dodavatele a mapa referencí dopravců | Země, chybějící místo, změna služby, nové mapy, historická místa |
| Ochrana kontaktu/registrace | Podporovaný současný antispam | Serverová validace, host/AJAX, chybové a výpadkové cesty |
| Souhlasy a blokování měření | Jedna CMP a napojené současné moduly | Před souhlasem, odmítnutí, odvolání, změna stránky, jazyky |
| Stará Hook/Auth/Contact těla | Nahradit funkcí aktuálních modulů | Nevkládat staré 1.6 controllers/classes do PS 9 |

Současné nálezy snižují nejistotu ohledně účelu sedmi overrides: jde o známé modulové funkce, žádné z těchto sedmi těl neprokázalo nový zakázkový objednávkový systém. Následné porovnání dostupného default-bootstrap a PDF je uzavřené v uvedeném rozsahu (FTP-KOD-A-INTEGRACE.md). Odhad 8–18 pracovních dnů stále vyžaduje pilot a ověření runtime. Warehouse není těmito nálezy potřeba. Ověřit existující licence Shaim i Valašinec před novými nákupy.

## Ochrana podkladů během analýzy

Neobvyklý kořenový PHP konfigurační soubor `.htj75qsk.appconfig.php` obsahuje údaj označený `db-pass` jako prostý text za ukončeným PHP. Při jeho čtení pomocný filtr jednou chybně propustil tento řádek do výstupu nástroje. Hodnota nebyla opsána do reportů ani dále použita. Filtr nyní tento typ konfigurace odmítá a vynechává ne-PHP obsah; syntetické regresní kontroly prošly. Doporučit změnu příslušného údaje kvůli zobrazení ve výstupu, s ověřením jeho skutečného použití správcem a aktualizací potřebné konfigurace. Jeho skutečná platnost ani vztah k aktuální produkční databázi nebyly ověřovány a nic na produkci nebylo změněno. Soubor je v kořeni `C:\Users\Utrh\Desktop\kafkaFTP\www\domains\kafka.monster`; jeho obsah neposílat do chatu.
