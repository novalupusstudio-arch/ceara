# Flow Purchase Wax

## Supplier Types

- PF
- Producator agricol
- PJ/PFA

## Entry Flow

1. Select `Achizitie ceara` from dashboard.
2. Open `Achizitie ceara` page.
3. Choose supplier type.
4. Enter supplier identity and SIRUTA location.
5. Enter external document reference:
   - PF: borderou-like series/number/position
   - Producator agricol: carnet-like series/number/position
   - PJ/PFA: invoice series/number/date
6. Enter purchase date, gross kg, shrinkage and price with VAT. Shrinkage and price are defaulted from the assigned store/gestiune and can be edited for the purchase.
7. Save purchase lot.
8. App adds positive `wax_purchased` stock only.

No internal purchase PDF is generated. External paper/accounting documents are referenced only.

Purchase lot stores its effective commercial values (`shrinkage_pct`, `purchase_price_cents_per_kg`) so later reports keep the original transaction values even if store defaults change.

## Exit Flow

Page `Iesire ceara` records purchased wax leaving stock by partner/factory and external document. It writes negative `wax_purchased` and cannot exceed stock.

## Register

Page `Registru achizitie` shows current purchased wax stock, opening/closing balances, movements and lot list.
