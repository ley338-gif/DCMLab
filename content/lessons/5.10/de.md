---
title: "AE-Title- und Port-Registry als Betriebsgrundlage"
teaser: "Wenn niemand sicher sagen kann, wem `CT01` gehört und auf welchem Port es lauscht, ist die nächste Störung bereits vorbereitet."
objectives:
  - Eine zentrale Registry als technische Quelle für DICOM-Endpunkte erklären
  - Pro Endpunkt Identität, Netzwerk, Rollen und Verantwortlichkeit dokumentieren
  - Namenskonflikte und historische Altlasten erkennen
  - Änderungen kontrolliert durchführen, statt AE Titles nebenbei umzubenennen
---

## Drei Systeme heißen `CT01`

Das alte CT wurde vor fünf Jahren außer Betrieb genommen. Sein AE Title `CT01` blieb im PACS stehen. Ein neues Gerät erhielt denselben Namen. Zusätzlich existiert auf einem DICOM-Router eine Route mit dem Alias `CT01`.

Solange alles funktioniert, merkt niemand etwas. Beim ersten Retrieve oder Routing-Problem beginnt dann die Suche: Welches `CT01` meint dieses Log eigentlich?

Eine AE-Title-Registry ist deshalb keine Bürokratie. Sie ist die **Inventarliste der DICOM-Kommunikation**.

## Was pro DICOM-Endpunkt dokumentiert werden sollte

Mindestens diese Felder sind praktisch:

| Feld | Warum es wichtig ist |
|---|---|
| System / Gerät | sprechender Name |
| Standort / Raum | physische Zuordnung |
| AE Title | DICOM-Identität |
| IP / DNS | Netzwerkziel |
| Port | Listener |
| Rollen | z. B. Storage SCU/SCP, MWL SCU, Q/R SCP |
| SOP Classes | was tatsächlich gebraucht wird |
| TLS | ja/nein, Zertifikatsbezug |
| Owner | fachlich/technisch verantwortlich |
| Herstellerkontakt | Eskalationsweg |
| Inbetriebnahme | zeitliche Einordnung |
| Status | aktiv, Test, außer Betrieb |

Nicht jede Organisation braucht jedes Feld. Aber **AE Title + IP + Port ohne Rolle** ist zu wenig: Dass ein Gerät auf einem Port DICOM spricht, sagt noch nicht, für welche Services.

## Ein AE Title ist kein DNS-Name

Ein häufiger Denkfehler ist, AE Titles wie weltweit eindeutige Hostnamen zu behandeln. In der Praxis sind sie lokale Application Entity Titles innerhalb einer DICOM-Konfiguration.

Darum darfst du nicht darauf vertrauen, dass `CT01` automatisch auflösbar oder organisationsweit eindeutig ist. Die Zuordnung zu Host und Port lebt in der Konfiguration der beteiligten Systeme.

## Lebenszyklus statt Excel-Friedhof

Eine Registry hilft nur, wenn sie denselben Lebenszyklus wie die Systeme hat:

<!-- kein-beispiel -->
```text
geplant -> Test -> produktiv -> geändert -> außer Betrieb -> entfernt
```

Wird ein Gerät stillgelegt, müssen nicht nur Switchport und IP verschwinden. Prüfe auch:

- PACS-AE-Konfiguration
- DICOM-Router
- MWL-Zuordnung
- Retrieve-Zieltabellen
- Firewall-Regeln
- Monitoring
- Zertifikate
- Dokumentation bei Hersteller oder Medizintechnik

Ein verwaister AE-Eintrag ist später kaum von einem noch gültigen, aber selten genutzten Ziel zu unterscheiden.

## Im Alltag heißt das

Bei jeder DICOM-Störung sollte die Registry drei Fragen sofort beantworten können:

1. **Wer ist das?** — System, Standort, Owner.
2. **Wie erreiche ich es?** — IP/DNS, Port, TLS.
3. **Was soll es können?** — Rollen und benötigte SOP Classes.

Wenn du diese drei Antworten erst aus Tickets, Screenshots und Hersteller-PDFs zusammensuchen musst, ist die Betriebsdokumentation selbst ein Fehlerverstärker.

## Stolperfallen

- **AE Title ohne Standort dokumentieren.** Mobile oder ausgetauschte Geräte werden schnell verwechselt.
- **IP als Identität verwenden.** Adressen ändern sich, die fachliche Rolle bleibt.
- **Alte Einträge nur „zur Sicherheit“ behalten.** Sie erzeugen Mehrdeutigkeit.
- **Test- und Produktionsziele vermischen.** Ein falscher C-STORE kann dann echte Daten an ein Testsystem schicken.
- **Owner weglassen.** Technik ohne Verantwortlichkeit altert unbemerkt.

## Selbstcheck

1. Welche drei Fragen sollte eine AE-Registry bei einer Störung sofort beantworten?
2. Warum ist ein AE Title kein Ersatz für DNS?
3. Welche Einträge musst du bei der Außerbetriebnahme einer Modalität außerhalb des PACS mitdenken?
4. Warum ist die dokumentierte Rolle genauso wichtig wie IP und Port?

## Quiz

*Wissenskarten — kommen später zur Wiederholung zurück.*

**q1 — Welche Angaben gehören sinnvollerweise in eine AE-Title- und Port-Registry?** *(Mehrfachauswahl)*
1. AE Title, IP/Host und Port
2. Rollen und benötigte SOP Classes
3. Owner und Eskalationsweg
4. Status (aktiv, Test, außer Betrieb)

**q2 — Warum ist ein AE Title kein Ersatz für einen DNS-Namen?**
1. AE Titles sind länger als DNS-Namen
2. AE Titles sind nur innerhalb der jeweiligen DICOM-Konfiguration eindeutig, nicht global auflösbar
3. AE Titles ändern sich automatisch bei jedem Neustart
4. DNS unterstützt keine Großbuchstaben
