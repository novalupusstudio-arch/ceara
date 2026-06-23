# Project Spec

## Objective

Build and maintain a local-first PHP/MySQL application for wax operations with two independent operational flows:

1. Processing customer wax held in custody.
2. Purchasing wax into company-owned inventory.

The application must preserve traceability and never mix custody wax with purchased wax.

## Core Principles

- Quantities are stored as integer grams.
- UI displays kg with three decimals.
- Operational stock movements are append-only in `inventory_transactions`.
- Audit-sensitive actions are logged in `audit_log`.
- Users operate in their assigned store/gestiune.
- Processing stock and purchase stock are separate.
- External paper/accounting documents are referenced when the app does not generate documents.

## Roles

Seeded local admin:

- user: `admin`
- password: `admin`

Production SQL seed admin:

- user: `admin`
- password: `CearaAdmin!2026`

Roles:

- `admin`
- `operator`

Main permissions:

- user management
- store management
- processor management
- document template management
- processing creation/actions
- purchase creation/actions
- reports
- audit

## Dashboard And Navigation

The app starts with no active business flow.

General sidebar:

- Dashboard
- Documente
- Rapoarte
- Setari
- Audit

Dashboard flow buttons:

- `Schimb de ceara`
- `Achizitie ceara`

When processing is selected, sidebar adds:

- Procesare ceara
- Loturi ceara
- Predare fabrica
- Buffer fabrica
- Registru gestiune

When purchase is selected, sidebar adds:

- Achizitie ceara
- Iesire ceara
- Registru achizitie

## Processing Flow Specification

### Create Processing Lot

Inputs:

- customer type PF/PJ
- existing customer search or new customer
- PF: name, phone, CNP/CI, county, locality, address
- PJ: name, CUI, phone, representative, county, locality, address
- processor preselected from user's assigned store
- gross wax kg
- processing price / shrinkage defaulted from the assigned store/processor and editable for this lot

Actions:

- create `processing_lots`
- snapshot `processing_price_cents` and `shrinkage_pct` on the lot
- create `processing_lot_movements.RECEIVE_WAX_FROM_CLIENT`
- add `inventory_transactions.wax_custody` positive movement
- create linked `PV-CUST` document row

### Lot Detail

Shows:

- current calculated stock/balance values
- movement journal
- document buttons per movement
- exchange wax action
- return wax action

Exchange:

- cannot exceed wax available for exchange
- calculates foundations from the lot's saved shrinkage
- calculates service value from the lot's saved processing price
- cannot make `foundation_operational` negative
- writes movement and negative foundation stock
- can generate FGO invoice, FiscalWire receipt, PV-FAG

Return:

- cannot exceed custody wax
- writes movement and negative custody stock
- can generate PV-RET

### Factory Delivery

- Dedicated batch page.
- Uses only processing custody stock.
- Lists lots with wax still in custody for selected/assigned processor.
- Operator edits sent quantity and rejected quantity.
- Sum per lot cannot exceed custody remaining.
- Sent wax decreases `wax_custody`.
- Calculated foundations increase `foundation_operational`.
- Rejected factory wax is recorded as movement.

### Factory Buffer

- Adjusts `foundation_operational` directly by external aviz.
- Plus increases stock.
- Minus decreases stock and cannot go negative.
- Each buffer adjustment creates linked NIR record.

### Processing Register

Store-scoped register from `inventory_transactions`.

Filters:

- date start
- date end

Shows:

- current stock totals
- opening balance
- closing balance
- partner
- lot link where applicable
- document link where applicable
- date
- signed wax custody quantity
- signed foundation quantity
- operator

## Purchase Flow Specification

Purchase is fully separate from processing.

### Purchase Stock

Use inventory movement type:

- `wax_purchased`

Do not use:

- `wax_custody`
- processing factory delivery
- processing processor assignment rules

### Create Purchase Lot Page

Page: `purchases` / `Achizitie ceara`.

This page only creates new purchase lots. It does not list lots.

Supplier type radio:

- PF
- Producator agricol
- PJ/PFA

Common supplier fields:

- name
- phone
- county
- locality
- address

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

Purchase fields:

- purchase date
- gross kg
- shrinkage %, defaulted from the assigned store and editable for the purchase
- price with VAT lei/kg, defaulted from the assigned store and editable for the purchase
- calculated total
- calculated net kg
- assigned store display

Save behavior:

- create/update supplier by type + name/identifier or CUI
- create `purchase_lots`
- add positive `inventory_transactions.wax_purchased`
- do not create internal document/PDF records

Validation:

- gross quantity > 0
- PF/producator identifier required
- PJ/PFA CUI required
- document series + number required
- document position required for PF/producator
- invoice date required for PJ/PFA
- external document type + series + number + position unique

### Purchase Exit Page

Page: `purchase_exit` / `Iesire ceara`.

Purpose: record wax leaving purchased stock toward factory/partner/customer by external document.

Fields:

- partner/factory name
- partner identifier/CUI
- quantity kg
- document type
- document series
- document number
- document date
- notes

Save behavior:

- create `purchase_wax_exits`
- write negative `inventory_transactions.wax_purchased`
- cannot exceed current purchased wax stock
- no PDF generated

Current implementation is stock-level exit only, not allocated to specific purchase lots.

### Purchase Register Page

Page: `purchase_register` / `Registru achizitie`.

Filters:

- date start
- date end

Shows:

- current purchased wax stock
- opening period balance
- closing period balance
- movement table: partner, document, position, date, signed wax quantity, operator, notes
- lot list below register

## Location / SIRUTA

`release/siruta.csv` is committed and seeded at startup into:

- `siruta_counties`
- `siruta_localities`

Forms use county/locality combos. Duplicate locality names within one county include parent context.

PJ/PFA customer lookup for processing can use online ANAF demo endpoint. Purchase PJ/PFA lookup is not implemented yet.

## Documents And Templates

Editable templates live in `document_templates` and are managed in settings.

Versioned template source currently includes:

- `lib/templates/pv-cust.html`

Generated PDFs are stored in ignored runtime folder:

- `storage/documents/<store_code>/`

`PV-CUST` is a compact A4 table template with GDPR notice.

Future templates/documents may need refinement:

- PV-FAG
- PV-RET
- AVIZ
- NIR

## FGO

FGO invoice generation is used for processing service invoices.

Config:

- `config/config.php` defaults
- optional ignored `config/fgo.local.php`
- `company_settings.fgo_private_key` overrides private key if filled
- invoice series is read from the active store/gestiune (`stores.fgo_series`); if blank, the app may fall back to `FACT-<store_code>`

FGO response external invoice link is saved in document row.

## FiscalWire

FiscalWire creates `.inp` files for cash register.

Rules:

- only `S` and `T` lines
- VAT 21 uses fiscal code `1`
- cash payment `0`
- card payment `1`
- item name: `Servicii procesare`
- generated file downloads directly from browser
- file name: `<LOT_NUMBER>_<YYmmddHHmm>.inp`

## Settings

`Setari > Date societate`:

- company name
- CUI
- registry number
- address
- FGO API key

Other settings:

- roles and permissions
- users
- stores/gestiuni: uppercase code, name, address, FGO series, assigned processor, processing terms, purchase terms
- processors
- document templates

Store/gestiune commercial fields are saved in SQL and are the source of defaults for new operations:

- `processing_shrinkage_pct`
- `processing_price_cents`
- `purchase_shrinkage_pct`
- `purchase_price_cents_per_kg`

Processing lots and purchase lots save their own effective values at creation time. Reports and generated documents should use the saved lot values, not later changes to the store defaults.

## Document Series

Document numbering is scoped by store/gestiune and document type.

- Store code should be short uppercase, for example `BC` or `CJ`.
- Default series format is `<DOCUMENT_TYPE>-<STORE_CODE>`, for example `PV-CUST-BC`.
- Each `store_id + document_type` has its own counter in `document_series`.
- Numbering starts at `0001` and increments by one.
- Main document types: `PV-CUST`, `PV-FAG`, `PV-RET`, `AVIZ`, `NIR`, `FACT`, `BON`, `BORD`.

## Deployment

Local:

- use `scripts/sync-to-xampp.ps1`
- `config/xampp-target.local.txt` is local/ignored

Production:

- SQL seed: `deploy/sql/init-production.sql`
- build script: `scripts/build-production-zip.ps1`
- production config template: `deploy/production-config.template.php`
- local production config: `deploy/local/config.php`, ignored

Do not generate production zip unless the user explicitly asks.

## Open / Next Work

High priority:

- Manual test full purchase flow after latest implementation.
- Manual test purchase exit with over-stock validation.
- Manual test processing exchange error redirects and real FiscalWire download.
- Consider app version bump to force JS/CSS cache refresh.

Medium priority:

- Add ANAF lookup to purchase PJ/PFA suppliers.
- Add purchase register filters for supplier type/document/operator.
- Decide whether purchase exits should consume specific lots FIFO/manual allocation.
- Add detail page for purchase lots.
- Add factory/partner directory for purchase exits.

Production readiness:

- Build fresh production zip only when requested.
- Re-test init SQL after any schema change.
- Configure production DB and initial admin password change.
