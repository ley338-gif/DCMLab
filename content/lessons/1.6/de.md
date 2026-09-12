---
title: AE Title, Host, Port — eine Verbindung einrichten
teaser: Vier Felder, zwei Systeme, zwei Zuständige. Deshalb scheitert hier mehr als an jeder anderen Stelle.
objectives:
  - Eine neue DICOM-Verbindung beidseitig vollständig konfigurieren
  - Einen AE Title so vergeben, dass er zehn Jahre hält
  - Mit einem eigenen Empfänger prüfen, ob eine Gegenstelle wirklich sendet
---

## Montag kommt ein Ultraschallgerät

Die Medizintechnik meldet: Neues Gerät in der Ambulanz, Anschluss am Montag, es soll ins PACS senden. Der Techniker fragt, welche Daten er braucht.

Was du ihm gibst und was du selbst eintragen musst, ist der Inhalt dieser Lektion. Aus 1.5 weißt du, was ein AE Title ist und wer welche Rolle hat. Jetzt geht es darum, es zum Laufen zu bringen — und zwar so, dass es in fünf Jahren noch stimmt.

## Die Konfiguration ist immer symmetrisch

Das ist der Kern. Jede DICOM-Verbindung wird an **zwei** Stellen eingetragen, und beide Einträge müssen zusammenpassen:

```
   SENDER (SCU)                          EMPFÄNGER (SCP)
   ────────────────────────────          ────────────────────────────
   Eigener AE Title   US_AMBULANZ        Eigener AE Title   PACS-ARCHIV
   Ziel-AE Title      PACS-ARCHIV   ───► Erlaubte Absender
   Ziel-Host          10.20.0.10           US_AMBULANZ   ◄── muss hier stehen
   Ziel-Port          104                  CT_RAUM3
                                           MR_1
```

Vier Felder beim Sender, ein Eintrag beim Empfänger. Der Fehler, den man Woche für Woche sieht: Der Sender ist fertig konfiguriert, beim Empfänger fehlt der Absender — und die Verbindung wird abgewiesen, obwohl „doch alles eingetragen" ist.

Dazu kommt die dritte Stelle, an die niemand denkt: **die Firewall.** Wenn Modalitäten in einem eigenen Netzsegment hängen, braucht es eine Freigabe für genau diesen Port in genau diese Richtung — und für C-MOVE zusätzlich in die Gegenrichtung, weil das Archiv dort selbst eine Verbindung aufbaut (Lektion 1.5).

## Was du dem Techniker gibst

Diese Liste kann man auswendig. Sie ist beide Male dieselbe, nur seitenverkehrt:

| Was | Beispiel | Wer liefert es |
|---|---|---|
| Unser AE Title | `PACS-ARCHIV` | du |
| Unser Host | `10.20.0.10` | du |
| Unser Port | `104` | du |
| Sein AE Title | `US_2044` | **ihr legt ihn gemeinsam fest** |
| Seine IP | `10.20.0.44` | Netzwerk |

Die vierte Zeile ist die einzige mit Gestaltungsspielraum — und die einzige, die dir später Ärger macht, wenn du sie nebenbei entscheidest.

## Einen AE Title vergeben, der hält

Ein AE Title ist maximal **16 Zeichen** lang und wird **zeichengenau** verglichen. Er taucht in Logs, Konfigurationsmasken und Auswertungen auf, und er lässt sich nachträglich nur ändern, indem beide Seiten gleichzeitig umgestellt werden. Das macht niemand gern zweimal.

**Was einen guten AE Title ausmacht:**

- **Er beschreibt das Gerät, nicht den Ort.** `US_AMBULANZ` wird falsch, sobald das Gerät umzieht. `US_2044` (Inventarnummer) bleibt richtig.
- **Er passt in 16 Zeichen, mit Reserve.** Wer bei 16 anfängt, kann nie etwas anhängen. Plane mit zwölf.
- **Nur Großbuchstaben, Ziffern, Bindestrich oder Unterstrich** — und im ganzen Haus konsequent das eine oder das andere. Genau diese Verwechslung ist das Szenario deines ersten Labs.
- **Keine Leerzeichen, keine Umlaute, keine Sonderzeichen.** Führende und angehängte Leerzeichen sind laut Standard bedeutungslos, aber nicht jede Implementierung hält sich daran.
- **Ein Schema für das ganze Haus.** `<Modalität>_<Nummer>` reicht völlig. Wichtiger als das Schema selbst ist, dass es eines gibt.

**Und schreib es auf.** Nicht in eine Mail, sondern an eine Stelle, die in drei Jahren noch gefunden wird. Der einzige zuverlässige Weg, zwei Systeme zeichengenau übereinzubringen, ist, den Wert aus einer Quelle zu **kopieren** statt ihn zweimal abzutippen.

## Ports: 104 oder 11112

Beide sind offiziell registriert, und keiner ist der schlechtere.

`104` ist der klassische Port. Er liegt unter 1024 und ist damit auf unixoiden Systemen privilegiert — ein Dienst, der dort lauschen will, braucht erhöhte Rechte oder eine Umleitung.

`11112` ist ebenfalls für DICOM registriert und deshalb der Normalfall bei allem, was nicht mit Sonderrechten laufen soll. Orthanc verwendet standardmäßig `4242`, was wiederum eine Hausnummer des Projekts ist.

Praktisch heißt das: **Es gibt keinen Port, auf den du dich verlassen kannst.** Abfragen, eintragen, dokumentieren.

## Einrichten und prüfen

Die Reihenfolge ist wichtig, weil jeder Schritt genau eine Frage beantwortet.

### Schritt 1 — Erreicht meine Seite die Gegenstelle?

```
$ echoscu -v -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242
I: Requesting Association
I: Association Accepted (Max Send PDV: 16372)
I: Sending Echo Request (MsgID 1)
I: Received Echo Response (Success)
I: Releasing Association
```

**Was du daran abliest:** Netzweg, Port und Namen stimmen in dieser Richtung. Das ist die Hälfte der Verbindung — und nur die Hälfte.

### Schritt 2 — Was passiert bei einem falschen Namen?

```
$ echoscu -aet MEINE-WS -aec PACS_ARCHIV 127.0.0.1 4242
F: Association Rejected:
F:   Result: Rejected Permanent, Source: Service User
F:   Reason: Called AE Title Not Recognized
```

**Was du daran abliest:** Die Gegenstelle ist erreichbar und lehnt trotzdem ab — das ist eine *Konfigurations*-Aussage, keine Netzwerkaussage. Und sie nennt die Richtung: `Called AE Title Not Recognized` heißt, ich habe sie unter einem Namen angesprochen, den sie nicht als ihren erkennt. Stünde dort `Calling AE Title Not Recognized`, wäre es umgekehrt — sie kennt mich nicht.

Diese Meldung ist ein Geschenk. Rechne im Alltag damit, dass viele Systeme nur `no reason given` liefern oder die Verbindung kommentarlos zumachen.

### Schritt 3 — Erreicht mich die Gegenstelle?

Das ist der Schritt, der übersprungen wird. Stell dich selbst als Empfänger hin:

```
$ mkdir eingang
$ storescp -v -aet MEIN-EMPFANG -od ./eingang 11112
I: Association Received (127.0.0.1: MEINE-WS -> MEIN-EMPFANG)
I: Association Acknowledged (Max Send PDV: 16372)
I: Received Store Request (MsgID 1, CT)
I: Sending Store Response (Success)
```

**Was du daran abliest:** Hier siehst du, was die andere Seite sieht — welchen Calling AE Title sie tatsächlich schickt und ob überhaupt etwas ankommt. Das beantwortet die häufigste Streitfrage bei Inbetriebnahmen: „Wir senden doch." Wenn hier nichts erscheint, senden sie nicht — oder woandershin.

Im zweiten Terminal schickst du dir selbst etwas:

```
$ storescu -v -aet MEINE-WS -aec MEIN-EMPFANG 127.0.0.1 11112 daten/ct-thorax/0001.dcm
I: Requesting Association
I: Association Accepted (Max Send PDV: 16372)
I: Sending Store Request (MsgID 1, CT)
I: Received Store Response (Success)
```

**Was du daran abliest:** Beide Richtungen funktionieren, und das Objekt liegt anschließend wirklich in `./eingang` — nachprüfbar mit `dcmdump`. Ein erfolgreiches C-STORE beweist mehr als jedes C-ECHO.

## Im Alltag heißt das

Die Inbetriebnahme-Liste. Sie passt auf eine Karteikarte und erspart die meisten Rückrufe:

| # | Schritt | Erledigt, wenn |
|---|---|---|
| 1 | AE Title festlegen, nach Hausschema | im Verzeichnis eingetragen |
| 2 | IP vom Netzwerk bestätigen lassen | nicht geraten, bestätigt |
| 3 | Port vereinbaren | beide Seiten nennen dieselbe Zahl |
| 4 | Firewall-Freigabe beantragen | Richtung und Port benannt, C-MOVE bedacht |
| 5 | Absender im Archiv eintragen | steht in der Liste erlaubter Nodes |
| 6 | C-ECHO vom Gerät zum Archiv | grün |
| 7 | C-ECHO vom Archiv zum Gerät, falls nötig | grün |
| 8 | Ein echtes Bild senden | im Archiv sichtbar |
| 9 | Dokumentieren | AE Title, IP, Port, Dienste, Ansprechpartner |

Schritt 8 ist der einzige, der wirklich etwas beweist. Schritt 9 ist der, der dir in zwei Jahren den Abend rettet.

> ### Stolperfallen
>
> **„Ich habe es eingetragen, jetzt muss es gehen."**
> Auf einer Seite reicht nie. Ein ordentlich konfiguriertes Archiv nimmt nur Verbindungen von bekannten Absendern an — was richtig ist und was du nicht abschalten solltest.
>
> **„Der AE Title ist der Hostname."**
> Zwei unabhängige Dinge. Dass viele Häuser sie gleich benennen, ist eine gute Konvention und keine technische Tatsache.
>
> **„Wir nehmen den Raumnamen."**
> Bis der Raum umgebaut wird. Dann heißt das Gerät in Raum 5 immer noch `US_AMBULANZ`, und niemand traut sich, es zu ändern, weil vier Systeme daran hängen.
>
> **„Das Gerät zeigt ‚Verbindung OK'."**
> Viele Konsolen melden das schon, wenn der TCP-Verbindungsaufbau geklappt hat. Verlass dich auf die Gegenseite: Steht im Empfangslog eine Association, ist es in Ordnung. Steht dort nichts, ist es das nicht.
>
> **„Port 104, das ist ja Standard."**
> Es gibt zwei registrierte Ports und viele Systeme mit eigenen Voreinstellungen. Fragen, nicht annehmen.

## Dein Lab

Im Lab **Neue Node** kommt ein Gerät ins Haus, und du bekommst nur, was der Techniker am Telefon gesagt hat — teilweise falsch. Deine Aufgabe: die Verbindung beidseitig einrichten, bis ein echtes Bild im Archiv liegt, und dabei herausfinden, welche der genannten Angaben nicht stimmt.

## Selbstcheck

<details>
<summary>Der Techniker sagt: „Bei uns ist alles eingetragen, es kommt nichts an." Was prüfst du zuerst?</summary>

Ob dein System den Absender überhaupt kennt. Ein Archiv, das nur konfigurierte Calling AE Titles annimmt, lehnt eine ansonsten perfekte Verbindung ab. Danach stellst du die Verbindung von deiner Seite mit genau seinen Werten nach — dann siehst du die Ablehnung samt Grund selbst, statt sie dir beschreiben zu lassen.
</details>

<details>
<summary>Warum beweist ein „Verbindung OK" auf der Gerätekonsole wenig?</summary>

Weil viele Konsolen das schon melden, wenn die TCP-Verbindung zustande kam — also bevor irgendein DICOM-Name geprüft wurde. Aussagekräftig ist das Log der Gegenstelle: Taucht dort eine Association auf, gab es wirklich ein DICOM-Gespräch. Taucht nichts auf, ist auch nichts angekommen.
</details>

<details>
<summary>Warum ist `US_AMBULANZ` ein schlechterer AE Title als `US_2044`?</summary>

Weil er den Ort beschreibt und nicht das Gerät. Zieht das Gerät um oder wird die Ambulanz umbenannt, ist der Name falsch — ändern lässt er sich aber nur, wenn alle Gegenstellen gleichzeitig umgestellt werden. Eine Inventar- oder Gerätenummer bleibt über die gesamte Lebensdauer richtig.
</details>

## Quiz

*Wissenskarten — kommen später zur Wiederholung zurück.*

**q1 — An wie vielen Stellen muss eine neue DICOM-Verbindung eingetragen werden?**
1. Nur beim Sender
2. Nur beim Empfänger
3. Bei beiden, und meist zusätzlich in der Firewall
4. Nur im DNS

**q2 — Welche Aussagen stimmen?** *(Mehrfachauswahl)*
1. Ein AE Title darf höchstens 16 Zeichen haben
2. Port 104 ist der einzige zulässige DICOM-Port
3. `Calling AE Title Not Recognized` heißt, die Gegenstelle kennt dich nicht
4. Ein erfolgreiches C-ECHO beweist, dass Bilder ankommen

**q3 — Mit welchem Werkzeug machst du dich selbst zum Empfänger, um zu prüfen, ob ein Gerät wirklich sendet?** *(Freitext)*

---

**Als Nächstes:** [1.7 — Transfer Syntax und Kompression](../1.7/) erklärt, warum eine fertig eingerichtete Verbindung trotzdem nur die Hälfte der Bilder durchlässt.
