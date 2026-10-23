#Requires -Version 5.1
#Requires -RunAsAdministrator
[CmdletBinding()]
param(
    [string]$PublishedDir = $(if (Test-Path (Join-Path $PSScriptRoot 'MarsScannerAgent.exe')) { $PSScriptRoot } else { Join-Path $PSScriptRoot 'dist\win-x64' })
)
$ErrorActionPreference = 'Stop'
$serviceName = 'MarsScannerAgent'
$target = Join-Path $env:ProgramFiles 'MarsProject\ScannerAgent'
$dataDir = Join-Path $env:ProgramData 'MarsProject\ScannerAgent'
$configFile = Join-Path $dataDir 'agent.json'
$exe = Join-Path $target 'MarsScannerAgent.exe'
$origin = 'http://127.0.0.1:17864'

if (-not (Test-Path (Join-Path $PublishedDir 'MarsScannerAgent.exe'))) {
    throw "Hazır EXE bulunamadı. agent/build.ps1 çalıştırın veya GitHub Actions paketini açın: $PublishedDir"
}

New-Item -ItemType Directory -Force -Path $target, $dataDir | Out-Null
$existing = Get-Service -Name $serviceName -ErrorAction SilentlyContinue
if ($existing -and $existing.Status -ne 'Stopped') {
    Stop-Service -Name $serviceName -Force -ErrorAction Stop
    $existing.WaitForStatus('Stopped', [TimeSpan]::FromSeconds(30))
}
Copy-Item -LiteralPath (Join-Path $PublishedDir 'MarsScannerAgent.exe') -Destination $exe -Force

if (-not (Test-Path -LiteralPath $configFile)) {
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $secret = New-Object byte[] 32
        $rng.GetBytes($secret)
        $token = ([BitConverter]::ToString($secret)).Replace('-', '').ToLowerInvariant()
    } finally { $rng.Dispose() }
    $settings = @{
        Port = 17864
        Token = $token
        AllowedOrigins = @($origin)
    } | ConvertTo-Json -Depth 3
    [System.IO.File]::WriteAllText($configFile, $settings, (New-Object System.Text.UTF8Encoding($false)))
    & icacls.exe $configFile /inheritance:r /grant:r '*S-1-5-18:(F)' '*S-1-5-32-544:(F)' '*S-1-5-19:(R)' | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Ayar dosyası erişim izinleri güvenceye alınamadı.' }
}

# Ensure LocalService can read the retained configuration on upgrades too.
& icacls.exe $configFile /grant '*S-1-5-19:(R)' | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'LocalService erişim izni ayarlanamadı.' }

if (-not $existing) {
    $binaryPath = '"' + $exe + '" --config "' + $configFile + '"'
    New-Service -Name $serviceName -BinaryPathName $binaryPath -DisplayName 'Mars Scanner Agent' -Description 'MarsProject için yerel Windows WIA tarama hizmeti' -StartupType Automatic | Out-Null
}

# Service account defaults to LocalService, never LocalSystem.
# Built-in LocalService requires no saved account password.
& sc.exe config $serviceName obj= 'NT AUTHORITY\LocalService' | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'LocalService hizmet hesabı ayarlanamadı.' }
& sc.exe failure $serviceName reset= 86400 actions= restart/60000/restart/60000/restart/60000 | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'Servis kurtarma ayarları uygulanamadı.' }
Start-Service -Name $serviceName -ErrorAction Stop

$healthy = $false
for ($i = 0; $i -lt 20; $i++) {
    Start-Sleep -Milliseconds 500
    try {
        $status = Invoke-RestMethod -Uri "$origin/health" -TimeoutSec 2
        if ($status.status -eq 'ok') { $healthy = $true; break }
    } catch { }
}
if (-not $healthy) {
    throw 'Hizmet başlatıldı ancak yerel sağlık kontrolü başarısız. Windows Olay Görüntüleyicisi uygulama kayıtlarını kontrol edin.'
}

$config = Get-Content -LiteralPath $configFile -Raw | ConvertFrom-Json
Write-Host ''
Write-Host 'MARS_SCANNER_AGENT=RUNNING'
Write-Host "TARAMA_EKRANI=$origin"
Write-Host "ERISIM_ANAHTARI=$($config.Token)"
Write-Host 'Anahtarı yalnız güvendiğiniz bilgisayar kullanıcısıyla paylaşın.'
