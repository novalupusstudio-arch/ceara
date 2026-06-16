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
    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem

    if (Test-Path -LiteralPath $zipPath) {
        Remove-Item -LiteralPath $zipPath -Force
    }

    $zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)
    try {
        Get-ChildItem -LiteralPath $repoRoot -Force -Recurse | ForEach-Object {
            if ($_.PSIsContainer) {
                return
            }

            $relative = $_.FullName.Substring($repoRoot.Length).TrimStart("\")
            if (Test-IsExcluded -RelativePath $relative -IsDirectory $false) {
                return
            }

            $entryName = $relative.Replace("\", "/")
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $entryName) | Out-Null
        }

        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $localConfigPath, "config/local.php") | Out-Null
    } finally {
        $zip.Dispose()
    }

    Write-Host "Pachet productie creat: $zipPath"
} catch {
    if (Test-Path -LiteralPath $zipPath) {
        Remove-Item -LiteralPath $zipPath -Force
    }
    throw
}
