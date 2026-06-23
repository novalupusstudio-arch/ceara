# Settings

## Access Model

Settings are admin-only.

Operators should not configure the app. If configuration is missing, they should only see the operational error and ask admin to fix it.

## Company Data

- company name
- CUI
- trade registry number
- address
- phone
- email
- FGO URL
- FGO token

These values are stored in `company_settings`.

## Stores / Gestiuni

Each store contains the operational defaults for the business relation with the client:

- short uppercase code, e.g. `BC`, `CJ`
- name
- address
- FGO invoice series
- assigned processor
- processing shrinkage %
- processing price lei/kg
- purchase shrinkage %
- purchase price lei/kg

The store is the source of defaults for:

- new processing lots
- new purchase lots

If required store configuration is missing, the app should fail clearly instead of silently falling back.

## Processors

Each processor contains the business relation with the factory/processor:

- name
- CUI
- address
- processing price lei/kg in relation with processor
- processor shrinkage %

Processor fields are not the source of client-facing lot values once store values exist. Lot snapshots are the final source after creation.

## Document Series

Internal generated documents are numbered per store + document type.

- format example: `PV-CUST-BC`
- counter example: `0001`
- stored in `document_series`

Current internal managed types:

- `PV-CUST`
- `PV-FAG`
- `PV-RET`
- `AVIZ`
- `NIR`
- `BON`
- `BORD`

`FACT` is special:

- FGO series is configured per store in `stores.fgo_series`
- it maps to an existing FGO-side series

## Document Templates

Editable HTML templates are managed in settings and used to generate PDFs.

Generated PDFs are saved under:

- `storage/documents/<store_code>/`

## Recommended First-Run Order

1. `Date societate`
2. `Procesatori`
3. `Gestiuni`
4. `Serii documente`
5. `Roluri si drepturi`
6. `Creare useri`
7. `Template documente`
8. `Schimba parola`

## Users And Permissions

Current settings tabs should support:

- own password change
- role permission matrix
- user creation
- user editing
- gestiuni management

Business rule:

- one user works on one gestiune
- one gestiune may have many users

Permissions that are especially admin-only in current flow:

- user creation/edit/reset
- store management
- processor management
- role/permission management

## No-Fallback Direction

Critical configuration should not silently invent values.

This applies to:

- DB connection
- assigned store processor
- processing form defaults
- internal document series
- FGO URL/token/CUI
- store FGO series
