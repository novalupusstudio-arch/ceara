# AI Handover

## Current Development Status

Project: `Ceara`

- stack: plain PHP + MySQL
- runtime: XAMPP
- source path on this machine: `E:\NovaLupus\ceara`
- local deploy target on this machine: `E:\XAMP\htdocs\ceara`
- local URL: `http://localhost/ceara/`
- git remote: `https://github.com/novalupusstudio-arch/ceara.git`
- branch: `main`
- current app version: `1.2.000`

The app is already beyond first MVP and now runs two separate operational domains:

1. processing customer wax in custody
2. purchase stock for company-owned wax

The repo has active service extraction. Do not move business logic back into `App.php`.

## What Is Already Implemented

### Processing flow

- creation of processing lots
- PF/PJ customer handling on the same page
- dynamic customer lookup
- PJ support with company fields
- lot-level snapshot of processing price and shrinkage
- lot movement journal
- exchange from operational buffer to customer
- return wax to customer
- batch delivery to factory/processor
- support for factory rejection quantities
- factory buffer plus/minus adjustments
- processing register
- lot detail summary cards
- red warning styling for lots with rejected wax from factory

### Purchase flow

- purchase entry flow
- purchase exit flow
- separate `wax_purchased` stock register

### Settings/admin

- own password change
- role permissions matrix
- user creation
- editable users except username
- store/gestiune administration
- processor administration

### Documents/integrations

- document series
- HTML document templates
- Dompdf-based PDF generation
- FGO integration path
- FiscalWire output path
- processing register document links tied to actual document rows
- factory delivery now collects `aviz_number` and `aviz_date`
- factory delivery now auto-generates both `AVIZ` and `NIR`

## Completed Decisions To Preserve

1. Plain PHP + MySQL remains the chosen stack.
2. Quantities are stored in grams and shown as kg with three decimals.
3. Processing stock and purchased stock stay fully separate.
4. One user works on one gestiune; a gestiune may have many users.
5. The store context drives operational defaults.
6. Lot values snapshot at creation and later calculations must use lot values, not mutable global defaults.
7. `Acceptat` is terminal on the lot board; factory delivery is a separate batch page.
8. Factory delivery is processor-scoped and must not silently fall back to the first processor in DB.
9. Critical config should fail loudly instead of silently inventing values.
10. `App.php` should stay thin; calculations belong in services.

## Most Recent Fixes Included In This State

### 2026-06-24 status/closure fix

Lot closure logic was corrected so factory-rejected wax no longer keeps a lot open after that same quantity was already returned to the customer.

Current rule:

- open rejected wax = rejected - returned - wax loss

This affects both:

- `lib/ProcessingService.php`
- `lib/ProcessingWriteService.php`

### Recent factory delivery/register work

- `factory_batches` now stores `aviz_number` and `aviz_date`
- factory delivery form requires those values
- creating a factory batch generates `AVIZ` and `NIR`
- processing register rows now try to link to the real related documents instead of plain placeholder text

### Recent customer/PJ work

- PJ customer save path now uses `customer_cui` correctly even when PF hidden fields are empty
- ANAF/SIRUTA matching was improved for county/locality normalization
- form autofill pressure was reduced with `autocomplete="off"` in processing form fields

## Files A New Codex Should Read First

1. `AI_HANDOVER.md`
2. `PROJECT_CONTEXT.md`
3. `README.md`
4. `docs/spec.md`
5. `docs/source-specs/03-settings.md`
6. `docs/source-specs/04-flow-processing.md`
7. `decisions/architecture-decisions.md`
8. `deploy/DEPLOY_PRODUCTION.md` when packaging or production deploy matters

## Open Questions / Pending Business Clarifications

1. The later processing statuses after factory delivery are still only partially finalized in business terms.
2. Mock invoice / receipt generation is still transitional until final third-party API details are locked.
3. The database still technically allows multiple rows in `user_stores`; business rule says one active gestiune per user, so hard DB enforcement may still be worth doing later.
4. Some legacy persisted lot status values remain in schema even though operational behavior is increasingly movement-based.
5. Document templates and exact legal/commercial wording for all generated documents are still evolving.

## Recommended Next Tasks

1. Sanity-check the updated factory delivery flow end-to-end with aviz number/date, AVIZ, and NIR links.
2. Audit the remaining document buttons so failed generation never flips UI into a false "print" state.
3. Review settings/user-store enforcement so one-user-one-gestiune is explicit in backend validation too.
4. Continue cleaning legacy status assumptions where movement-based balances are now the real source of truth.
5. When new architectural/business rules are finalized, update:
   - `PROJECT_CONTEXT.md`
   - `AI_HANDOVER.md`
   - `decisions/architecture-decisions.md`

## Deployment / Transfer Notes

For this machine:

- source: `E:\NovaLupus\ceara`
- XAMPP deploy: `E:\XAMP\htdocs\ceara`

To move work to another PC:

1. pull latest Git state
2. deploy/sync repo into that machine's XAMPP target
3. create matching `config/local.php`
4. import/reset DB from `deploy/sql/init-production.sql` or a DB dump from the working machine

Current production-style seed login from SQL reset:

- user: `admin`
- password: `CearaAdmin!2026`
