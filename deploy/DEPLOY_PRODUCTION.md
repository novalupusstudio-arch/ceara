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

## Initializare DB

1. Incarca zip-ul nou pe server si dezarhiveaza continutul in folderul aplicatiei.
2. In phpMyAdmin sau consola MySQL, selecteaza baza de date goala.
3. Ruleaza scriptul `deploy/sql/init-production.sql`.
4. Deschide aplicatia in browser.

Scriptul creeaza schema si datele minime:

- utilizatorul `admin`
- permisiunile standard
- rolurile `admin` si `operator`

Nu creeaza gestiuni, procesatori, loturi, avize, documente sau tranzactii de stoc.
SIRUTA este importat din `release/siruta.csv` la prima rulare a aplicatiei.

## Primul login

- User: `admin`
- Parola initiala: `CearaAdmin!2026`

Schimba parola imediat dupa primul login.

## Configurare dupa login

1. Creeaza procesatorul/fabrica din `Setari`, daca folosesti fluxul de procesare.
2. Creeaza gestiunea si asigneaza procesatorul pentru procesare.
3. Asigneaza gestiunea la utilizatorul `admin` sau creeaza utilizatori noi.
4. Configureaza seriile de documente generate automat pentru gestiune.
5. Completeaza datele societatii, cheia FGO si setarile implicite de achizitie.
