# Inventory Model

Quantities are stored in grams and displayed in kilograms with three decimals.

## Processing / Custody

- `wax_custody`: customer wax held in custody
- `foundation_operational`: operational foundations used for client exchanges and processing buffer

## Purchase / Company-Owned

- `wax_purchased`: company-owned purchased wax
- `foundation_merchandise`: legacy/company-owned foundation stock category, not part of current purchase entry MVP

## Rules

- Custody stock and purchased stock must never be mixed.
- Processing factory delivery uses only custody wax.
- Purchase exits use only `wax_purchased`.
- Stock source of truth is `inventory_transactions`.
