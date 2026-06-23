# Deploy productie

## Modelul curent de deploy

Acest proiect este pregatit acum pentru deploy complet, de la zero:

1. stergi toate fisierele vechi din folderul aplicatiei de pe server
2. incarci arhiva noua
3. dezarhivezi tot continutul in folderul aplicatiei
4. rulezi resetul complet al bazei de date
5. intri in app si configurezi datele operationale din setari

Folder tinta:

- `../ceara`

## Pachetul zip

Construieste zip-ul doar cand se cere explicit:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\build-production-zip.ps1
```

Scriptul include in arhiva:

- codul aplicatiei
- `vendor/`
- `release/siruta.csv`
- `deploy/sql/init-production.sql`
- documentatia de deploy
- `deploy/local/config.php` injectat ca `config/local.php`

`deploy/local/config.php` este ignorat de Git si trebuie sa existe local pe masina care construieste zip-ul.

## Reset complet DB

Fisier:

- `deploy/sql/init-production.sql`

Acest script este distructiv pentru baza selectata:

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

## Primul login dupa reset

- user: `admin`
- parola: `CearaAdmin!2026`

Schimba parola imediat dupa primul login.

## Configurare dupa primul login

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

## Update incremental ulterior

Dupa ce productia va avea date reale, nu mai folosi `deploy/sql/init-production.sql` pentru update-uri.

Pentru update-uri ulterioare, ruleaza doar scripturile SQL punctuale pregatite pentru acea versiune.
