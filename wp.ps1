<#
Wrapper om WP-CLI van Local (de WordPress-app) te draaien zonder telkens
handmatig de Local-omgevingsvariabelen (PHP, MySQL, wp-cli) te moeten
instellen. Leest die variabelen uit app\.envrc, het bestand dat Local
zelf aanmaakt en actueel houdt zolang de site in Local draait.

Gebruik:
    .\wp.ps1 plugin list
    .\wp.ps1 user create dealer1 dealer1@voorbeeld.nl --role=dealer
#>

param(
	[Parameter(ValueFromRemainingArguments = $true)]
	[string[]]$WpArgs
)

$root  = $PSScriptRoot
$envrc = Join-Path $root "app\.envrc"

if (-not (Test-Path $envrc)) {
	Write-Error "app\.envrc niet gevonden. Start de site 'Homburg Dealerportaal' eerst in Local, zodat dit bestand wordt aangemaakt."
	exit 1
}

Get-Content $envrc | ForEach-Object {
	if ($_ -match '^export\s+([A-Za-z_][A-Za-z0-9_]*)="?([^"]*)"?$') {
		$name  = $Matches[1]
		$value = $Matches[2]
		if ($name -eq 'PATH') {
			$dir = ($value -split ':\$PATH')[0]
			$env:PATH = "$dir;$env:PATH"
		} else {
			Set-Item -Path "env:$name" -Value $value
		}
	}
}

Set-Location (Join-Path $root "app\public")

& wp @WpArgs 2>&1 | Where-Object { $_ -notmatch 'imagick' }
exit $LASTEXITCODE
