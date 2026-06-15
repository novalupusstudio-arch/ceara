# Database Schema

## Main Tables

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

## Notes

- `processing_lots` tracks the current lot state and quantities.
- `processing_lot_status_events` keeps the lot history.
- `factory_batches` and `factory_batch_items` store batch delivery history.
