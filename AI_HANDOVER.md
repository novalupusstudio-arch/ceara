# AI Handover

## Snapshot

Project: `Ceara`, plain PHP + MySQL app for wax operations.

Current source path on this machine: `D:\Novalupusstudio\ceara`.
Local XAMPP deployment target is configured per machine in `config/xampp-target.local.txt`; current machine uses `D:\xampp\htdocs\ceara`.
Git remote: `https://github.com/novalupusstudio-arch/ceara.git`, branch `main`.

As of this handoff, `main` on GitHub contains the development state through commit `5d212ff Adauga flux separat pentru achizitie ceara`. Local development may contain later uncommitted changes; do not commit or push unless the user explicitly asks.

## How To Continue On A New Machine

1. Clone the repo from GitHub.
2. Start XAMPP Apache and MySQL.
3. Create local file `config/xampp-target.local.txt` with the absolute XAMPP deploy path, for example:

```text
D:\xampp\htdocs\ceara
```

4. Sync source to XAMPP:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\sync-to-xampp.ps1
```

5. Open `http://localhost/ceara/`.
6. The app creates/seeds the local DB automatically from `db/schema.sql` + `lib/Database.php` migrations.
7. Default local login: `admin / admin`.

Composer is not required for normal local run because `vendor/` is committed intentionally. Dompdf is bundled.

## Runtime / Generated Files

Ignored runtime/local files:

- `config/local.php`
- `config/fgo.local.php`
- `config/xampp-target.local.txt`
- `deploy/local/`
- `deploy/output/`
- `storage/`

Important committed support files:

- `vendor/` for Dompdf and dependencies
- `release/siruta.csv` for judet/localitate seed
- `deploy/sql/init-production.sql` for empty production DB initialization
- `scripts/sync-to-xampp.ps1` for local deploy copy
- `scripts/build-production-zip.ps1` for production zip when requested

## Current Code Structure Direction

The project is being refactored incrementally away from one large `App.php`.

Current new modular folders under `lib/`:

- `lib/Integrations/` - external integrations (`FgoClient`, `FiscalWireExporter`)
- `lib/Documents/` - document flow helpers (`DocumentFiles`, `DocumentIssuer`, `DocumentGenerator`, `DocumentVariablesBuilder`, `PdfRenderer`, `TemplateRenderer`)
- `lib/Inventory/` - stock ledger helpers (`InventoryWriter`)

`lib/autoload.php` autoloads namespaced classes with the `Ceara\` prefix. Legacy global classes still exist for the main app shell (`App`, `Database`, `Auth`) while functionality is moved in small commits.

Refactoring rule: keep behavior unchanged, lint after each extraction, and commit each safe step before moving deeper business logic.

## Current Flows

The dashboard has two active business flows. Sidebar changes based on selected flow.

### Processing / Schimb Ceara

Pages:

- `processing` - create processing lot
- `lots` - calculated processing lot board
- `lot_detail` - movement journal and actions per lot
- `factory_delivery` - batch send wax to assigned processor/factory
- `factory_buffer` - operational foundation buffer plus/minus
- `processing_register` - store-scoped processing register

Key model:

- Customer wax is custody stock: `wax_custody`.
- Operational foundations are `foundation_operational`.
- Processing lot state is calculated from append-only `processing_lot_movements`.
- `inventory_transactions` is the stock ledger.
- Each store/gestiune has one assigned processor for processing.

Implemented movement types:

- `RECEIVE_WAX_FROM_CLIENT`
- `EXCHANGE_WAX_WITH_CLIENT`
- `RETURN_WAX_TO_CLIENT`
- `SEND_WAX_TO_FACTORY`
- `RECEIVE_FOUNDATION_FROM_FACTORY`
- `FACTORY_REJECT_WAX`
- `RECORD_LOSS`
- `RECOVER_FOUNDATION_FROM_CLIENT`

Important processing behavior:

- Creating a lot creates `RECEIVE_WAX_FROM_CLIENT`, inventory `wax_custody`, and linked `PV-CUST` document row.
- Processing price and shrinkage are defaulted from the user's assigned store/gestiune and assigned processor, but are editable on the lot creation form.
- The actual commercial values used for calculations are snapshotted on the lot (`processing_lots.processing_price_cents`, `processing_lots.shrinkage_pct`). Later lot detail, invoices, receipts and PV values must read the lot snapshot, not current global defaults.
- Exchange validates against unexchanged wax and operational foundation stock.
- Exchange can generate FGO invoice and FiscalWire receipt from movement row.
- Return wax decreases custody and links `PV-RET`.
- Factory delivery works in batch and decreases custody, adds operational foundations immediately based on shrinkage calculation.
- Buffer plus/minus changes `foundation_operational`; minus cannot go negative.

### Purchase / Achizitie Ceara

Pages:

- `purchases` - create purchase lot only
- `purchase_exit` - decrease purchased wax stock when wax is sold/sent to factory/partner
- `purchase_register` - store-scoped purchase stock register and lot list

Key model:

- Purchased wax is separate stock: `wax_purchased`.
- It is not custody stock and must never be mixed with `wax_custody`.
- Purchase entry does not generate internal PDF documents. It stores references to external paper/accounting documents.
- Purchase exit also uses external document references and decreases `wax_purchased`.

Supplier types:

- `PF`
- `Producator agricol`
- `PJ/PFA`

Purchase lot fields include supplier data, SIRUTA location, purchase date, external document series/number/date/position, gross grams, shrinkage, net grams, price with VAT per kg, and total.

External document behavior:

- PF: borderou-like reference with position required.
- Producator agricol: carnet-like reference with position required.
- PJ/PFA: factura series/number/date required; no position.

## Documents / Templates / PDFs

Document templates are editable in `Setari > Template documente` for users with `DOCUMENT_TEMPLATE_MANAGE`.

Dompdf generates PDFs from HTML templates and stores files under `storage/documents/<store_code>/`.

Implemented template-backed documents include:

- `PV-CUST` - custody wax intake, table-style A4 template with GDPR text. Source file: `lib/templates/pv-cust.html`.
- `PV-FAG`, `PV-RET`, `NIR`, `AVIZ` are present as editable templates/records in the app flow where implemented.

Runtime generated PDFs are ignored in Git. During development, if templates are changed, existing document `file_path` may need clearing to force regeneration.

## FGO Integration

FGO is used for invoice generation on processing exchange movements.

Config sources:

- default `config/config.php`
- local ignored config `config/fgo.local.php`
- `company_settings.fgo_private_key` entered in `Setari > Date societate` overrides private key from config if present
- invoice series comes from the active store/gestiune (`stores.fgo_series`); if it is blank the app may fall back to a generated `FACT-<store_code>` style series

The app sends the invoice data and stores the returned external PDF/link in `documents.external_url`.

Production/local secrets must not be committed except via ignored local config or DB settings.

## FiscalWire Integration

FiscalWire receipt generation creates `.inp` text files for cash register integration.

Current behavior:

- Receipt uses only `S` and `T` lines.
- VAT code `1`, cash payment `0`, card payment `1` per current business rule.
- Product name is short: `Servicii procesare`.
- Generated receipt file name format: `<LOT_NUMBER>_<YYmmddHHmm>.inp`.
- Opening/printing BON downloads the `.inp` file directly.

## SIRUTA / Locations

`release/siruta.csv` is committed and seeded into:

- `siruta_counties`
- `siruta_localities`

Seed imports 42 counties and about 16,936 localities. Duplicate village names inside the same county get parent context in display, e.g. `FLORESTI (com. BUCIUM)`.

Used by processing customer form and purchase supplier form.

## Settings

`Setari > Date societate` currently stores:

- company name
- CUI
- registry number
- address
- FGO API/private key

Store/gestiune settings store operational defaults in SQL:

- short uppercase code, used in document series, e.g. `BC`, `CJ`
- name and address
- FGO invoice series
- assigned default processor
- processing shrinkage % and processing price with VAT lei/kg
- purchase shrinkage % and purchase price with VAT lei/kg

Processor settings keep processor identity/master data. The assigned store values are the operational defaults used when creating new lots and purchases.

Other settings pages include users, stores, processors, roles, document templates.

## Document Numbering

Document series are per store and document type in `document_series`.

- default series format: `<DOCUMENT_TYPE>-<STORE_CODE>`, for example `PV-CUST-BC`
- numbering is independent per store + document type
- `next_number` starts from `1`
- displayed document number is padded to four digits, for example `PV-CUST-BC-0001`

Main document types currently used/planned in this rule: `PV-CUST`, `PV-FAG`, `PV-RET`, `AVIZ`, `NIR`, `FACT`, `BON`, `BORD`.

## Production Deploy State

Current Git has production scripts and SQL updated for the current schema.

Important: last generated production zip in `deploy/output` is stale and ignored. Do not use it for current production deployment.

When user requests production package:

1. Verify `deploy/local/config.php` exists locally and has production DB credentials.
2. Verify `deploy/sql/init-production.sql` is current.
3. Build with:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\build-production-zip.ps1
```

4. Upload the new zip to server.
5. Initialize an empty DB with `deploy/sql/init-production.sql`.
6. Initial production login from SQL seed: `admin / CearaAdmin!2026`.
7. Change password immediately.

For an already initialized production database, do not run the full reset script. Apply only the incremental SQL files requested for the change. Current incremental scripts from the 2026-06-23 settings/series work:

- `deploy/sql/20260623-add-store-fgo-series.sql`
- `deploy/sql/20260623-normalize-document-series.sql`
- `deploy/sql/20260623-add-store-commercial-terms.sql`

## Known Current Gaps / Recommended Next Work

Refactoring:

- Next low-risk target: move FiscalWire receipt storage/path handling out of `App.php` or expand `InventoryService` with stock balance queries.
- Next medium-risk target: split POST actions from `index.php` into small action handlers/controllers.
- Larger target after that: split processing and purchase business logic into separate services/repositories.

Processing:

- Validate full real-world flow after latest changes: create lot, exchange, FGO invoice, FiscalWire `.inp`, return, factory delivery, buffer, register.
- Decide whether generated PDFs become immutable after issue or can regenerate during MVP.
- Add stronger detail/history pages for factory batches if needed.
- Add explicit recovery/loss UI if business flow requires it.

Purchase:

- Test entry for PF, Producator agricol, PJ/PFA.
- Test `purchase_exit` stock decrease and over-stock validation.
- Improve purchase register filters if needed: supplier type, document, operator.
- Decide whether purchase exits should allocate quantities against specific purchase lots or remain stock-level only. Current implementation is stock-level only.
- Add ANAF lookup for PJ/PFA suppliers if desired.

Production:

- Build a fresh production zip only when explicitly requested.
- Re-test production init after any schema changes.

## Developer Rules From User

- Do not create production zips unless explicitly asked.
- Do not commit or push unless explicitly asked.
- Work locally and sync to XAMPP for testing.
- Keep custody wax and purchased wax completely separate.
