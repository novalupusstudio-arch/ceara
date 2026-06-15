# Ceara

Web application project workspace.

## Working Locations

- Source workspace: `E:\NovaLupus\ceara`
- Local XAMPP test path: `E:\XAMP\htdocs\ceara`
- GitHub remote: `https://github.com/novalupusstudio-arch/ceara.git`
- Main branch: `main`

## Workflow

1. Keep source changes in this repository.
2. Commit small, focused checkpoints.
3. Use `scripts/sync-to-xampp.ps1` to copy the working tree into the XAMPP test folder.
4. Test through the local XAMPP URL once the app structure exists.
5. Record requirements and decisions in `docs/spec.md`.

## MVP Stack

- PHP + MySQL
- XAMPP local runtime
- Server-rendered pages with small JavaScript helpers

## Local Setup

1. Start Apache and MySQL in XAMPP.
2. Sync the repository into XAMPP:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\sync-to-xampp.ps1
```

3. Open the local app:

`http://localhost/ceara/`

The app creates the `ceara` database and seeds baseline data automatically when MySQL is available.

Default credentials:

- user: `admin`
- password: `admin`

## MVP Scope

- login/logout
- dashboard KPI
- processing lots
- purchase lots
- generated mock documents
- reports
- settings for store, processor, and document series
- audit log

Quantities are stored as integer grams and displayed as kilograms with three decimals.

## Project Status

MVP generated from the initial wax application specs.
