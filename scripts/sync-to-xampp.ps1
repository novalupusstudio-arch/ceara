param(
    [ValidateSet("dev", "stage")]
    [string] $Profile = "dev"
)

$ErrorActionPreference = "Stop"

$source = Resolve-Path (Join-Path $PSScriptRoot "..")
$targetConfigMap = @{
    dev = "config\xampp-target.local.txt"
    stage = "config\xampp-stage-target.local.txt"
}
$defaultTargetMap = @{
    dev = "D:\xampp\htdocs\ceara"
    stage = "D:\xampp\htdocs\ceara_stage"
}
$targetConfig = Join-Path $source $targetConfigMap[$Profile]
$target = $defaultTargetMap[$Profile]

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
    "dist",
    "build",
    "coverage",
    "storage",
    "tmp",
    "temp",
    ".cache"
)

$excludedFiles = @(
    ".gitattributes",
    ".gitignore",
    ".env",
    "xampp-target.local.txt",
    "xampp-stage-target.local.txt",
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

Write-Host "Synced profile '$Profile' from $source to $target"
