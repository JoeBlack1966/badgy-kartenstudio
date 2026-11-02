# Legt die Verknüpfung „Badgy Kartenstudio“ auf dem Desktop und im Startmenü an (Symbol kartenstudio.ico).
# Sie startet tools\kartenstudio-starten.ps1: Edge als eigenes Fenster, Druck ohne Druckfenster direkt auf den Badgy.
# Ausführen: Rechtsklick auf diese Datei → „Mit PowerShell ausführen“.
$dir    = Split-Path -Parent $PSScriptRoot
$ico    = Join-Path $dir 'kartenstudio.ico'
$start  = Join-Path $PSScriptRoot 'kartenstudio-starten.ps1'
$wsh    = New-Object -ComObject WScript.Shell
$ziele  = @(
  (Join-Path ([Environment]::GetFolderPath('Desktop')) 'Badgy Kartenstudio.lnk'),
  (Join-Path ([Environment]::GetFolderPath('Programs')) 'Badgy Kartenstudio.lnk')
)
foreach ($ziel in $ziele) {
  $lnk = $wsh.CreateShortcut($ziel)
  $lnk.TargetPath       = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
  $lnk.Arguments        = "-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$start`""
  $lnk.IconLocation     = "$ico,0"
  $lnk.WorkingDirectory = $dir
  $lnk.Description      = 'Badgy Kartenstudio'
  $lnk.Save()
  Write-Host "Angelegt: $ziel"
}
