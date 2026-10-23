#Requires -Version 5.1
[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
$name = 'MarsScannerAgent'
$service = Get-Service -Name $name -ErrorAction Stop
if ($service.Status -ne 'Running') { throw "Windows hizmeti çalışmıyor: $($service.Status)" }
Write-Host "SERVICE=RUNNING"

$origin = 'http://127.0.0.1:17864'
$health = Invoke-RestMethod "$origin/health" -TimeoutSec 5
if ($health.status -ne 'ok' -or $health.backend -ne 'WIA') { throw "Health yanıtı beklenen biçimde değil." }
Write-Host "LOCAL_HEALTH=PASS"

$listener = Get-NetTCPConnection -LocalPort 17864 -State Listen -ErrorAction Stop
if ($listener | Where-Object { $_.LocalAddress -ne '127.0.0.1' }) {
    throw "GUVENLIK_HATASI: Agent loopback dışında port dinliyor."
}
Write-Host "LOOPBACK_ONLY=PASS"

try {
    Invoke-WebRequest "$origin/api/scanners" -UseBasicParsing -TimeoutSec 5 | Out-Null
    throw 'GUVENLIK_HATASI: Kimliksiz API çağrısı engellenmedi.'
} catch [System.Net.WebException] {
    $response = $_.Exception.Response
    if (-not $response -or [int]$response.StatusCode -ne 401) { throw }
}
Write-Host "UNAUTHORIZED_ACCESS=BLOCKED"
Write-Host "SCANNER_UI=$origin"
