# Legt die Verknüpfung „Badgy Kartenstudio“ auf dem Desktop und im Startmenü an.
# Sie öffnet index.html als eigenes Programmfenster in Microsoft Edge (sonst Chrome) mit dem Symbol kartenstudio.ico.
# Gedruckt wird ohne Druckfenster direkt auf den Kartendrucker (Name enthält „Badgy“):
#   --kiosk-printing           druckt sofort, ohne Druckdialog
#   eigenes Browserprofil      damit der Schalter auch wirkt, wenn der Browser schon offen ist
#   Richtlinie PrintPreviewDefaultDestinationSelectionRules   wählt den Badgy als Ziel (nur für Edge bzw. Chrome)
# Ausführen: Rechtsklick auf diese Datei → „Mit PowerShell ausführen“.
param([string]$Drucker = '')
$dir  = Split-Path -Parent $PSScriptRoot
$html = Join-Path $dir 'index.html'
$ico  = Join-Path $dir 'kartenstudio.ico'

# Kartendrucker suchen
if (-not $Drucker) {
  $p = Get-Printer | Where-Object { $_.Name -match 'Badgy|Evolis' } | Select-Object -First 1
  if ($p) { $Drucker = $p.Name }
}
if (-not $Drucker) { Write-Warning 'Kein Drucker mit „Badgy“ oder „Evolis“ im Namen gefunden – es wird der Standarddrucker benutzt.' }

# Browser: bevorzugt Edge, damit die Druckregel das normale Chrome nicht verändert
$edge = @("${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe", "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe") | Where-Object { Test-Path $_ } | Select-Object -First 1
$chrome = @("$env:ProgramFiles\Google\Chrome\Application\chrome.exe", "${env:ProgramFiles(x86)}\Google\Chrome\Application\chrome.exe", "$env:LOCALAPPDATA\Google\Chrome\Application\chrome.exe") | Where-Object { Test-Path $_ } | Select-Object -First 1
if ($edge) { $browser = $edge; $policy = 'HKCU:\Software\Policies\Microsoft\Edge' }
elseif ($chrome) { $browser = $chrome; $policy = 'HKCU:\Software\Policies\Google\Chrome' }
else { Write-Error 'Weder Edge noch Chrome gefunden.'; exit 1 }

# Druckziel festlegen
if ($Drucker) {
  $rule = @{ kind = 'local'; namePattern = '^' + [regex]::Escape($Drucker) + '$' } | ConvertTo-Json -Compress
  New-Item -Path $policy -Force | Out-Null
  Set-ItemProperty -Path $policy -Name 'PrintPreviewDefaultDestinationSelectionRules' -Value $rule
  Write-Host "Druckziel: $Drucker"
}

$profil = Join-Path $env:LOCALAPPDATA 'BadgyKartenstudio\Browser'
New-Item -ItemType Directory -Path $profil -Force | Out-Null
$url  = ([Uri]$html).AbsoluteUri
$wsh  = New-Object -ComObject WScript.Shell
$ziele = @(
  (Join-Path ([Environment]::GetFolderPath('Desktop')) 'Badgy Kartenstudio.lnk'),
  (Join-Path ([Environment]::GetFolderPath('Programs')) 'Badgy Kartenstudio.lnk')
)
foreach ($ziel in $ziele) {
  $lnk = $wsh.CreateShortcut($ziel)
  $lnk.TargetPath       = $browser
  $lnk.Arguments        = "--app=`"$url`" --kiosk-printing --user-data-dir=`"$profil`" --no-first-run --no-default-browser-check"
  $lnk.IconLocation     = "$ico,0"
  $lnk.WorkingDirectory = $dir
  $lnk.Description      = 'Badgy Kartenstudio'
  $lnk.Save()
  Write-Host "Angelegt: $ziel"
}
