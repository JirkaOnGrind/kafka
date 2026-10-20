# Závazná vizuální předloha pro PrestaShop

Potvrzeno uživatelem 12. 10. 2026: současný frontend je závazná vizuální předloha. Výsledný obchod se mu má podobat co nejvíce.

Upřesnění uživatele: klient vybral černou. Přepínač barev je pouze součást klientské předlohy a na skutečném webu být nesmí. Homepage má mít původní vlastní kategoriální karty a ikony, nikoli obecné bloky Doporučené / Nejnovější / Speciální.

Další schválené úpravy 12. 10. 2026: hlavička používá oříznuté vektorové logo klienta s obloukovým nápisem a průhledným pozadím. Pod středovým názvem KAFKA MONSTER je menší PUNK ROCK SHOP stejným fontem. Červené akcenty nativního obchodu mají sytou barvu loga `#ca0007`, hover `#e00008`; text na červených tlačítkách je bílý. Původní prototyp zůstává pro porovnání beze změny.

## Zdroje

### Košík — schválená změna 20. 10. 2026

Celý výchozí blok Ujištění zákazníka s modrými ikonami a instalačními texty se v prázdném ani naplněném košíku nevykresluje. Košík používá tmavé hranaté panely, jemné obrysy, přehlednější ceny, 48 px vysoké ovládání množství a červené hlavní tlačítko. Souhrn má rudý horní okraj; na mobilu navazuje pod položkami. Prázdný stav má jednoduchou jednobarevnou ikonu košíku a výrazné pokračování v nákupu. Nativní položky, varianty, ceny, promo kódy, AJAX aktualizace a přechod do objednávky zůstávají. Původní prototyp se nemění.

### Spojenci na scéně — schválená změna 20. 10. 2026

Pod šesticí kategoriálních karet homepage je sekce „Spojenci na scéně“ s partnery převzatými z veřejné původní homepage kafka.monster: The Punkavec a Punkhudba. Používá skutečná průhledná loga z jejich vlastních webů, uložená místně a vykreslená bíle na černém podkladu, rudé akcenty a přístupné odkazy do nové karty. Desktop má úvod vedle dvojice karet, mobil karty pod sebou. Obsah spravuje modul `kafkahome` v `data/partners.json`; původní prototyp zůstává zachován.

### Odstranění pouze nadpisu „Vyber si“ — schválená změna 20. 10. 2026

Odstraněný je pouze text nadpisu „Vyber si“. Karty podkategorií a jejich původní vzhled na desktopu i mobilu zůstávají.

### Mobilní výpis produktů — schválená změna 20. 10. 2026

Do 767 px jsou skryté obrázkové bannery kategorií nad výpisem. Nákupní tlačítka produktových karet zobrazují pouze vystředěnou ikonu košíku, bez viditelného textu, s minimální výškou 48 px. Nativní přístupný název tlačítka, volba množství a přidávání do košíku zůstávají zachované. Desktop a původní prototyp se nemění.

### Mobilní homepage — schválená změna 20. 10. 2026

Do 767 px se skrývá celá trojice „Doprava a platba“, „Rychlé odeslání“, „Vrácení a reklamace“. Hlavní fotka navazuje pod hlavičkou, hero má minimální výšku 500 px a méně utlumený horní okraj. Červené tlačítko do obchodu vyplňuje šířku textového bloku, má minimálně 64 px na výšku a větší šipku. Zachovává odkaz na šest kategoriálních karet. Desktop a původní prototyp se nemění.

### Hover navigace — schválená změna 20. 10. 2026

Horní a boční kategoriální navigace má shodný jednoduchý hover: pouze rudý text, bez podbarvení, barevných čar a náklonu. Aktuální kategorie zůstává rudá i bez hoveru. Horní navigace označuje aktivní sekci také uvnitř jejích podkategorií, včetně mobilního menu. Viditelný klávesnicový fokus a nativní rozbalování zůstávají zachované.

### Detail produktu — schválená změna 20. 10. 2026

Navazující úprava: Popis a Detaily produktu tvoří jeden souvislý panel bez mezery, se společným obrysem a vnitřní dělicí čárou. Nativní komentáře pokračují přímo pod popisem v levé části se sjednoceným nadpisem; při prázdném popisu zůstávají samostatně vlevo. Na mobilu části navazují pod sebou a sklápění zůstává. Pod panelem je pouze jeden seznam nejvýše čtyř dalších produktů ze stejné kategorie a pak patička; prohlédnuté produkty a samostatné příslušenství se na detailu nevykreslují. Pokud kategorie nemá čtyři další produkty, obsah se nedoplňuje z jiné kategorie.

Nadpisy Popis a Detaily produktu mají vnitřní odsazení 16 px svisle a 20 px vodorovně, obsah 20 px. Na desktopu od 768 px jsou tyto dva panely vždy otevřené se statickým nadpisem bez šipky a ovládacího tlačítka. Na mobilu zůstává nativní sklápění. Přechod z mobilu na desktop zobrazí i dříve zavřený obsah; stejné výšky desktopových karet platí nadále.

Navazující upřesnění: při chybějícím nebo obsahově prázdném popisu zaujmou levý spodní panel nativní komentáře. V takovém případě se pod stránkou neopakují. Oba spodní panely vedle sebe mají na desktopu stejnou výšku, stejně jako horní galerie a nákupní karta. Na mobilu zůstávají přirozené výšky. Ostatní výstupy modulů, přílohy a doplňující obsah zůstávají zachované.

Výchozí modrý blok „Ujištění zákazníka“ se na produktové stránce nezobrazuje. Jeho pravý spodní sloupec nahrazují nativní detaily produktu, ve výchozím stavu rozbalené, vedle popisu. Galerie a nákupní karta mají na desktopu stejnou výšku podle obsahu vyšší z nich. Na mobilu zůstávají karty i informace pod sebou s přirozenou výškou. Nativní varianty, galerie a košík zůstávají funkční; obsah detailů vychází z živých dat. Původní prototyp zůstává pro srovnání.

### Mobilní hlavička — schválená změna 20. 10. 2026

Na šířkách do 767 px je logo vystředěné nad názvem. KAFKA MONSTER a PUNK ROCK SHOP mají vlastní řádky přes šířku hlavičky. Hledání, účet, košík a hamburger jsou rovnoměrně rozložené v samostatné řadě s minimálně 48 px vysokými ovládacími plochami. Kategoriální panel `ps_categorytree` nad výpisem je na mobilu skrytý; celý strom zůstává dostupný v hamburgeru. Desktop a původní prototyp se touto změnou nemění.

Navazující mobilní úprava přehledu „Vyber si“: podkategorie jsou ve dvou kompaktních sloupcích s mezerou 8 px, minimální výškou odkazu 48 px a menším písmem. Dekorativní náhledy jsou na mobilu skryté; názvy, šipky a nativní odkazy zůstávají. Desktopové karty zůstávají původní.

- `graficky_navrh/index.html`, `graficky_navrh/app.js`, `graficky_navrh/catalog-data.js` a `graficky_navrh/HOMEPAGE-ARCHITECTURE.md`: homepage, navigace a katalogové interakce.
- `graficky_navrh/product.html`, `graficky_navrh/product.css` a `graficky_navrh/product.js`: detail produktu.
- `graficky_navrh/theme-system.js` a `assets/`: barevná témata, logo, ilustrace, textury a další existující grafika.
- Aktuální vykreslení zdrojových stránek má při porovnání přednost před staršími konceptovými obrázky v `design/`.

## Pravidla převodu

- Zachovat typografii, barevná témata, šířky obsahu, rozestupy, obrysy, textury, grafiku a charakter interakcí podle předlohy.
- Hummingbird slouží jako funkční základ; jeho výchozí vzhled neurčuje výsledný design.
- Převádět komponenty na nativní data a události PrestaShopu. Ceny, sklad, varianty, odkazy a košík nesmí zůstat demonstračními hodnotami.
- Stránky bez existující předlohy (například checkout a účet) navrhnout ve stejném vizuálním jazyce s funkčním ovládáním.
- Vizuální odchylky kvůli funkčnosti nebo přístupnosti evidovat a vysvětlit; zásadní změny vzhledu řešit s uživatelem.
- Předlohu při převodu zachovat pro průběžné porovnávání.

## Kontrola věrnosti

Před převodem uložit aktuální referenční snímky homepage a detailu na desktopu a mobilu. Porovnávat novou šablonu při stejných rozměrech, barevném tématu a odpovídajícím obsahu. Vést krátký seznam rozdílů a postupně jej uzavírat. Ověřit navigaci, změnu barevného tématu, varianty a přidání do skutečného košíku.
