# Documents

## Internal Generated Types

- `PV-CUST`
- `PV-FAG`
- `PV-RET`
- `AVIZ`
- `NIR`
- `BON`
- `FACT`
- `BORD`

## Numbering

Internal document counters:

- stored in `document_series`
- scoped by `store_id + document_type`

FGO invoice series:

- stored in `stores.fgo_series`

## Strict Rules

- missing internal document series must raise error
- missing store FGO series must raise error
- FGO response must contain final series and number

## Storage

PDFs:

- `storage/documents/<store_code>/`

FiscalWire:

- `storage/fiscalwire-out/`

## Templates

Editable HTML templates live in `document_templates`.

Buffer NIR-related variables use the operational aviz date from `factory_buffer_adjustments.aviz_date`.
