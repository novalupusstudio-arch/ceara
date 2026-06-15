$ErrorActionPreference = "Stop"

$source = Resolve-Path (Join-Path $PSScriptRoot "..")
$target = "E:\XAMP\htdocs\ceara"

$excludedDirectories = @(
    ".git",
    ".idea",
    ".vscode",
    "node_modules",
    "vendor",
    "dist",
    "build",
    "coverage",
    "tmp",
    "temp",
    ".cache"
)

$excludedFiles = @(
    ".env",
    ".DS_Store",
    "Thumbs.db",
    "Desktop.ini"
)

New-Item -ItemType Directory -Force -Path $target | Out-Null

Get-ChildItem -Path $source -Force | ForEach-Object {
    $item = $_

    if ($item.PSIsContainer -and $excludedDirectories -contains $item.Name) {
        return
    }

    if (-not $item.PSIsContainer -and $excludedFiles -contains $item.Name) {
        return
    }

    $destination = Join-Path $target $item.Name
    Copy-Item -LiteralPath $item.FullName -Destination $destination -Recurse -Force
}

Write-Host "Synced $source to $target"
