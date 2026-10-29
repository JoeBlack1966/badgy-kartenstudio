# Badgy Kartenstudio

Kleines Designstudio für Mitgliedskarten, VIP-Karten und Gutscheine im Kartenformat CR80 (85,6 × 54 mm), gedruckt auf dem Evolis Badgy200.

## Starten
`index.html` doppelklicken → öffnet in Chrome. Keine Installation, kein Server.
Internet wird nur für die Schriften (Google Fonts) gebraucht; ohne Internet greifen Ersatzschriften.

## Als Programm in der Taskleiste
1. Rechtsklick auf `tools/verknuepfung-erstellen.ps1` → **Mit PowerShell ausführen**.
   Das legt „Badgy Kartenstudio“ auf dem Desktop und im Startmenü an (Symbol `kartenstudio.ico`,
   öffnet als eigenes Edge-Fenster ohne Adressleiste). Über diese Verknüpfung wird **ohne Druckfenster direkt auf den
   Badgy** gedruckt (`--kiosk-printing`, eigenes Browserprofil, Edge-Richtlinie für das Druckziel).
   Anderer Druckername: `tools\verknuepfung-erstellen.ps1 -Drucker "Name"`.
2. Startmenü öffnen, „Badgy Kartenstudio“ suchen, Rechtsklick → **An Taskleiste anheften**.

Das Symbol neu erzeugen: `python tools/make_icon.py`.

## Funktionen
- Vorder- und Rückseite, Vorlagen (wechseln sofort beim Auswählen, Strg+Z holt das vorige Design zurück): Mitgliedskarte, VIP-Karte, Gutschein, VIP Neon, VIP Clean White, Leer
- Original-Logo Cooltourszene in Gold auf allen drei Karten, auch als Werkzeug „Logo (Gold)“
- Mitgliedskarte mit Kategorie (Gönner, Aktiv, Passiv), „gültig ab“ und automatisch berechnetem „gültig bis“ (ein Jahr)
- QR-Code auf der Rückseite mit allen Kartendaten (Kartenart, Name, Nummer, Telefon, E-Mail, Wert, gültig bis; leere Angaben werden weggelassen)
- Fortlaufende Nummern (z. B. GS-0001 … GS-0010) mit Vorsilbe, Stellenzahl, Startnummer und „Nächste Serie“
- Doppelklick auf einen Text ändert ihn direkt auf der Karte; bei Datenfeldern (Name, Telefon, Kategorie …) nur für diese Karte, der QR-Code passt sich an
- Text, Datenfelder `{{Spalte}}`, Bild/Logo, Fotofeld, QR-Code, Rechteck, Kreis, Linie
- Gold-/Silber-/Roségold-Effekt, Leuchten, Verläufe, Hintergrundbild
- Liste (Mitglieder oder Gutscheine) per Copy & Paste aus Excel oder über das Eingabeformular „Karte eingeben“ (Name, Nummer, Telefon, E-Mail, gültig bis) → Serienkarten, eine Zeile pro Karte
- Druck direkt aus Chrome (eine Karte pro Seite, randlos), PNG-Export in 300 dpi
- Speichern/Öffnen als `.kartenstudio.json`, Autosave im Browser

## Drucken auf dem Badgy200
Im Chrome-Druckdialog: Drucker **Evolis Badgy200**, Ränder **Keine**, Skalierung **Standard**.
Der Badgy200 druckt einseitig. „Vorder- und Rückseite“ druckt automatisch zuerst alle Vorderseiten; danach
erscheint ein Hinweis zum Wenden des Stapels und ein Knopf „Rückseiten drucken“ (bei Bedarf „Rückseiten in
umgekehrter Reihenfolge“).

## Entwicklung
Quelle: `src/app.html`. `python3 build.py` bettet die QR-Bibliothek (qrcode-generator, MIT) und das Gold-Logo ein und schreibt `index.html`.
Das Gold-Logo entsteht aus `assets/logo-cooltourszene-original.jpg` mit `python tools/make_logo.py` (braucht Pillow).
