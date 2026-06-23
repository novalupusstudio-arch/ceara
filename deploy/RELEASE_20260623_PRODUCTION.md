# Release productie - 2026-06-23

## Scop

Acest release este gandit pentru reset complet:

- se sterg toate fisierele vechi din folderul aplicatiei de pe server
- se dezarhiveaza integral arhiva noua
- se ruleaza reset complet de baza de date

## Artefacte

- zip aplicatie: `deploy/output/ceara-production-<timestamp>.zip`
- script reset/init DB: `deploy/sql/init-production.sql`
- config inclus in zip: `config/local.php`, generat din `deploy/local/config.php`

## Ce contine zip-ul

- codul aplicatiei
- `assets/`
- `views/`
- `lib/`
- `config/`
- `db/schema.sql`
- `vendor/`
- `release/siruta.csv`
- `deploy/sql/init-production.sql`
- documentatia de deploy

## Ce nu se bazeaza pe server

Nu este necesar:

- Composer pe server
- fisiere locale vechi ramase din release-uri anterioare
- date vechi in baza

## Reset DB

`deploy/sql/init-production.sql`:

1. sterge toate tabelele aplicatiei
2. recreeaza schema curenta
3. insereaza doar admin + permisiuni + roluri standard

## Login initial

- user: `admin`
- parola: `CearaAdmin!2026`

## Configurare minima dupa login

1. schimbi parola admin
2. completezi `Date societate`
3. creezi `Procesatori`
4. creezi `Gestiuni`
5. asignezi gestiunile la useri
6. configurezi `Serii documente`
7. configurezi seria FGO pe fiecare gestiune

## Observatii

- aplicatia nu mai are fallback-uri silentioase pentru configurari critice
- lipsa configurarii trebuie rezolvata din setari, nu este completata automat de sistem
