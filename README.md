# Ceara

Local-first PHP + MySQL operational app for:

- processing customer wax held in custody
- managing separate company-owned wax purchases and exits

## Current Repo State

- source path on this machine: `D:\Novalupusstudio\ceara`
- local DEV XAMPP target: `D:\xampp\htdocs\ceara`
- local DEV URL: `http://localhost/ceara/`
- local STAGE XAMPP target: `D:\xampp\htdocs\ceara_stage`
- local STAGE URL: `http://localhost/ceara_stage/`
- git remote: `https://github.com/novalupusstudio-arch/ceara.git`
- branch: `main`
- current version: `1.2.012`

## Environments

Current working model:

- `CODE`: `D:\Novalupusstudio\ceara`
- `DEV`: `D:\xampp\htdocs\ceara` + DB `ceara`
- `STAGE`: `D:\xampp\htdocs\ceara_stage` + DB `ceara_stage`
- `PROD`: `../ceara` + DB `stuparul_ceara`

Recommended release flow:

1. develop in `CODE`
2. sync to `DEV` for free testing
3. sync candidate to `STAGE`
4. import live production DB backup into `STAGE` when needed
5. re-enter `FGO URL` and `FGO token` in `Date societate` after DB import
6. validate candidate on `STAGE`
7. deploy the same candidate to `PROD`

## Database Config Files By Environment

- source repo local dev config:
  - `config/local.php`
- source repo production build config:
  - `deploy/local/config.php`
- DEV runtime config:
  - `D:\xampp\htdocs\ceara\config\local.php`
- STAGE runtime config:
  - `D:\xampp\htdocs\ceara_stage\config\local.php`
- PROD runtime config:
  - `../ceara/config/local.php`

## XAMPP Sync

Machine-specific sync targets live outside Git:

- `config/xampp-target.local.txt`
- `config/xampp-stage-target.local.txt`

Sync DEV:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\sync-to-xampp.ps1
```

Sync STAGE:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\sync-to-xampp.ps1 -Profile stage
```

## Production Deploy

Build production zip only when explicitly requested:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\build-production-zip.ps1
```

Current production-ready full package:

- `deploy/output/ceara-production-20260624-143740.zip`

Current production patch package for the `external_checked_at` fix:

- `deploy/output/ceara-production-patch-1.2.011-20260624.zip`

Production reset SQL:

- `deploy/sql/init-production.sql`

Seeded reset login:

- user: `admin`
- password: `CearaAdmin!2026`

## Current Implemented Areas

### Processing flow

- processing lot creation
- PF/PJ customer handling
- ANAF + SIRUTA assisted PJ/locality flow
- dynamic customer lookup
- lot-level snapshot of price and shrinkage
- lot movement journal
- exchange from operational buffer
- wax return to customer
- processor batch delivery with aviz date/number
- factory rejection support
- factory buffer plus/minus
- processing register with document links

### Purchase flow

- purchase entry flow
- purchase exit flow
- separate `wax_purchased` register

### Documents / integrations

- internal document series
- editable HTML templates
- Dompdf PDF generation
- FGO invoice integration path
- FiscalWire `.inp` export path
- AVIZ and NIR generation from factory delivery

### Settings / admin

- company data
- processors
- stores / gestiuni
- document series
- document templates
- roles / permissions
- user management
- backup and DB import tools

## Important Business Rules

- `wax_custody`, `foundation_operational`, and `wax_purchased` are separate stock buckets
- quantities are stored in grams and shown in kg with three decimals
- one user works on one gestiune
- a gestiune may have many users
- lot processing price and shrinkage are snapshotted on the lot
- processor values and store values are different business relations and must stay separate
- admin without assigned store must still be able to log in after clean init and reach settings
- critical config should fail loudly, not silently fall back

## Important Local Files

- `AI_HANDOVER.md`
- `PROJECT_CONTEXT.md`
- `SYNC.md`
- `docs/spec.md`
- `docs/source-specs/03-settings.md`
- `docs/source-specs/04-flow-processing.md`
- `deploy/DEPLOY_PRODUCTION.md`
