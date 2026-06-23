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
- `document_series`
- `company_settings`
- `inventory_transactions`
- `audit_log`

## Notes

- `processing_lots` is a container; current operational state is calculated from `processing_lot_movements`
- `processing_lots.processing_price_cents` and `processing_lots.shrinkage_pct` snapshot the effective lot terms
- `purchase_lots.purchase_price_cents_per_kg` and `purchase_lots.shrinkage_pct` snapshot the effective purchase terms
- `inventory_transactions` is the stock ledger
- quantities are stored as integer grams

## Stores

`stores` contains active operational defaults per gestiune:

- `code`
- `name`
- `address`
- `fgo_series`
- `processor_id`
- `processing_shrinkage_pct`
- `processing_price_cents`
- `purchase_shrinkage_pct`
- `purchase_price_cents_per_kg`

## Processors

Active processor fields:

- `name`
- `cui`
- `address`
- `processing_price_cents`
- `exchange_shrinkage_pct`

Removed legacy columns should not be reintroduced:

- `contact`
- `purchase_shrinkage_pct`

## Company Settings

Active company settings fields:

- `company_name`
- `vat_number`
- `registry_number`
- `address`
- `phone`
- `email`
- `fgo_url`
- `fgo_token`
- `updated_by`
- `updated_at`

Removed legacy columns:

- `fgo_private_key`
- `purchase_default_shrinkage_pct`
- `purchase_default_price_cents_per_kg`
- `purchase_factory_shrinkage_pct`
- `purchase_factory_price_cents_per_kg`

## Factory Buffer

`factory_buffer_adjustments` stores:

- `adjustment_type`
- `aviz_number`
- `aviz_date`
- `reception_date`
- `qty_g`
- `store_id`
- `notes`
- `created_by`
- `created_at`

## Document Series

`document_series` stores counters per:

- `store_id`
- `document_type`

Default series format:

- `<DOCUMENT_TYPE>-<STORE_CODE>`

Displayed number example:

- `PV-CUST-BC-0001`
