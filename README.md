# Ceara

Local-first PHP + MySQL operational app for:

- processing customer wax held in custody
- managing separate company-owned wax purchases and exits

## Current Repo State

- source path on this machine: `E:\NovaLupus\ceara`
- local XAMPP target on this machine: `E:\XAMP\htdocs\ceara`
- local URL: `http://localhost/ceara/`
- git remote: `https://github.com/novalupusstudio-arch/ceara.git`
- branch: `main`
- current version: `1.2.000`

## Stack

- plain PHP
- MySQL
- XAMPP
- server-rendered views
- small JS/CSS layer in `assets/`
- Dompdf committed in `vendor/`

## Main Business Areas

### Processing flow

- create processing lots
- PF/PJ customer handling
- dynamic customer lookup
- exchange foundations from local buffer
- return wax to customer
- batch factory delivery per processor
- factory rejection support
- factory buffer plus/minus
- movement-based lot summaries
- processing register with document links

### Purchase flow

- purchase lot creation
- purchase stock exits
- separate purchase register

### Settings/admin

- own password change
- role permissions
- user creation/edit
- store management
- processor management
- document series
- document templates

## Critical Rules

- `wax_custody`, `foundation_operational`, and `wax_purchased` are separate stock buckets
- quantities are stored in grams and shown in kg with three decimals
- one user works on one gestiune; one gestiune may have many users
- lot processing price and shrinkage are snapshotted on the lot
- factory delivery is separate from the lot board
- critical config should fail loudly, not silently fall back
- business logic should stay in services, not drift back into `App.php` or views

## Local Setup On A New PC

1. Clone the repo.
2. Start Apache and MySQL in XAMPP.
3. Create ignored `config/local.php` with DB credentials.
4. Create ignored `config/xampp-target.local.txt` with the local XAMPP target.
5. Initialize or restore the database.
6. Sync the repo into XAMPP.
7. Open the local URL.

Sync command:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\sync-to-xampp.ps1
```

Production-style reset SQL:

```powershell
E:\XAMP\mysql\bin\mysql.exe -u root ceara < E:\NovaLupus\ceara\deploy\sql\init-production.sql
```

Seeded reset login:

- user: `admin`
- password: `CearaAdmin!2026`

## Important Local Files

- `AI_HANDOVER.md`
- `PROJECT_CONTEXT.md`
- `docs/spec.md`
- `decisions/architecture-decisions.md`
- `docs/source-specs/03-settings.md`
- `docs/source-specs/04-flow-processing.md`

## Deploy / Packaging

- local sync script: `scripts/sync-to-xampp.ps1`
- production reset SQL: `deploy/sql/init-production.sql`
- production guidance: `deploy/DEPLOY_PRODUCTION.md`
- production package output: `deploy/output/`
