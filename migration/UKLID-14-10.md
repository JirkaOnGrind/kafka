# Jednorázový úklid — 14. 10. 2026

Na výslovný pokyn uživatele uvolněno **4 144 164 344 bajtů = 4 144,16 MB (3 952,18 MiB)** odstraněním 18 nadbytečných souborů. Jde o součet skutečných délek odstraněných souborů, nikoli odhad podle volného místa celého disku. Úklid je jednorázová výjimka ze zachovávání starých záloh; nezavádí automatické mazání.

## Odstraněno

- Starší pracovní šifrovaný archiv `local/backups/cloud-ready-20261014-142327/` a jeho metadata. Nahrazuje jej zachovaný finální balíček; starší klíč zůstal v `local/private/`.
- Nadbytečný `local/backups/cloud-ready-20261014-final.rar`. Všech 15 položek, které z něj archivní nástroj načetl, bylo po jednotlivých souborech porovnáno a shodovalo se s existujícími soubory projektu. Finální šifrovaný balíček i doplněk ZIP jsou zachované samostatně.
- Pracovní archiv `implementation-20261014-142208/project.tar.gz` a jeho soupis otisků. Žádný z jeho souborů nechybí v novější zachované projektové záloze `recovery-set-20261014-final/project.tar.gz`.
- `project-before-migration-20261014-135149/project.tar.gz` a jeho soupis otisků. Archiv je podle SHA-256 totožný se zachovaným `20261014-135120/project.tar.gz`.
- SQL a shopové archivy dvou starších záloh `20261012-120210` a `20261012-120329`, včetně jejich již neplatných soupisů otisků. Oba shopové archivy byly totožné; novější kompletní zálohy z 14. 10. zůstaly. Malé soubory `.env` a `compose.yaml` z 12. 10. byly zachované; tyto složky už nejsou kompletní obnovovací sadou.
- Pět obnovitelných souborů Python cache v `migration/__pycache__/`.

## Zachováno

- `local/backups/20261014-135120/`: kompletní záloha připojená k aktuální obnovené kopii PS 9.
- `local/backups/source-original-20261014-135846/`: kompletní původní FTP a SQL, připojené k izolovanému migračnímu zdroji.
- `local/backups/recovery-set-20261014-final/`: finální nešifrovaná obnovovací sada PS 9 včetně projektu.
- Celá `local/backups/cloud-ready-20261014-final/`: finální šifrovaný balíček, JSON, doplněk ZIP a obnovovací návod.
- Veškeré soukromé klíče, `.env`, pracovní zdroj, Docker volumes a obrazy, instalační balíčky, Git historie, migrační podklady, grafická předloha, assety, šablony a vlastní modul.

## Ověření

Finální šifrovaný archiv před mazáním prošel skutečnou autentizací AES-GCM, průběžným dešifrováním a porovnáním obou SHA-256 otisků. Kontrolní součty finální nešifrované obnovovací sady také souhlasily.

Všech 519 chráněných souborů (nativní šablony a modul, předloha, grafika, skripty, soukromé soubory, zdrojový runtime, hlavní `.env`, Compose a BAT soubory, místní konfigurace a stavové soubory) mělo po úklidu stejné SHA-256 jako před ním. Aktivní připojení kontejnerů byla před úklidem prověřena; žádný používaný zálohový adresář nebyl odstraněn. Nebyl proveden restart, import, změna DB ani zásah do Docker volumes nebo produkčního webu.

Po úklidu odpověděly HTTP 200 všechny čtyři místní homepage: vývojový shop 8080, předloha 4173, obnovený PS 9 na 8081 a izolovaný zdroj na 8082. Vzhledové a aplikační soubory se nezměnily. Soukromý přesný seznam odstraněných souborů je v `local/cleanup-deleted-20261014.json`; výsledek v `local/cleanup-result-20261014.json`.

Upload finálního balíčku na cloud a ověření stažené cloudové kopie tím nejsou potvrzené. Původní místní finální balíček zůstává připravený pro tento krok podle `OBNOVA-A-ZALOHY.md`.
