# Startet das Badgy Kartenstudio so, dass immer auf den Kartendrucker gedruckt wird.
# Edge druckt mit --kiosk-printing ohne Druckfenster auf das zuletzt benutzte Ziel aus dem Studio-Profil
# (weder der Windows-Standarddrucker noch die Edge-Richtlinie für das Druckziel ändern das).
# Deshalb wird vor jedem Start im Studio-Profil der Badgy als letztes Ziel eingetragen:
# randlos, ohne Kopf-/Fußzeile, mit Hintergrundgrafiken.
param([string]$Drucker = '')
$dir    = Split-Path -Parent $PSScriptRoot
$url    = ([Uri](Join-Path $dir 'index.html')).AbsoluteUri
$profil = Join-Path $env:LOCALAPPDATA 'BadgyKartenstudio\Browser'
$edge   = @("${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe", "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe") | Where-Object { Test-Path $_ } | Select-Object -First 1
$args_  = @("--app=`"$url`"", '--kiosk-printing', "--user-data-dir=`"$profil`"", '--no-first-run', '--no-default-browser-check')

if (-not $Drucker) { $Drucker = Get-Printer | Where-Object { $_.Name -match 'Badgy|Evolis' } | Select-Object -First 1 -ExpandProperty Name }

# Neueste Fassung auch auf den Mac kopieren (~/Badgy Kartenstudio)
& (Join-Path $PSScriptRoot 'mac-kopie.ps1')

# Studio-Fenster mit dem Studio-Profil, die ohne --kiosk-printing laufen, würden mit Druckfenster drucken: beenden
$studio = @(Get-CimInstance Win32_Process -Filter "Name='msedge.exe'" | Where-Object { $_.CommandLine -match [regex]::Escape($profil) })
$ohneKiosk = $studio | Where-Object { $_.CommandLine -notmatch '--type=' -and $_.CommandLine -notmatch '--kiosk-printing' }
if ($ohneKiosk) {
  $ohneKiosk | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
  Start-Sleep -Seconds 2
}

# Nur ändern, solange das Studio nicht läuft – Edge überschreibt die Datei sonst beim Beenden
$laeuft = Get-CimInstance Win32_Process -Filter "Name='msedge.exe'" | Where-Object { $_.CommandLine -match [regex]::Escape($profil) }
$prefs  = Join-Path $profil 'Default\Preferences'
if ($Drucker -and -not $laeuft) {
  $state = [ordered]@{
    version = 2
    recentDestinations = @([ordered]@{ id = $Drucker; origin = 'local'; displayName = $Drucker; extensionId = ''; extensionName = '' })
    marginsType = 1
    isHeaderFooterEnabled = $false
    isCssBackgroundEnabled = $true
    isColorEnabled = $true
    scaling = '100'
  } | ConvertTo-Json -Compress -Depth 4
  if (Test-Path $prefs) {
    $p = Get-Content $prefs -Raw -Encoding UTF8 | ConvertFrom-Json
  } else {
    New-Item -ItemType Directory -Path (Split-Path $prefs) -Force | Out-Null
    $p = [pscustomobject]@{}
  }
  if (-not $p.printing) { $p | Add-Member -NotePropertyName printing -NotePropertyValue ([pscustomobject]@{}) }
  $p.printing | Add-Member -NotePropertyName print_preview_sticky_settings -NotePropertyValue ([pscustomobject]@{ appState = $state }) -Force
  [IO.File]::WriteAllText($prefs, ($p | ConvertTo-Json -Compress -Depth 100), (New-Object Text.UTF8Encoding $false))
}

Start-Process -FilePath $edge -ArgumentList $args_
