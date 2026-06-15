# Lot Statuses

- `In Validare`
- `Acceptat`
- `Predat Fabricii`
- `Respins`
- `Returnat`

Transitions:

- `In Validare` -> `Acceptat`
- `In Validare` -> `Respins`
- `Acceptat` -> `Predat Fabricii` through the batch delivery page
- `Respins` -> `Returnat`
