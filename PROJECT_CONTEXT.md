# Project Context

## Purpose

`Ceara` is a local-first PHP + MySQL operational webapp for a wax-processing business that runs two separate domains:

1. customer wax received in custody and exchanged into foundations
2. company-owned wax purchased into stock and later exited separately

The same codebase is used for local DEV, local STAGE, and live PROD.

## Environment Layout

### Code / source

- `D:\Novalupusstudio\ceara`

This is the only place where code should be edited.

### DEV

- code deploy: `D:\xampp\htdocs\ceara`
- URL: `http://localhost/ceara/`
- DB: `ceara`
- runtime DB config: `D:\xampp\htdocs\ceara\config\local.php`

### STAGE

- code deploy: `D:\xampp\htdocs\ceara_stage`
- URL: `http://localhost/ceara_stage/`
- DB: `ceara_stage`
- runtime DB config: `D:\xampp\htdocs\ceara_stage\config\local.php`

### PROD

- code deploy: `../ceara`
- URL: `https://www.stuparul.com/ceara/`
- DB: `stuparul_ceara`
- build-time DB config source: `deploy/local/config.php`
- runtime DB config: `../ceara/config/local.php`

## Current Version

- `1.2.012`

## Local Sync / Build Files

- DEV sync script:
  - `scripts/sync-to-xampp.ps1`
- DEV target override:
  - `config/xampp-target.local.txt`
- STAGE target override:
  - `config/xampp-stage-target.local.txt`
- production build script:
  - `scripts/build-production-zip.ps1`
- production reset SQL:
  - `deploy/sql/init-production.sql`

## Main Business Flows

### 1. Processing / custody

Pages:

- `processing`
- `lots`
- `lot_detail`
- `factory_delivery`
- `factory_buffer`
- `processing_register`

Important stock buckets:

- `wax_custody`
- `foundation_operational`

Core interpretation:

- exchanging foundations to client does not remove custody wax
- custody wax leaves only when sent to factory, returned, or recorded as loss

### 2. Purchase

Pages:

- `purchases`
- `purchase_exit`
- `purchase_register`

Important stock bucket:

- `wax_purchased`

Purchased wax must never mix with custody wax.

## Lot / Movement Logic

Processing movement types:

- `RECEIVE_WAX_FROM_CLIENT`
- `EXCHANGE_WAX_WITH_CLIENT`
- `RETURN_WAX_TO_CLIENT`
- `SEND_WAX_TO_FACTORY`
- `RECEIVE_FOUNDATION_FROM_FACTORY`
- `FACTORY_REJECT_WAX`
- `RECORD_LOSS`
- `RECOVER_FOUNDATION_FROM_CLIENT`

Important current calculations:

- custody wax = received - sent to factory - returned - wax loss
- exchangeable wax = received - exchanged - returned - wax loss
- exchangeable foundations = shrinkage-applied result of exchangeable wax
- open rejected wax = rejected - returned - wax loss

Operational calculated states:

- `Procesare`
- `Recuperare`
- `Inchis`

## User / Store Rule

Business rule:

- one user works on one gestiune
- one gestiune may have many users

Important current behavior:

- a clean `init-production.sql` creates `admin` without any store
- admin must still be able to log in and reach settings
- dashboard therefore must not hard-fail when no store is assigned yet

## Settings Model

Settings are admin-only.

Key areas:

1. `Date societate`
2. `Procesatori`
3. `Gestiuni`
4. `Serii documente`
5. `Roluri si drepturi`
6. `Creare useri`
7. `Template documente`
8. `Backup si sync`

Important split:

- store values = defaults for relation with client
- processor values = relation with factory
- lot values = snapshot used by operations/documents for that lot

## Documents

Current document families in active use:

- `PV-CUST`
- `PV-FAG`
- `PV-RET`
- `AVIZ`
- `NIR`
- `FACT`
- `BON`
- `BORD`

Important numbering rule:

- internal documents are per `store + document type`
- invoice series for FGO lives in the store

## Production Notes

Current known-good full package:

- `deploy/output/ceara-production-20260624-143740.zip`

Current patch package:

- `deploy/output/ceara-production-patch-1.2.011-20260624.zip`

Current seeded clean reset login:

- user: `admin`
- password: `CearaAdmin!2026`

## Recommended Release Workflow

1. code in `D:\Novalupusstudio\ceara`
2. sync to DEV
3. test freely in DEV
4. sync candidate to STAGE
5. import production backup into STAGE when needed
6. re-enter FGO URL/token after import
7. validate in STAGE
8. deploy the same candidate to PROD
