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

## Notes

- `processing_lots` is a lot container; operational state is calculated from `processing_lot_movements`.
- `purchase_lots` stores purchased wax entries and external document references.
- `purchase_wax_exits` stores stock-level exits for purchased wax.
- `inventory_transactions` is the ledger for stock calculations.
- `siruta_counties` and `siruta_localities` are seeded from `release/siruta.csv`.
