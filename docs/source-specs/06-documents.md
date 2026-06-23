# Documents

## Generated / Template Documents

Documents are stored as DB rows in `documents`. Template-backed PDFs are rendered with Dompdf and stored under ignored `storage/documents/<store_code>/`.

Current key templates/outputs:

- `PV-CUST`: table-style custody intake PDF with GDPR text; source template file `lib/templates/pv-cust.html`.
- `PV-FAG`: client foundation delivery PV template/record.
- `PV-RET`: wax return PV template/record.
- `AVIZ`, `NIR`: used in factory/buffer flows where implemented.

## External Integrations

- `FACT`: FGO invoice generation for processing service invoices.
- `BON`: FiscalWire `.inp` receipt download for cash/card.

## Series And Numbering

Generated internal document counters are stored in `document_series` per `store_id + document_type`.

- default series format: `<DOCUMENT_TYPE>-<STORE_CODE>`
- example: `PV-CUST-BC-0001`
- numbering starts from `0001` for every store/document type pair
- store code should be short uppercase
- FGO invoice API uses the store-level invoice series from `stores.fgo_series`

## Purchase Documents

Purchase flow currently does not generate internal PDFs. It records external document references:

- PF: borderou-like reference and position
- Producator agricol: carnet-like reference and position
- PJ/PFA: invoice series/number/date

Purchase exits also record external document type/series/number/date.
