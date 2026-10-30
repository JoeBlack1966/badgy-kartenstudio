# Kopiert das Kartenstudio in den Mac-Ordner ~/Badgy Kartenstudio (über die Parallels-Freigabe \\Mac\Home).
# Kopiert nur geänderte Dateien. Ist die Freigabe nicht erreichbar, passiert nichts.
param([string]$Ziel = '\\Mac\Home\Badgy Kartenstudio')
$dir = Split-Path -Parent $PSScriptRoot
if (-not (Test-Path (Split-Path $Ziel))) { exit }
$dateien = @('index.html', 'kartenstudio.ico') + @(Get-ChildItem (Join-Path $dir 'assets') -File | ForEach-Object { "assets\$($_.Name)" })
foreach ($d in $dateien) {
  $von = Join-Path $dir $d; $nach = Join-Path $Ziel $d
  if ((Test-Path $nach) -and (Get-FileHash $von).Hash -eq (Get-FileHash $nach).Hash) { continue }
  New-Item -ItemType Directory -Path (Split-Path $nach) -Force | Out-Null
  Copy-Item $von $nach -Force
}
