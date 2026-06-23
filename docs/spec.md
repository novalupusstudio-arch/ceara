# Project Spec

## Objective

Maintain a local-first PHP/MySQL application for two separate wax flows:

1. processing customer wax held in custody
2. purchasing company-owned wax

The app must preserve stock traceability and never mix custody wax with purchased wax.

## Core Principles

- quantities are stored as integer grams
- UI displays kg with three decimals
- inventory movements are append-only
- critical settings should not silently fall back
- settings are configured by admin only
- operators should see operational errors and ask admin for fixes

## Roles

Roles:

- `admin`
- `operator`

Local seed login:

- user: `admin`
- password: `admin`

Production reset seed login:

- user: `admin`
- password: `CearaAdmin!2026`

## Dashboard And Flow Selection

Dashboard exposes two flows:

- `Schimb de ceara`
- `Achizitie ceara`

General navigation:

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

## Processing Flow

### Create Processing Lot

Inputs:

- PF/PJ customer data
- processor
- gross wax kg
- processing price
- shrinkage

Default source:

- processor is taken from assigned store
- price and shrinkage are taken from assigned store
- user can still edit the lot values in the form

Strict rules:

- assigned store is required
- assigned store processor is required
- lot processing price is required
- lot shrinkage is required
- backend no longer falls back to processor values if the form is incomplete

Save behavior:

- create `processing_lots`
- snapshot `processing_price_cents` and `shrinkage_pct`
- create `RECEIVE_WAX_FROM_CLIENT`
- increase `wax_custody`
- issue `PV-CUST`

### Lot Detail

Shows:

- calculated balances
- movement journal
- generated documents

Actions:

- exchange wax with client
- return wax to client

Rules:

- exchange cannot exceed exchangeable wax
- exchange cannot make `foundation_operational` negative
- service value uses lot snapshot price
- foundation quantity uses lot snapshot shrinkage
- return cannot exceed wax still in custody

### Factory Delivery

- only for processing stock
- uses the selected or assigned processor
- no fallback to the first processor in DB
- if no valid processor exists, screen must fail with clear error
- sent wax decreases `wax_custody`
- expected foundation increases `foundation_operational`

### Factory Buffer

- adjusts `foundation_operational` by external aviz
- `plus` increases stock
- `minus` decreases stock and cannot go negative
- each adjustment issues linked `NIR`

Buffer adjustment fields:

- adjustment type
- aviz number
- aviz date
- reception date
- quantity
- store
- notes

Both dates default to today in UI.

### Processing Register

Store-scoped register from `inventory_transactions`.

Filters:

- date start
- date end

Shows:

- current totals
- opening balance
- closing balance
- partner
- lot link
- document link
- date
- signed wax custody quantity
- signed foundation quantity
- operator

## Purchase Flow

Purchase stock:

- `wax_purchased`

Must never use:

- `wax_custody`

### Purchase Entry

Supplier types:

- `PF`
- `Producator agricol`
- `PJ/PFA`

Store defaults:

- purchase shrinkage
- purchase price with VAT

These defaults come from `stores`.

Save behavior:

- create/update supplier
- create `purchase_lots`
- add positive `wax_purchased`
- do not generate internal PDF

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

Internal generated document counters live in:

- `document_series`

Current managed internal types:

- `PV-CUST`
- `PV-FAG`
- `PV-RET`
- `AVIZ`
- `NIR`
- `BON`
- `BORD`

FGO invoice series is mapped from:

- `stores.fgo_series`

Strict rules:

- missing internal document series => runtime error
- missing store FGO series => runtime error
- missing FGO URL/token/CUI => runtime error
- invalid FGO response without final series/number => runtime error

PDF storage:

- `storage/documents/<store_code>/`

FiscalWire storage:

- `storage/fiscalwire-out/`

FiscalWire current hardcoded operational values:

- article name `Servicii procesare`
- VAT code `1`
- extension `.inp`

## Settings

### Company

- company name
- CUI
- registry number
- address
- phone
- email
- FGO URL
- FGO token

### Stores

- code
- name
- address
- FGO series
- assigned processor
- processing shrinkage %
- processing price lei/kg
- purchase shrinkage %
- purchase price lei/kg

### Processors

Active processor fields:

- name
- CUI
- address
- processing price lei/kg in relation with processor
- processor shrinkage %

Removed dead processor/company legacy fields should no longer be reused.

## Local Runtime

Required local DB config:

- ignored `config/local.php`, or
- env vars `CEARA_DB_*`

Required local sync target file:

- ignored `config/xampp-target.local.txt`

Startup no longer auto-creates default stores/processors.

## Deployment

### Local

- use `scripts/sync-to-xampp.ps1`

### Production

Current expected production deployment mode:

1. delete all old files from app folder
2. extract fresh full archive
3. run full reset SQL on selected DB
4. open app and configure admin-side settings

Main files:

- `deploy/sql/init-production.sql`
- `deploy/DEPLOY_PRODUCTION.md`
- release zip in `deploy/output/`
