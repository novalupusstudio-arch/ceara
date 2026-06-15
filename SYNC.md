# XAMPP Sync

The project source lives in:

`E:\NovaLupus\ceara`

The local test copy will live in:

`E:\XAMP\htdocs\ceara`

Use the sync script from the project root:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\sync-to-xampp.ps1
```

The script copies project files while skipping `.git`, dependency folders, build caches, and local-only files.

Current local setup:

- URL: `http://localhost/ceara/`
- Database: MySQL via XAMPP
- Default credentials: `admin` / `admin`

Keep this document aligned with the working local deployment path.
