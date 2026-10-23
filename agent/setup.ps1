#Requires -Version 5.1
#Requires -RunAsAdministrator
# Single-command install on a Windows PC with .NET 10 SDK.
[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'

$build = Join-Path $PSScriptRoot 'build.ps1'
$installer = Join-Path $PSScriptRoot 'install.ps1'
$published = Join-Path $PSScriptRoot 'dist\win-x64'

& $build -Runtime win-x64
if ($LASTEXITCODE -ne 0) { throw 'Windows programı derlenemedi.' }
& $installer -PublishedDir $published
