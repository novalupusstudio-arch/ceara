# Flow Processing Wax

## Current Flow

1. Create a processing lot from `Procesare ceara`.
2. Lot receives custody wax through `RECEIVE_WAX_FROM_CLIENT` and `wax_custody` inventory.
3. The form defaults processing price and shrinkage from the assigned store/processor, but both values are editable for the lot.
4. The lot snapshots `processing_price_cents` and `shrinkage_pct`; all later calculations use the saved lot values.
5. Lot board and lot detail display calculated status/balances.
6. Exchange wax with client from lot detail; this creates movement, service value and foundation stock decrease.
7. Generate processing documents from movement rows: FGO invoice, FiscalWire receipt, PV-FAG, PV-CUST, PV-RET where applicable.
8. Return wax to client from lot detail when needed.
9. Send custody wax to assigned processor/factory through batch `Predare fabrica` page.
10. Manage operational foundation buffer through `Buffer fabrica`.
11. Review store movements in `Registru gestiune`.

## Source Of Truth

- `processing_lots` is the lot container.
- `processing_lot_movements` is the operational movement journal.
- `inventory_transactions` is the stock ledger.

## Stock Types

- `wax_custody`
- `foundation_operational`
