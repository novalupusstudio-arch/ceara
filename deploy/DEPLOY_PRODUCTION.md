# Deploy productie

## Server tinta

- Folder aplicatie: `../ceara`
- Baza de date: creata manual in hosting
- Webroot: folderul aplicatiei, daca Apache permite `.htaccess`

## Pachet zip

Nu folosi zip-uri vechi din `deploy/output/`. Folderul de output este ignorat si
arhivele existente pot fi ramase din commituri anterioare. Construieste un zip
nou doar cand se cere explicit deploy pe productie.

Pachetul se construieste local cu:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\build-production-zip.ps1
```

Scriptul copiaza aplicatia, include `vendor/`, `release/siruta.csv` si
`deploy/local/config.php` ca `config/local.php`, apoi creeaza arhiva in
`deploy/output/`.

`deploy/local/config.php` este ignorat de Git si trebuie sa existe local pe masina
care construieste zip-ul. Template-ul versionat este `deploy/production-config.template.php`.

## Initializare / reset DB

Atentie: `deploy/sql/init-production.sql` este script de reset complet. Pe baza de date selectata el sterge toate tabelele aplicatiei, inclusiv loturi, documente, tranzactii, audit/loguri operationale si orice seed anterior, apoi recreeaza schema curata.

1. Incarca zip-ul nou pe server si dezarhiveaza continutul in folderul aplicatiei.
2. In phpMyAdmin sau consola MySQL, selecteaza baza de date de productie.
3. Ruleaza integral scriptul `deploy/sql/init-production.sql`.
4. Deschide aplicatia in browser.

Scriptul creeaza schema si datele minime:

- utilizatorul `admin`
- permisiunile standard
- rolurile `admin` si `operator`

Nu creeaza gestiuni, procesatori, loturi, avize, documente, tranzactii de stoc sau audit operational. SIRUTA si template-urile de documente sunt importate/seed-uite din fisierele aplicatiei la prima rulare.


## Migrare productie existenta

Dupa ce productia are date configurate sau operationale, nu mai rula `deploy/sql/init-production.sql` pentru update-uri. Pentru schimbari incrementale ruleaza doar scripturile dedicate din `deploy/sql/`.

Pentru modificarile 2026-06-23 legate de seria FGO pe gestiune, seriile de documente si termenii comerciali pe gestiune, ruleaza o singura data, in ordinea de mai jos:

```sql
source deploy/sql/20260623-add-store-fgo-series.sql;
source deploy/sql/20260623-normalize-document-series.sql;
source deploy/sql/20260623-add-store-commercial-terms.sql;
```

In phpMyAdmin, deschide fiecare fisier din lista, copiaza continutul si ruleaza-l pe baza de productie selectata.

## Primul login
- User: `admin`
- Parola initiala: `CearaAdmin!2026`

Schimba parola imediat dupa primul login.

## Configurare dupa login

1. Creeaza procesatorul/fabrica din `Setari`, daca folosesti fluxul de procesare.
2. Creeaza gestiunea si asigneaza procesatorul pentru procesare.
3. Asigneaza gestiunea la utilizatorul `admin` sau creeaza utilizatori noi.
4. Configureaza seriile de documente generate automat pentru gestiune.
5. Completeaza datele societatii si cheia FGO.
6. In `Setari -> Gestiuni`, completeaza `Cod`, `Seria FGO`, procesatorul default, scazamantul/pretul de procesare si scazamantul/pretul de achizitie pentru fiecare gestiune.
