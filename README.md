# Ceara

PHP + MySQL operational app for wax processing and wax purchase management.

## Current Status

Implemented active flows:

- `Schimb de ceara` / processing flow
- `Achizitie ceara` / purchase flow

The two flows use separate stock rules. Customer custody wax and purchased company-owned wax must not be mixed.

## Repository

- GitHub: `https://github.com/novalupusstudio-arch/ceara.git`
- Branch: `main`
- Current local source path used during development: `D:\Novalupusstudio\ceara`

## Local Runtime

- XAMPP Apache + MySQL
- PHP plain server-rendered app
- MySQL database is created and migrated by the app on startup
- Dompdf and dependencies are committed in `vendor/`, so Composer is not required for normal local running

## Local Setup On A New PC

1. Clone repository.
2. Start Apache and MySQL in XAMPP.
3. Create this ignored local file:

`config/xampp-target.local.txt`

Content example:

```text
D:\xampp\htdocs\ceara
```

4. Sync to XAMPP:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\sync-to-xampp.ps1
```

5. Open:

`http://localhost/ceara/`

6. Login locally:

- user: `admin`
- password: `admin`

## Local Config Files

Ignored optional local files:

- `config/local.php`
- `config/fgo.local.php`
- `config/xampp-target.local.txt`

Do not commit local secrets.

## Code Structure

The app is plain PHP, but new code should use the `Ceara\` namespaced autoload from `lib/autoload.php`.

Current extracted modules:

- `lib/Integrations/` - FGO and FiscalWire
- `lib/Documents/` - document issuing, files, PDF/template rendering and variable building
- `lib/Inventory/` - inventory transaction writer, balances and register rows

`lib/App.php` is still the main facade and is being reduced gradually through small, behavior-preserving commits.

## Important Committed Runtime Support

- `vendor/` - Dompdf and PHP dependencies
- `release/siruta.csv` - SIRUTA seed data
- `db/schema.sql` - schema base
- `lib/Database.php` - lightweight migrations and seeds
- `deploy/sql/init-production.sql` - production empty DB init
- `scripts/sync-to-xampp.ps1` - local XAMPP sync
- `scripts/build-production-zip.ps1` - production package builder, use only when requested

## Main Features

Processing:

- create processing lots
- movement-based lot balances
- exchange wax with client
- return wax to client
- batch factory delivery
- factory buffer plus/minus
- processing register
- PDF documents from templates
- FGO invoice integration
- FiscalWire `.inp` receipt download

Purchase:

- create purchase lots for PF, Producator agricol, PJ/PFA
- store external document references instead of generating internal docs
- separate purchased wax stock `wax_purchased`
- record purchased wax exits to factory/partner by external document
- purchase register with opening/closing/current stock

Settings:

- company data
- FGO API key
- users, roles, stores, processors
- store-level FGO series and commercial defaults for processing/purchase
- editable document templates

## Production Deploy

Production notes live in:

`deploy/DEPLOY_PRODUCTION.md`

Production DB initialization:

`deploy/sql/init-production.sql`

The output zip folder is ignored. Any existing zip in `deploy/output` may be stale. Build a fresh production zip only when explicitly requested:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\build-production-zip.ps1
```

Production seed login from SQL:

- user: `admin`
- password: `CearaAdmin!2026`

Change password immediately after first production login.

## Documentation For Next Codex

Read in this order:

1. `AI_HANDOVER.md`
2. `PROJECT_CONTEXT.md`
3. `docs/spec.md`
4. `decisions/architecture-decisions.md`
5. `deploy/DEPLOY_PRODUCTION.md` only when production package is requested

## Development Rules From User

- Work locally by default.
- Sync to XAMPP for testing.
- Do not create production zips unless explicitly requested.
- Do not commit or push unless explicitly requested.
- Keep docs/specs aligned after significant changes.
