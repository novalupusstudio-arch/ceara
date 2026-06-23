# Ceara

PHP + MySQL operational app for:

- processing customer wax held in custody
- purchasing company-owned wax stock

## Repository

- GitHub: `https://github.com/novalupusstudio-arch/ceara.git`
- Branch: `main`
- Current source path used on this machine: `D:\Novalupusstudio\ceara`

## Local Runtime

- XAMPP Apache + MySQL
- plain PHP app
- Dompdf and runtime dependencies are committed in `vendor/`
- app creates/migrates schema on startup
- app requires explicit DB config through ignored `config/local.php` or env vars

## Local Setup On A New PC

1. Clone repository.
2. Start Apache and MySQL in XAMPP.
3. Create ignored `config/local.php` with DB credentials.
4. Create ignored `config/xampp-target.local.txt` with local htdocs path.
5. Run sync:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\sync-to-xampp.ps1
```

6. Open:

`http://localhost/ceara/`

7. Login:

- user: `admin`
- password: `admin`

Important:

- local startup no longer auto-creates store/processor defaults
- after first login, admin must configure processors, stores, user-store assignments and document series

## Ignored Local Files

- `config/local.php`
- `config/xampp-target.local.txt`
- `deploy/local/config.php`

Do not commit local secrets.

## Main Features

Processing flow:

- create processing lots
- movement-based lot state
- exchange wax with client
- return wax to client
- batch factory delivery
- factory buffer plus/minus
- processing register
- generated PDF documents
- FGO invoice integration
- FiscalWire `.inp` receipt download

Purchase flow:

- create purchase lots for `PF`, `Producator agricol`, `PJ/PFA`
- keep external document references
- separate purchased wax stock `wax_purchased`
- record purchased wax exits
- purchase register with opening/closing/current stock

Settings:

- company data
- FGO URL/token
- stores/gestiuni
- processors
- document series
- document templates
- users / roles / permissions

## Critical Business Rules

- `wax_custody` and `wax_purchased` must never mix
- settings are admin-only
- operators should only see operational errors and ask admin to fix setup
- critical config has no silent fallback anymore:
  - store processor
  - processing defaults
  - internal document series
  - FGO series
  - FGO URL/token/CUI

## Important Runtime Support In Repo

- `vendor/`
- `release/siruta.csv`
- `db/schema.sql`
- `deploy/sql/init-production.sql`
- `scripts/sync-to-xampp.ps1`
- `scripts/build-production-zip.ps1`

## Production

Production deploy guidance:

- `deploy/DEPLOY_PRODUCTION.md`

Production DB reset/init:

- `deploy/sql/init-production.sql`

Production seed login:

- user: `admin`
- password: `CearaAdmin!2026`

Change password immediately after first login.

## Read Order For A New Codex

1. `AI_HANDOVER.md`
2. `PROJECT_CONTEXT.md`
3. `docs/spec.md`
4. `docs/source-specs/03-settings.md`
5. `docs/source-specs/13-database-schema.md`
6. `deploy/DEPLOY_PRODUCTION.md` when production is involved
