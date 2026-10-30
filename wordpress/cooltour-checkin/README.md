# Cooltour Check-in (WordPress-Plugin)

Einlasskontrolle mit dem QR-Code der Clubkarten aus dem Badgy-Kartenstudio.

- Der QR-Code auf Mitglieds- und VIP-Karten enthält nur `https://cooltourszene.com/clubkarte/<Nummer>.<Code>`, keine Kontaktdaten.
- Wer den Code ohne Anmeldung scannt, sieht nur „Clubkarte der Cooltourszene“.
- Die Security meldet sich an, öffnet `/einlass/`, wählt das Event und scannt mit der Handykamera direkt auf der Seite.
  Grün = Einlass (Besuch wird gespeichert), Orange = schon eingecheckt, Rot = unbekannt, abgelaufen oder gesperrt.
  Angezeigt werden Name, Telefon, E-Mail, Kartenart, Gültigkeit und Anzahl Besuche.
- Im Backend unter **Check-in**: Events mit Gästeliste, Kartenliste mit Besuchen, Sperren/Notiz pro Karte, Export als CSV für Excel.

## Installation

1. Ordner `cooltour-checkin` als ZIP packen (oder `cooltour-checkin.zip` aus den Projektdateien nehmen).
2. WordPress → Plugins → Installieren → Plugin hochladen → aktivieren.
3. Einstellungen → Permalinks einmal speichern, falls `/einlass/` nicht gefunden wird.
4. Benutzer für die Security mit der Rolle **Security (Einlass)** anlegen.
5. Im Kartenstudio (Tab „Liste“) **Für Webseite exportieren** und unter Check-in → Karten importieren hochladen.

Events aus „The Events Calendar“ (gestern bis morgen) stehen auf der Scan-Seite zur Auswahl, sonst wird der Eventname eingetippt.

Voraussetzungen: WordPress ab 6.0, PHP ab 7.4, HTTPS (für die Kamera im Browser).

QR-Erkennung: jsQR 1.4.0 (Apache-2.0, `assets/jsQR-LICENSE.txt`).
