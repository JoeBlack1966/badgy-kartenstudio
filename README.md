# Badgy Kartenstudio

Kleines Designstudio für Mitgliedskarten, VIP-Karten und Gutscheine im Kartenformat CR80 (85,6 × 54 mm), gedruckt auf dem Evolis Badgy200.

## Starten
`index.html` doppelklicken → öffnet in Chrome. Keine Installation, kein Server.
Internet wird nur für die Schriften (Google Fonts) gebraucht; ohne Internet greifen Ersatzschriften.

## Als Programm in der Taskleiste
1. Rechtsklick auf `tools/verknuepfung-erstellen.ps1` → **Mit PowerShell ausführen**.
   Das legt „Badgy Kartenstudio“ auf dem Desktop und im Startmenü an (Symbol `kartenstudio.ico`,
   öffnet als eigenes Fenster ohne Adressleiste).
2. Startmenü öffnen, „Badgy Kartenstudio“ suchen, Rechtsklick → **An Taskleiste anheften**.

Das Symbol neu erzeugen: `python tools/make_icon.py`.

## Funktionen
- Vorder- und Rückseite, Vorlagen: Mitgliedskarte, VIP-Karte, Gutschein, VIP Neon, VIP Clean White, Leer
- QR-Code auf der Rückseite mit allen Kartendaten (Kartenart, Name, Nummer, Wert, gültig bis)
- Fortlaufende Nummern (z. B. GS-0001 … GS-0010) mit Vorsilbe, Stellenzahl, Startnummer und „Nächste Serie“
- Text, Datenfelder `{{Spalte}}`, Bild/Logo, Fotofeld, QR-Code, Rechteck, Kreis, Linie
- Gold-/Silber-/Roségold-Effekt, Leuchten, Verläufe, Hintergrundbild
- Liste (Mitglieder oder Gutscheine) per Copy & Paste aus Excel → Serienkarten, eine Zeile pro Karte
- Druck direkt aus Chrome (eine Karte pro Seite, randlos), PNG-Export in 300 dpi
- Speichern/Öffnen als `.kartenstudio.json`, Autosave im Browser

## Drucken auf dem Badgy200
Im Chrome-Druckdialog: Drucker **Evolis Badgy200**, Ränder **Keine**, Skalierung **Standard**.
Der Badgy200 druckt einseitig: zuerst alle Vorderseiten, dann den Stapel wenden und die Rückseiten drucken
(bei Bedarf „Reihenfolge umkehren“).

## Entwicklung
Quelle: `src/app.html`. `python3 build.py` bettet die QR-Bibliothek (qrcode-generator, MIT) ein und schreibt `index.html`.
