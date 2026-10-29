# Legt die Verknüpfung „Badgy Kartenstudio“ auf dem Desktop und im Startmenü an.
# Sie öffnet index.html als eigenes Programmfenster (Chrome, sonst Edge) mit dem Symbol kartenstudio.ico.
# Ausführen: Rechtsklick auf diese Datei → „Mit PowerShell ausführen“.
$dir  = Split-Path -Parent $PSScriptRoot
$html = Join-Path $dir 'index.html'
$ico  = Join-Path $dir 'kartenstudio.ico'
$browser = @(
  "$env:ProgramFiles\Google\Chrome\Application\chrome.exe",
  "${env:ProgramFiles(x86)}\Google\Chrome\Application\chrome.exe",
  "$env:LOCALAPPDATA\Google\Chrome\Application\chrome.exe",
  "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe",
  "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe"
) | Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $browser) { Write-Error 'Weder Chrome noch Edge gefunden.'; exit 1 }
$url  = ([Uri]$html).AbsoluteUri
$wsh  = New-Object -ComObject WScript.Shell
$ziele = @(
  (Join-Path ([Environment]::GetFolderPath('Desktop')) 'Badgy Kartenstudio.lnk'),
  (Join-Path ([Environment]::GetFolderPath('Programs')) 'Badgy Kartenstudio.lnk')
)
foreach ($ziel in $ziele) {
  $lnk = $wsh.CreateShortcut($ziel)
  $lnk.TargetPath       = $browser
  $lnk.Arguments        = "--app=`"$url`""
  $lnk.IconLocation     = "$ico,0"
  $lnk.WorkingDirectory = $dir
  $lnk.Description      = 'Badgy Kartenstudio'
  $lnk.Save()
  Write-Host "Angelegt: $ziel"
}
