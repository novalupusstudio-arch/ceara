# Deploy productie

## Actualizare peste datele existente

Productia are date reale. Nu rula `deploy/sql/init-production.sql` si nu importa baza DEV peste baza de productie. Scriptul de reset ramane numai local si este exclus din pachetul de productie.

1. Testeaza aceeasi versiune pe STAGE, folosind o copie recenta a bazei de productie. Verifica predarea si returul la fabrica, NIR/AVIZ, registrul de gestiune si raportul de incasari.
2. Fa backup la baza de productie si la folderul aplicatiei, inclusiv `storage/`, `uploads/` si `config/local.php`. Verifica faptul ca backupurile pot fi restaurate.
3. Verifica `deploy/local/config.php` inainte de construirea pachetului: acesta devine `config/local.php` in arhiva si trebuie sa indice baza de productie.
4. Construieste pachetul si verifica faptul ca nu contine `deploy/sql/init-production.sql`.
5. In fereastra de mentenanta, inlocuieste numai fisierele aplicatiei cu cele din arhiva. Pastreaza datele din `storage/` si `uploads/`; nu sterge folderul aplicatiei si nu reseta baza de date.
6. La prima accesare, aplicatia executa migrarile incrementale prin `Database::migrateAndSeed()`. Verifica erorile, apoi fluxurile principale si documentele deja emise.
7. Daca apar probleme, revino la backupul codului si, daca migrarile au schimbat schema, restaureaza si backupul bazei facut in acelasi moment.

## Pachetul zip

Construieste zip-ul doar cand se cere explicit:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\build-production-zip.ps1
```

Scriptul include in arhiva:

- codul aplicatiei
- `vendor/`
- `release/siruta.csv`
- documentatia de deploy
- `deploy/local/config.php` injectat ca `config/local.php`

Nu include resetul SQL, `storage/` sau `uploads/`.

`deploy/local/config.php` este ignorat de Git si trebuie sa existe local pe masina care construieste zip-ul.

## Reset local, niciodata in productie

Fisier:

- `deploy/sql/init-production.sql`

Acest script este distructiv pentru baza selectata si nu se foloseste la actualizarea productiei:

- sterge toate tabelele aplicatiei
- recreeaza schema curenta
- insereaza doar:
  - userul `admin`
  - permisiunile standard
  - drepturile standard pentru `admin` si `operator`

Nu creeaza:

- gestiuni
- procesatori
- clienti
- furnizori
- loturi
- documente
- tranzactii de stoc
- audit operational

SIRUTA si template-urile de documente sunt seed-uite de aplicatie la prima rulare.

## Primul login dupa un reset local

- user: `admin`
- parola: `CearaAdmin!2026`

Schimba parola imediat dupa primul login.

## Configurare dupa un reset local

Ordinea recomandata:

1. `Setari -> Date societate`
   - date societate
   - FGO URL
   - FGO token
2. `Setari -> Procesatori`
3. `Setari -> Gestiuni`
   - cod gestiune
   - seria FGO
   - procesator asignat
   - pret/scazamant procesare client
   - pret/scazamant achizitie
4. `Setari -> Creare useri`
   - asignezi gestiunile la useri
5. `Setari -> Serii documente`

## Important

- aplicatia nu mai are fallback-uri silentioase pentru configurari critice
- daca lipsesc procesatorul gestiunii, seria FGO, seriile interne sau tokenul/URL-ul FGO, fluxurile vor da eroare explicit
- asta este intentionat

## Migrari incrementale

La deschiderea aplicatiei, `Database::migrateAndSeed()` ruleaza schema si migrarile conditionale. Testeaza-le pe copia recenta a bazei de productie in STAGE inainte de deploy; nu aplica manual resetul SQL.
