#Requires -Version 5.1
[CmdletBinding()]
param(
    [ValidateSet("win-x64","win-arm64")]
    [string]$Runtime = "win-x64"
)
$ErrorActionPreference = "Stop"
$project = Join-Path $PSScriptRoot "MarsScannerAgent\MarsScannerAgent.csproj"
$target = Join-Path $PSScriptRoot "dist\$Runtime"

if (-not (Get-Command dotnet -ErrorAction SilentlyContinue)) {
    throw ".NET 10 SDK yüklü değil. https://dotnet.microsoft.com/download/dotnet/10.0 adresinden SDK kurun."
}
if (-not (Test-Path -LiteralPath $project)) {
    throw "Proje dosyası bulunamadı: $project"
}

dotnet publish $project --configuration Release --runtime $Runtime --self-contained true -p:PublishSingleFile=true -p:PublishTrimmed=false -p:DebugType=none --output $target --nologo
if ($LASTEXITCODE -ne 0) {
    throw "Mars Scanner Agent derlenemedi."
}

$exe = Join-Path $target "MarsScannerAgent.exe"
if (-not (Test-Path -LiteralPath $exe)) {
    throw "Derleme tamamlandı ancak çalıştırılabilir dosya bulunamadı."
}
Write-Host "PROGRAM_HAZIR=$exe"
Write-Host ('KURULUM= .\install.ps1 -PublishedDir "' + $target + '"')
