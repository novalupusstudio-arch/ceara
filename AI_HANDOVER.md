# AI Handover

## Current Development Status

Project: `Ceara`

- stack: plain PHP + MySQL
- runtime: XAMPP locally, Linux hosting in production
- source repo: `D:\Novalupusstudio\ceara`
- DEV deploy target: `D:\xampp\htdocs\ceara`
- STAGE deploy target: `D:\xampp\htdocs\ceara_stage`
- current app version: `1.2.012`
- branch: `main`
- remote: `https://github.com/novalupusstudio-arch/ceara.git`

Production is now live and must be treated as a real environment with preserved data from this point onward.

## Environment Map

### CODE

- path: `D:\Novalupusstudio\ceara`
- this is the only source of truth for code changes

### DEV

- path: `D:\xampp\htdocs\ceara`
- URL: `http://localhost/ceara/`
- DB: `ceara`
- config DB file: `D:\xampp\htdocs\ceara\config\local.php`

### STAGE

- path: `D:\xampp\htdocs\ceara_stage`
- URL: `http://localhost/ceara_stage/`
- DB: `ceara_stage`
- config DB file: `D:\xampp\htdocs\ceara_stage\config\local.php`
- purpose: production-like validation before real deploy

### PROD

- app folder: `../ceara`
- URL: `https://www.stuparul.com/ceara/`
- DB: `stuparul_ceara`
- build-time DB config source: `D:\Novalupusstudio\ceara\deploy\local\config.php`
- runtime DB config on server: `../ceara/config/local.php`

## What Is Implemented

### Processing flow

- create processing lots
- PF/PJ customer handling on one page
- dynamic customer lookup
- ANAF lookup for PJ prefill
- SIRUTA counties/localities
- lot-level price/shrinkage snapshot
- movement-based lot math
- exchange foundations to customer
- return wax to customer
- batch delivery to processor/factory
- rejected wax handling
- factory buffer plus/minus
- processing register with document links

### Purchase flow

- purchase entry
- purchase exit
- separate purchase register
- stock kept separate from custody flow

### Documents / integrations

- internal document series
- editable HTML templates
- Dompdf PDF generation
- AVIZ generation
- NIR generation
- FGO integration path
- FiscalWire `.inp` output path

### Settings / admin

- company data
- processors
- stores / gestiuni
- document series
- document templates
- role permissions
- user creation/edit
- DB backup / DB import tab

## Decisions To Preserve

1. Plain PHP + MySQL stays the stack.
2. Quantities are stored in grams and shown in kg with three decimals.
3. `wax_custody`, `foundation_operational`, and `wax_purchased` never mix.
4. One user works operationally on one gestiune.
5. Store values are client-facing defaults; processor values are relation-to-factory values.
6. Lot values are snapshotted and later calculations must use lot values, not mutable defaults.
7. Critical config must fail loudly.
8. Business logic should stay in services, not drift back into `App.php`.
9. Admin without assigned store must still be able to log in after clean init and reach settings.
10. CODE is always `D:\Novalupusstudio\ceara`; XAMPP folders are deploy targets only.

## Most Recent Important Fixes

### 2026-06-24 production login loop fix

After clean DB init, `admin` has no assigned store. Dashboard used to loop because it still touched processing summaries that required a store.

Current behavior:

- admin can log in after `init-production.sql`
- dashboard opens with zero balances
- dashboard shows warning that no store is assigned yet

Relevant files:

- `lib/App.php`
- `views/pages/dashboard.php`

### 2026-06-24 subfolder redirect fix

Redirect building was made subfolder-safe for `/ceara/` style hosting paths.

Relevant file:

- `lib/helpers.php`

### 2026-06-24 customer nullable datetime fix

`customers.external_checked_at` must receive `NULL`, not empty string.

Relevant file:

- `lib/CustomerService.php`

Patch zip produced for this:

- `deploy/output/ceara-production-patch-1.2.011-20260624.zip`

## Production Packages

Current known-good full production package:

- `deploy/output/ceara-production-20260624-143740.zip`

Current known-good clean init SQL:

- `deploy/sql/init-production.sql`

Seeded reset login:

- user: `admin`
- password: `CearaAdmin!2026`

## Files A New Codex Should Read First

1. `AI_HANDOVER.md`
2. `PROJECT_CONTEXT.md`
3. `README.md`
4. `SYNC.md`
5. `docs/spec.md`
6. `docs/source-specs/03-settings.md`
7. `docs/source-specs/04-flow-processing.md`
8. `deploy/DEPLOY_PRODUCTION.md`

## Recommended Working Flow

1. code only in `D:\Novalupusstudio\ceara`
2. sync to `DEV`
3. validate broadly in `DEV`
4. sync candidate to `STAGE`
5. import production DB backup into `STAGE` when release validation requires live-like data
6. re-enter `FGO URL` and `FGO token` after import
7. validate candidate in `STAGE`
8. only then build / deploy to `PROD`

## Open/Pending Areas

1. More polish on settings UX is still possible.
2. Some production-safe backup/import workflow docs may still be worth expanding.
3. Purchase flow may still need additional business-rule passes once real use increases.
4. Generated legal/commercial document wording can still evolve over time.
