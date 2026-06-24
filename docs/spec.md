# Project Spec

## Objective

Maintain a local-first operational app for wax processing and wax purchase workflows, with strict stock separation, traceable documents, and machine-portable deployment through XAMPP.

## Core Principles

- quantities are stored as integer grams
- UI displays kilograms with three decimals
- inventory movements are append-only
- critical config should not silently fall back
- settings are configured by admin-capable users
- operators should see operational errors and ask admin to fix missing setup
- business calculations belong in services, not in views

## Roles

Current roles:

- `admin`
- `operator`

Production-style reset seed from SQL:

- user: `admin`
- password: `CearaAdmin!2026`

Business rule:

- one user works on one gestiune
- one gestiune may have multiple users
- clean init may leave `admin` without gestiune, but login must still succeed so setup can continue

## Navigation

Main navigation:

- Dashboard
- Documente
- Rapoarte
- Setari
- Audit

Processing navigation:

- Procesare ceara
- Loturi ceara
- Predare fabrica
- Buffer fabrica
- Registru gestiune

Purchase navigation:

- Achizitie ceara
- Iesire ceara
- Registru achizitie

## Environments

Current environment map:

- CODE: `D:\Novalupusstudio\ceara`
- DEV: `D:\xampp\htdocs\ceara` with DB `ceara`
- STAGE: `D:\xampp\htdocs\ceara_stage` with DB `ceara_stage`
- PROD: `../ceara` with DB `stuparul_ceara`

## Stock Model

Separate stock buckets:

- `wax_custody`
- `foundation_operational`
- `wax_purchased`

They must never mix.

## Processing Flow

### Create Processing Lot

Inputs:

- customer type `PF` / `PJ`
- processor
- gross wax kg
- processing price
- shrinkage

PF required fields:

- customer name
- phone
- address

PJ required fields:

- company name
- CUI
- phone
- address
- representative

Lookup behavior:

- PF lookup by phone while typing
- PJ lookup by CUI while typing
- ANAF data is only prefill help; final saved values are the edited form values at submit

Save behavior:

- create/update customer
- create `processing_lots`
- snapshot `processing_price_cents` and `shrinkage_pct`
- create `RECEIVE_WAX_FROM_CLIENT`
- increase `wax_custody`
- issue `PV-CUST`

### Lot Detail

Shows:

- simplified summary cards
- collapsible detail area
- movement journal
- generated documents

Main user-facing summary meanings:

- wax received
- wax already exchanged and wax still available to exchange
- foundations already handed over and foundations still possible to hand over
- wax returned
- lot state

Rules:

- exchange cannot exceed exchangeable wax
- exchange cannot exceed available `foundation_operational`
- return cannot exceed current custody wax
- lot calculations use the lot snapshot values

Important operational meaning:

- exchanging foundations to the client does not remove custody wax
- custody wax leaves only when sent to factory, returned to the client, or recorded as loss

### Factory Delivery

- separate page from lot board
- processor-scoped
- page shows only lots for the selected processor
- each row supports delivered quantity and rejected quantity
- operator fills `aviz_number` and `aviz_date`

Batch save behavior:

- create `factory_batches`
- create `factory_batch_items`
- create `SEND_WAX_TO_FACTORY` movement where applicable
- create `FACTORY_REJECT_WAX` movement where applicable
- create `RECEIVE_FOUNDATION_FROM_FACTORY` movement for delivered quantity
- decrease `wax_custody`
- increase `foundation_operational`
- auto-generate `AVIZ`
- auto-generate `NIR`

### Factory Buffer

- adjusts `foundation_operational`
- `plus` increases stock
- `minus` decreases stock
- minus cannot exceed available operational stock

Fields:

- adjustment type
- aviz number
- aviz date
- reception date
- quantity
- store
- notes

Each adjustment issues linked `NIR`.

### Processing Register

Store-scoped register based on `inventory_transactions`.

Shows:

- opening balances
- closing balances
- current balances
- partner
- lot link
- document link
- signed wax qty
- signed foundation qty
- operator

Register rows should link to the actual generated document rows whenever those documents exist.

## Lot State Logic

Movement types:

- `RECEIVE_WAX_FROM_CLIENT`
- `EXCHANGE_WAX_WITH_CLIENT`
- `RETURN_WAX_TO_CLIENT`
- `SEND_WAX_TO_FACTORY`
- `RECEIVE_FOUNDATION_FROM_FACTORY`
- `FACTORY_REJECT_WAX`
- `RECORD_LOSS`
- `RECOVER_FOUNDATION_FROM_CLIENT`

Current important balance rules:

- custody wax = received - sent to factory - returned - wax loss
- exchangeable wax = received - exchanged - returned - wax loss
- exchangeable foundations = shrinkage-applied result of exchangeable wax
- open rejected wax = rejected - returned - wax loss

Current calculated operational states:

- `Procesare`
- `Recuperare`
- `Inchis`

Lot closes when custody reaches zero and no open derived balances remain.

## Purchase Flow

Purchase stock bucket:

- `wax_purchased`

### Purchase Entry

Supplier types:

- `PF`
- `Producator agricol`
- `PJ/PFA`

Save behavior:

- create/update supplier
- create `purchase_lots`
- add positive `wax_purchased`
- keep external commercial references

### Purchase Exit

Fields:

- partner/factory
- identifier/CUI
- quantity
- external document type/series/number/date
- notes

Save behavior:

- create `purchase_wax_exits`
- add negative `wax_purchased`
- cannot exceed purchased stock

## Documents

Internal managed types:

- `PV-CUST`
- `PV-FAG`
- `PV-RET`
- `AVIZ`
- `NIR`
- `BON`
- `BORD`

Invoice path:

- `FACT` uses FGO series from `stores.fgo_series`

Rules:

- missing internal series => runtime error
- missing store FGO series => runtime error
- missing FGO URL/token/CUI => runtime error
- invalid FGO response without final series/number => runtime error

Storage:

- PDFs: `storage/documents/<store_code>/`
- FiscalWire output: `storage/fiscalwire-out/`

## Settings

Current recommended first-run order:

1. `Date societate`
2. `Procesatori`
3. `Gestiuni`
4. `Serii documente`
5. `Roluri si drepturi`
6. `Creare useri`
7. `Template documente`
8. `Schimba parola`

## Local Runtime

Current machine locations:

- source: `E:\NovaLupus\ceara`
- XAMPP deploy: `E:\XAMP\htdocs\ceara`
- local URL: `http://localhost/ceara/`

Required local DB config:

- ignored `config/local.php`, or
- env vars `CEARA_DB_*`

Required local sync target file:

- ignored `config/xampp-target.local.txt`

## Deployment

### Local

- use `scripts/sync-to-xampp.ps1`

### Production

Expected model:

1. replace app files with fresh archive
2. run `deploy/sql/init-production.sql` on the target DB when doing a clean reset
3. configure settings in-app
