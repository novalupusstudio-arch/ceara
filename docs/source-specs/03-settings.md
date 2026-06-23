# Settings

## Company Data

- name
- CUI
- trade registry number
- address
- FGO API/private key

## Stores

- short uppercase code, e.g. `BC`, `CJ`
- name
- address
- FGO invoice series
- default processor
- processing shrinkage %
- processing price with VAT lei/kg
- purchase shrinkage %
- purchase price with VAT lei/kg

## Processors

- name
- CUI
- address
- contact
- identity/master data

Operational commercial defaults are stored on the store/gestiune. Processor fields are not the final source for lot calculations once store values exist.

## Document Series

- per store/gestiune and document type
- default series format `<DOCUMENT_TYPE>-<STORE_CODE>`
- independent increasing counter per store + document type
- displayed number is padded to four digits, e.g. `PV-CUST-BC-0001`
