# Ceara

Web application project workspace.

## Working Locations

- Source workspace: local git clone
- Local XAMPP test path: configured per machine in `config/xampp-target.local.txt`
- GitHub remote: `https://github.com/novalupusstudio-arch/ceara.git`
- Main branch: `main`

## Workflow

1. Keep source changes in this repository.
2. Keep the specification files in sync with implementation.
3. Use `scripts/sync-to-xampp.ps1` to copy the working tree into the XAMPP test folder.
4. Test through the local XAMPP URL once the app structure exists.
5. Record requirements and decisions in `docs/spec.md`, `PROJECT_CONTEXT.md`, `AI_HANDOVER.md`, and `decisions/architecture-decisions.md`.

## MVP Stack

- PHP + MySQL
- Dompdf bundled in `vendor/` for PDF generation
- XAMPP local runtime
- Server-rendered pages with small JavaScript helpers

## Local Setup

1. Start Apache and MySQL in XAMPP.
2. Sync the repository into XAMPP:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\sync-to-xampp.ps1
```

3. Open the local app:

`http://localhost/ceara/`

The app creates the `ceara` database and seeds baseline data automatically when MySQL is available.

Composer is not required to run the app on another PC because `vendor/` is
committed intentionally. `composer.json` and `composer.lock` remain available
for future dependency maintenance.

Default credentials:

- user: `admin`
- password: `admin`

## Production Deploy

Production packaging and database initialization are documented in
`deploy/DEPLOY_PRODUCTION.md`.

## MVP Scope

- login/logout
- dashboard KPI and flow selector
- movement-based processing lots
- factory delivery
- factory buffer avize
- processing store register
- generated mock documents
- reports
- settings for store, processor, and document series
- settings for company data and editable document templates
- Dompdf-generated PDF for `PV-CUST`
- audit log

Quantities are stored as integer grams and displayed as kilograms with three decimals.

## Project Status

Working MVP for the processing flow. Purchase is intentionally disabled in the
navigation and will be rebuilt separately.
