"""Update analyst reports only inside migration; never touches FTP or frontend."""
from pathlib import Path
import csv

HERE = Path(__file__).resolve().parent
STAMP = '### Uzavření ruční analýzy dostupného kódu — 9. 10. 2026'
NOTES = {
    'FTP-KOD-A-INTEGRACE.md': '''
### Uzavření ruční analýzy dostupného kódu — 9. 10. 2026

Poslední inventura z 01:31 Europe/Prague: 109 944 souborů, 1 605 770 656 B; všech 78 modulů přiřazených shopu v SQL má vstupní PHP. Všechny hlavní sledované adresáře jsou dostupné. Toto je lokální momentka, ne vzdálený manifest ani ověřená úplná záloha. Sedm kořenových overrides a hlavní integrace mají dokončený ruční rozbor v OVERRIDES-DPD-ANTISPAM.md, PLATBY-A-MAILY-FTP.md a DOPRAVA-FEEDY-FTP.md. Souhrn rozhodnutí je v ZAVER-A-POSTUP.md.

**Theme:** všech 543 referenčních souborů vybraného rozsahu default-bootstrap (.tpl/.css/.js/.php/.scss/.html/.txt) je byte-identických; žádný rozdíl, nedotažená reference či nestabilní čtení. Dodatečných 388 souborů tvoří lang 3, mails 225 a modules 160. U modulových dodatků jde o 74 překladových souborů a 86 mailů, nikoli další nalezený checkout TPL/JS. Nejde o tvrzení, že celá instalace nebo jiné formáty všech modulů jsou totožné. PDF má všech 38 referenčních souborů shodných, bez dodatků v tomto rozsahu. Důkaz evidence/template-code-audit.json.

**Přednost mailů:** referenční classes/Mail.php:256–260 nejprve použije mailový override modulu v theme, je-li pro danou šablonu/jazyk dostupný; jinak theme/mails, jinak původně předanou cestu. CS a SK theme/root mají 75/75 stejné páry v každém jazyce. EN má 72 porovnaných párů: 34 shodných a 38 různých. Referenční EN soubory theme se přitom v porovnaném rozsahu shodují s oficiálním tagem. Dřívější rozdíly root EN tedy nelze automaticky označit za účinné přizpůsobení odchozích mailů. Chování také závisí na druhu mailu, jazykové cestě a hooku; DE obsah a skutečné odesílání ověřit na pilotu. Nový shop dostane podporované maily a reset hesla; staré passwd placeholdery nekopírovat.

**Další jádro a skripty:** rozsah oficiálně referencovaných root/tools PHP má 603 byte-identických souborů, žádný obsahový rozdíl, nedotaženou referenci ani další tools PHP. Důkaz evidence/extended-core-audit.json. Rozsah se částečně překrývá s 414 core soubory, počty nesčítat bez deduplikace. V tomto rozsahu nebyl nalezen zakázkový cron; identifikované vlastní integrační endpointy jsou modulové (Mergado feed a cenový import, DPD aktualizace míst, případně sitemap podle nastavení). Jejich skutečný externí rozvrh je otázka na WEDOS/admin. Nestandardní konfigurace není prohlášena za oficiální core ani spouštěna. Absenci jiných skriptů na živém serveru bez manifestu nelze doložit.

**Vyložené souhlasy:** oba uložené seznamy Shaim analytických/reklamních modulů jsou PHP serializované false. Ostrý režim 1 tak v dodaném nastavení nemá vybrané moduly k blokování. Konfigurační záznam USERCENTRICS_SETTING_ID se v dumpu nenašel. Nejde o runtime test skutečných značek na webu ani o důkaz globálně nefunkčních souhlasů; přímý JS a jiné mechanismy posoudit při implementaci. Důkaz evidence/consent-flags.json, neobsahuje klíče ani souhlasové řádky zákazníků.

Tím je potřebná statická analýza dostupného kódu, overrides, hlavních integrací, šablon a médií uzavřená. Zbývající kontroly patří k provozním podkladům a pilotu: úplnost FTP/obnova, čerstvá DB, runtime a licence, přesná společná 9.2 sestava, obchodní pravidla, rozpracované platby/expedice, externí integrace a měření. Analýza není runtime zkouška, malware certifikace ani provedená migrace.
''',
    'PLATBY-A-MAILY-FTP.md': '''
### Uzavření ruční analýzy dostupného kódu — 9. 10. 2026

Kořenový override/classes/order/Order.php už je čitelný a obsahově odpovídá dm_cashondelivery po odstranění komentářů a whitespace při zachování řetězců. Účel zachování dobírkovného je potvrzen skutečným kořenovým tělem; dřívější označení za pouhého kandidáta už neplatí. PDF má 38 shodných referencí bez obsahového rozdílu v porovnaném rozsahu. Výslednou fakturu přesto ovlivní DPD hook a uložené částky.

Theme maily mají přednost před root maily podle classes/Mail.php:256–260, případně ještě přednější modulovou cestu v theme. CS/SK theme a root jsou shodné po 75 párech, EN má 38 různých z 72 párů. EN theme reference se shodují s oficiálním tagem; rozdílný root EN sám neprokazuje používanou úpravu. Podrobnosti a rozsah FTP-KOD-A-INTEGRACE.md, důkaz evidence/template-code-audit.json. Pilot mailů musí zachytit společně platební proměnné a údaje pobočky a nové resetování hesla. Závěr a nákupy: ZAVER-A-POSTUP.md.
''',
    'DOPRAVA-FEEDY-FTP.md': '''
### Uzavření ruční analýzy dostupného kódu — 9. 10. 2026

Všechna tři kořenová Mergado těla i dobírkový Order už jsou dostupná a obsahově přiřazená modulovým kandidátům. Sedm skutečných kořenových overrides vysvětluje OVERRIDES-DPD-ANTISPAM.md a evidence/override-origins.json. Dřívější nejistota chybějících kořenových těl je vyřešená.

DPD 2.3.8 od Shaim má dokončený rozbor: místo k id_cart, reference dopravců, admin, mail/PDF hooky, cron aktualizace cache. Samotná tato funkce neprokazuje automatické podání zásilek/štítků. Cíl vyžaduje novou podporovanou sestavu a skutečný klientův expediční postup. Cookie seznamy jsou v dumpu serializované false; měření a Mergado souhlasy vyžadují runtime ověření. Root/tools reference byly dokončeně srovnány bez obsahových změn, neznámé externí cron rozvrhy stále potvrdit. Souhrnná mapa a rozhodnutí jsou v ZAVER-A-POSTUP.md.
''',
    'STAV-A-NAKUPY.md': '''
### Uzavření ruční analýzy dostupného kódu — 9. 10. 2026

Kořenové overrides, DPD, antispam, theme a PDF jsou nyní posouzené; jejich dřívější čekání na FTP v historických odstavcích je vyřešené. Závěrečná nákupní tabulka je v ZAVER-A-POSTUP.md. Warehouse pro nynější rozsah **nekupovat**. MigrationPro je vhodný datový nástroj, ale nákup stále podmínit přesnou identitou produktu a odpovědí na 1.6.1.13 → 9.2.0, PHP, staging licenci, ID/hesla/historii a finální synchronizaci nových i změněných dat. Pilot nebyl proveden.

Kromě Valašince nejprve ověřit klientův nárok na aktualizace Shaim pro DPD, reCAPTCHA a cookies. Vybrat jednu potřebnou CMP; neobjednávat automaticky dva překrývající se moduly. Packeta a Mergado použít v současných podporovaných vydáních; Mergado Pack je bezplatný modul, účet/služba jsou samostatný náklad. Stará code metadata podpory řady PS 9 nenahrazují test přesné sestavy 9.2 s vlastním frontendem.

Aktuální potřebné odpovědi jsou na začátku OTAZKY-NA-RANO.md. Hosting zůstává WEDOS podle uživatele, migrátor ani Warehouse nejsou zakoupené; toto už není třeba znovu zjišťovat. Zdrojová verze je potvrzená 1.6.1.13, PHP webu zůstává provozní otázka. Nejprve získat podklady a konkrétní ceny, potom uživatel schválí fakturaci a koupí licence. Žádné nákupy či externí zprávy v analýze neproběhly.
''',
    'SPUSTENI-A-KONTROLY.md': '''
### Uzavření ruční analýzy dostupného kódu — 9. 10. 2026

Do pilotu zahrnout konkrétní nálezy z FTP, nikoli jen obecný průchod homepage:

- Comgate: TLS certifikát/hostitel, vazba objednávka–částka–měna–transakce, opakovaný a opožděný callback, CANCEL/AUTH/PAID, PAID po doručení; existující otevřené platby a staré opakované odkazy. Ověřit, že zaplacená objednávka nevytvoří duplicitní obchodní událost ani nevrátí pokročilý stav.
- Dobírka: schválená sazba 30/49 Kč a limity, CZ/SK/EUR, zaokrouhlení, editace dopravy a množství variant po vytvoření objednávky; příplatek nesmí zmizet ani se zdvojit. Historické částky nezměnit.
- Packeta/DPD: ID místa a reference dopravce v košíku i objednávce, chybějící/zrušené místo, přepnutí země/služby, boxy vs. pobočky, mobil, nový seznam míst, mail/PDF, rozpracované staré zásilky a skutečný CSV/API postup expedice.
- Mergado: feed ITEM_ID produktu/varianty, nové URL a rozvrhy, TLS cenového zdroje, cena kombinace minus základ, slevy a měny před/po případném importu. Staging nesmí měnit ostré externí feedy, ceny či odesílat konverze/refundace.
- Souhlasy: běžný prohlížeč (ne pouze Lighthouse), před souhlasem/odmítnutí/odvolání, GTM a přímé JS i modulové hooky, jazyky a změny stránky/checkoutu. Staré session/souhlasy nepovažovat za automaticky přenositelné.
- Antispam: platný/chybějící/chybný token/action, síťový výpadek, kontakt a účet/host/AJAX. Schválený postup při nedostupnosti služby musí být funkční; starý modul v této situaci propouští.
- Maily/faktury: skutečná precedence theme/modul/root, branding a používané jazyky, místo+dobírkové+platební odkaz současně, číslování a uložené historické součty, bezpečný reset hesla. Shoda PDF šablon není test výsledné faktury.

Čerstvou úplnost záloh, faktický runtime a synchronizaci nových **i změněných** záznamů ověřit samostatně. Statická analýza žádný z těchto provozních testů nevykonala. Podklady a nákupní postup: ZAVER-A-POSTUP.md.
''',
    'MIGRACNI-ANALYZA.md': '''
### Uzavření ruční analýzy dostupného kódu — 9. 10. 2026

Původní text z 5. 10. je zachovaný historický rozbor. Aktuální rozhodnutí a stav podkladů přináší ZAVER-A-POSTUP.md. Verze zdroje 1.6.1.13 je potvrzená nastavením FTP. Všech 414 sledovaných core PHP a samostatně 603 root/tools referencí je shodných s oficiálním tagem v jejich částečně se překrývajícím rozsahu. Dostupných sedm kořenových overrides má vysvětlený účel. Theme 543 a PDF 38 referencí jsou shodné; všechny SQL očekávané produktové originály a přílohy jsou dostupné.

Staré dm platební moduly jsou od Davida Mrózka / Valašince, nikoli Shaim. Nejprve ověřit aktualizace jeho licencí. DPD/reCAPTCHA/cookies jsou Shaim; jejich nárok na aktualizace řešit samostatně. Warehouse není koupený a pro schválený vlastní návrh jej nyní nedoporučuji kupovat. MigrationPro Ultimate je konkrétní kandidát, stále podmíněný potvrzením přesné sestavy a synchronizace. Staré informace o dosud nedotažených důležitých složkách už neplatí pro dostupnou momentku, **celá záloha ale není ověřená**. PHP, licence, externí integrace a skutečné chování nových modulů zůstávají provozní kontrola a pilot.

Podrobnosti: FTP-KOD-A-INTEGRACE.md, PLATBY-A-MAILY-FTP.md, DOPRAVA-FEEDY-FTP.md a OVERRIDES-DPD-ANTISPAM.md. Nákupy STAV-A-NAKUPY.md, aktuální otázky OTAZKY-NA-RANO.md a nové konkrétní testy SPUSTENI-A-KONTROLY.md. Analýza nevykonala zdrojový PHP/SQL a nezasáhla původní frontend ani produkci.
'''
}

PRIORITIES = '''
## Aktuální priority po dokončení FTP analýzy — 9. 10. 2026

Už víme: migrátor a Warehouse nejsou koupené, web zůstává u WEDOS a máš přístup; zdrojové PHP je v kopii a verze **1.6.1.13** je potvrzená. Autoři hlavních modulů i účel sedmi overrides jsou zjištěné. Tyto odpovědi nemusíš opakovat. Historický seznam níže zůstává pro podrobnosti, není seznam všech znovu potřebných odpovědí.

Začni těmito podklady (bez hesel, tokenů a zákaznických údajů):

1. **FTP:** je fronta dokončená a nejsou nevyřešené neúspěšné přenosy? Počet souborů sám nestačí. Je dostupný vzdálený soupis nebo hostingová záloha a možnost obnovy?
2. **WEDOS:** přesný tarif, současné PHP webu, memory_limit a možnost samostatného cílového PHP/hostingu. Stačí zakrytý screenshot; nic nepřepínej. Dále názvy a frekvence cronů bez URL s klíči.
3. **Migrátor:** přesný odkaz, který jsi našel. Připravený dotaz dodavateli v STAV-A-NAKUPY.md potřebuje potvrzení 9.2, zdrojového/cílového PHP, staging licence, ID/hesel/dokladů a přenosu nových i změněných dat. Nákup až potom; nic jsem dodavateli neposlal.
4. **Licence:** klientův nárok na aktualizace Valašinec (Comgate/dobírka) a Shaim (DPD/reCAPTCHA/cookies). Stačí dostupné verze a potvrzení nároku, neposílat přihlášení.
5. **Obchodní pravidla:** závazná doprava zdarma 1 500 / 1 699 / 3 000 Kč, dobírka 30 / 49 Kč a limity, odeslání 48 hodin / čtyři pracovní dny, CZ/SK/EUR, DPH a vlastní potisk. Zdrojový SQL a texty si odporují.
6. **Provoz:** způsob expedice Packety/DPD, párování převodů, počet rozpracovaných plateb/zakázek/zásilek a případné účetnictví/sklad mimo shop. Stačí postupy a počty bez konkrétních zákazníků.
7. **Mergado a souhlasy:** používá se cenový import, které feedy/měření běží, která lišta je skutečně zapojená a kdo to spravuje? V dodaném SQL mají oba Shaim seznamy blokovaných modulů hodnotu serializované false; Usercentrics setting záznam se nenašel. Nejde o runtime důkaz chování celého webu.
8. **Pilot:** testovací zákaznický účet, jehož heslo znáš (heslo neposílat), člověk pro ověření CZK/EUR platby a expedice, termín a závazné funkce prvního spuštění. Je nutná fotografie podle barvy a nové filtry hned, nebo se mohou doplnit postupně?

**Nákupní závěr:** Warehouse nekupovat pro nynější rozsah. MigrationPro je vhodný kandidát až po uvedeném potvrzení. U provozních modulů nejprve aktualizace stávajících licencí. Nejlehčí postup a odhad jsou v [ZAVER-A-POSTUP.md](ZAVER-A-POSTUP.md).

Při mém čtení nestandardní konfigurace se jeden citlivý řádek chybně zobrazil ve výstupu nástroje. Filtr je opravený a údaj není v reportech. Doporučuji příslušný údaj změnit, bez zasílání jeho hodnoty. Nebyla testovaná platnost ani provedena produkční změna.

## Historický podrobný seznam otázek

'''

def main():
    for name, note in NOTES.items():
        path = HERE / name
        old = path.read_text(encoding='utf-8')
        if STAMP not in old:
            path.write_text(old.rstrip() + '\n\n' + note.strip() + '\n', encoding='utf-8')
    question_path = HERE / 'OTAZKY-NA-RANO.md'
    old = question_path.read_text(encoding='utf-8')
    if '## Aktuální priority po dokončení FTP analýzy' not in old:
        title, rest = old.split('\n', 1)
        question_path.write_text(title + '\n\n' + PRIORITIES.strip() + '\n\n' + rest.lstrip(), encoding='utf-8')
    original_path = HERE / 'MIGRACNI-ANALYZA.md'
    old = original_path.read_text(encoding='utf-8')
    pointer = '> Aktuální závěr po FTP analýze z 9. 10.: [ZAVER-A-POSTUP.md](ZAVER-A-POSTUP.md). Níže je zachovaný původní rozbor s pozdějším datovaným doplněním na konci.'
    if pointer not in old:
        title, rest = old.split('\n', 1)
        original_path.write_text(title + '\n\n' + pointer + '\n\n' + rest.lstrip(), encoding='utf-8')
    matrix_path = HERE / 'module-matrix.csv'
    with matrix_path.open(encoding='utf-8-sig', newline='') as stream:
        reader = csv.DictReader(stream)
        fields, rows = reader.fieldnames, list(reader)
    updates = {
        'dm_comgate': (
            'Autor David Mrózek / Valašinec potvrzen z FTP; nejprve aktualizace licence. Současný podporovaný modul, TLS a stavový pilot; otevřené transakce a staré odkazy řešit zvlášť.',
            'Výrobce nabízí PS9 rodinu; přesná 9.2 a společná sestava čekají na potvrzení/pilot. Starý 3.3.2 deklaruje jen 1.6.',
            'https://prestashop.valasinec.cz/platebni-moduly-a-brany/108-comgate-platebni-brana-modul-prestashop-17.html'),
        'dm_cashondelivery': (
            'Autor David Mrózek / Valašinec; aktualizace licence před novým nákupem. Kořenový Order override potvrzen; sazby a zachování příplatku po editaci testovat.',
            'Výrobce nabízí PS9 rodinu; přesná 9.2 ověřit pilotem. SQL aktivní CZ 49 Kč vs veřejný text 30 Kč.',
            'https://prestashop.valasinec.cz/platebni-moduly-a-brany/83-dobirka-s-priplatkem-a-vazbou-na-dopravce-prestashop-17.html'),
        'shaim_dpdparcelshop': (
            'Aktualizace stávající Shaim licence; místo k id_cart, reference dopravců, cron, maily/PDF; expedici potvrdit zvlášť.',
            'Výrobce deklaruje PS9/Hummingbird; přesnou 9.2 a mobilní checkout ověřit pilotem.',
            'https://psmoduly.cz/dpd-parcel-shop-pick-up-vyber-odberneho-mista-primo-v-objednavce-alzaboxy-242/vydejni-mista.htm'),
        'shaim_recaptcha_v3': (
            'Současná podporovaná ochrana; stávající licence nejprve. Kořenové Auth/Contact potvrzené; starý modul při síťovém výpadku propouští.',
            'Balíček deklaruje rozsah do 9.99.99, přesná 9.2/host/AJAX/výpadek vyžadují pilot.',
            'https://psmoduly.cz/44-zabezpeceni'),
        'shaim_cookies_eu': (
            'Vybrat jednu CMP a obnovit skutečné blokování měření. Kořenový Hook potvrzen; oba seznamy modulů v dumpu serializované false.',
            'Cílovou kompatibilitu a přímé JS/GTM otestovat; staré sessions/souhlasy nepřenášet automaticky.',
            'https://psmoduly.cz/'),
        'usercentrics': (
            'Prověřit skutečné použití; záznam setting ID v dumpu nenalezen. Vybrat jednu CMP a testovat měření.',
            'Aktivní/přiřazený modul sám neprokazuje běžící CMP ani kompatibilitu 9.2.', ''),
        'mergado': (
            'Současný Mergado Pack; feed cron i zapisující cenový import, produktová/variantní ID, souhlasy a měření. Starý cenový import vypíná TLS.',
            'Bezplatný aktuální modul deklaruje PS9/PHP8+; vybranou 9.2 sestavu a aktivované služby ověřit.',
            'https://pack.mergado.cz/prestashop'),
    }
    for row in rows:
        if row['name'] in updates:
            row['proposed_action'], row['compatibility_evidence'], row['source'] = updates[row['name']]
    with matrix_path.open('w', encoding='utf-8', newline='') as stream:
        writer = csv.DictWriter(stream, fieldnames=fields)
        writer.writeheader()
        writer.writerows(rows)
    print('Updated analyst reports, current question priorities and module matrix inside migration.')

if __name__ == '__main__':
    main()
