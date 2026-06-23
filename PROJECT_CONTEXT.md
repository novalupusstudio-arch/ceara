# Project Context

## Project Purpose

`Ceara` is a local-first PHP + MySQL operational webapp for a wax-processing business that works in two parallel flows:

1. customer wax received in custody and partially or fully exchanged into foundations
2. company-owned wax purchased into stock and later exited through separate commercial documents

The app is designed to run the same way on multiple Windows PCs through XAMPP, with source kept in Git and local deploys copied into XAMPP.

## Current Runtime And Locations

- source repo: `E:\NovaLupus\ceara`
- local XAMPP target on this machine: `E:\XAMP\htdocs\ceara`
- local URL: `http://localhost/ceara/`
- current app version: `1.2.000`
- git remote: `https://github.com/novalupusstudio-arch/ceara.git`
- main branch: `main`

Local machine-specific values live outside Git when possible:

- `config/local.php` for DB connection
- `config/xampp-target.local.txt` for the XAMPP sync target

## Business Flows

### 1. Processing / Custody Flow

Main pages:

- `processing`
- `lots`
- `lot_detail`
- `factory_delivery`
- `factory_buffer`
- `processing_register`

Core operational flow:

1. receive wax from customer and create processing lot
2. optionally exchange some or all of that wax from local foundation buffer
3. later send some or all remaining custody wax to processor/factory in batch
4. factory may reject part of the sent wax
5. rejected wax may be returned to the client or carried as loss/recovery work
6. lot closes only when custody reaches zero and no remaining obligations stay open

Important clarification from business flow:

- when wax is exchanged from buffer, the client has already received foundations
- `faguri de predat` means the additional theoretical quantity that can still be exchanged from wax still in custody, using the current shrinkage
- factory rejection does not automatically block all future commercial exchange; the business may still choose to exchange on loss

### 2. Purchase Flow

Main pages:

- `purchases`
- `purchase_exit`
- `purchase_register`

This is separate from customer custody processing. Purchased wax belongs to the company and must never mix with custody wax.

## Lot Statuses

There are two status layers in the app.

### Persisted lot status fields

Current database status values on `processing_lots` / status events:

- `In Validare`
- `Acceptat`
- `Predat Fabricii`
- `Respins`
- `Returnat`

These are legacy UI/status-event values and still exist for traceability.

### Calculated operational lot states

The current lot board uses calculated movement-based states:

- `Procesare`
- `Recuperare`
- `Inchis`

Meaning:

- `Procesare`: normal active lot work
- `Recuperare`: lot has recovery/rejected-foundation implications still open
- `Inchis`: no wax remains in custody and no open pending balances remain

New lot behavior currently expected:

- a newly created lot immediately creates `PV-CUST`
- the lot appears on the board with active work
- `Acceptat` stays terminal on the board itself; moving wax to factory happens only from batch delivery page

## Inventory Model

Quantities are stored as integer grams and shown in UI as kilograms with 3 decimals.

Strict stock buckets:

- `wax_custody`: customer wax in custody
- `foundation_operational`: operational foundations available locally
- `wax_purchased`: company-owned purchased wax

They must never mix.

Current processing balance interpretation:

- custody wax decreases only when wax is sent to factory, returned to customer, or recorded as loss
- exchanging foundations to the customer does not itself remove custody wax
- that is intentional because the raw wax remains physically in custody until factory transfer or return

## Current Movement Model

Processing movement types:

- `RECEIVE_WAX_FROM_CLIENT`
- `EXCHANGE_WAX_WITH_CLIENT`
- `RETURN_WAX_TO_CLIENT`
- `SEND_WAX_TO_FACTORY`
- `RECEIVE_FOUNDATION_FROM_FACTORY`
- `FACTORY_REJECT_WAX`
- `RECORD_LOSS`
- `RECOVER_FOUNDATION_FROM_CLIENT`

Key calculations now in services:

- custody wax = received - sent to factory - returned to client - wax loss
- exchangeable wax = received - already exchanged - returned to client - wax loss
- foundations still exchangeable = shrinkage-applied result of current exchangeable wax
- open rejected wax = rejected - returned to client - wax loss

This last rule is important: a factory rejection is considered resolved if that same wax has already been returned to the client.

## User Roles

Current roles:

- `admin`
- `operator`

Current seeded reset login from `deploy/sql/init-production.sql`:

- username: `admin`
- password: `CearaAdmin!2026`

Settings and permission behavior:

- users can change their own password without entering old password
- only the initial/admin-capable account should see role/permission management
- user creation and user editing are admin-controlled
- processors and stores/gestiuni are admin-managed

Operational rule:

- one user works on one gestiune
- one gestiune may have many users

Current schema still uses `user_stores`, so the business rule is stricter than the raw table shape. The app should continue treating one active store per user as the intended rule.

## Document Types

Current internal document types in active use:

- `PV-CUST` - reception into custody
- `PV-FAG` - exchange / handover of foundations to client
- `PV-RET` - wax return to client
- `AVIZ` - delivery toward processor/factory
- `NIR` - reception note, including auto-generated NIR after factory delivery and buffer operations
- `FACT` - invoice through third-party integration / mock path where applicable
- `BON` - cash receipt / FiscalWire output or mock path
- `BORD` - managed in document series even if some purchase/accounting flows remain partial

Document numbering:

- internal counters live in `document_series`
- numbering is per `store_id + document_type`
- FGO invoice series lives in `stores.fgo_series`

Current document behavior:

- processing lot creation issues `PV-CUST`
- client wax return issues `PV-RET`
- factory buffer adjustments issue `NIR`
- factory delivery now records `aviz_number` + `aviz_date` and auto-generates both `AVIZ` and `NIR`
- processing register rows should link to the real stored document rows when documents exist

## Current Architecture

Stack:

- plain PHP
- MySQL
- XAMPP runtime
- server-rendered PHP views
- light JS in `assets/app.js`
- CSS in `assets/styles.css`
- Dompdf committed in `vendor/`

Current architectural direction:

- `App.php` remains a thin facade
- business logic should stay in services
- UI views should not accumulate calculation logic

Important services/folders:

- `lib/ProcessingService.php`
- `lib/ProcessingWriteService.php`
- `lib/PurchaseService.php`
- `lib/CustomerService.php`
- `lib/SettingsService.php`
- `lib/DocumentService.php`
- `lib/ProcessingDocumentService.php`
- `lib/FgoService.php`
- `lib/FiscalWireService.php`
- `lib/Documents/`
- `lib/Http/`
- `lib/Inventory/`

## Important Business Rules

- customer PF/PJ selection happens on the processing screen
- PF requires name, phone, address
- PJ requires company name, CUI, phone, address, representative
- customer search is dynamic while typing: phone for PF, CUI for PJ
- ANAF lookup is only a prefill helper; final saved values are whatever remains in the form at submit time
- processor is preselected from the user store context and lot values snapshot from form values
- processing price and shrinkage are mandatory on the lot and must not silently fall back in the backend
- exchange from buffer cannot exceed available exchangeable wax or available operational foundations
- return cannot exceed current custody wax
- factory delivery is batch-based and scoped to one processor
- factory delivery page currently works with both delivered and rejected quantities per lot
- register document links should point to real documents, not plain text placeholders
- lots with factory-rejected wax should display a red warning style in lot detail

## Recommended First-Run Settings Order

Current preferred order in `Setari`:

1. `Date societate`
2. `Procesatori`
3. `Gestiuni`
4. `Serii documente`
5. `Roluri si drepturi`
6. `Creare useri`
7. `Template documente`
8. `Schimba parola`

## Deployment Notes

Local deploy flow on this machine:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\sync-to-xampp.ps1
```

Production/local reset SQL:

- `deploy/sql/init-production.sql`

Production package guidance:

- `deploy/DEPLOY_PRODUCTION.md`
- `deploy/output/`

The intended model is to keep source in Git, sync to local XAMPP for testing, and be able to continue work from another PC with the same repo plus matching DB/bootstrap setup.
