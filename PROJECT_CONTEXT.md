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

- `1.0.009`

## Processing Refactor Note

Processing lots are no longer intended to behave as one rigid status workflow.
The new model keeps `processing_lots` as the main lot container and uses
append-only rows in `processing_lot_movements` as the source of truth for
balances and operational state.

Implemented movement types:

- `RECEIVE_WAX_FROM_CLIENT`
- `EXCHANGE_WAX_WITH_CLIENT`
- `RETURN_WAX_TO_CLIENT`
- `SEND_WAX_TO_FACTORY`
- `RECEIVE_FOUNDATION_FROM_FACTORY`
- `FACTORY_REJECT_WAX`
- `RECORD_LOSS`
- `RECOVER_FOUNDATION_FROM_CLIENT`

The `Loturi ceara` page now shows calculated totals and calculated status:

- `Procesare`
- `Recuperare`
- `Inchis`

The `lot_detail` page shows lot header data, calculated balances, movement
journal, exchange action, return action, and document buttons tied to movement
rows. Exchange documents (`FACT`, `BON`, `PV-FAG`) are generated manually from
the exchange movement row.

Factory delivery remains batch-based. It creates `SEND_WAX_TO_FACTORY`
movements, decreases wax custody, creates automatic
`RECEIVE_FOUNDATION_FROM_FACTORY` movements for the calculated foundation
quantity, and increases `foundation_operational`.

The local XAMPP database was intentionally cleared of old processing lot data
after this refactor so new testing starts from the movement model.

## Current Workflow Model

The application now starts from a dashboard with two intended business flows:

- `Schimb de ceara` / processing flow: active and partially functional.
- `Achizitie ceara`: intentionally disabled for now and planned to be rebuilt
  from zero with separate stock rules.

Before a flow is selected, the sidebar shows only general pages: Dashboard,
Documente, Rapoarte, Setari, and Audit. Selecting `Schimb de ceara` activates
the processing menu:

- Procesare ceara
- Loturi ceara
- Predare fabrica
- Buffer fabrica
- Registru gestiune

The dashboard KPI row currently shows only:

- Stoc faguri operational
- Ceara in custodie
- Ceara proprie

## Store / Processor Rule

Each store / gestiune is assigned to exactly one processor for the processing
flow. If a physical location needs multiple processors, create separate
gestiuni, for example `Onesti_Boca` and `Onesti_Stuparul`.

Users operate through their primary assigned store. Processing register and
operational actions are scoped to that store.

## Processing Register

`Registru gestiune` is a store-scoped operational register for processing.
It is calculated from `inventory_transactions`, not entered manually.

The register shows:

- Partner: client or factory / assigned processor.
- Lot: link to lot detail when the movement belongs to a lot.
- Document: mock link now; later it will open the generated PDF from disk.
- Data.
- Ceara in custodie: signed in/out quantity.
- Faguri in custodie: signed in/out quantity.
- Operator.

Totals at the top show the current wax custody and operational foundation
stock for the operator's store. Column totals show the sum of displayed
movements.

## Buffer Fabrica

`Buffer fabrica` manages initial and corrective foundation stock received from
or returned to the assigned processor. Each buffer entry is append-only:

- `Plus` increases `foundation_operational`.
- `Minus` decreases `foundation_operational` and cannot make stock negative.
- Each buffer aviz automatically generates a `NIR` document record.
- Existing and new NIR records are linked through the mock document endpoint.

## Document Links

Documents are still mock records. Links currently open
`index.php?page=document_mock&document_id=...`, which displays a simple text
placeholder. Later, generated PDF files will be saved to disk and the same
document links should open/print those PDFs.

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
