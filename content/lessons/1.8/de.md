---
title: Association und Presentation Context
teaser: „Association Accepted" heißt nicht, dass etwas durchgeht. Es heißt nur, dass die Verhandlung stattgefunden hat.
objectives:
  - Den Ablauf eines Verbindungsaufbaus in seine Schritte zerlegen
  - Aus einem Association-Log ablesen, welcher Objekttyp in welcher Kodierung angenommen wurde
  - Erklären, warum eine angenommene Association trotzdem kein Bild überträgt
---

## Zwei Hersteller, zwei Schuldige

Der Modalitätenhersteller sagt, er sende korrekt. Der Archivhersteller sagt, er nehme alles an, was der Standard vorsieht. Beide haben Logs, beide zeigen nichts Auffälliges, und seit drei Tagen bewegt sich nichts.

Solche Diskussionen enden in drei Minuten, wenn jemand den Verbindungsaufbau mitliest. Denn dort steht wörtlich, was angeboten und was davon angenommen wurde — und es ist regelmäßig etwas anderes, als beide Seiten behaupten.

## Eine Association ist eine Verhandlung

Bevor ein einziges Bild fließt, einigen sich die beiden Seiten darauf, **worüber** sie überhaupt reden können. Der Ablauf hat vier Schritte:

<!-- kein-beispiel -->
```
   SCU                                              SCP
    │                                                │
    │  ── A-ASSOCIATE-RQ ──────────────────────────► │   "Ich bin CT_RAUM3,
    │     wer ich bin, wen ich meine,                │    ich möchte zu
    │     was ich anbieten kann                      │    PACS-ARCHIV, und
    │                                                │    ich kann folgendes"
    │  ◄────────────────────── A-ASSOCIATE-AC ────── │
    │     was davon ich annehme — einzeln            │   "Davon nehme ich das
    │                                                │    und das"
    │  ── C-STORE / C-FIND / … ────────────────────► │
    │  ◄────────────────────────── Antworten ─────── │
    │                                                │
    │  ── A-RELEASE-RQ ────────────────────────────► │   geordnetes Ende
    │  ◄────────────────────── A-RELEASE-RP ──────── │
```

Statt `AC` kann auch `RJ` kommen — die Ablehnung mit Grund, die du aus Lektion 1.6 kennst. Und statt eines geordneten Release kann jederzeit ein **A-ABORT** auftreten: Abbruch, meistens ohne brauchbare Begründung. Ein Abort im Log heißt fast immer, dass eine Seite in einen Zustand geraten ist, mit dem sie nicht gerechnet hat.

## Der Presentation Context

Das Herzstück des Angebots. Ein Presentation Context besteht aus drei Teilen:

| Teil | Was es ist | Beispiel |
|---|---|---|
| **ID** | eine ungerade Zahl, nur für diese Verbindung gültig | `1`, `3`, `5`, … |
| **Abstract Syntax** | *was* — die SOP Class, also der Objekttyp | CT Image Storage |
| **Transfer Syntaxes** | *wie* — eine Liste von Kodierungen, aus der die Gegenseite wählt | Explicit VR LE, Implicit VR LE |

Der Anfragende schlägt eine ganze Liste solcher Kontexte vor — einen je Objekttyp, den er senden möchte, mit allen Kodierungen, die er dafür beherrscht. Der Annehmende antwortet **auf jeden einzeln**.

Genau hier liegt der Punkt, den die meisten übersehen:

> **Die Association wird als Ganzes angenommen. Jeder Presentation Context wird einzeln angenommen oder abgelehnt.**

Eine Association kann also erfolgreich zustande kommen, während kein einziger der angebotenen Kontexte angenommen wurde. Das Log sagt dann „Association Accepted" — und es geht trotzdem nichts.

## Beim einfachsten Fall zusehen

`-d` zeigt die Verhandlung im Klartext. Bei einem C-ECHO gibt es nur einen Kontext:

```
$ echoscu -d -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242 2>&1 | head -24
D: Request Parameters:
D: ====================== BEGIN A-ASSOCIATE-RQ =====================
D: Our Implementation Class UID:      1.2.276.0.7230010.3.0.3.6.8
D: Calling Application Name:          MEINE-WS
D: Called Application Name:           ORTHANC
D: Our Max PDU Receive Size:          16384
D: Presentation Contexts:
D:   Context ID:        1 (Proposed)
D:     Abstract Syntax: =VerificationSOPClass
D:     Proposed Transfer Syntax(es):
D:       =LittleEndianExplicit
D:       =BigEndianExplicit
D:       =LittleEndianImplicit
D: ======================= END A-ASSOCIATE-RQ ======================
D: ====================== BEGIN A-ASSOCIATE-AC =====================
D: Their Max PDU Receive Size:        16372
D: Presentation Contexts:
D:   Context ID:        1 (Accepted)
D:     Abstract Syntax: =VerificationSOPClass
D:     Accepted Transfer Syntax:      =LittleEndianExplicit
D: ======================= END A-ASSOCIATE-AC ======================
```

**Was du daran abliest:** Ein einziger Kontext, angeboten mit drei Kodierungen, angenommen mit einer. Beachte die beiden PDU-Größen: Jede Seite nennt, wie große Datenpakete sie entgegennehmen kann, und es gilt für jede Richtung der Wert des Empfängers. Und beachte, was **nicht** verhandelt wurde: Von CT-Bildern war nirgends die Rede. Deshalb beweist ein grünes C-ECHO über Bildübertragung nichts.

## Beim echten Fall zusehen

Beim Senden eines Bildes sieht das Angebot anders aus:

```
$ storescu -d -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242 daten/ct-thorax/0001.dcm 2>&1 | \
      grep -A3 "Context ID"
D:   Context ID:        1 (Proposed)
D:     Abstract Syntax: =CTImageStorage
D:     Proposed Transfer Syntax(es):
D:       =LittleEndianExplicit
D:   Context ID:        1 (Accepted)
D:     Abstract Syntax: =CTImageStorage
D:     Accepted Transfer Syntax:      =LittleEndianExplicit
```

**Was du daran abliest:** Der Objekttyp `CTImageStorage` wurde angeboten und angenommen, in genau einer Kodierung. Erst jetzt darf ein CT-Bild fließen. Hätte die Datei eine andere SOP Class — etwa ein Structured Report —, bräuchte es dafür einen eigenen Kontext.

## Und wenn es schiefgeht

Die Antwort auf einen Kontext ist nicht nur ja oder nein, sondern nennt einen Grund:

| Ergebnis | Heißt |
|---|---|
| `Accepted` | Objekttyp und eine Kodierung passen |
| `User Rejection` | Die Anwendung will nicht — meist Konfiguration |
| `No Reason` | Ablehnung ohne Angabe |
| `Abstract Syntax Not Supported` | Diesen **Objekttyp** kenne ich nicht |
| `Transfer Syntaxes Not Supported` | Den Objekttyp ja — diese **Kodierung** nicht |

Die letzten beiden sind die, die im Alltag zählen, und sie führen zu völlig verschiedenen Maßnahmen:

```
$ storescu -d -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242 /tmp/lossless.dcm 2>&1 | \
      grep -A3 "Context ID:"
D:   Context ID:        1 (Proposed)
D:     Abstract Syntax: =CTImageStorage
D:     Proposed Transfer Syntax(es):
D:       =JPEGLossless:Non-hierarchical-1stOrderPrediction
D:   Context ID:        1 (Transfer Syntaxes Not Supported)
E: No presentation context for: (JPEGLossless:Non-hierarchical-1stOrderPrediction) CTImageStorage
```

**Was du daran abliest:** Der Objekttyp wäre in Ordnung — abgelehnt wurde die Kodierung. Damit ist die Maßnahme klar: entweder das Archiv lernt JPEG Lossless, oder der Sender wandelt vorher um (Lektion 1.7). Stünde dort `Abstract Syntax Not Supported`, wäre das Umwandeln sinnlos gewesen, weil die Gegenstelle den Objekttyp überhaupt nicht führt.

Diese eine Unterscheidung erspart die häufigste Fehlmaßnahme bei Übertragungsproblemen.

## Wenn gar kein Log hilft

Manche Gegenstellen protokollieren nichts Brauchbares. Dann bleibt der Mitschnitt — auch ohne Wiresharks DICOM-Dissektion liest sich der rohe PDU-Typ direkt aus den Feldern:

```
$ tshark -i lo -Y 'dicom' -T fields -e ip.src -e ip.dst -e dicom.pdu.type
127.0.0.1	127.0.0.1	0x01
127.0.0.1	127.0.0.1	0x02
127.0.0.1	127.0.0.1	0x04
127.0.0.1	127.0.0.1	0x04
127.0.0.1	127.0.0.1	0x05
127.0.0.1	127.0.0.1	0x06
```

**Was du daran abliest:** Sechs PDUs, genau der Ablauf von oben — `0x01`
(A-ASSOCIATE-RQ), `0x02` (A-ASSOCIATE-AC), zweimal `0x04` (P-DATA: die
C-ECHO-Anfrage, dann die Antwort), `0x05`/`0x06` (A-RELEASE-RQ/-RP).
Kein Name, kein Grund — nur Typnummern. Aber schon das reicht, um eine
angenommene Association von einer laufenden Übertragung zu
unterscheiden, ganz ohne Wiresharks DICOM-Dissektion.

<!-- kein-beispiel -->
```
Eine echte Ablehnung laesst sich in der aktuellen Spielwiese nicht live
erzeugen -- aus demselben Grund wie in Lektion 4.1: Orthanc laeuft hier
bewusst mit DicomAlwaysAllowEcho und den verwandten Optionen
(containers/orthanc/orthanc.json, ADR 0008) und nimmt deshalb jeden
Called/Calling AE Title an. Real, im Standard definiert (PS3.8, Tabelle
9-1), sind trotzdem zwei weitere PDU-Typen, die an genau dieser Stelle
auftauchen koennten:

  0x03   A-ASSOCIATE-RJ   Die Association wird abgelehnt, mit Grund.
  0x07   A-ABORT          Abbruch, meist ohne brauchbare Begruendung.

Ein Mitschnitt mit `0x01` gefolgt von `0x03` (statt `0x02`) waere damit
schon am reinen Zahlenpaar als Ablehnung erkennbar -- ganz ohne ein
einziges Log einer der beiden Seiten.
```

Der vollständige Inhalt der RQ — Namen und alle angebotenen Kontexte — steht im aufgeklappten Paket (ohne `-T fields`) und ist die sauberste Quelle überhaupt, weil sie keiner der beiden Hersteller geschrieben hat.

## Im Alltag heißt das

| Im Log steht | Bedeutung | Nächster Schritt |
|---|---|---|
| `Association Rejected — Called AE Title Not Recognized` | Name passt nicht | Konfiguration beider Seiten vergleichen (1.6) |
| `Association Accepted`, danach nichts | Kontexte abgelehnt | Kontextergebnisse ansehen |
| `Abstract Syntax Not Supported` | Objekttyp unbekannt | Archiv freischalten lassen, Conformance prüfen |
| `Transfer Syntaxes Not Supported` | Kodierung unbekannt | Umwandeln oder freischalten (1.7) |
| `A-ABORT` ohne Grund | Eine Seite ist ausgestiegen | Mitschnitt, beide Logs, Zeitstempel vergleichen |
| Alles grün, Bilder trotzdem weg | Nicht die Verbindung — die Verarbeitung danach | Archiv-Log nach dem C-STORE prüfen |

> ### Stolperfallen
>
> **„Association Accepted, also läuft es."**
> Der zentrale Irrtum dieser Lektion. Die Verbindung steht, die Kontexte können trotzdem alle abgelehnt sein.
>
> **„Das Archiv kann CT, also nimmt es mein CT."**
> Es muss CT **in deiner Kodierung** können. Objekttyp und Kodierung werden gemeinsam ausgehandelt, nie getrennt.
>
> **„Mehr angebotene Kontexte sind besser."**
> Meistens ja, aber ältere Geräte haben Grenzen für die Zahl der Kontexte und für die Größe der RQ. Wer alles gleichzeitig anbietet, bekommt bei solchen Gegenstellen einen Abort statt einer Antwort.
>
> **„Die Kontext-ID sagt etwas über den Objekttyp."**
> Nein. Sie ist eine laufende ungerade Nummer, die nur innerhalb dieser einen Verbindung gilt. In der nächsten Association kann derselbe Objekttyp eine andere ID haben.
>
> **„A-ABORT heißt Netzwerkproblem."**
> Manchmal. Genauso oft ist es eine Zeitüberschreitung, ein zu großes Paket oder ein Objekt, an dem sich die Gegenstelle verschluckt hat. Der Abort ist das Symptom, nicht die Ursache.

## Dein Lab

Im Lab **Mitgehört** bekommst du das Association-Log einer Verbindung, die nicht funktioniert, und die Aussage beider Hersteller, bei ihnen sei alles in Ordnung. Deine Aufgabe: aus dem Log benennen, welcher Kontext mit welchem Ergebnis abgelehnt wurde — und wer von beiden etwas ändern muss.

## Selbstcheck

<details>
<summary>Im Log steht „Association Accepted", es kommt trotzdem kein Bild an. Wo schaust du?</summary>

Auf die Ergebnisse der einzelnen Presentation Contexts. Die Association als Ganzes kann angenommen sein, während jeder einzelne Kontext abgelehnt wurde. Das Ergebnis steht je Kontext dabei und sagt auch, woran es lag — Objekttyp oder Kodierung.
</details>

<details>
<summary>Was ist der Unterschied zwischen „Abstract Syntax Not Supported" und „Transfer Syntaxes Not Supported"?</summary>

Im ersten Fall kennt die Gegenstelle den Objekttyp nicht — ein Umwandeln der Kodierung hilft nicht, sie muss den Typ freigeschaltet bekommen. Im zweiten Fall kennt sie den Typ, aber nicht die angebotene Kodierung — dann hilft Umwandeln oder das Freischalten der Kodierung. Zwei Meldungen, zwei völlig verschiedene Maßnahmen.
</details>

<details>
<summary>Warum sagt ein erfolgreiches C-ECHO nichts über die Bildübertragung?</summary>

Weil beim C-ECHO nur ein einziger Presentation Context ausgehandelt wird, nämlich die Verification SOP Class. Über CT-, MR- oder sonstige Bildobjekte wurde dabei gar nicht gesprochen. Deren Kontexte werden erst beim nächsten Verbindungsaufbau angeboten — und können dort abgelehnt werden.
</details>

## Quiz

*Wissenskarten — kommen später zur Wiederholung zurück.*

**q1 — Woraus besteht ein Presentation Context?**
1. Aus AE Title und Port
2. Aus einer ID, einer Abstract Syntax und einer Liste von Transfer Syntaxes
3. Aus Absender und Empfänger
4. Aus der Max-PDU-Größe

**q2 — Welche Aussagen stimmen?** *(Mehrfachauswahl)*
1. Jeder Presentation Context wird einzeln angenommen oder abgelehnt
2. Eine angenommene Association garantiert, dass Objekte übertragen werden
3. Kontext-IDs sind ungerade und gelten nur für diese Verbindung
4. Beim C-ECHO wird nur die Verification SOP Class ausgehandelt

**q3 — Welche Meldung bekommst du, wenn die Gegenstelle den Objekttyp gar nicht kennt?** *(Freitext)*

---

**Als Nächstes:** Track 1 ist damit durch. [Track 4 — Troubleshooting](../../4/) nimmt jedes dieser Fehlerbilder einzeln auseinander; wer lieber die Dienste vollständig kennenlernen will, geht zu [Track 2](../../2/).
