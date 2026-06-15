# Project Spec

## Purpose

Ceara is a PHP + MySQL web app for managing a wax business with two core flows:

1. Processing customer wax into wax foundations.
2. Purchasing wax and turning it into company-owned inventory.

The core goal is lot traceability, with clear separation between customer custody stock and company-owned stock.

## Principles

- Full lot traceability.
- Separate custody stock from company-owned stock.
- Append-only audit trail for sensitive actions.
- Multi-store support.
- Multi-processor support.
- Mock fiscal/document outputs first, real integrations later.

## Users and Roles

### Admin

The seeded initial admin is `admin / admin`.

Admin can manage:

- users
- roles and permissions
- stores
- processors
- settings
- audit access

### Operator

Operator can:

- create processing lots
- create purchase lots
- use the assigned store only
- work inside the permissions granted by role

## Recommended Permissions

- `USER_CREATE`
- `USER_EDIT`
- `USER_RESET_PASSWORD`
- `ROLE_PERMISSION_MANAGE`
- `STORE_MANAGE`
- `PROCESSOR_MANAGE`
- `PROCESSING_CREATE`
- `PROCESSING_ACCEPT`
- `PROCESSING_REJECT`
- `PURCHASE_CREATE`
- `REPORT_VIEW`
- `AUDIT_VIEW`

## Settings

### Company Data

- name
- fiscal code / CUI
- trade registry number
- address
- bank
- IBAN
- phone
- email

### Stores

- code
- name
- address

### Processors

- name
- CUI
- address
- contact
- processing price
- exchange shrinkage percentage

### Document Series

- Every document has a store / gestiune series.
- Numbering starts simple for MVP: `1`, `2`, `3`.
- The exact numbering rules can be refined later.

## Processing Flow

### Statuses

- `In Validare`
- `Acceptat`
- `Predat Fabricii`
- `Respins`
- `Returnat`

### Current Behavior

- New lots start in `In Validare`.
- The lot board is the main validation screen.
- `Acceptat` is the end state on the lot board.
- Predarea la fabrica is handled in batch on a separate page.

### Main Screens

- Processing lot creation
- Lot board with live filters
- Batch factory delivery page

## Purchase Flow

### Supplier Types

- PF
- PFA
- PJ / SRL

### Current Behavior

- Purchase flow exists as a separate business path.
- It follows the same general model of mock documents and stock movement.

## Documents

### Processing Documents

- PV custodie
- Factura serviciu
- Bon fiscal
- PV returnare
- AVIZ procesator
- NIR

### Purchase Documents

- Borderou
- Factura furnizor
- NIR materie prima
- AVIZ procesator
- NIR produse finite

### Fiscal Output

- Documents are mock records for now.
- Future integrations will replace the mock layer later.

## Inventory Model

### Custody

- customer wax
- operational wax foundations

### Company-Owned

- purchased wax
- merchandise wax foundations

Custody stock and company-owned stock must never be mixed.

### Quantity Precision

- Store quantities in grams.
- Show quantities in kilograms with three decimals.
- Keep calculations in integer grams to avoid rounding drift.

## Dashboard

### Main Actions

- Procesare Ceara
- Achizitie Ceara

### KPI

- operational wax foundation stock
- wax in custody
- lots pending validation
- accepted lots
- rejected lots
- company-owned wax stock
- merchandise wax foundation stock

## Reports

### Processing

- open lots
- accepted lots
- rejected lots
- wax in custody

### Purchase

- PF purchases
- PFA/PJ purchases
- wax stock
- wax foundation stock

## Audit

Log every important operation with:

- user
- date
- operation
- old value
- new value

Audit records are append-only and must not be deleted.

## Future Integrations

- eFactura
- POS
- SMS
- Email
- QR codes
- Mobile app

## Technical Stack

- Backend: plain PHP
- Database: MySQL
- Local runtime: XAMPP
- Frontend: server-rendered PHP with light JavaScript helpers
- Source workspace: `E:\NovaLupus\ceara`
- XAMPP test copy: `E:\XAMP\htdocs\ceara`

## Database Direction

Main tables include:

- `users`
- `roles`
- `permissions`
- `stores`
- `processors`
- `customers`
- `suppliers`
- `processing_lots`
- `processing_lot_status_events`
- `factory_batches`
- `factory_batch_items`
- `purchase_lots`
- `documents`
- `inventory_transactions`
- `audit_log`

## Open Decisions

- Final lot and document numbering rules.
- Whether batch history needs dedicated screens.
- Whether more detailed per-lot delivery auditing is needed.
- How strict password change enforcement should be for new users.
