#Requires -Version 5.1
#Requires -RunAsAdministrator
[CmdletBinding()]
param([switch]$DeleteSettings)
$ErrorActionPreference = 'Stop'
$name = 'MarsScannerAgent'
$program = Join-Path $env:ProgramFiles 'MarsProject\ScannerAgent'
$data = Join-Path $env:ProgramData 'MarsProject\ScannerAgent'

$service = Get-Service -Name $name -ErrorAction SilentlyContinue
if ($service) {
    if ($service.Status -ne 'Stopped') {
        Stop-Service -Name $name -Force
        $service.WaitForStatus('Stopped', [TimeSpan]::FromSeconds(30))
    }
    & sc.exe delete $name | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Windows hizmeti kaldırılamadı.' }
}

if (Test-Path -LiteralPath $program) {
    Remove-Item -LiteralPath $program -Recurse -Force
}
if ($DeleteSettings -and (Test-Path -LiteralPath $data)) {
    Remove-Item -LiteralPath $data -Recurse -Force
}
Write-Host 'MARS_SCANNER_AGENT=UNINSTALLED'
Write-Host 'Ayarlar ve erişim anahtarı varsayılan olarak korunur. Silmek için -DeleteSettings kullanın.'
