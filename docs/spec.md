# Project Spec

## Purpose

Ceara is a web application for managing two business flows:

1. Wax processing: exchanging customer wax for wax foundations.
2. Wax purchase: buying wax and converting it into company-owned stock.

The core product goal is complete traceability by lot, with strict separation between customer custody inventory and company-owned inventory.

## Principles

- Full lot traceability.
- Complete separation between custody stock and company-owned stock.
- Complete audit trail for sensitive operations.
- Multi-store support.
- Multi-processor support.
- Future billing/fiscal integration.

## Users and Roles

### Admin

Admins can manage:

- users
- roles and permissions
- stores
- processors
- prices
- shrinkage rules
- integrations
- audit access

### Operator

Operators can:

- create and process wax lots
- create wax purchases
- generate operational documents
- access only assigned stores

## Recommended Permissions

- `USER_CREATE`
- `USER_EDIT`
- `USER_RESET_PASSWORD`
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
- contact
- processing price
- exchange shrinkage percentage
- purchase shrinkage percentage

### Billing

- API endpoint
- token
- invoice series
- document series

## Processing Flow

### Statuses

- In Validare
- Acceptat
- Predat Fabricii
- Respins
- Returnat

### Known Customer Flow

1. PV Custodie
2. Acceptare
3. Factură
4. Bon fiscal
5. PV Predare Faguri
6. Status Acceptat

### Unknown Customer Flow

1. PV Custodie
2. Status In Validare
3. Predare lot la fabrică
4. Acceptat or Respins
5. If accepted: Factură + PV Predare
6. If rejected: PV Returnare

### Lot Transitions

- In Validare -> Acceptat
- In Validare -> Respins
- Acceptat -> Predat Fabricii
- Respins -> Returnat

## Purchase Flow

### Supplier Types

- PF, using borderou
- Agricultural producer
- PFA/SRL

### Flow

1. Achiziție
2. NIR
3. Stoc ceară proprie
4. Predare la procesator
5. Recepție faguri
6. Stoc marfă

## Documents

### Processing Documents

- PV predare în custodie
- Factură serviciu
- Bon fiscal
- PV predare faguri
- PV returnare ceară
- Aviz predare procesator
- NIR custodie faguri

### Purchase Documents

- Borderou
- Factură furnizor
- NIR materie primă
- Aviz procesator
- NIR produse finite

## Inventory Model

### Custody

- operational wax foundation stock
- customer wax

### Company-Owned

- purchased wax
- merchandise wax foundations

Custody stock and company-owned stock must never be mixed.

## Dashboard

### Main Actions

- Procesare Ceară
- Achiziție Ceară

### KPI

- operational wax foundation stock
- wax in custody
- lots pending validation
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
- PFA/SRL purchases
- wax stock
- wax foundation stock

## Audit

Every important operation should log:

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
- QR Codes
- Mobile app

## Database Direction

Main tables:

- `users`
- `roles`
- `permissions`
- `stores`
- `processors`
- `customers`
- `suppliers`
- `processing_lots`
- `purchase_lots`
- `documents`
- `inventory_transactions`
- `audit_log`

### `processing_lots`

- `id`
- `lot_number`
- `customer_id`
- `status`
- `gross_kg`
- `shrinkage_pct`
- `foundation_kg`
- `store_id`
- `created_by`

### `inventory_transactions`

- `id`
- `date`
- `type`
- `qty`
- `store_id`
- `reference_document`

## UI Direction

### Dashboard

The dashboard starts with two primary actions:

- Procesare Ceară
- Achiziție Ceară

It should also show the KPI listed above.

### Processing

- lot list
- create lot
- accept lot
- reject lot
- send to factory

### Purchase

- create purchase
- NIR
- send to factory
- receive wax foundations

## Open Decisions

- Exact stack: PHP-only, PHP + MySQL, or another structure.
- Authentication model and password reset flow.
- Document numbering rules.
- Fiscal integration timing: mock first or real integration from MVP.
- Whether unknown customer validation requires processor feedback before accept/reject.
- Whether inventory quantities need batch/lot-level costing.
