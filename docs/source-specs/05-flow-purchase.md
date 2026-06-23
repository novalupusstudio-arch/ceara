# Purchase Flow

## Pages

- `purchases`
- `purchase_exit`
- `purchase_register`

## Core Stock

- purchased wax: `wax_purchased`

This stock must remain separate from:

- `wax_custody`

## Create Purchase Lot

Supplier types:

- `PF`
- `Producator agricol`
- `PJ/PFA`

Store defaults:

- `purchase_shrinkage_pct`
- `purchase_price_cents_per_kg`

These defaults are editable per purchase, then snapshotted on the lot.

The app stores external document references only. It does not generate internal PDF documents for purchase entry.

## Purchase Exit

- creates stock-level negative movement on `wax_purchased`
- cannot exceed current purchased stock
- stores external document references only

## Current Scope

Purchase exits are not allocated to specific lots yet.
