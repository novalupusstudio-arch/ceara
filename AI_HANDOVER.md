# AI Handover

## Snapshot

Project: `Ceara`, plain PHP + MySQL app for wax operations.

Current source path on this machine: `D:\Novalupusstudio\ceara`.
Local XAMPP deployment target is configured per machine in `config/xampp-target.local.txt`; current machine uses `D:\xampp\htdocs\ceara`.
Git remote: `https://github.com/novalupusstudio-arch/ceara.git`, branch `main`.

Local development is ahead of the last GitHub snapshot. Do not commit or push unless the user explicitly asks.

## What Is Already Structured

The app has been split into smaller services so `App.php` stays mostly as a facade:

- `lib/CustomerService.php` - customer reference data, SIRUTA lookup, ANAF company lookup, default processor/store helpers
- `lib/SupplierService.php` - purchase supplier resolution and upsert
- `lib/DocumentService.php` - generic document issuing
- `lib/DocumentCatalogService.php` - document listing, preview and PDF read helpers
- `lib/FgoService.php` - FGO invoice emission
- `lib/FiscalWireService.php` - FiscalWire `.inp` export
- `lib/ProcessingWriteService.php` - processing lot and movement writes
- `lib/ProcessingDocumentService.php` - processing document orchestration
- `lib/PurchaseService.php` - purchase lot and exit writes
- `lib/SettingsService.php` - settings/users/stores/processors/templates
- `lib/ProcessingService.php` - processing reads and calculated summaries
- `lib/Documents/`, `lib/Inventory/`, `lib/Http/`, `lib/Integrations/` - supporting infrastructure

`lib/autoload.php` autoloads namespaced classes with the `Ceara\` prefix. Legacy global classes still exist for the main shell (`App`, `Database`, `Auth`) while behavior is moved out in small commits.

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
- Processing price and shrinkage are defaulted from the assigned store and processor, but are editable on the lot creation form.
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

Purchase entry fields:

- supplier name
- phone
- county/locality/address
- purchase date
- gross kg
- shrinkage %, defaulted from the assigned store and editable for the purchase
- price with VAT lei/kg, defaulted from the assigned store and editable for the purchase
- total calculated
- net estimated quantity

PF / Producator agricol:

- CNP/CI identifier
- document series
- document number
- document position

PJ/PFA:

- CUI
- invoice series
- invoice number
- invoice date

Purchase entry does not generate PDF or internal fiscal documents. It stores external document references only.

Purchase exit:

- partner/factory name
- CUI/identifier
- kg quantity
- external document type/series/number/date
- notes

It writes negative `wax_purchased`. It cannot exceed current stock.

Current exit model is stock-level only, not allocated to specific purchase lots.

## Documents

Document records live in `documents`.

PDF-enabled templates are editable through `Setari > Template documente`.

Dompdf saves generated PDFs under:

`storage/documents/<store_code>/`

`storage/` is ignored.

`PV-CUST` source template file is versioned at:

`lib/templates/pv-cust.html`

It is a compact table-style A4 document with GDPR text.

Fiscal integrations:

- FGO invoice generation for processing exchange invoice (`FACT`)
- FiscalWire `.inp` download for cash/card receipt (`BON`)

FiscalWire file name format:

`<LOT_NUMBER>_<YYmmddHHmm>.inp`

## Settings

Company settings include:

- company name
- CUI
- registry number
- address
- FGO API/private key override

Admin/settings also manage:

- users
- stores/gestiuni: code, name, address, FGO series, assigned processor, processing terms, purchase terms
- processors
- roles/permissions
- document templates

## Database Direction

Important tables:

- `users`
- `permissions`
- `role_permissions`
- `stores`
- `user_stores`
- `processors`
- `customers`
- `suppliers`
- `siruta_counties`
- `siruta_localities`
- `processing_lots`
- `processing_lot_movements`
- `factory_batches`
- `factory_batch_items`
- `factory_buffer_adjustments`
- `purchase_lots`
- `purchase_wax_exits`
- `documents`
- `document_templates`
- `company_settings`
- `inventory_transactions`
- `audit_log`

Quantities are stored as integer grams. UI displays kilograms with three decimals and Romanian decimal comma where helpers are used.

Store-level operational settings are stored in `stores`:

- `code`
- `fgo_series`
- `processor_id`
- `processing_shrinkage_pct`
- `processing_price_cents`
- `purchase_shrinkage_pct`
- `purchase_price_cents_per_kg`

Generated document counters are stored in `document_series` per `store_id + document_type`. Default series format is `<DOCUMENT_TYPE>-<STORE_CODE>` and displayed numbers use four digits, for example `PV-CUST-BC-0001`.

## Production

`deploy/sql/init-production.sql` is intended for empty production DB initialization and is regenerated from `db/schema.sql` plus minimal admin/permissions seed.

Current production zip in `deploy/output` is stale and ignored. Build a new one only when explicitly requested.

Production initial login from SQL seed:

- user: `admin`
- password: `CearaAdmin!2026`

Change production password immediately.

## Current Version Caveat

`config/config.php` app version may need bumping after UI/CSS/JS changes to avoid browser cache issues.
