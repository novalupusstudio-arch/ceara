param(
    [string] $OutputDir = ".\deploy\output",
    [string] $LocalConfig = ".\deploy\local\config.php"
)

$ErrorActionPreference = "Stop"

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$outputPath = Join-Path $repoRoot $OutputDir
$localConfigPath = Join-Path $repoRoot $LocalConfig

if (-not (Test-Path -LiteralPath $localConfigPath)) {
    throw "Lipseste configurarea locala de productie: $localConfigPath"
}

New-Item -ItemType Directory -Force -Path $outputPath | Out-Null

$stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$stagePath = Join-Path ([System.IO.Path]::GetTempPath()) "ceara-production-$stamp"
$zipPath = Join-Path $outputPath "ceara-production-$stamp.zip"

$excludeDirs = @(
    ".git",
    ".idea",
    ".vscode",
    "deploy\local",
    "deploy\output",
    "logs",
    "tmp",
    "temp",
    ".cache",
    "node_modules",
    "vendor",
    "dist",
    "build",
    "coverage",
    "uploads",
    "storage"
)

$excludeFiles = @(
    ".env",
    "config\local.php",
    "config\secrets.php",
    "config\xampp-target.local.txt"
)

function Test-IsExcluded {
    param([string] $RelativePath, [bool] $IsDirectory)

    $normalized = $RelativePath.TrimStart(".", "\", "/").Replace("/", "\")
    foreach ($dir in $excludeDirs) {
        if ($normalized -eq $dir -or $normalized.StartsWith("$dir\")) {
            return $true
        }
    }
    if (-not $IsDirectory) {
        foreach ($file in $excludeFiles) {
            if ($normalized -eq $file) {
                return $true
            }
        }
    }
    return $false
}

try {
    New-Item -ItemType Directory -Force -Path $stagePath | Out-Null

    Get-ChildItem -LiteralPath $repoRoot -Force -Recurse | ForEach-Object {
        $relative = $_.FullName.Substring($repoRoot.Length).TrimStart("\")
        if (Test-IsExcluded -RelativePath $relative -IsDirectory $_.PSIsContainer) {
            return
        }

        $destination = Join-Path $stagePath $relative
        if ($_.PSIsContainer) {
            New-Item -ItemType Directory -Force -Path $destination | Out-Null
            return
        }

        $destinationDir = Split-Path -Parent $destination
        New-Item -ItemType Directory -Force -Path $destinationDir | Out-Null
        Copy-Item -LiteralPath $_.FullName -Destination $destination -Force
    }

    Copy-Item -LiteralPath $localConfigPath -Destination (Join-Path $stagePath "config\local.php") -Force

    if (Test-Path -LiteralPath $zipPath) {
        Remove-Item -LiteralPath $zipPath -Force
    }
    Compress-Archive -Path (Join-Path $stagePath "*") -DestinationPath $zipPath -Force
    Write-Host "Pachet productie creat: $zipPath"
} finally {
    if (Test-Path -LiteralPath $stagePath) {
        Remove-Item -LiteralPath $stagePath -Recurse -Force
    }
}
