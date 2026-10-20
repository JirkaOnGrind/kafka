# Ranní stav analýzy — 8. 10. 2026, 10:36 Europe/Prague

## Závěr a hranice ověření

Z dosavadního SQL auditu a veřejného webu vychází jako nejsnazší cesta čistý PrestaShop 9.2, přenos standardních dat se zachováním ID a URL a převod schváleného návrhu do nativní šablony. Přímé přepsání staré instalace nedoporučuji. Nejde o provedenou ani pilotně ověřenou migraci.

Kontrola v 10:36 našla 22 435 souborů, z toho 22 391 v cache. Proti předchozí kontrole přibylo 431 souborů. Přenos tedy podle lokální inventury pokračuje; není důvod označit jej za zastavený. Stav fronty, chyby FTP klienta ani vzdálený manifest nejsou dostupné, proto úplnost zálohy není potvrzená.

Upřesnění v 10:55: plánovač spustil další kontrolu i po předpokládaném konci nočního intervalu. Předchozí oznámení konce sledování bylo předčasné. Tento dokument je datovaný průběžný stav, nikoli potvrzení ukončené automatizace nebo dokončeného auditu.

V kořeni shopu jsou zatím adresáře Adapter, cache a Core. Konfigurace, classes, controllers, modules, override, themes, mails, pdf, img, download a upload dosud nejsou v této kopii dostupné. To není zjištění, že chybějí na serveru. Celou hlubokou analýzu kódu proto nebylo možné dokončit.

## Co už má oporu v důkazech

- SQL obsahuje 3 296 produktů (3 194 aktivních), 11 786 kombinací včetně dvou osiřelých, 582 zákaznických záznamů a 574 objednávek. Inventury a datové výjimky jsou v MIGRACNI-ANALYZA.md.
- Z dostupného FTP bylo 36 sledovaných souborů shodných s oficiální referencí 1.6.1.13. Dalších 378 sledovaných referenčních PHP souborů dosud není dostupných. Nelze potvrdit nepřítomnost úprav v celém jádru. Verze 1.6.1.13 je zatím údaj z SQL; settings.inc.php ji ještě nepotvrdil.
- Registr cache uvádí sedm override cest. Jejich těla, skutečný současný účel a původ ještě nejsou ověřené.
- Posouzeny jsou dostupné routy, robots a sitemap. Staré sitemap nejsou úplným aktuálním seznamem katalogu.
- SQL očekává 4 962 produktových obrázků a devět příloh. Jejich soubory nelze ověřit, dokud nedorazí img a download.

## Nejrychlejší další krok v FTP klientu

Při zachování běžící zálohy stáhnout přednostně následující části `www/domains/kafka.monster` do stejné odpovídající lokální struktury, případně jako samostatnou kopii a sdělit její cestu. Na serveru nic nemazat ani neupravovat.

1. `config`, `override`, `classes`, `controllers` — skutečná verze a vlastní zásahy do cen, objednávek, registrace a checkoutu.
2. `modules` — zejména dm_comgate, dm_cashondelivery, packetery, shaim_dpdparcelshop, mergado a antispam/cookies moduly; pro úplnost pak celý adresář modulů.
3. `themes`, `mails`, `pdf` a vlastní soubory v kořeni — šablony, faktury, texty, feedy a další vstupní skripty.
4. `img`, `download`, `upload` — obrázky, přílohy a další média. Velká cache nemusí být hotová před analýzou těchto částí.

Do chatu neposílat obsah settings.inc.php, hesla ani API klíče. Stačí cesta ke kopii a stav přenosu. Po dokončení ověřit frontu i nezdařené přenosy; samotný neměnný počet lokálních souborů nestačí.

## Obchodní funkce a cílová náhrada

Tato mapa vychází z SQL a předchozího pozorování webu. Označuje cílový způsob řešení, nikoli již prokázanou kompatibilitu konkrétních modulů nebo vysvětlení dosud nedostupného kódu.

| Funkce | Cíl v PS 9.2 | Co zbývá ověřit |
| --- | --- | --- |
| Katalog, varianty, příplatky, slevy, sklad | Standardní entity PS a migrátor se zachováním vazeb/ID | Pilot, dvě osiřelé kombinace, ceny a sklad |
| Účty a historie objednávek | Migrace zákazníků, hesel, objednávek, plateb a faktur | Přihlášení, účetní součty, historie odstraněných produktů |
| Schválený vzhled | Vlastní nativní šablona na základu Hummingbird pro 9.2 | Napojení prototypu na reálná data a checkout |
| Comgate | Podporovaný modul pro přesné PS 9.2 a zvolené PHP | Dodavatel dm_comgate, callbacky, historické reference, otevřené platby |
| Dobírka a bankovní převod | Kompatibilní platební moduly, zachované obchodní poplatky | Účel dm_cashondelivery a případných overrides, příplatek a fakturace |
| Packeta a DPD | Kompatibilní výběr místa a případně tvorba zásilek/štítků | Uložená ID míst, rozpracované zásilky, skutečně používané funkce |
| Mergado a vlastní feedy | Podporovaný modul nebo nová implementace konkrétního feedu | Cron, stabilní produktová/variantní ID, příjemci a formáty |
| Antispam a souhlasy | Podporované moduly a jeden zamýšlený souhlasový mechanismus | Auth/Contact úpravy, spuštění měření podle souhlasu |
| Zakázková výroba | Zachování procesu domluvy a následného objednání produktu | Potvrzení klientova pracovního postupu |
| Maily a faktury | Cílové šablony s potřebným obsahem a správnými údaji | Odlišnosti původních šablon a číslování |
| SEO | Zachované adresy, konkrétní 301 mapa změn a nové sitemap | Finální URL testy, jazyky a indexovatelnost CMS |

## Nákupy a pořadí migrace

Warehouse pro nynější schválený vlastní návrh nekupovat. MigrationPro Ultimate od PrestaHero zůstává doporučeným kandidátem; nákup zatím podmínit potvrzením přesné verze 9.2.0, zdrojového PHP, režimu závěrečné synchronizace a použití licence na stagingu. Předchozí doporučení nepředstavuje záruku úspěšného přenosu vlastních modulových tabulek. Připravený dotaz je v STAV-A-NAKUPY.md a nebyl odeslán.

Po dokončení kódového auditu: ověřit WEDOS tarif a PHP → zvolit oddělené prostředí → potvrdit kompatibilní sestavu → pilotně migrovat → napojit frontend → otestovat ceny, dopravu, platby, účty a faktury → nacvičit přenos nových i změněných dat → teprve potom naplánovat přepnutí a návrat při problému. Orientační odhad 8–18 pracovních dnů není závazný termín; účel overrides a integrací ještě může rozsah změnit.

Od uživatele jsou nyní potřeba stav fronty a chyb FTP, dostupnost uvedených prioritních složek a údaje o tarifu/PHP ve WEDOS. Všechny další otázky jsou připraveny v OTAZKY-NA-RANO.md. Zdrojová kopie i původní frontend zůstaly beze změny; nic nebylo spuštěno na produkci ani zakoupeno.

Navazující podklady: FTP-ANALYZA.md, FTP-KOD-A-INTEGRACE.md, MIGRACNI-ANALYZA.md, STAV-A-NAKUPY.md, OTAZKY-NA-RANO.md a SPUSTENI-A-KONTROLY.md.

## Večerní aktualizace — 8. 10. 2026, 23:17 Europe/Prague

Předchozí ranní stav zachovává historickou momentku. Nově dorazily hlavní třídy, controllery a konfigurace: settings.inc.php potvrzuje PrestaShop **1.6.1.13** a všech **414 sledovaných PHP souborů jádra** se přesně shoduje s oficiální referencí. V této části nebyly zjištěny obchodní úpravy; rozsah nezahrnuje celý shop.

V kopii je všech **9 SQL příloh** nenulové velikosti a **636 z 4 962 očekávaných produktových originálů**. Obrázky se stále doplňují. Modules, override, themes, mails a pdf dosud nejsou lokálně dostupné; rozbor integrací a sedmi override cest čeká na jejich skutečný kód. Úplnost FTP a obnova zálohy zůstávají neověřené. Nákupní doporučení a orientační odhad se tímto dílčím výsledkem nemění.
