# XAMPP Sync

The project source lives in each developer's local clone.

The local XAMPP target path is machine-specific and should be stored in this
untracked file:

`config/xampp-target.local.txt`

The file should contain only the absolute deploy path, for example:

```text
D:\xampp\htdocs\ceara
```

This file is ignored by git. Each Codex instance / workstation must create its
own local copy before using the sync script. If the file is missing, the script
falls back to the original development path:

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
