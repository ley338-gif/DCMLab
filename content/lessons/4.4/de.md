---
title: „Timeout"
teaser: Timeout ist keine Diagnose, sondern eine Sammelmeldung — sie lässt sich in vier unterscheidbare Ursachen zerlegen.
objectives:
  - Timeout als Symptom von der eigentlichen Ursache trennen
  - "Die Schicht eingrenzen: Netzweg, Port, DICOM-Aufbau oder laufender Transfer"
  - Idle Timeouts und MTU-Probleme als eigene Ursachenklassen erkennen
---

## „Timeout beim Senden"

Ticket: Ein Sendeauftrag hängt und bricht nach einer Weile mit
„Timeout" ab. Mehr steht nicht im Log der Modalität.

„Timeout" ist keine Diagnose — es ist eine Sammelmeldung für „es kam
keine Antwort, rechtzeitig". Das kann an vier ganz unterschiedlichen
Stellen liegen, und jede braucht einen anderen nächsten Schritt.

## Vier Stellen, an denen es hängen kann

<!-- kein-beispiel -->
```
1. Netzweg/Firewall    Die TCP-Verbindung kommt nie zustande
2. Falscher Port       Verbindung steht, aber niemand spricht DICOM dort
3. DICOM-Aufbau        Verbindung steht, Gegenstelle antwortet nicht auf die Association
4. Laufender Transfer  Verbindung stand, bricht erst während der Übertragung ab
```
Die ersten beiden lassen sich mit denselben Werkzeugen unterscheiden,
die schon Lektion 4.1 benutzt hat — hier geht es aber nicht um eine
Ablehnung, sondern darum, dass gar keine Antwort kommt.

## Fall 1 und 2: in der Spielwiese nachvollziehen

```
$ echoscu -v -aec ORTHANC 127.0.0.1 9999
I: Requesting Association
E: Association request failed: unable to connect to remote
E: TCP Initialisation Error: [Errno 111] Connection refused
I: Aborting Association
```
**Was du daran abliest:** `Connection refused` kommt sofort — der
Zielrechner ist erreichbar, aber auf diesem Port hört niemand. Das ist
kein Timeout, sondern eine sofortige Ablehnung auf TCP-Ebene. Ein
echter Timeout (keine Antwort, statt einer sofortigen Ablehnung) sieht
anders aus — typisch für eine Firewall, die Pakete stillschweigend
verwirft, statt sie mit einem TCP-Reset zu beantworten.

```
$ echoscu -v -aec ORTHANC -ta 5 127.0.0.1 8042
I: Requesting Association
E: Unknown PDU type received '0x48'
E: Association Aborted
E: Unknown PDU type received '0x2E'
```
**Was du daran abliest:** Port `8042` ist offen — die TCP-Verbindung
steht —, aber dahinter läuft Orthancs HTTP-Oberfläche, kein DICOM-Dienst.
`echoscu` schickt eine Association-Anfrage, bekommt eine HTTP-Antwort
zurück und kann die ersten Bytes nicht als gültiges DICOM-PDU
interpretieren. Das ist Fall 2: der Port ist erreichbar, aber falsch —
ein Verwechslungsklassiker, wenn eine Gegenstelle mehrere Dienste auf
verschiedenen Ports anbietet (hier: DICOM auf 4242, HTTP auf 8042).

## Fall 3 und 4: zwei Ursachenklassen, die sich nicht live erzeugen lassen

<!-- kein-beispiel -->
```
Beide Faelle brauchen eine Gegenstelle, die sich absichtlich falsch
verhaelt (Fall 3: TCP-Verbindung steht, aber die Association-Anfrage
bleibt unbeantwortet) oder eine Netzwerkbedingung, die sich in einem
einzelnen Docker-Netz nicht herstellen laesst (Fall 4: ein laufender
Transfer, der erst nach einer Weile abbricht, z. B. durch einen
Idle Timeout oder ein MTU-Problem auf dem Pfad). Reale, im Standard
und in DCMTK/pynetdicom vorhandene Begriffe dafuer:

  ACSE Timeout      Wartezeit auf die Antwort beim Verbindungsaufbau
                     selbst (real: --acse-timeout, siehe storescu --help)
  DIMSE Timeout      Wartezeit auf die Antwort einer einzelnen
                     DICOM-Nachricht waehrend einer laufenden Association
  Netzwerk-Timeout   allgemeine Wartezeit auf TCP-Ebene

Diese drei sind eigene, unabhaengig konfigurierbare Werte -- nicht
derselbe Timeout dreimal benannt. Ein Pfad-MTU-Problem ist keine
DICOM-spezifische Ursache, sondern ein allgemeines Netzwerkphaenomen
(zu grosse Pakete werden unterwegs verworfen statt fragmentiert): bei
kleinen Nachrichten (C-ECHO) faellt es nicht auf, bei grossen
Objektuebertragungen schon -- daher das typische Bild "kleine
Übertragungen klappen, grosse nicht".
```

## Im Alltag

Von außen nach innen eingrenzen, nicht raten:

| Schritt | Prüfung | Zeigt |
|---|---|---|
| 1 | `ping` | Ist der Host überhaupt erreichbar? (beweist nichts über den DICOM-Port) |
| 2 | `echoscu` gegen den Zielport | TCP-Ebene: Verbindung steht — oder nicht |
| 3 | `echoscu` mit kurzem `-ta`/`-td` | DICOM-Aufbau: antwortet die Gegenstelle überhaupt? |
| 4 | `storescu` mit realer Datei | Laufender Transfer: bricht es erst während der Übertragung ab? |

Jeder Schritt schließt eine der vier Ursachenklassen aus, bevor man
zur nächsten übergeht.

## Stolperfallen

- **„Timeout heißt Netzwerk."** Ein DICOM-Aufbau, der nicht antwortet
  (Fall 3), ist ein Anwendungsproblem auf der Gegenstelle, kein
  Netzwerkproblem — `ping` und ein offener Port beweisen nichts über
  diese Ebene.
- **Ping als Beweis.** `ping` prüft ICMP, nicht den DICOM-Port. Ein
  Host kann pingbar sein und trotzdem auf Port 104 nichts anbieten —
  und umgekehrt in gehärteten Netzen ICMP blockieren, obwohl der
  DICOM-Dienst läuft.
- **Alle drei Timeout-Werte für denselben halten.** ACSE-, DIMSE- und
  Netzwerk-Timeout sind unabhängig konfigurierbar — ein zu kurzer
  DIMSE-Timeout bricht mitten in einer sonst funktionierenden
  Übertragung ab, während ACSE und Netzwerk-Timeout nie ein Problem
  waren.

## Selbstcheck

1. `echoscu` liefert sofort `Connection refused`. Ist das ein Timeout?
2. Ein Sendeauftrag bricht nach einer Weile ab, nicht sofort. Welche der
   vier Ursachenklassen scheiden dadurch schon aus?
3. Warum beweist ein erfolgreiches `ping` nichts über den DICOM-Port?

*Wissenskarten (spaced repetition) folgen, sobald `meta.yml` ein
strukturiertes `quiz`-Feld mit Antwortschlüssel bekommt — siehe
`docs/content-todo.md`, Abschnitt P3.*

Werkzeuglage geprüft am: 2026-09-13
