# Processing Flow

## Pages

- `processing`
- `lots`
- `lot_detail`
- `factory_delivery`
- `factory_buffer`
- `processing_register`

## Core Stock

- custody wax: `wax_custody`
- operational foundations: `foundation_operational`

## Create Lot

The assigned user store is mandatory.

The lot processor is expected from store context and must be valid.

Form defaults:

- processor from store
- processing price from store
- shrinkage from store

The user may edit lot price and shrinkage before save. The backend snapshots those values on the lot.

Customer behavior:

- PF is default
- PF requires name, phone, address
- PJ requires company name, CUI, phone, address, representative
- PF lookup is by phone while typing
- PJ lookup is by CUI while typing
- ANAF is only a prefill helper, not the final source of truth

No silent fallback should happen if required values are missing.

## Exchange / Return

- exchange cannot exceed exchangeable wax
- exchange cannot make `foundation_operational` negative
- return cannot exceed wax still in custody
- exchange documents and service values use lot snapshot values

Important meaning:

- client exchange does not remove custody wax
- wax stays in custody until it goes to factory, is returned, or is recorded as loss

## Factory Delivery

- separate page from lot board
- processor-scoped
- shows only lots belonging to that processor
- operator enters `aviz_number` and `aviz_date`
- each lot row may contain delivered quantity and rejected quantity

Save behavior:

- creates `factory_batches`
- creates `factory_batch_items`
- records `SEND_WAX_TO_FACTORY`
- records `FACTORY_REJECT_WAX` where applicable
- records `RECEIVE_FOUNDATION_FROM_FACTORY`
- decreases `wax_custody`
- increases `foundation_operational`
- auto-generates linked `AVIZ`
- auto-generates linked `NIR`

## Factory Rejection / Recovery Meaning

- factory rejection does not automatically block future commercial exchange
- the business may still choose to exchange on loss or return wax
- lots with rejected wax are visually highlighted in lot detail
- open rejected wax is considered resolved when that wax was returned to client or recorded as loss

## Factory Buffer

- plus increases `foundation_operational`
- minus decreases `foundation_operational`
- minus cannot go negative
- adjustment stores aviz number, aviz date, reception date
- each adjustment generates linked `NIR`

## Processing Register

- store-scoped
- based on `inventory_transactions`
- lot and document columns should link to real related records where available
