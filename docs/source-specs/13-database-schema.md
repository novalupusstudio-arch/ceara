# Database Schema

## Main Tables

- `users`
- `permissions`
- `role_permissions`
- `stores`
- `user_stores`
- `processors`
- `customers`
- `suppliers`
- `siruta_counties`
- `siruta_localities`
- `processing_lots`
- `processing_lot_status_events`
- `processing_lot_movements`
- `factory_batches`
- `factory_batch_items`
- `factory_buffer_adjustments`
- `purchase_lots`
- `purchase_wax_exits`
- `documents`
- `document_templates`
- `company_settings`
- `inventory_transactions`
- `audit_log`
- `document_series`

## Notes

- `processing_lots` is a lot container; operational state is calculated from `processing_lot_movements`.
- `processing_lots.processing_price_cents` and `processing_lots.shrinkage_pct` snapshot the effective lot terms.
- `purchase_lots` stores purchased wax entries and external document references.
- `purchase_lots.purchase_price_cents_per_kg` and `purchase_lots.shrinkage_pct` snapshot the effective purchase terms.
- `purchase_wax_exits` stores stock-level exits for purchased wax.
- `inventory_transactions` is the ledger for stock calculations.
- `siruta_counties` and `siruta_localities` are seeded from `release/siruta.csv`.
- `stores` stores the active operational defaults per gestiune: `code`, `fgo_series`, `processor_id`, `processing_shrinkage_pct`, `processing_price_cents`, `purchase_shrinkage_pct`, `purchase_price_cents_per_kg`.
- `document_series` stores independent counters per `store_id + document_type`; default series format is `<DOCUMENT_TYPE>-<STORE_CODE>`.
