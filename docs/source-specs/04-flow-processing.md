# Processing Flow

## Pages

- `processing`
- `lots`
- `lot_detail`
- `factory_delivery`
- `factory_buffer`
- `processing_register`

## Core Stock

- custody wax: `wax_custody`
- operational foundations: `foundation_operational`

## Create Lot

The assigned store is mandatory.

The assigned store processor is mandatory.

The form defaults:

- processor from store
- processing price from store
- shrinkage from store

The user may edit price and shrinkage on the lot form, and the backend snapshots those values on the lot.

No fallback to processor defaults should happen if store or form values are missing.

## Exchange / Return

- exchange cannot exceed exchangeable wax
- exchange cannot make `foundation_operational` negative
- return cannot exceed wax still in custody
- exchange documents and financial values use lot snapshot values

## Factory Delivery

- uses processing custody stock only
- uses selected processor or assigned store processor
- must not fall back to the first processor in DB
- sent wax decreases `wax_custody`
- expected foundation increases `foundation_operational`

## Factory Buffer

- plus increases `foundation_operational`
- minus decreases `foundation_operational`
- minus cannot go negative
- adjustment stores aviz number, aviz date, reception date
- each adjustment can generate linked `NIR`
