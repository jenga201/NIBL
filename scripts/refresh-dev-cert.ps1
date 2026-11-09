# refresh-dev-cert.ps1
#
# Regenerate the local nibl.co.uk dev TLS cert with mkcert, install it into
# swag's keys, and reload swag. Run from Windows PowerShell (mkcert's CA lives
# in the *Windows* trust store, so this must run on Windows — not WSL).
#
# Usage:
#   powershell -ExecutionPolicy Bypass -File '\\wsl.localhost\Debian\opt\code\NIBL\scripts\refresh-dev-cert.ps1'

$ErrorActionPreference = 'Stop'

# Generate into a local Windows dir (mkcert can be finicky writing to UNC).
$Work    = Join-Path $env:TEMP 'nibl-dev-cert'
$Cert    = Join-Path $Work 'dev.nibl.co.uk.pem'
$Key     = Join-Path $Work 'dev.nibl.co.uk-key.pem'
$SwagKey = '\\wsl.localhost\Debian\opt\code\NIBL\data\swag\keys'

New-Item -ItemType Directory -Force -Path $Work | Out-Null

# Regenerate with the existing mkcert CA. The wildcard covers any single-label
# subdomain (api.nibl.co.uk, www.nibl.co.uk, ...) — not a.b.nibl.co.uk.
mkcert -cert-file $Cert -key-file $Key dev.nibl.co.uk '*.nibl.co.uk'

# swag's ssl.conf hardcodes these two filenames, so overwrite them in place.
Copy-Item -Force $Cert (Join-Path $SwagKey 'cert.crt')
Copy-Item -Force $Key  (Join-Path $SwagKey 'cert.key')

# Reload nginx inside swag so the new cert takes effect without a full restart.
wsl -d Debian docker exec swag nginx -s reload

Write-Host 'Done: dev cert regenerated, installed into swag, and reloaded.'