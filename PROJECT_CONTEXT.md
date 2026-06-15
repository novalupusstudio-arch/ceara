# Project Context

## Project Purpose

Ceara is a PHP + MySQL web app for managing a wax business with two core operational flows:

1. Processing customer wax into wax foundations.
2. Purchasing wax and turning it into company-owned inventory.

The system is built around lot traceability, simple local deployment through XAMPP, and a clean separation between customer custody stock and company-owned stock.

## Business Flows

### Processing Flow

- Create a processing lot from the processing screen.
- Select PF or PJ customer type.
- Search/select an existing customer or create a new one.
- The lot is created in `In Validare`.
- The validation and status-board actions are handled on the separate `Loturi ceara` page.
- `Acceptat` is the endpoint on that board.
- Predarea către fabrică is handled in batch on the separate `Predare fabrica` page.

### Factory Delivery Flow

- Select a processor.
- View all processing lots for that processor in `In Validare` and `Acceptat`.
- Edit the quantity to send for each lot.
- The batch creates a factory delivery record.
- Stock is moved out of custody and into operational foundation stock.
- Mock documents are generated for the batch.

### Purchase Flow

- Create a purchase lot.
- Generate mock purchase documents.
- Move purchased wax into company-owned stock.
- Advance the purchase through its own purchase statuses.

### Settings Flow

- Change own password.
- Admin initial can manage role permissions.
- Admin initial can create users.
- Admin and operators can have store assignments based on permissions.
- Admin manages stores and processors.

## Lot Statuses

Processing lot statuses currently implemented in code:

- `In Validare`
- `Acceptat`
- `Predat Fabricii`
- `Respins`
- `Returnat`

Important behavior:

- New processing lots start in `In Validare`.
- `Acceptat` is reached from `In Validare`.
- `Predat Fabricii` is now reached through the batch delivery page, not from the lot board.
- `Respins` can move to `Returnat`.

## Inventory Model

Inventory quantities are stored in grams.

Visible stock categories currently used in code:

- `wax_custody`: customer wax held in custody
- `foundation_operational`: wax foundations produced from processing
- `wax_owned`: company-owned purchased wax
- `foundation_merchandise`: company-owned wax foundations from purchase flow

Current processing-specific quantity fields:

- `gross_g`: original lot quantity
- `factory_sent_g`: quantity already sent to factory
- `processing_price_cents`: processing cost per kilogram in cents
- `shrinkage_pct`: shrinkage percentage
- `foundation_g`: expected foundation output in grams

UI convention:

- Show kilograms with three decimals, for example `1.234 kg`
- Keep calculations in integer grams internally where possible

## User Roles

### Admin

The seeded initial admin is `admin / admin`.

Admins can:

- manage users
- manage role permissions
- manage stores
- manage processors
- access audit and reports
- create and operate lots according to assigned permissions

### Operator

Operators can:

- create processing lots
- use assigned store only
- create purchases
- work with operational screens allowed by role permissions

## Document Types

Mock document types currently in the app:

- `PV-CUST` - custody intake / preluare
- `FACT` - invoice
- `BON` - cash receipt
- `PV-RET` - return PV
- `AVIZ` - factory / processor dispatch note
- `NIR` - reception note
- `BORD` - supplier borderou for PF purchases

Notes:

- Processing lot documents are mocked for now.
- Factory delivery batches generate mock `AVIZ` and `NIR`.
- Purchase flow uses `BORD` or `FACT` plus `NIR`.

## Current Architecture

- Plain PHP, no framework
- Server-rendered pages
- MySQL persistence
- XAMPP for local runtime
- Shared utilities in `lib/`
- Routing and action handling in `index.php`
- Styling in `assets/styles.css`
- Small client-side behavior in `assets/app.js`
- Schema + lightweight migrations in `db/schema.sql` and `lib/Database.php`
- Source of truth lives in `E:\NovaLupus\ceara`
- Local deployment copy syncs to `E:\XAMP\htdocs\ceara`

Current app version in config:

- `1.0.006`

## Important Business Rules

- The initial admin exists and is the only account seeded at startup.
- Admin initial uses the first user record and is the only one allowed to edit role permissions and create users at the moment.
- Users are linked to a primary store, and that store is used for processing operations.
- Processors have price and shrinkage settings that prefill processing calculations.
- Processing lots always start in validation.
- Predarea to factory is batch-based and happens on a dedicated page.
- Batch quantities can be edited per lot before submission.
- Inventory values should not mix custody stock with company-owned stock.
- Documents are generated as mock records now and are intended to be replaceable later with real integrations.
- Audit logging is append-only for sensitive operations.
- The local sync script is the expected deployment path during development.
