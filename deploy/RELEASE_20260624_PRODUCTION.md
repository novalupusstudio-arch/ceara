# Release productie - 2026-06-24

## Versiune aplicatie

- `1.2.005`

## Tip release

Prima lansare de productie, de la zero:

- se sterg toate fisierele vechi din folderul aplicatiei
- se incarca si se dezarhiveaza integral arhiva noua
- se ruleaza reset complet / initializare completa de baza de date

## Artefacte

- arhiva productie: `deploy/output/ceara-production-<timestamp>.zip`
- script init DB: `deploy/sql/init-production.sql`
- config inclus in zip: `config/local.php`, generat din `deploy/local/config.php`

## Ce contine arhiva

- codul aplicatiei
- `assets/`
- `views/`
- `lib/`
- `config/`
- `vendor/`
- `release/siruta.csv`
- `deploy/sql/init-production.sql`
- documentatia de deploy si handover

## Login initial dupa init

- user: `admin`
- parola: `CearaAdmin!2026`

## Configurare minima dupa primul login

1. schimbi parola admin
2. completezi `Setari -> Date societate`
   - inclusiv `FGO URL`
   - inclusiv `FGO token`
3. creezi `Procesatori`
4. creezi `Gestiuni`
5. asignezi gestiunile la useri
6. configurezi `Serii documente`
7. verifici seria FGO pe fiecare gestiune

## Observatii importante

- aplicatia ruleaza fara fallback-uri silentioase pentru configurari critice
- lipsa seriilor, a FGO sau a contextului operational trebuie rezolvata explicit din setari
- cantitatile sunt stocate in grame si afisate in kg
- operatiile sunt legate de gestiunea userului conectat
- procesatorul este selectabil in operatiile de procesare/fabrica

## Flux recomandat dupa lansare

1. date reale doar in `PROD`
2. development continuu in `DEV`
3. verificare release in `STAGE`
4. backup PROD inainte de fiecare deploy ulterior
