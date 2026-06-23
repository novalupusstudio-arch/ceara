# Project Context

## Purpose

`Ceara` is a local-first PHP + MySQL operational app for two independent business flows:

1. processing customer wax held in custody into foundations
2. purchasing company-owned wax into stock and sending/selling it out later

Strict stock separation is mandatory:

- custody wax: `wax_custody`
- processing foundations: `foundation_operational`
- purchased wax: `wax_purchased`

## Stack

- plain PHP
- MySQL
- XAMPP for local runtime
- server-rendered PHP views
- light JS in `assets/app.js`
- CSS in `assets/styles.css`
- Dompdf committed in `vendor/`
- SIRUTA source CSV committed in `release/siruta.csv`

## Source / Runtime Paths

Current source path:

`D:\Novalupusstudio\ceara`

Local XAMPP target is machine-local:

`config/xampp-target.local.txt`

Current target on this machine:

`D:\xampp\htdocs\ceara`

Local URL:

`http://localhost/ceara/`

Sync command:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\sync-to-xampp.ps1
```

## Local Startup

Required ignored config:

- `config/local.php` for DB connection, unless `CEARA_DB_*` env vars are set
- `config/xampp-target.local.txt` for local sync target

Current source config no longer includes DB fallback defaults like `root/ceara`.

Local login after bootstrap:

- user: `admin`
- password: `admin`

Important behavior change:

- startup no longer auto-creates a default store or default processor
- admin must configure processors/stores/assignments explicitly

## Flow Selection

Dashboard starts with flow selection:

- `Schimb de ceara`
- `Achizitie ceara`

General sidebar before/alongside flow context:

- Dashboard
- Documente
- Rapoarte
- Setari
- Audit

Processing flow pages:

- `processing`
- `lots`
- `factory_delivery`
- `factory_buffer`
- `processing_register`

Purchase flow pages:

- `purchases`
- `purchase_exit`
- `purchase_register`

## Processing Flow

Processing movement types:

- `RECEIVE_WAX_FROM_CLIENT`
- `EXCHANGE_WAX_WITH_CLIENT`
- `RETURN_WAX_TO_CLIENT`
- `SEND_WAX_TO_FACTORY`
- `RECEIVE_FOUNDATION_FROM_FACTORY`
- `FACTORY_REJECT_WAX`
- `RECORD_LOSS`
- `RECOVER_FOUNDATION_FROM_CLIENT`

Important rules:

- lots snapshot their own processing price and shrinkage
- later documents and calculations must use lot snapshot values
- exchange cannot exceed available lot wax or operational foundation stock
- return cannot exceed wax still in custody
- factory delivery cannot exceed custody stock
- buffer minus cannot exceed operational foundation stock

Strictness recently enforced:

- no fallback to first processor
- no fallback from store terms to processor terms for processing form defaults
- no fallback for lot processing price/shrinkage in backend write path

## Factory Buffer

Buffer records now store:

- `aviz_number`
- `aviz_date`
- `reception_date`
- `qty_g`
- `store_id`
- `notes`

The form defaults both dates to today.

## Purchase Flow

Purchased wax uses:

- `wax_purchased`

It must never mix with:

- `wax_custody`

Purchase defaults come from store fields:

- `purchase_shrinkage_pct`
- `purchase_price_cents_per_kg`

## Documents

Internal generated document counters live in:

- `document_series`

FGO invoice series mapping lives in:

- `stores.fgo_series`

Current strict document behavior:

- internal document series missing => runtime error
- store FGO series missing => runtime error
- FGO URL/token/CUI missing => runtime error
- FGO response without final series/number => runtime error

Generated PDFs are stored in:

`storage/documents/<store_code>/`

FiscalWire output is stored in:

`storage/fiscalwire-out/`

Current hardcoded FiscalWire operational values:

- article name: `Servicii procesare`
- VAT code: `1`
- extension: `.inp`

## Settings

Admin-only settings manage:

- company data
- FGO URL/token
- processors
- stores
- document series
- document templates
- users
- roles/permissions

Operators should only see operational errors and ask admin to fix configuration.

## Refactoring Direction

Code is being split gradually, not rewritten wholesale.

Main split services:

- `lib/CustomerService.php`
- `lib/SupplierService.php`
- `lib/DocumentService.php`
- `lib/DocumentCatalogService.php`
- `lib/FgoService.php`
- `lib/FiscalWireService.php`
- `lib/ProcessingWriteService.php`
- `lib/ProcessingDocumentService.php`
- `lib/PurchaseService.php`
- `lib/SettingsService.php`
- `lib/ProcessingService.php`

Support folders:

- `lib/Http/`
- `lib/Documents/`
- `lib/Inventory/`
- `lib/Integrations/`

`App.php` remains the facade and should stay small.

## Production Direction

Production deploy model for the current state:

- wipe old app files from production folder
- extract full fresh archive
- run full reset SQL on the selected production DB

Artifacts to care about:

- `deploy/sql/init-production.sql`
- `deploy/DEPLOY_PRODUCTION.md`
- `deploy/RELEASE_20260623_PRODUCTION.md`
- fresh archive in `deploy/output/`

Production first login after reset:

- user: `admin`
- password: `CearaAdmin!2026`
