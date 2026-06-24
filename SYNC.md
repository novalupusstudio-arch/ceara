# XAMPP Sync

## Source Of Truth

Code is edited only in:

- `D:\Novalupusstudio\ceara`

XAMPP folders are deploy targets only.

## Profiles

### DEV

- target folder: `D:\xampp\htdocs\ceara`
- URL: `http://localhost/ceara/`
- DB: `ceara`

### STAGE

- target folder: `D:\xampp\htdocs\ceara_stage`
- URL: `http://localhost/ceara_stage/`
- DB: `ceara_stage`

## Machine-Specific Target Files

Ignored local files:

- `config/xampp-target.local.txt`
- `config/xampp-stage-target.local.txt`

Each should contain only the absolute target path if you want to override the defaults.

Examples:

```text
D:\xampp\htdocs\ceara
```

```text
D:\xampp\htdocs\ceara_stage
```

## Sync Commands

DEV:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\sync-to-xampp.ps1
```

STAGE:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\sync-to-xampp.ps1 -Profile stage
```

The script copies project files while skipping `.git`, caches, local-only files, and runtime storage folders.

## Runtime DB Config Files

After sync, the runtime DB config is environment-specific:

- DEV:
  - `D:\xampp\htdocs\ceara\config\local.php`
- STAGE:
  - `D:\xampp\htdocs\ceara_stage\config\local.php`

## Stage Bootstrap

Recommended stage bootstrap:

1. sync code with `-Profile stage`
2. create/reset DB `ceara_stage`
3. import `deploy/sql/init-production.sql`
4. log in with:
   - `admin`
   - `CearaAdmin!2026`
5. configure or restore the needed settings/data
