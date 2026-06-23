# AI Handover

## Snapshot

Project: `Ceara`, plain PHP + MySQL app for two separate wax business flows:

1. `Schimb de ceara` / processing customer wax held in custody
2. `Achizitie ceara` / purchasing company-owned wax stock

Current source path on this machine: `D:\Novalupusstudio\ceara`

Local XAMPP deploy target is machine-local in `config/xampp-target.local.txt` and is currently:

`D:\xampp\htdocs\ceara`

Open local app:

`http://localhost/ceara/`

Git remote:

`https://github.com/novalupusstudio-arch/ceara.git`

Branch:

`main`

Current app version in source:

`1.1.006`

## Important Current Rules

- Work locally by default.
- Sync to XAMPP for testing.
- Settings are admin-only. Operators should only see the operational error and contact admin.
- No silent business/config fallback should remain for processing/store/FGO/document-series critical settings.
- DB connection defaults were removed from source config. Running the app requires either:
  - environment variables `CEARA_DB_*`, or
  - ignored local file `config/local.php`

## Current Structure

`App.php` is now a thin facade over split services:

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
- `lib/Documents/`
- `lib/Inventory/`
- `lib/Http/`
- `lib/Integrations/`

The refactor direction is still incremental. Do not re-centralize logic into `App.php`.

## Processing Flow

Pages:

- `processing`
- `lots`
- `lot_detail`
- `factory_delivery`
- `factory_buffer`
- `processing_register`

Core stock model:

- customer custody wax: `wax_custody`
- operational foundations: `foundation_operational`

Important implemented behavior:

- creating a lot writes `RECEIVE_WAX_FROM_CLIENT`, increases `wax_custody`, and issues `PV-CUST`
- lot price and shrinkage are snapshotted on `processing_lots`
- exchange uses lot snapshot values, not current globals
- return wax decreases custody and issues `PV-RET`
- factory delivery decreases custody and increases operational foundations
- factory buffer adjusts operational foundations by external aviz
- buffer minus cannot go negative

Strictness added recently:

- processing form defaults come only from the assigned store
- if the user store has no assigned processor, processing screens fail clearly
- factory delivery no longer falls back to the first processor in DB
- creating a processing lot requires explicit lot price and shrinkage; backend no longer falls back to processor terms

## Factory Buffer

`factory_buffer_adjustments` now stores:

- `aviz_number`
- `aviz_date`
- `reception_date`
- `qty_g`
- `store_id`
- `notes`

UI now shows:

- `Data aviz`
- `Data receptiei`

Both default to today in the form.

NIR generation for buffer still exists. For related document variables, `aviz_date` now comes from the real aviz date, not `created_at`.

## Purchase Flow

Pages:

- `purchases`
- `purchase_exit`
- `purchase_register`

Separate stock:

- `wax_purchased`

Must never mix with:

- `wax_custody`

Purchase commercial defaults come from store fields:

- `purchase_shrinkage_pct`
- `purchase_price_cents_per_kg`

## Documents

Document counters are in `document_series` per `store_id + document_type`.

Internal types currently managed in settings:

- `PV-CUST`
- `PV-FAG`
- `PV-RET`
- `AVIZ`
- `NIR`
- `BON`
- `BORD`

FGO invoice series is not in `document_series`; it is mapped from `stores.fgo_series`.

Recent strictness:

- missing internal series now raises explicit runtime error
- missing store FGO series raises explicit runtime error
- missing FGO URL/token/CUI raises explicit runtime error
- FGO response must contain final series and number; placeholder values are no longer accepted silently

## Settings

Admin-manageable settings:

- company data
- FGO URL
- FGO token
- processors
- stores
- document series
- document templates
- users
- roles/permissions

Store fields:

- `code`
- `name`
- `address`
- `fgo_series`
- `processor_id`
- `processing_shrinkage_pct`
- `processing_price_cents`
- `purchase_shrinkage_pct`
- `purchase_price_cents_per_kg`

Processor fields now in active use:

- `name`
- `cui`
- `address`
- `processing_price_cents`
- `exchange_shrinkage_pct`

Recently removed dead settings/schema:

- `company_settings.fgo_private_key`
- `company_settings.purchase_default_*`
- `company_settings.purchase_factory_*`
- `processors.contact`
- `processors.purchase_shrinkage_pct`
- old `save_settings` path
- old global default processor helper path

## Local Startup From Zero

1. Clone repo.
2. Start XAMPP Apache + MySQL.
3. Create ignored `config/local.php` with DB credentials, or set `CEARA_DB_*`.
4. Create ignored `config/xampp-target.local.txt` with local htdocs target.
5. Run sync script.
6. Open app.

Local login:

- user: `admin`
- password: `admin`

Important:

- local startup no longer auto-creates a gestiune or processor
- after first login, admin must configure processors, stores, user-store mapping and document series

## Production

Production is now prepared for full replacement deploy:

- delete old files from app folder
- extract the fresh archive completely
- run fresh `deploy/sql/init-production.sql`

`init-production.sql` is a full reset for the selected DB. It should be used only for empty/new reset deployment.

Production seed after reset:

- user: `admin`
- password: `CearaAdmin!2026`

After first login, admin must configure:

1. company data + FGO URL/token
2. processors
3. stores
4. user-store assignments
5. internal document series
6. FGO series per store

## Files To Read First In A New Codex Session

1. `AI_HANDOVER.md`
2. `PROJECT_CONTEXT.md`
3. `README.md`
4. `docs/spec.md`
5. `docs/source-specs/03-settings.md`
6. `docs/source-specs/13-database-schema.md`
7. `deploy/DEPLOY_PRODUCTION.md` when production is relevant

## Current Open Direction

Best next audit area after this handover:

- document/value source audit across all generated documents and numbering paths

Then:

- polish user-facing operational error messages for operator/admin contexts where useful
