# Otázky pro klienta — příprava migrace Kafka Monster

Text k předání klientovi. Nebyl odeslán. Verzi zdroje, hosting, PHP a seznam modulů již známe; není potřeba je znovu zjišťovat. Odpovědi stačí stručné, bez zákaznických údajů, přístupových hesel a API klíčů.

Dobrý den, připravujeme přenos současného e-shopu do nového PrestaShopu a schváleného vzhledu. Prosím o potvrzení těchto provozních pravidel:

1. **Doprava:** které dopravce a země zachovat při spuštění? U každé kombinace dopravce/země prosím cenu dopravy, hranici dopravy zdarma, cenu dobírky a případné limity nebo výjimky. V současných podkladech se liší hranice zdarma 1 500 / 1 699 / 3 000 Kč a dobírka 30 / 49 Kč; potřebujeme jednu platnou tabulku.
2. **Platby a měny:** zachováme Comgate, bankovní převod a dobírku? Má být nadále možné objednávat v CZK i EUR a které země skutečně obsluhujeme?
3. **Expedice a účetnictví:** jak dnes vznikají zásilky Packety a DPD — ručně, přes CSV export nebo API? Jak tisknete štítky, párujete převody a vystavujete faktury? Používáte externí účetní/skladový systém? Jak řešíte rozpracované vlastní zakázky a potisk? Stačí popis postupu.
4. **Ceny a daňové nastavení:** potvrďte s účetní aktuální režim DPH a způsob zobrazování cen. Jaká je platná doba odeslání: 48 hodin, čtyři pracovní dny, nebo jiná? Kdo schválí dopravu, obchodní podmínky a fakturu před spuštěním?
5. **Licence a integrace:** máte nárok na aktualizace Comgate/dobírky od Valašince a modulů Shaim pro DPD, reCAPTCHA a cookies? Je aktivní předplatné Usercentrics? Používáte Mergado jen pro export feedů, nebo i pro import cen? Které měření a marketingové integrace musí pokračovat? Nepotřebujeme přihlašovací údaje v odpovědi.
6. **První spuštění:** které nové funkce jsou nezbytné hned? Zejména nové filtry a fotografie podle barvy/varianty: jejich doplnění není součástí prostého přenosu dat. Počítáme s celým katalogem, variantami, účtem, dopravou, platbami a mobilním nákupem.
7. **Testování:** kdo za vás projde testovací nákup, porovná ceny/fakturu a vyzkouší expedici? Je dostupný testovací zákaznický účet se známým heslem? Heslo prosím neposílejte; přihlášení ověří držitel účtu.
8. **Termín a odstávka:** který den a čas má nejméně objednávek? Souhlasíte s plánovaným uzavřením nákupu pro závěrečné testování, případně až na jeden den? Většinu práce provedeme lokálně předem a dobu odstávky zpřesníme po nácviku. Kdo rozhodne o otevření nového shopu, nebo návratu ke starému?

Samostatně rozpočtujeme PrestaHero MigrationPro Ultimate podle aktuální ceny při nákupu. Vývoj proběhne na PC a nový shop se nasadí na současný WEDOS Extra; druhý hosting nyní nepořizujeme. Před přepnutím sepíšeme i všechny otevřené objednávky, platby a zásilky, aby během změny žádná nezůstala bez zpracování.
