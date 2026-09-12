---
title: SCU und SCP — Rollen, nicht Geräte
teaser: Dasselbe CT ist mal das eine, mal das andere. Wer das verinnerlicht, versteht die Hälfte aller Verbindungsfehler.
objectives:
  - SCU und SCP als Rollen pro Dienst begreifen, nicht als Geräteeigenschaft
  - Called und Calling AE Title korrekt zuordnen
  - Die Rollenumkehr bei C-MOVE erklären
  - Einschätzen, was ein erfolgreiches C-ECHO beweist — und was nicht
---

## „Sie müssen das als SCP eintragen"

Der Techniker des Modalitätenherstellers ist am Telefon. Neues Ultraschallgerät, soll ins Archiv senden. Er sagt: „Tragen Sie das bei sich als SCP ein."

Du fragst: „Und was bin dann ich?"

Kurze Pause. Dann: „Na, Sie sind das SCP."

Beide haben recht. Und genau da liegt das Missverständnis, das dich sonst durch deine gesamte PACS-Laufbahn begleitet.

## Der eine Satz, der alles klärt

> **SCU und SCP sind keine Geräte. Es sind Rollen in einem einzelnen Gespräch.**

{{term:scu}} steht für Service Class User: **die Seite, die einen Dienst in Anspruch nimmt.**

{{term:scp}} steht für Service Class Provider: **die Seite, die den Dienst erbringt.**

Das Telefon in deinem Büro ist nicht „ein Anrufer". Es ist ein Telefon. Ob du gerade Anrufer oder Angerufener bist, hängt davon ab, wer gewählt hat — und das kann sich zehnmal am Tag ändern.

Genauso bei DICOM. Die Rolle hängt nicht am Gerät, sondern an **einem Dienst in einer konkreten Verbindung.**

> **Faustregel, kein Gesetz:** In den allermeisten Fällen ist der SCU auch der, der die Verbindung aufbaut — wer anruft, will etwas. Das trägt für C-ECHO, C-STORE, C-FIND und die Worklist. Es gibt aber zwei Dienste, bei denen Anrufer und Rolle auseinanderfallen; du triffst sie weiter unten. Merke dir deshalb die Definition, nicht das Telefonbild.

## Dasselbe Gerät, vier Rollen

Schau dir ein einziges CT an einem normalen Vormittag an:

| Was passiert | Dienst | Das CT ist | Die Gegenstelle ist |
|---|---|---|---|
| CT holt die Tagesliste | Modality Worklist | **SCU** | RIS / Worklist-Broker: SCP |
| CT meldet „Untersuchung begonnen" | MPPS | **SCU** | RIS / Broker: SCP |
| CT schickt die Bilder | Storage (C-STORE) | **SCU** | Archiv: SCP |
| CT lässt sich den Eingang bestätigen | Storage Commitment | **SCU** | Archiv: SCP |
| Das Monitoring prüft, ob das CT erreichbar ist | Verification (C-ECHO) | **SCP** | Monitoring: SCU |

Fünfmal dasselbe Gerät, viermal SCU, einmal SCP. Wer in Geräten denkt, verliert hier den Faden. Wer in Diensten denkt, liest die Tabelle flüssig.

Zwei Zeilen verdienen einen zweiten Blick. **Worklist und MPPS** landen meistens nicht im Archiv, sondern im RIS oder einem vorgeschalteten Broker — wer MPPS-Probleme im PACS-Log sucht, sucht oft im falschen System. Und **Storage Commitment** ist die erste Stelle, an der die Telefon-Faustregel bricht: Das CT fragt „hast du meine Bilder wirklich sicher?" und ist damit SCU. Die Antwort kommt aber oft erst Minuten später — und für die baut das Archiv unter Umständen selbst eine Verbindung auf, **ohne dadurch zum SCU zu werden.** Es bleibt der Erbringer des Dienstes. Wer die Verbindung aufbaut und welche Rolle er hat, sind zwei verschiedene Dinge; DICOM handelt das bei Bedarf ausdrücklich aus (SCP/SCU Role Selection).

Zurück zum Telefonat: Der Techniker meinte „Ihr Archiv ist beim Bilderempfang das SCP" — das stimmt. Du hättest gefragt „und für die Worklist?" — dann wäre schnell klar geworden, dass ihr über zwei verschiedene Verbindungen redet.

## Beide Rollen selbst einnehmen

Das Ganze wird greifbar, sobald man es einmal gemacht hat. Zwei Terminals, fünf Minuten.

**Du als SCU.** Du fragst das Archiv etwas, also nimmst du einen Dienst in Anspruch:

```
$ echoscu -v -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242
I: Requesting Association
I: Association Accepted (Max Send PDV: 16372)
I: Sending Echo Request (MsgID 1)
I: Received Echo Response (Success)
I: Releasing Association
```

**Was du daran abliest:** *Requesting*, *Sending*, *Releasing* — alle Verben liegen bei dir. Du bist der Nehmende, Orthanc der Gebende. `-aet` ist dein eigener Name (Calling), `-aec` der Name der Gegenstelle (Called). Die Endung `scu` im Werkzeugnamen sagt dieselbe Sache noch einmal.

**Du als SCP.** Jetzt dieselbe Person, dasselbe Notebook — andere Rolle. Im zweiten Terminal:

```
$ mkdir eingang
$ storescp -v -aet MEIN-EMPFANG -od ./eingang 11112
I: Association Received (127.0.0.1: MEINE-WS -> MEIN-EMPFANG)
I: Association Acknowledged (Max Send PDV: 16372)
I: Received Store Request (MsgID 1, CT)
I: Sending Store Response (Success)
```

**Was du daran abliest:** Jetzt heißen die Verben *Received*, *Acknowledged*, *Sending Response* — du wartest und antwortest. Und lies die erste Zeile genau: Sie nennt beide Namen in der Richtung des Anrufs, `MEINE-WS -> MEIN-EMPFANG`, also Calling vor Called. Was dort links steht, ist der Name, den die Gegenstelle tatsächlich schickt — nicht der, den jemand behauptet eingetragen zu haben.

Ausgelöst hat das ein `storescu` im ersten Terminal:

```
$ storescu -v -aet MEINE-WS -aec MEIN-EMPFANG 127.0.0.1 11112 daten/ct-thorax/0001.dcm
I: Requesting Association
I: Association Accepted (Max Send PDV: 16372)
I: Sending Store Request (MsgID 1, CT)
I: Received Store Response (Success)
```

**Was du daran abliest:** Ein Dienst, zwei Seiten, zwei Werkzeuge mit demselben Stamm und verschiedener Endung. Du hast eben beide Rollen gleichzeitig besetzt — und genau deshalb ist „unser System ist ein SCP" keine sinnvolle Aussage ohne den Dienst dazu.

## Die Pointe: C-MOVE

Hier wird es interessant, und hier trennt sich, wer es wirklich verstanden hat.

Eine Befundstation möchte eine alte Untersuchung sehen. Sie fragt das Archiv per {{term:c-move}}: *Schick mir die Studie 4711.*

<!-- kein-beispiel -->
```
  Schritt 1: Die Anfrage
  ┌──────────────┐   C-MOVE-Anfrage    ┌──────────────┐
  │ Befundstation│ ──────────────────► │    Archiv    │
  │   = SCU      │                     │    = SCP     │
  └──────────────┘                     └──────────────┘

  Schritt 2: Die Lieferung — die Rollen drehen sich um
  ┌──────────────┐   ◄── C-STORE ───── ┌──────────────┐
  │ Befundstation│                     │    Archiv    │
  │   = SCP  (!) │                     │   = SCU  (!) │
  └──────────────┘                     └──────────────┘
```

Das Archiv baut für die Lieferung eine **neue, eigene Verbindung** zur Station auf. In dieser zweiten Verbindung ist das Archiv der Anrufer — also Storage SCU — und die Station der Empfänger — also Storage SCP.

Daraus folgt etwas sehr Praktisches: **Das Archiv muss die Station kennen.** Ein C-MOVE scheitert regelmäßig nicht an der Anfrage, sondern daran, dass das Archiv nicht weiß, wohin es liefern soll. Der Klassiker im Ticketsystem lautet „Abruf funktioniert nicht" — und die Ursache sitzt in einer Adressliste im Archiv, die niemand angefasst hat.

Wenn du dir aus dieser Lektion nur eine Sache merkst, dann diese.

Es geht auch anders: Bei **C-GET** läuft die Lieferung auf derselben Verbindung zurück, die die Station aufgebaut hat — das Archiv braucht dann keinen Eintrag für das Ziel, und es muss keine Verbindung von innen nach außen aufbauen. Genau deshalb ist C-GET (und ebenso DICOMweb) der Weg, der durch Firewalls und NAT kommt, wo C-MOVE scheitert. Verbreitet ist trotzdem C-MOVE, weil viele Altsysteme nichts anderes können. Details in Lektion 2.4.

## Das Adress-Trio

Damit zwei Seiten überhaupt zueinander finden, braucht es drei Angaben — und einen Namen, den viele unterschätzen:

| Angabe | Bedeutung |
|---|---|
| Hostname / IP | Wo im Netz |
| Port | Welche Tür — 104 oder 11112 |
| {{term:ae-title}} | Welcher Dienst hinter der Tür |

Beide Ports sind offiziell registriert. Der Standard empfiehlt 104 für Systeme, die privilegierte Ports benutzen dürfen — und 11112 für alle anderen. Da moderne Dienste selten mit Sonderrechten laufen, ist 11112 heute der häufigere Fall und kein Ausweichport zweiter Klasse.

Ein AE Title (Application Entity Title) ist der Name, unter dem sich eine DICOM-Anwendung meldet. Auf einem Server können mehrere laufen — der AE Title sagt, welcher gemeint ist.

Beim Verbindungsaufbau nennt der Anrufer immer beide Namen:

- **{{term:calling-ae-title}}** — *wer ich bin*
- **{{term:called-ae-title}}** — *wen ich sprechen möchte*

Merkhilfe: Wie beim Telefonat. „Hier ist Müller (Calling), ich möchte zu Frau Schmidt (Called)."

Und jetzt die Stelle, an der es in der Praxis knallt: Diese Namen stehen an **zwei Orten** und müssen zueinander passen.

<!-- kein-beispiel -->
```
  CT-Konsole                          Archiv
  ─────────────────────────           ──────────────────────────
  Eigener AE Title: CT_RAUM3          Eigener AE Title: PACS-ARCHIV
  Ziel-AE Title:   PACS-ARCHIV        Erlaubte Absender:
  Ziel-Host:       10.20.0.10           - CT_RAUM3      ✓
  Ziel-Port:       104                  - MR_RAUM1
                                        - US_AMBULANZ
```

Vier Felder, zwei Systeme, meist zwei verschiedene Zuständige. Ein Bindestrich statt Unterstrich, ein Buchstabe groß statt klein — und nichts geht mehr. AE Titles werden **zeichengenau** verglichen: `PACS-ARCHIV` und `PACS_ARCHIV` sind zwei verschiedene Namen, `Archiv` und `ARCHIV` ebenfalls. Maximal 16 Zeichen.

Eine Ausnahme gibt es: **Führende und angehängte Leerzeichen sind laut Standard bedeutungslos** und müssen von beiden Seiten weggeschnitten werden — im Verbindungsaufbau ist das Feld sogar immer exakt 16 Zeichen lang und wird mit Leerzeichen aufgefüllt. `ARCHIV` und `ARCHIV␣␣` sind derselbe Name. Verlass dich im Alltag trotzdem nicht darauf: Nicht jede Implementierung hält sich daran, und ein versehentliches Leerzeichen mitten im Namen ist ohnehin ein anderer Name.

Zur Groß- und Kleinschreibung: Der Standard kennt **keine** Normalisierung. Also zählt jedes Zeichen. Dass manche Systeme intern alles großschreiben, ist deren Eigenmächtigkeit — kein Standardverhalten, auf das du bauen kannst.

Das ist keine Theorie. Das ist die häufigste Fehlkonfiguration überhaupt — und genau das Szenario deines Labs.

## C-ECHO: der Ping, der keiner ist

{{term:c-echo}} ist der Funktionstest: *Bist du da und redest du mit mir?*

Er ist deutlich mehr als ein Netzwerk-Ping. Ein `ping` sagt nur, dass ein Rechner erreichbar ist. Ein C-ECHO baut eine vollständige {{term:association}} auf — das heißt, es prüft gleichzeitig:

- ist der Port offen und lauscht dort eine DICOM-Anwendung
- akzeptiert die Gegenseite den Called AE Title — *sofern sie ihn überhaupt prüft*
- akzeptiert sie meinen Calling AE Title — dito
- einigen wir uns auf eine Kodierung

Deshalb ist C-ECHO immer der erste Handgriff bei einer Störung.

Was dabei tatsächlich ausgehandelt wird, kann man sich ansehen:

```
$ echoscu -d -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242 2>&1 | grep -A2 "Context ID"
D:   Context ID:        1 (Proposed)
D:     Abstract Syntax: =VerificationSOPClass
D:     Proposed Transfer Syntax(es):
D:   Context ID:        1 (Accepted)
D:     Abstract Syntax: =VerificationSOPClass
D:     Accepted Transfer Syntax:      =LittleEndianExplicit
```

**Was du daran abliest:** Genau ein Objekttyp wurde verhandelt — `VerificationSOPClass`, also das C-ECHO selbst. Von CT-Bildern war nirgends die Rede. Damit ist schwarz auf weiß belegt, was der nächste Kasten behauptet.

> **Ein erfolgreiches C-ECHO beweist nicht, dass Bilder ankommen.**
>
> Beim Verbindungsaufbau wird ausgehandelt, über welche Objekttypen geredet werden darf. Ein C-ECHO handelt nur die Verification {{term:sop-class}} aus — sonst nichts. Dass die Gegenstelle auch CT-Bilder annimmt, ist damit in keiner Weise gesagt.
>
> „C-ECHO ist grün, also muss es am Netzwerk liegen" ist der Satz, der schon viele Stunden gekostet hat. C-ECHO grün heißt: Die beiden finden zueinander. Mehr nicht. Was dann noch schiefgehen kann, ist Thema von Lektion 1.8 und Track 4.

Noch eine Einschränkung: Viele Archive prüfen den Called AE Title **gar nicht** und nehmen jeden Namen an — bei Übungsservern ist diese Prüfung oft standardmäßig aus. Ein grünes C-ECHO sagt dann noch weniger. Umgekehrt heißt das: Ein Archiv, das dich sauber ablehnt, tut dir einen Gefallen.

## Im Alltag heißt das

Wenn eine neue Verbindung eingerichtet wird, stellst du ab jetzt vier Fragen, und zwar in dieser Reihenfolge:

1. **Um welchen Dienst geht es?** Bilder senden, Worklist abrufen, Bilder abholen — jeder ist eine eigene Verbindung mit eigenen Rollen.
2. **Wer ruft an?** Das ist der SCU. Bei ihm werden Ziel-Host, Ziel-Port und Called AE Title eingetragen.
3. **Wer nimmt ab?** Das ist der SCP. Bei ihm muss der Anrufer als erlaubter Absender bekannt sein.
4. **Gibt es einen Rückweg?** Bei C-MOVE dreht sich die Rolle um, bei Storage Commitment nur die Verbindungsrichtung — praktisch heißt beides dasselbe: Die Gegenstelle muss dich erreichen können und als Absender kennen. Sonst funktioniert die Verbindung „zur Hälfte".

Punkt 4 ist der, der vergessen wird — Senden klappt, Abrufen nicht.

> ### Stolperfallen
>
> **„Unser Archiv ist ein SCP."**
> Beim Bilderempfang ja. Beim Ausliefern per C-MOVE ist es Storage SCU. Bei Storage Commitment bleibt es SCP, baut die Verbindung für die Antwort aber womöglich selbst auf. Die Aussage ist ohne Dienst wertlos.
>
> **„C-ECHO läuft, also kann ich senden."**
> Nein. Siehe oben — es wurde nur die Verification SOP Class ausgehandelt.
>
> **„Ich habe den AE Title eingetragen, jetzt muss es gehen."**
> Auf einer Seite reicht nicht. Der Empfänger muss den Absender kennen, sonst lehnt er die Verbindung ab.
>
> **„Groß- und Kleinschreibung ist doch egal."**
> Ist sie nicht. AE Titles werden zeichengenau verglichen. Viele Systeme schreiben intern alles groß, andere nicht — verlass dich nie darauf.
>
> **„Der AE Title ist der Hostname."**
> Zwei völlig verschiedene Dinge. Dass viele Häuser sie gleich benennen, ist Konvention und erleichtert das Leben — aber technisch hat der AE Title mit DNS nichts zu tun.

## Dein Lab: Silent CT

Das CT in Raum 3 sendet seit dem Wartungsfenster am Wochenende keine Bilder mehr. Der Techniker sagt, am Gerät sei nichts verändert worden. Du hast Zugriff auf das Archiv und auf die CT-Konsole.

Bring die Bilder ins Archiv. Der Flag ist die Series Description der Serie, die dort ankommt.

Alles, was du dafür brauchst, steht in dieser Lektion. Plane 15 Minuten ein.

## Selbstcheck

<details>
<summary>Eine Befundstation ruft per C-MOVE Bilder ab. Welche Rolle hat das Archiv?</summary>

Beide — nacheinander. Bei der C-MOVE-Anfrage ist das Archiv SCP (es nimmt die Anfrage entgegen). Für die Lieferung baut es eine neue Verbindung zur Station auf und ist dabei Storage SCU. Die Station ist in dieser zweiten Verbindung Storage SCP.
</details>

<details>
<summary>Das C-ECHO von der Modalität zum Archiv ist erfolgreich, Bilder kommen trotzdem nicht an. Nenne zwei mögliche Ursachen.</summary>

Erstens: Das Archiv akzeptiert den Objekttyp nicht — etwa weil die SOP Class der Modalität dort nicht freigeschaltet ist. Zweitens: Die beiden Seiten finden keine gemeinsame Kodierung für die Bilddaten, also keine gemeinsame Transfer Syntax. Beide Fälle passieren erst beim Verbindungsaufbau für den Storage-Dienst — beim C-ECHO wurde darüber gar nicht verhandelt. Denkbar sind außerdem ein volles Archiv oder Größenlimits.
</details>

<details>
<summary>Im Archiv-Log steht: „Association rejected: called AE title not recognized". Wo suchst du?</summary>

Der Anrufer hat einen Called AE Title genannt, den das Archiv nicht als den seinen erkennt. Vergleiche zeichengenau, was die Modalität als Ziel-AE-Title einträgt, mit dem AE Title, unter dem das Archiv tatsächlich läuft. Achte auf Bindestrich gegen Unterstrich und auf Groß- und Kleinschreibung. Führende und angehängte Leerzeichen sind dagegen laut Standard bedeutungslos — dort liegt der Fehler nur, wenn die Gegenstelle sich nicht an die Regel hält.
</details>

<details>
<summary>Warum ist „Wir tragen den AE Title einfach genauso wie den Hostnamen ein" eine gute Konvention, aber eine schlechte Annahme?</summary>

Als Hausregel ist es hilfreich, weil es die Zuordnung im Betrieb erleichtert. Als Annahme über fremde Systeme ist es falsch: AE Title und Hostname sind unabhängig voneinander. Ein Gerät kann unter `10.20.0.10` erreichbar sein und sich `PACS-ARCHIV` nennen — und ein zweiter Dienst auf demselben Host heißt anders.
</details>

## Quiz

*Wissenskarten — kommen später zur Wiederholung zurück.*

**q1 — Ein CT schickt Bilder an das Archiv. Welche Rolle hat das CT?**
1. Storage SCP
2. Storage SCU
3. Kommt auf das Archiv an
4. Beides gleichzeitig

**q2 — Welche Aussagen über AE Titles stimmen?** *(Mehrfachauswahl)*
1. Sie entsprechen immer dem Hostnamen
2. Maximal 16 Zeichen
3. Groß- und Kleinschreibung ist egal
4. Führende und angehängte Leerzeichen sind bedeutungslos

**q3 — Ein erfolgreiches C-ECHO beweist, dass …**
1. … die beiden Seiten eine Association aufbauen können
2. … Bilder übertragen werden können
3. … das Archiv genug Speicher hat
4. … die Transfer Syntax passt

---

**Als Nächstes:** [1.6 — AE Title, Host, Port](../1.6/) macht aus dem Adress-Trio eine Konfiguration, die du selbst aufsetzt — bis das C-ECHO grün ist.
