# Project Context

## Purpose

`Ceara` is a PHP + MySQL operational app for two separate wax business flows:

1. Processing customer wax held in custody into wax foundations.
2. Purchasing wax into company-owned stock and later sending/selling it out.

The core rule is strict stock separation:

- customer custody wax: `wax_custody`
- operational foundations for processing exchanges: `foundation_operational`
- company-owned purchased wax: `wax_purchased`

Purchased wax must not be mixed with custody wax, neither in stock nor in factory delivery screens.

## Stack

- Plain PHP, no framework
- MySQL
- XAMPP local runtime
- Server-rendered PHP views
- Light JavaScript in `assets/app.js`
- CSS in `assets/styles.css`
- Dompdf bundled in committed `vendor/`
- SIRUTA source CSV committed in `release/siruta.csv`
- Lightweight `Ceara\` autoload for new namespaced classes under `lib/`

## Refactoring Direction

The application is being split incrementally to reduce large-file edits and context load.

Current modular folders:

- `lib/Integrations/`: FGO and FiscalWire integration clients.
- `lib/Http/`: request/action dispatchers.
- `lib/Documents/`: document issuing, file handling, PDF/template rendering and variable building.
- `lib/Inventory/`: inventory ledger writer, stock balances and register rows.

Existing `App.php` remains the temporary facade while behavior is moved out in small, tested commits. Avoid big-bang rewrites; keep schema/business behavior stable unless the user explicitly asks for a behavior change.

## Repo / Runtime Paths

Current source path on this machine:

`D:\Novalupusstudio\ceara`

Local XAMPP test target is configured per machine in:

`config/xampp-target.local.txt`

Current machine target:

`D:\xampp\htdocs\ceara`

Sync command:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\sync-to-xampp.ps1
```

Open locally:

`http://localhost/ceara/`

## Local Startup From Zero

1. Clone GitHub repo.
2. Start XAMPP Apache and MySQL.
3. Create `config/xampp-target.local.txt` with the local htdocs path.
4. Run sync script.
5. Open app in browser.
6. App creates DB and seeds baseline data automatically.

Default local login:

- user: `admin`
- password: `admin`

The app uses `config/config.php` defaults. Optional ignored local overrides:

- `config/local.php`
- `config/fgo.local.php`

## Flow Selection

The dashboard has two active flow buttons:

- `Schimb de ceara`
- `Achizitie ceara`

Before selecting a flow, sidebar shows general pages only:

- Dashboard
- Documente
- Rapoarte
- Setari
- Audit

Processing flow sidebar:

- Procesare ceara
- Loturi ceara
- Predare fabrica
- Buffer fabrica
- Registru gestiune

Purchase flow sidebar:

- Achizitie ceara
- Iesire ceara
- Registru achizitie

## Processing Flow

### Screens

- `processing`: create processing lot
- `lots`: processing board/list with calculated lot status
- `lot_detail`: balances, movement journal, exchange/return actions, documents
- `factory_delivery`: batch send custody wax to assigned processor
- `factory_buffer`: add/subtract operational foundation stock by external aviz
- `processing_register`: register calculated from stock transactions

### Model

Processing lots are containers. Real state comes from append-only movements in `processing_lot_movements`:

- `RECEIVE_WAX_FROM_CLIENT`
- `EXCHANGE_WAX_WITH_CLIENT`
- `RETURN_WAX_TO_CLIENT`
- `SEND_WAX_TO_FACTORY`
- `RECEIVE_FOUNDATION_FROM_FACTORY`
- `FACTORY_REJECT_WAX`
- `RECORD_LOSS`
- `RECOVER_FOUNDATION_FROM_CLIENT`

Stock is written to `inventory_transactions`.

Processing stock types:

- `wax_custody`
- `foundation_operational`

### Important Rules

- A processing lot creates custody stock and a linked `PV-CUST` document row.
- Exchange cannot exceed wax available for exchange.
- Exchange cannot make `foundation_operational` negative.
- Return wax cannot exceed wax in custody.
- Factory delivery is per assigned processor/store and cannot exceed custody stock.
- Buffer minus cannot make operational foundation stock negative.
- Each store has one assigned processor for processing.
- Processing commercial defaults are stored on the assigned store/gestiune: processing shrinkage %, processing price, purchase shrinkage % and purchase price.
- New processing lots snapshot their actual processing price and shrinkage. Lot detail, exchange calculations, invoices, receipts and PV values must use the lot snapshot even if store defaults change later.

## Purchase Flow

### Screens

- `purchases`: create purchase lot only
- `purchase_exit`: send/sell purchased wax out by external document
- `purchase_register`: register and stock view for purchased wax

### Model

Purchase flow uses company-owned stock only:

- stock type: `wax_purchased`
- entry reference: `purchase_lot`
- exit reference: `purchase_wax_exit`

No custody stock is touched.

### Supplier Types

- `PF`
- `Producator agricol`
- `PJ/PFA`

### Purchase Entry Fields

Common:

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

### Purchase Exit

`purchase_exit` records wax leaving purchased stock:

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

- FGO invoice generation for processing exchange invoice (`FACT`).
- FiscalWire `.inp` download for cash/card receipt (`BON`).

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
