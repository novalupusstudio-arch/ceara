$ErrorActionPreference = "Stop"

$source = Resolve-Path (Join-Path $PSScriptRoot "..")
$targetConfig = Join-Path $source "config\xampp-target.local.txt"
$target = "E:\XAMP\htdocs\ceara"

if (Test-Path -LiteralPath $targetConfig) {
    $configuredTarget = (Get-Content -LiteralPath $targetConfig -Raw).Trim()

    if ($configuredTarget) {
        $target = $configuredTarget
    }
}

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
    "xampp-target.local.txt",
    ".DS_Store",
    "Thumbs.db",
    "Desktop.ini"
)

New-Item -ItemType Directory -Force -Path $target | Out-Null

Get-ChildItem -Path $source -Force -Recurse -File | ForEach-Object {
    $file = $_
    $relativePath = $file.FullName.Substring($source.Path.Length).TrimStart("\", "/")
    $parts = $relativePath -split "[\\/]"

    if ($parts | Where-Object { $excludedDirectories -contains $_ }) {
        return
    }

    if ($excludedFiles -contains $file.Name) {
        return
    }

    $destination = Join-Path $target $relativePath
    $destinationDirectory = Split-Path -Parent $destination
    New-Item -ItemType Directory -Force -Path $destinationDirectory | Out-Null
    Copy-Item -LiteralPath $file.FullName -Destination $destination -Force
}

Write-Host "Synced $source to $target"
