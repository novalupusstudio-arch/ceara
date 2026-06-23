# Release productie - 2026-06-22

## Artefacte generate local

- Zip aplicatie: `deploy/output/ceara-production-<timestamp>.zip`
- Script DB reset/init: `deploy/sql/init-production.sql`
- Config inclus in zip: `config/local.php`, generat din `deploy/local/config.php`

## Ce intra in zip

- cod PHP aplicatie
- `assets/`, `views/`, `lib/`, `config/`, `db/schema.sql`
- `vendor/` cu Dompdf si dependinte PHP, deci nu este necesar Composer pe server
- `release/siruta.csv` pentru seed judete/localitati
- `deploy/sql/init-production.sql` si documentatia de deploy

## Ce nu intra in zip

- `.git/`
- `deploy/local/` si `deploy/output/`
- `storage/`, `uploads/`, loguri si fisiere temporare
- configurari locale/dev: `config/local.php`, `config/fgo.local.php`, `config/xampp-target.local.txt`, `.env*`
- `composer.phar`
- dump-uri/dev data vechi: eliminate din repo

## Reset baza de date productie

Scriptul `deploy/sql/init-production.sql` este distructiv pentru baza selectata:

1. dezactiveaza temporar foreign key checks
2. sterge toate tabelele aplicatiei, inclusiv audit/loguri operationale
3. recreeaza schema curenta
4. adauga doar utilizatorul admin, permisiunile si rolurile standard

Nu insereaza gestiuni, procesatori, clienti, furnizori, loturi, documente, stocuri sau tranzactii.

## Login initial

- User: `admin`
- Parola: `CearaAdmin!2026`

Parola trebuie schimbata imediat dupa primul login.

## Configurare dupa deploy

1. Despacheteaza zip-ul in folderul serverului `../ceara`.
2. Ruleaza `deploy/sql/init-production.sql` in baza de date de productie.
3. Deschide aplicatia in browser.
4. Schimba parola admin.
5. Creeaza procesatorul/fabrica, gestiunea si asigneaza gestiunea la admin.
6. Configureaza seriile documentelor, datele societatii si cheia FGO.
7. In `Setari -> Gestiuni`, completeaza `Cod`, `Seria FGO`, procesatorul default, scazamantul/pretul de procesare si scazamantul/pretul de achizitie pentru fiecare gestiune.
8. Verifica selectia de flux din dashboard: `Procesare` si `Achizitie`.


## Observatii

Acest release porneste productia de la zero. Daca exista fisiere PDF vechi in `storage/` pe server, sterge folderul `storage/` inainte sau dupa deploy ca sa nu ramana documente orfane pe disc.
